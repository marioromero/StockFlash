<?php

namespace App\Console\Commands;

use App\Support\Inventory\InventoryDecimal;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación determinista y estrictamente de solo lectura (INV-02).
 *
 * Detecta:
 * - current_stock.on_hand distinto del balance_after del último movimiento en
 *   orden de anexado (`id`), porque `balance_after` se encadena en ese orden
 *   y `occurred_at` es tiempo de negocio que puede estar retrodatado,
 * - filas con on_hand != 0 sin ningún movimiento,
 * - movimientos originales con más de una reversa,
 * - reserved distinto de la suma de reservas activas cuando
 *   `stock_reservations` existe. Si la tabla no existe, se informa
 *   explícitamente como diferida; nunca se declara PASS.
 *
 * No ejecuta INSERT/UPDATE/DELETE ni corrige datos. Exit 0 solo si no hay
 * discrepancias; exit 1 si hay alguna. La salida no contiene PII ni secretos.
 */
class ReconcileInventoryCommand extends Command
{
    protected $signature = 'inventory:reconcile
        {--branch= : ID o código de sucursal (por defecto todas)}
        {--chunk=500 : Filas examinadas por lote (1-5000)}
        {--limit=0 : Máximo de filas por verificación (0 = sin límite)}
        {--json : Salida JSON determinista}';

    protected $description = 'Informe de reconciliación de inventario (solo lectura, sin correcciones automáticas).';

    public function handle(): int
    {
        $chunk = max(1, min(5000, (int) $this->option('chunk')));
        $limit = max(0, (int) $this->option('limit'));
        $branch = $this->resolveBranch();

        if ($this->option('branch') !== null && $this->option('branch') !== '' && $branch === null) {
            $this->error('La sucursal indicada no existe.');

            return self::FAILURE;
        }

        $branchId = $branch === null ? null : (int) $branch->id;
        $mismatches = [];
        $checked = $this->scanCurrentStock($branchId, $chunk, $limit, function (object $row) use (&$mismatches): void {
            $onHand = InventoryDecimal::quantityFromStorage($row->on_hand);

            $latest = DB::table('stock_movements')
                ->where('branch_id', $row->branch_id)
                ->where('variant_id', $row->variant_id)
                ->orderByDesc('id')
                ->first(['id', 'balance_after']);

            if ($latest === null) {
                if ($onHand !== 0) {
                    $mismatches[] = [
                        'check' => 'balance_without_movements',
                        'branch_id' => (int) $row->branch_id,
                        'variant_id' => (int) $row->variant_id,
                        'on_hand' => InventoryDecimal::formatQuantity($onHand),
                    ];
                }

                return;
            }

            $balance = InventoryDecimal::quantityFromStorage($latest->balance_after);

            if ($balance !== $onHand) {
                $mismatches[] = [
                    'check' => 'balance_mismatch',
                    'branch_id' => (int) $row->branch_id,
                    'variant_id' => (int) $row->variant_id,
                    'on_hand' => InventoryDecimal::formatQuantity($onHand),
                    'latest_balance_after' => InventoryDecimal::formatQuantity($balance),
                    'movement_id' => (int) $latest->id,
                ];
            }
        });

        foreach ($this->duplicateReversals($branchId, $limit) as $row) {
            $mismatches[] = [
                'check' => 'duplicate_reversal',
                'reverses_movement_id' => (int) $row->reverses_movement_id,
                'count' => (int) $row->total,
            ];
        }

        $reservedCheck = $this->checkReserved($branchId, $chunk, $limit, $mismatches);

        usort($mismatches, fn (array $left, array $right): int => $this->sortKey($left) <=> $this->sortKey($right));

        $hasMismatch = $mismatches !== [];
        $payload = [
            'packet' => 'INVCORE-001',
            'branch' => $branch === null ? null : [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
            ],
            'checked_stock_rows' => $checked,
            'reserved_check' => $reservedCheck,
            'mismatches' => $mismatches,
            'result' => $hasMismatch ? 'MISMATCH' : 'CLEAN',
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->renderText($payload, $chunk, $limit);
        }

        return $hasMismatch ? self::FAILURE : self::SUCCESS;
    }

    private function resolveBranch(): ?object
    {
        $raw = $this->option('branch');

        if ($raw === null || $raw === '') {
            return null;
        }

        $raw = trim((string) $raw);
        $branch = null;

        if (ctype_digit($raw)) {
            $branch = DB::table('branches')->where('id', (int) $raw)->whereNull('deleted_at')->first();
        }

        return $branch ?? DB::table('branches')->where('code', $raw)->whereNull('deleted_at')->first();
    }

    /**
     * Recorre current_stock por keyset (branch_id, variant_id) con límites
     * acotados y deterministas. Solo lee.
     */
    private function scanCurrentStock(?int $branchId, int $chunk, int $limit, callable $callback): int
    {
        $lastBranch = 0;
        $lastVariant = 0;
        $checked = 0;
        $first = true;

        while (true) {
            $take = $chunk;

            if ($limit > 0) {
                $remaining = $limit - $checked;

                if ($remaining <= 0) {
                    break;
                }

                $take = min($take, $remaining);
            }

            $query = DB::table('current_stock')
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->when(! $first, function ($query) use ($lastBranch, $lastVariant): void {
                    $query->where(function ($query) use ($lastBranch, $lastVariant): void {
                        $query->where('branch_id', '>', $lastBranch)
                            ->orWhere(function ($query) use ($lastBranch, $lastVariant): void {
                                $query->where('branch_id', $lastBranch)
                                    ->where('variant_id', '>', $lastVariant);
                            });
                    });
                })
                ->orderBy('branch_id')
                ->orderBy('variant_id')
                ->limit($take);

            $rows = $query->get(['branch_id', 'variant_id', 'on_hand', 'reserved']);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $checked++;
                $lastBranch = (int) $row->branch_id;
                $lastVariant = (int) $row->variant_id;
                $callback($row);
            }

            $first = false;

            if ($rows->count() < $take) {
                break;
            }
        }

        return $checked;
    }

    /**
     * @return Collection<int, object>
     */
    private function duplicateReversals(?int $branchId, int $limit): Collection
    {
        $cap = $limit > 0 ? $limit : 1000;

        return DB::table('stock_movements')
            ->select('reverses_movement_id', DB::raw('COUNT(*) as total'))
            ->where('type', 'reversal')
            ->whereNotNull('reverses_movement_id')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('reverses_movement_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('reverses_movement_id')
            ->limit($cap)
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $mismatches
     */
    private function checkReserved(?int $branchId, int $chunk, int $limit, array &$mismatches): string
    {
        if (! Schema::hasTable('stock_reservations')) {
            return 'deferred';
        }

        $this->scanCurrentStock($branchId, $chunk, $limit, function (object $row) use (&$mismatches): void {
            $reserved = InventoryDecimal::quantityFromStorage($row->reserved);
            $active = DB::table('stock_reservations')
                ->where('branch_id', $row->branch_id)
                ->where('variant_id', $row->variant_id)
                ->where('status', 'active')
                ->sum('quantity');
            $activeScaled = InventoryDecimal::quantityFromStorage($active ?? 0);

            if ($activeScaled !== $reserved) {
                $mismatches[] = [
                    'check' => 'reserved_mismatch',
                    'branch_id' => (int) $row->branch_id,
                    'variant_id' => (int) $row->variant_id,
                    'reserved' => InventoryDecimal::formatQuantity($reserved),
                    'active_reservations' => InventoryDecimal::formatQuantity($activeScaled),
                ];
            }
        });

        $orphans = DB::table('stock_reservations as sr')
            ->leftJoin('current_stock as cs', function ($join): void {
                $join->on('cs.branch_id', '=', 'sr.branch_id')
                    ->on('cs.variant_id', '=', 'sr.variant_id');
            })
            ->where('sr.status', 'active')
            ->whereNull('cs.branch_id')
            ->when($branchId !== null, fn ($query) => $query->where('sr.branch_id', $branchId))
            ->groupBy('sr.branch_id', 'sr.variant_id')
            ->select([
                'sr.branch_id',
                'sr.variant_id',
                DB::raw('SUM(sr.quantity) as total'),
            ])
            ->orderBy('sr.branch_id')
            ->orderBy('sr.variant_id')
            ->limit($limit > 0 ? $limit : 1000)
            ->get();

        foreach ($orphans as $row) {
            $mismatches[] = [
                'check' => 'reservation_without_stock_row',
                'branch_id' => (int) $row->branch_id,
                'variant_id' => (int) $row->variant_id,
                'active_reservations' => InventoryDecimal::formatQuantity(
                    InventoryDecimal::quantityFromStorage($row->total ?? 0),
                ),
            ];
        }

        foreach ($mismatches as $mismatch) {
            if (in_array($mismatch['check'], ['reserved_mismatch', 'reservation_without_stock_row'], true)) {
                return 'mismatch';
            }
        }

        return 'ok';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderText(array $payload, int $chunk, int $limit): void
    {
        $this->line('INVCORE-001 inventory:reconcile (solo lectura, sin correcciones)');
        $this->line('branch='.($payload['branch'] === null
            ? 'all'
            : $payload['branch']['id'].':'.$payload['branch']['code']));
        $this->line('chunk='.$chunk.' limit='.$limit);
        $this->line('checked_stock_rows='.$payload['checked_stock_rows']);
        $this->line('reserved_check='.$payload['reserved_check'].($payload['reserved_check'] === 'deferred'
            ? ' (tabla stock_reservations ausente)'
            : ''));
        $this->line('mismatches='.count($payload['mismatches']));

        foreach ($payload['mismatches'] as $mismatch) {
            $this->line('MISMATCH '.$mismatch['check'].' '.$this->formatFields($mismatch));
        }

        $this->line('result='.$payload['result']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formatFields(array $row): string
    {
        unset($row['check']);
        ksort($row);

        $parts = [];

        foreach ($row as $key => $value) {
            $parts[] = $key.'='.(is_scalar($value) ? (string) $value : json_encode($value));
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sortKey(array $row): string
    {
        return sprintf(
            '%s|%012d|%012d|%012d',
            (string) $row['check'],
            (int) ($row['branch_id'] ?? 0),
            (int) ($row['variant_id'] ?? 0),
            (int) ($row['reverses_movement_id'] ?? 0),
        );
    }
}
