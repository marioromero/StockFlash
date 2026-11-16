<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiptSettingsRequest;
use App\Support\AuditLogger;
use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes del comprobante interno NO tributario (CFG-001 · CORE-09).
 *
 * Solo administra las tres claves contractuales de Parte 2 §3.1:
 * `receipt_header`, `receipt_footer` y `receipt_paper`. La escritura y su
 * auditoría ocurren en la misma transacción; si la auditoría falla, los
 * valores quedan intactos.
 */
class ReceiptSettingsController extends Controller
{
    /**
     * Claves administradas. No se consulta ni serializa ninguna otra.
     *
     * @var list<string>
     */
    private const KEYS = [
        'receipt_header',
        'receipt_footer',
        'receipt_paper',
    ];

    /**
     * Tamaños de papel admitidos (Parte 2 §3.1).
     *
     * @var list<string>
     */
    private const PAPER_SIZES = [
        '58mm',
        '80mm',
        'A4',
    ];

    private const DEFAULT_PAPER = '80mm';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(): Response
    {
        $rows = DB::table('system_configs')
            ->whereIn('config_key', self::KEYS)
            ->pluck('config_value', 'config_key');

        $paper = $rows->get('receipt_paper');

        return Inertia::render('Settings/Receipt', [
            'receipt_header' => (string) ($rows->get('receipt_header') ?? ''),
            'receipt_footer' => (string) ($rows->get('receipt_footer') ?? ''),
            'receipt_paper' => $this->normalizePaper($paper),
            'read_only' => $this->licenseIsReadOnly(),
        ]);
    }

    public function update(ReceiptSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = (int) $request->user()->getKey();

        DB::transaction(function () use ($data, $actorId): void {
            $this->assertLicenseAllowsWrites();

            $existing = DB::table('system_configs')
                ->whereIn('config_key', self::KEYS)
                ->get()
                ->keyBy('config_key');

            $changedKeys = [];

            foreach (self::KEYS as $key) {
                $value = $data[$key] ?? null;
                $row = $existing->get($key);

                if ($row === null) {
                    // Solo se inserta la clave administrada, con los metadatos
                    // contractuales por defecto. Nunca se toca otra fila.
                    DB::table('system_configs')->insert([
                        'config_key' => $key,
                        'config_value' => $value,
                        'value_type' => 'string',
                        'module_code' => null,
                        'is_secret' => 0,
                        'updated_by_user_id' => $actorId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $changedKeys[] = $key;

                    continue;
                }

                if ($this->sameValue($row->config_value, $value)) {
                    continue;
                }

                // Update acotado: se preservan `value_type`, `is_secret`,
                // `module_code`, `description` y el resto de las columnas.
                DB::table('system_configs')
                    ->where('config_key', $key)
                    ->update([
                        'config_value' => $value,
                        'updated_by_user_id' => $actorId,
                        'updated_at' => now(),
                    ]);

                $changedKeys[] = $key;
            }

            if ($changedKeys === []) {
                return;
            }

            // PRV-06/RN-70: solo se auditan los nombres de las claves
            // modificadas. Nunca el contenido de encabezado/pie (posible PII).
            $this->auditLogger->record(
                'core.receipt_settings.updated',
                'system_configs',
                null,
                null,
                null,
                ['changed_keys' => $changedKeys],
                'core',
            );
        });

        return redirect()
            ->route('settings.receipt.edit')
            ->with('success', 'Ajustes del comprobante actualizados correctamente.');
    }

    private function normalizePaper(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, self::PAPER_SIZES, true)) {
            return self::DEFAULT_PAPER;
        }

        return $value;
    }

    private function sameValue(mixed $current, mixed $next): bool
    {
        if ($current === null || $next === null) {
            return $current === null && $next === null;
        }

        return (string) $current === (string) $next;
    }

    /**
     * Misma semántica que `BranchController::assertLicenseAllowsWrites` (RN-70):
     * una licencia suspendida sin gracia o con gracia vencida deja el sistema
     * en solo lectura. Sin fila de licencia se permite escribir.
     */
    private function assertLicenseAllowsWrites(): void
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $this->licenseRowIsReadOnly($license)) {
            return;
        }

        throw ValidationException::withMessages([
            'read_only' => 'La licencia está suspendida fuera del período de gracia; el sistema está en modo solo lectura.',
        ]);
    }

    private function licenseIsReadOnly(): bool
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->first();

        return $this->licenseRowIsReadOnly($license);
    }

    private function licenseRowIsReadOnly(?object $license): bool
    {
        return LicenseReadOnlyRule::isReadOnly($license);
    }
}
