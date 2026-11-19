<?php

namespace App\Support\Inventory;

use App\Models\StockMovement;
use App\Support\Authorization\PinAuthorizationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * API interna de escritura manual de existencias (INVWRITE-001 · INV-04/INV-05).
 *
 * Todas las mutaciones pasan por `StockLedger`: este servicio nunca escribe
 * `current_stock` ni `stock_movements` directamente. La sucursal, el actor y
 * los saldos se resuelven siempre en servidor; el cliente solo aporta variante,
 * cantidad, motivo/nota y, cuando el umbral lo exige, autorizador y PIN.
 *
 * La carga inicial es única por `(branch_id, variant_id)`: se rechaza si ya
 * existe cualquier movimiento previo para esa combinación y el propio ledger
 * bloquea el reintento con la clave de origen `(source_type, source_id, ...)`.
 *
 * Los ajustes usan `source_type='inventory_adjustment'` y un `source_id`
 * derivado de un token de página (ver `sourceIdFromToken`). Un token nuevo
 * permite un ajuste nuevo; el mismo token no duplica el movimiento.
 */
final class StockWriteService
{
    private const INITIAL_SOURCE_TYPE = 'inventory_initial';

    private const ADJUSTMENT_SOURCE_TYPE = 'inventory_adjustment';

    private const DEFAULT_OVERRIDE_MIN_AUTHORITY = 50;

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly InventoryWriteGuard $guard,
        private readonly PinAuthorizationService $pin,
        private readonly StockAdjustmentPinPolicy $pinPolicy,
    ) {}

    public function loadInitial(
        int $branchId,
        int $variantId,
        mixed $quantity,
        int $userId,
    ): StockMovement {
        $this->eligibleVariant($variantId);
        $scaledQuantity = $this->positiveQuantity($quantity);

        try {
            return $this->ledger->recordMovement([
                'branch_id' => $branchId,
                'variant_id' => $variantId,
                'type' => 'initial',
                'quantity' => InventoryDecimal::formatQuantity($scaledQuantity),
                'unit_cost' => null,
                'source_type' => self::INITIAL_SOURCE_TYPE,
                'source_id' => $variantId,
                'user_id' => $userId,
            ], function () use ($branchId, $variantId): void {
                // Invariante autoritativa bajo el lock de la fila de stock:
                // ninguna carga inicial puede convivir con historial previo
                // (ajuste, reversa, venta, etc.), aunque otra escritura haya
                // ganado la carrera después de la prevalidación.
                if ($this->hasAnyMovement($branchId, $variantId)) {
                    throw new StockWriteException(
                        'variant_id',
                        'Esta variante ya tiene historial de movimientos en la sucursal y no admite una carga inicial.',
                    );
                }
            });
        } catch (DuplicateStockMovementException) {
            throw new StockWriteException(
                'variant_id',
                'La carga inicial de esta variante ya fue registrada; actualice la página para continuar.',
            );
        } catch (InvalidArgumentException $exception) {
            throw new StockWriteException('quantity', $exception->getMessage());
        }
    }

    /**
     * Ajuste manual. Nunca decide el PIN con una lectura preliminar: la
     * decisión autoritativa la toma `StockAdjustmentPinPolicy` dentro de la
     * transacción y bajo los locks de licencia/variante/configuración. Un
     * cambio de costo o umbral entre una eventual vista previa y el commit no
     * puede habilitar una escritura sin el PIN que la configuración vigente
     * exige.
     *
     * Orden de locks:
     * - sin PIN: licencia -> variante/producto -> system_configs -> stock;
     * - con PIN: usuarios/sucursal/roles (COREPIN) -> licencia ->
     *   variante/producto -> system_configs -> stock.
     *
     * El orden de locks en MariaDB y sus deadlocks potenciales quedan NOT_RUN
     * en esta ola: las pruebas usan SQLite, donde `lockForUpdate()` es no-op.
     */
    public function adjust(
        int $branchId,
        int $variantId,
        mixed $quantity,
        mixed $reasonCodeId,
        ?string $note,
        string $requestToken,
        int $userId,
        ?int $authorizerId,
        ?string $pin,
    ): StockMovement {
        $this->eligibleVariant($variantId);
        $scaledQuantity = $this->signedQuantity($quantity);
        $reasonId = $this->positiveIdentifier($reasonCodeId, 'reason_code_id', 'El motivo es obligatorio.');
        $normalizedNote = $this->assertReason($reasonId, $note);
        $sourceId = self::sourceIdFromToken($requestToken);

        if ($this->movementExistsForSource($branchId, $variantId, $sourceId)) {
            throw new StockWriteException(
                'request_token',
                'Este ajuste ya fue registrado con el mismo token; no se duplicó el movimiento.',
            );
        }

        $movementPayload = [
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'type' => 'adjustment',
            'quantity' => InventoryDecimal::formatQuantity($scaledQuantity),
            'unit_cost' => null,
            'source_type' => self::ADJUSTMENT_SOURCE_TYPE,
            'source_id' => $sourceId,
            'user_id' => $userId,
            'reason_code_id' => $reasonId,
            'note' => $normalizedNote,
        ];

        $pinSupplied = $authorizerId !== null
            && $authorizerId > 0
            && $pin !== null
            && trim($pin) !== '';

        if ($pinSupplied) {
            if ($this->guard->isReadOnly()) {
                throw new StockWriteException(
                    'read_only',
                    'La licencia está suspendida fuera del período de gracia; el inventario está en modo solo lectura.',
                );
            }

            [$onHand, $resulting] = $this->balancePreview($branchId, $variantId, $scaledQuantity);

            /** @var StockMovement|null $approvedMovement */
            $approvedMovement = null;

            $this->authorizePin(
                $branchId,
                $variantId,
                $authorizerId,
                $pin,
                InventoryDecimal::formatQuantity($onHand),
                InventoryDecimal::formatQuantity($resulting),
                $normalizedNote,
                function () use (&$approvedMovement, $movementPayload, $scaledQuantity, $variantId): array {
                    // Dentro de la transacción de autorización: primero se
                    // adquiere el lock de licencia y luego la política relee y
                    // bloquea variante/producto/configuración para decidir con
                    // los valores que se confirmarán.
                    $this->guard->assertWritable();
                    $this->pinPolicy->requiresPinUnderLock($variantId, $scaledQuantity);

                    // La decisión puede ser `false` si el costo/umbral cambió
                    // a la baja: no se rechaza una autorización ya suministrada
                    // y válida por una prevalidación obsoleta.
                    try {
                        $approvedMovement = $this->ledger->recordMovement($movementPayload);
                    } catch (DuplicateStockMovementException) {
                        throw new StockWriteException(
                            'request_token',
                            'Este ajuste ya fue registrado con el mismo token; no se duplicó el movimiento.',
                        );
                    } catch (InvalidArgumentException $exception) {
                        throw new StockWriteException('quantity', $exception->getMessage());
                    }

                    $balanceAfter = InventoryDecimal::quantityFromStorage($approvedMovement->balance_after);
                    $authoritativeOnHand = InventoryDecimal::addQuantity($balanceAfter, -$scaledQuantity);

                    return [
                        'old_value' => InventoryDecimal::formatQuantity($authoritativeOnHand),
                        'new_value' => InventoryDecimal::formatQuantity($balanceAfter),
                    ];
                },
            );

            if (! $approvedMovement instanceof StockMovement) {
                throw new StockWriteException(
                    'adjustment',
                    'No fue posible completar el ajuste autorizado; vuelva a intentar.',
                );
            }

            return $approvedMovement;
        }

        try {
            return DB::transaction(function () use ($movementPayload, $variantId, $scaledQuantity): StockMovement {
                // Primero el lock de licencia; después la política bloquea
                // variante/producto/configuración y decide con los valores que
                // la escritura confirmará en esta misma transacción.
                $this->guard->assertWritable();

                if ($this->pinPolicy->requiresPinUnderLock($variantId, $scaledQuantity)) {
                    throw new StockWriteException(
                        'pin',
                        'Este ajuste ahora requiere autorización con PIN; vuelva a intentar con autorizador y PIN.',
                    );
                }

                try {
                    return $this->ledger->recordMovement($movementPayload);
                } catch (InvalidArgumentException $exception) {
                    throw new StockWriteException('quantity', $exception->getMessage());
                }
            });
        } catch (DuplicateStockMovementException) {
            throw new StockWriteException(
                'request_token',
                'Este ajuste ya fue registrado con el mismo token; no se duplicó el movimiento.',
            );
        }
    }

    /**
     * @return list<array{id: int, label: string, requires_note: bool}>
     */
    public function reasonOptions(): array
    {
        return DB::table('reason_codes')
            ->where('scope', 'stock_adjustment')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'label', 'requires_note'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->label,
                'requires_note' => (int) $row->requires_note === 1,
            ])
            ->values()
            ->all();
    }

    /**
     * Autorizadores válidos para la sucursal: activos, no borrados, con rol
     * aplicable (global o de la sucursal) que expone `override.authorize` y
     * autoridad suficiente. Incluye al usuario actual cuando cumple la
     * política (COREPIN-001 no prohíbe la autoautorización). Solo se devuelven
     * id y nombre.
     *
     * @return list<array{id: int, name: string}>
     */
    public function authorizerOptions(int $branchId): array
    {
        $permissionId = DB::table('permissions')->where('code', 'override.authorize')->value('id');

        if ($permissionId === null) {
            return [];
        }

        $minimumAuthority = $this->configInt(
            'override_min_authority',
            self::DEFAULT_OVERRIDE_MIN_AUTHORITY,
        );

        return DB::table('users as u')
            ->leftJoin('branch_user as bu', function ($join) use ($branchId): void {
                $join->on('bu.user_id', '=', 'u.id')
                    ->where('bu.branch_id', '=', $branchId);
            })
            ->join('roles as r', function ($join): void {
                $join->on('r.id', '=', DB::raw('COALESCE(u.global_role_id, bu.role_id)'));
            })
            ->where('u.is_active', 1)
            ->whereNull('u.deleted_at')
            ->where('r.authority_level', '>=', $minimumAuthority)
            ->whereExists(function ($query) use ($permissionId): void {
                $query->select(DB::raw(1))
                    ->from('role_permissions')
                    ->whereColumn('role_permissions.role_id', 'r.id')
                    ->where('role_permissions.permission_id', (int) $permissionId);
            })
            ->orderBy('u.name')
            ->orderBy('u.id')
            ->limit(50)
            ->get(['u.id', 'u.name'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Rechazo temprano de reintentos con el mismo token antes de PIN/AO.
     * El ledger conserva además su guarda de duplicados como defensa en profundidad.
     */
    private function movementExistsForSource(int $branchId, int $variantId, int $sourceId): bool
    {
        return DB::table('stock_movements')
            ->where('source_type', self::ADJUSTMENT_SOURCE_TYPE)
            ->where('source_id', $sourceId)
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->where('type', 'adjustment')
            ->exists();
    }

    public function hasAnyMovement(int $branchId, int $variantId): bool
    {
        return DB::table('stock_movements')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->exists();
    }

    /**
     * `source_id` determinista y acotado a 60 bits a partir del token de página.
     */
    public static function sourceIdFromToken(string $requestToken): int
    {
        if (preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestToken) !== 1) {
            throw new StockWriteException(
                'request_token',
                'El token de la solicitud no es válido; recargue la página e intente nuevamente.',
            );
        }

        $sourceId = (int) hexdec(substr(hash('sha256', $requestToken), 0, 15));

        if ($sourceId <= 0) {
            throw new StockWriteException(
                'request_token',
                'El token de la solicitud no es válido; recargue la página e intente nuevamente.',
            );
        }

        return $sourceId;
    }

    /**
     * Elegibilidad temprana (sin lock) para rechazo rápido. La revalidación
     * autoritativa bajo lock la realiza `StockAdjustmentPinPolicy` dentro de la
     * transacción de escritura.
     */
    private function eligibleVariant(int $variantId): object
    {
        $variant = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pv.id', $variantId)
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('pv.is_active', 1)
            ->where('p.is_active', 1)
            ->where('p.tracks_stock', 1)
            ->whereIn('p.type', ['simple', 'variable'])
            ->first(['pv.id']);

        if ($variant === null) {
            throw new StockWriteException(
                'variant_id',
                'La variante no existe, está inactiva o no controla existencias físicas.',
            );
        }

        return $variant;
    }

    private function positiveQuantity(mixed $value): int
    {
        try {
            $scaled = InventoryDecimal::quantity($value);
        } catch (InvalidArgumentException $exception) {
            throw new StockWriteException('quantity', $exception->getMessage());
        }

        if ($scaled <= 0) {
            throw new StockWriteException('quantity', 'La cantidad inicial debe ser mayor que cero.');
        }

        return $scaled;
    }

    private function signedQuantity(mixed $value): int
    {
        try {
            $scaled = InventoryDecimal::quantity($value);
        } catch (InvalidArgumentException $exception) {
            throw new StockWriteException('quantity', $exception->getMessage());
        }

        if ($scaled === 0) {
            throw new StockWriteException('quantity', 'La cantidad del ajuste no puede ser cero.');
        }

        return $scaled;
    }

    private function positiveIdentifier(mixed $value, string $field, string $message): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            throw new StockWriteException($field, $message);
        }

        return (int) $id;
    }

    private function assertReason(int $reasonCodeId, ?string $note): ?string
    {
        $reason = DB::table('reason_codes')
            ->where('id', $reasonCodeId)
            ->first(['scope', 'is_active', 'requires_note']);

        if (
            $reason === null
            || $reason->scope !== 'stock_adjustment'
            || (int) $reason->is_active !== 1
        ) {
            throw new StockWriteException(
                'reason_code_id',
                'El motivo seleccionado no está activo o no corresponde a un ajuste de existencias.',
            );
        }

        $normalizedNote = $note === null ? null : trim($note);
        $normalizedNote = $normalizedNote === '' ? null : $normalizedNote;

        if ($normalizedNote !== null && mb_strlen($normalizedNote) > 255) {
            throw new StockWriteException('note', 'La nota no puede superar 255 caracteres.');
        }

        if ((int) $reason->requires_note === 1 && $normalizedNote === null) {
            throw new StockWriteException('note', 'El motivo seleccionado exige una nota explicativa.');
        }

        return $normalizedNote;
    }

    /**
     * @return array{0: int, 1: int} Existencia actual y resultante en milésimas.
     */
    private function balancePreview(int $branchId, int $variantId, int $scaledQuantity): array
    {
        $raw = DB::table('current_stock')
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->value('on_hand');

        $onHand = $raw === null ? 0 : InventoryDecimal::quantityFromStorage($raw);

        try {
            $resulting = InventoryDecimal::addQuantity($onHand, $scaledQuantity);
        } catch (InvalidArgumentException $exception) {
            throw new StockWriteException('quantity', $exception->getMessage());
        }

        return [$onHand, $resulting];
    }

    /**
     * Política de autorización: solo exige autorizador válido y PIN. Un
     * solicitante con `override.authorize`, autoridad suficiente y PIN puede
     * autorizar su propio ajuste (COREPIN-001 no prohíbe la autoautorización).
     *
     * @param  (callable(): array<string, mixed>)|null  $onApproved
     */
    private function authorizePin(
        int $branchId,
        int $variantId,
        ?int $authorizerId,
        ?string $pin,
        string $oldValue,
        string $newValue,
        ?string $note,
        ?callable $onApproved = null,
    ): void {
        if ($authorizerId === null || $authorizerId <= 0) {
            throw new StockWriteException(
                'authorizer_id',
                'El valor del ajuste exige un autorizador con permiso de autorización.',
            );
        }

        if ($pin === null || trim($pin) === '') {
            throw new StockWriteException('pin', 'Debe ingresar el PIN del autorizador.');
        }

        $this->pin->authorize(
            type: 'stock_adjustment',
            branchId: $branchId,
            authorizer: $authorizerId,
            pin: $pin,
            referenceType: self::ADJUSTMENT_SOURCE_TYPE,
            referenceId: $variantId,
            oldValue: $oldValue,
            newValue: $newValue,
            reasonCodeId: null,
            reason: $note,
            onApproved: $onApproved,
        );
    }

    private function configInt(string $key, int $fallback): int
    {
        $value = DB::table('system_configs')->where('config_key', $key)->value('config_value');

        if ($value === null || $value === '') {
            return $fallback;
        }

        $int = (int) $value;

        return $int > 0 ? $int : $fallback;
    }
}
