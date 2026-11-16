<?php

namespace Tests\Feature\Core;

use App\Models\Branch;
use App\Support\DocumentSequenceAllocator;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class DocumentSequenceAllocatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const BRANCH_TYPES = [
        'ticket',
        'sale',
        'return',
        'transfer',
        'count',
        'goods_receipt',
        'cash_session',
    ];

    public function test_primera_reserva_formatea_codigo_de_sucursal_y_crea_fila_con_defectos(): void
    {
        $branch = $this->makeBranch('SUC-A');

        $result = $this->allocator()->reserve('sale', $branch->id);

        $this->assertSame('SUC-A000001', $result['visible']);
        $this->assertSame(1, $result['number']);
        $this->assertSame('sale', $result['doc_type']);
        $this->assertSame('branch', $result['scope_type']);
        $this->assertSame($branch->id, $result['scope_id']);

        $row = $this->sequence('sale', 'branch', $branch->id);

        $this->assertNotNull($row);
        $this->assertSame('SUC-A', $row->prefix);
        $this->assertSame(6, (int) $row->padding);
        $this->assertSame(2, (int) $row->next_number);
    }

    public function test_segunda_reserva_consecutiva_incrementa_el_visible(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $allocator = $this->allocator();

        $allocator->reserve('sale', $branch->id);
        $second = $allocator->reserve('sale', $branch->id);

        $this->assertSame('SUC-A000002', $second['visible']);
        $this->assertSame(2, $second['number']);
        $this->assertSame(3, (int) $this->sequence('sale', 'branch', $branch->id)->next_number);
    }

    public function test_fila_preexistente_conserva_prefix_padding_y_continua_su_correlativo(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('sale', 'branch', $branch->id, 'FACT', 4, 7);

        $result = $this->allocator()->reserve('sale', $branch->id);

        $this->assertSame('FACT0007', $result['visible']);
        $this->assertSame(7, $result['number']);

        $row = $this->sequence('sale', 'branch', $branch->id);

        $this->assertSame('FACT', $row->prefix);
        $this->assertSame(4, (int) $row->padding);
        $this->assertSame(8, (int) $row->next_number);
    }

    public function test_sucursales_y_tipos_distintos_no_comparten_estado(): void
    {
        $branchA = $this->makeBranch('SUC-A', 'Sucursal A');
        $branchB = $this->makeBranch('SUC-B', 'Sucursal B');
        $allocator = $this->allocator();

        $this->assertSame('SUC-A000001', $allocator->reserve('sale', $branchA->id)['visible']);
        $this->assertSame('SUC-B000001', $allocator->reserve('sale', $branchB->id)['visible']);
        $this->assertSame('SUC-A000002', $allocator->reserve('sale', $branchA->id)['visible']);
        $this->assertSame('SUC-A000001', $allocator->reserve('ticket', $branchA->id)['visible']);

        $this->assertSame(3, (int) $this->sequence('sale', 'branch', $branchA->id)->next_number);
        $this->assertSame(2, (int) $this->sequence('sale', 'branch', $branchB->id)->next_number);
        $this->assertSame(2, (int) $this->sequence('ticket', 'branch', $branchA->id)->next_number);
    }

    public function test_todos_los_tipos_por_sucursal_crean_filas_independientes(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $allocator = $this->allocator();

        foreach (self::BRANCH_TYPES as $docType) {
            $result = $allocator->reserve($docType, $branch->id);

            $this->assertSame('SUC-A000001', $result['visible'], $docType);
            $this->assertSame('branch', $result['scope_type'], $docType);
            $this->assertSame($branch->id, $result['scope_id'], $docType);
        }

        $this->assertDatabaseCount('document_sequences', count(self::BRANCH_TYPES));

        foreach (self::BRANCH_TYPES as $docType) {
            $this->assertNotNull($this->sequence($docType, 'branch', $branch->id), $docType);
        }
    }

    public function test_purchase_order_usa_ambito_instalacion_cero(): void
    {
        $result = $this->allocator()->reserve('purchase_order');

        $this->assertSame('000001', $result['visible']);
        $this->assertSame('installation', $result['scope_type']);
        $this->assertSame(0, $result['scope_id']);
        $this->assertSame('installation', $this->sequence('purchase_order', 'installation', 0)->scope_type);
    }

    public function test_purchase_order_rechaza_sucursal_sin_crear_fila(): void
    {
        $branch = $this->makeBranch('SUC-A');

        try {
            $this->allocator()->reserve('purchase_order', $branch->id);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado
        }

        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_tipo_por_sucursal_sin_branch_id_falla_sin_crear_fila(): void
    {
        try {
            $this->allocator()->reserve('sale');
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado
        }

        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_tipo_desconocido_falla_sin_crear_fila(): void
    {
        $branch = $this->makeBranch('SUC-A');

        try {
            $this->allocator()->reserve('invoice', $branch->id);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado
        }

        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_sucursal_inexistente_falla_sin_crear_fila(): void
    {
        try {
            $this->allocator()->reserve('sale', 999_999);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado
        }

        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_transfer_usa_la_sucursal_de_origen_recibida(): void
    {
        $origin = $this->makeBranch('ORI', 'Origen');
        $destination = $this->makeBranch('DES', 'Destino');

        $result = $this->allocator()->reserve('transfer', $origin->id);

        $this->assertSame('ORI000001', $result['visible']);
        $this->assertSame($origin->id, $result['scope_id']);
        $this->assertNotNull($this->sequence('transfer', 'branch', $origin->id));
        $this->assertNull($this->sequence('transfer', 'branch', $destination->id));
    }

    public function test_rollback_exterior_revierte_fila_nueva_y_contador_existente(): void
    {
        $emptyBranch = $this->makeBranch('SUC-E', 'Sucursal vacía');
        $existingBranch = $this->makeBranch('SUC-X', 'Sucursal existente');
        $allocator = $this->allocator();

        try {
            DB::transaction(function () use ($allocator, $emptyBranch): void {
                $allocator->reserve('sale', $emptyBranch->id);

                throw new RuntimeException('rollback receptor');
            });
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException) {
            // esperado: la transacción exterior revirtió la reserva
        }

        $this->assertDatabaseCount('document_sequences', 0);

        $allocator->reserve('sale', $existingBranch->id);

        try {
            DB::transaction(function () use ($allocator, $existingBranch): void {
                $allocator->reserve('sale', $existingBranch->id);

                throw new RuntimeException('rollback receptor');
            });
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame(1, DB::table('document_sequences')->count());
        $this->assertSame(2, (int) $this->sequence('sale', 'branch', $existingBranch->id)->next_number);
    }

    public function test_creacion_idempotente_reutiliza_fila_y_no_duplica(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('sale', 'branch', $branch->id, 'AB', 6, 1);

        $result = $this->allocator()->reserve('sale', $branch->id);

        $this->assertSame('AB000001', $result['visible']);
        $this->assertSame(1, DB::table('document_sequences')->count());
        $row = $this->sequence('sale', 'branch', $branch->id);
        $this->assertSame('AB', $row->prefix);
        $this->assertSame(2, (int) $row->next_number);
    }

    public function test_unique_del_esquema_impide_duplicar_ambito(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('sale', 'branch', $branch->id, 'A', 6, 1);

        $this->expectException(QueryException::class);

        DB::table('document_sequences')->insert([
            'doc_type' => 'sale',
            'scope_type' => 'branch',
            'scope_id' => $branch->id,
            'prefix' => 'B',
            'next_number' => 1,
            'padding' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_codigo_de_sucursal_mayor_a_diez_exige_override_sin_truncar(): void
    {
        $branch = $this->makeBranch('ABCDEFGHIJK');
        $allocator = $this->allocator();

        try {
            $allocator->reserve('sale', $branch->id);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado: no se trunca ni se crea la fila
        }

        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertSame('ABCDEFGHIJK', $branch->fresh()->code);

        $result = $allocator->reserve('sale', $branch->id, 'OVR');

        $this->assertSame('OVR000001', $result['visible']);
        $this->assertSame('OVR', $this->sequence('sale', 'branch', $branch->id)->prefix);
        $this->assertSame('ABCDEFGHIJK', $branch->fresh()->code);

        try {
            $allocator->reserve('sale', $branch->id, 'DEMASIADOLARGO');
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // esperado: override inválido
        }
    }

    public function test_padding_fuera_de_rango_falla_antes_de_mutar(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('ticket', 'branch', $branch->id, 'P', 0, 1);
        $this->seedSequence('sale', 'branch', $branch->id, 'P', 19, 1);

        foreach (['ticket', 'sale'] as $docType) {
            try {
                $this->allocator()->reserve($docType, $branch->id);
                $this->fail('Se esperaba DomainException.');
            } catch (DomainException) {
                // esperado
            }

            $row = $this->sequence($docType, 'branch', $branch->id);
            $this->assertSame(1, (int) $row->next_number, $docType);
        }
    }

    public function test_prefix_corrupto_falla_antes_de_mutar(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('sale', 'branch', $branch->id, 'ABCDEFGHIJK', 6, 1);

        try {
            $this->allocator()->reserve('sale', $branch->id);
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado
        }

        $row = $this->sequence('sale', 'branch', $branch->id);
        $this->assertSame('ABCDEFGHIJK', $row->prefix);
        $this->assertSame(1, (int) $row->next_number);
    }

    public function test_next_number_cero_falla_antes_de_mutar(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('sale', 'branch', $branch->id, 'P', 6, 0);

        try {
            $this->allocator()->reserve('sale', $branch->id);
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertSame(0, (int) $this->sequence('sale', 'branch', $branch->id)->next_number);
    }

    public function test_agotar_la_capacidad_numerica_falla_sin_incrementar(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $this->seedSequence('sale', 'branch', $branch->id, 'P', 3, 999);
        $allocator = $this->allocator();

        $last = $allocator->reserve('sale', $branch->id);
        $this->assertSame('P999', $last['visible']);
        $this->assertSame(999, $last['number']);
        $this->assertSame(1000, (int) $this->sequence('sale', 'branch', $branch->id)->next_number);

        try {
            $allocator->reserve('sale', $branch->id);
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado: no hay número 1000 con padding 3
        }

        $this->assertSame(1000, (int) $this->sequence('sale', 'branch', $branch->id)->next_number);
    }

    public function test_formato_de_ancho_fijo_y_tipo_entero_del_numero(): void
    {
        $branch = $this->makeBranch('S1');
        $allocator = $this->allocator();

        $first = $allocator->reserve('sale', $branch->id);
        $second = $allocator->reserve('sale', $branch->id);

        $this->assertSame('S1000001', $first['visible']);
        $this->assertSame('S1000002', $second['visible']);
        $this->assertIsInt($first['number']);
        $this->assertSame(1, $first['number']);
        $this->assertSame(strlen('S1') + 6, strlen($first['visible']));
    }

    public function test_reservar_no_modifica_code_ni_propiedades_de_la_sucursal(): void
    {
        $branch = $this->makeBranch('SUC-A');
        $before = $branch->fresh()->getAttributes();

        $this->allocator()->reserve('sale', $branch->id);

        $this->assertSame($before, $branch->fresh()->getAttributes());
        $this->assertDatabaseCount('branches', 1);
    }

    private function allocator(): DocumentSequenceAllocator
    {
        return new DocumentSequenceAllocator();
    }

    private function makeBranch(string $code, string $name = 'Sucursal Prueba'): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => $name,
            'type' => 'store',
            'is_active' => true,
            'requires_cash_control' => true,
        ]);
    }

    private function seedSequence(
        string $docType,
        string $scopeType,
        int $scopeId,
        string $prefix,
        int $padding,
        int $nextNumber,
    ): void {
        DB::table('document_sequences')->insert([
            'doc_type' => $docType,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'prefix' => $prefix,
            'padding' => $padding,
            'next_number' => $nextNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sequence(string $docType, string $scopeType, int $scopeId): ?object
    {
        return DB::table('document_sequences')
            ->where('doc_type', $docType)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();
    }
}
