<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Support\AuditLogger;
use App\Support\Inventory\InitialStockCsvParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

/**
 * Carga inicial de existencias desde CSV (INVCSV-001 · INV-05).
 *
 * Solo SQLite :memory:; MariaDB, navegador real y operaciones remotas quedan
 * NOT_RUN. Las pruebas usan el mismo middleware y controladores reales.
 */
class InitialStockCsvImportTest extends InventoryWriteTestCase
{
    public function test_pantalla_de_importacion_expone_limites_y_plantilla(): void
    {
        $branch = $this->makeBranch();

        $this->actingAs($this->admin)
            ->get($this->url($branch->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Import')
                ->where('branch.id', $branch->id)
                ->where('branch.code', $branch->code)
                ->where('can_adjust', true)
                ->where('can_view_inventory', true)
                ->where('read_only', false)
                ->where('preview', null)
                ->where('limits.max_rows', 500)
                ->where('limits.max_bytes', 524288)
                ->where('limits.max_sku_length', 100)
                ->where('limits.max_quantity_length', 20)
                ->where('template_url', route('inventory.import.template', ['branch' => $branch->id]))
            );
    }

    public function test_plantilla_csv_descarga_bom_encabezado_y_no_expone_rutas(): void
    {
        $branch = $this->makeBranch();

        $response = $this->actingAs($this->admin)
            ->get($this->url($branch->id, '/template'));

        $response->assertOk();
        $this->assertSame("\xEF\xBB\xBFsku;cantidad\r\n", $response->streamedContent());
        $this->assertStringContainsString(
            'plantilla-stock-inicial.csv',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringNotContainsString('storage', (string) $response->headers->get('content-disposition'));
    }

    public function test_preview_acepta_bom_utf8_separador_chileno_y_crlf(): void
    {
        $this->makeProductVariant('CSV-BOM-1');
        $this->makeProductVariant('CSV-BOM-2');
        $branch = $this->makeBranch();

        $content = "\xEF\xBB\xBFsku;cantidad\r\nCSV-BOM-1;5.250\r\nCSV-BOM-2;1\r\n";

        $response = $this->preview($branch->id, $content);

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('preview.totals.valid', 2)
            ->where('preview.totals.invalid', 0)
            ->where('preview.totals.total_quantity', '6.250')
            ->where('preview.has_errors', false)
            ->has('preview.rows', 2)
            ->where('preview.rows.0.status', 'ok')
            ->where('preview.rows.0.sku', 'CSV-BOM-1')
            ->where('preview.rows.0.quantity', '5.250')
        );

        $props = $this->pageProps($response);
        $this->assertSame('CSV-BOM-1', $props['preview']['rows'][0]['variant'] ?? null);
        $this->assertNotNull($props['preview']['rows'][0]['product'] ?? null);
        $this->assertSame('CSV-BOM-2', $props['preview']['rows'][1]['variant'] ?? null);
    }

    public function test_preview_acepta_coma_campos_entre_comillas_y_lf(): void
    {
        $this->makeProductVariant('CSV-Q1');
        $this->makeProductVariant('CSV-Q2');
        $branch = $this->makeBranch();

        $content = "sku,quantity\n\"CSV-Q1\",\"2.500\"\nCSV-Q2,3\n";

        $this->preview($branch->id, $content)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.totals.valid', 2)
                ->where('preview.totals.total_quantity', '5.500')
            );
    }

    public function test_parser_rechaza_utf8_invalido_sin_leer_datos_inseguros(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invcsv');
        $this->assertIsString($path);
        file_put_contents($path, "sku,quantity\nCSV-1,\xFF\n");

        try {
            $result = app(InitialStockCsvParser::class)->parse($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame([], $result['rows']);
        $this->assertNotSame([], $result['global_errors']);
        $this->assertStringContainsString('UTF-8', implode(' ', $result['global_errors']));
    }

    public function test_preview_rechaza_encabezado_faltante_duplicado_o_columnas_incorrectas(): void
    {
        $branch = $this->makeBranch();

        $cases = [
            "CSV-1;5.000\n",
            "sku;sku\nCSV-1;5.000\n",
            "sku;cantidad;extra\nCSV-1;5.000;1\n",
            "sku;precio\nCSV-1;5.000\n",
            "sku\nCSV-1\n",
        ];

        foreach ($cases as $content) {
            $props = $this->pageProps($this->preview($branch->id, $content));
            $this->assertNotSame([], $props['preview']['global_errors'], $content);
            $this->assertTrue($props['preview']['has_errors']);
            $this->assertSame(0, $props['preview']['totals']['valid']);
        }
    }

    public function test_preview_rechaza_comillas_mal_formadas_y_saltos_en_campos(): void
    {
        $this->makeProductVariant('CSV-QUOTE');
        $branch = $this->makeBranch();

        $unbalanced = "sku;cantidad\nCSV-QUOTE;\"5.000\n";
        $props = $this->pageProps($this->preview($branch->id, $unbalanced));
        $this->assertSame(0, $props['preview']['totals']['valid']);
        $this->assertSame(1, $props['preview']['totals']['invalid']);
        $this->assertStringContainsString('comillas', mb_strtolower((string) $props['preview']['rows'][0]['message']));

        $multiline = "sku;cantidad\n\"CSV-QUOTE\nX\";5.000\n";
        $props = $this->pageProps($this->preview($branch->id, $multiline));
        $this->assertTrue($props['preview']['has_errors']);
    }

    public function test_preview_rechaza_filas_en_blanco_y_archivo_sin_datos(): void
    {
        $this->makeProductVariant('CSV-BLANK-1');
        $this->makeProductVariant('CSV-BLANK-2');
        $branch = $this->makeBranch();

        $withBlank = "sku;cantidad\nCSV-BLANK-1;1.000\n\nCSV-BLANK-2;2.000\n";
        $props = $this->pageProps($this->preview($branch->id, $withBlank));
        $this->assertSame(1, $props['preview']['totals']['invalid']);
        $this->assertTrue($props['preview']['has_errors']);

        $emptyData = "sku;cantidad\n";
        $props = $this->pageProps($this->preview($branch->id, $emptyData));
        $this->assertSame([], $props['preview']['rows']);
        $this->assertStringContainsString(
            'no contiene filas de datos',
            mb_strtolower(implode(' ', $props['preview']['global_errors'])),
        );

        $emptyColumns = "sku;cantidad\n;\n";
        $props = $this->pageProps($this->preview($branch->id, $emptyColumns));
        $this->assertSame(1, $props['preview']['totals']['invalid']);
    }

    public function test_preview_rechaza_sku_duplicado_en_el_mismo_archivo(): void
    {
        $this->makeProductVariant('CSV-DUP');
        $branch = $this->makeBranch();

        $content = "sku;cantidad\nCSV-DUP;1.000\nCSV-DUP;2.000\n";

        $this->preview($branch->id, $content)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.totals.valid', 1)
                ->where('preview.totals.invalid', 1)
                ->where('preview.rows.1.status', 'error')
                ->where('preview.has_errors', true)
            );
    }

    public function test_preview_rechaza_sku_desconocido_y_variantes_inelegibles(): void
    {
        [$productInactive] = $this->makeProductVariant('CSV-INE-1');
        [, $variantInactive] = $this->makeProductVariant('CSV-INE-2');
        [$productNoStock] = $this->makeProductVariant('CSV-INE-3');
        [$productCombo] = $this->makeProductVariant('CSV-INE-4');
        [$productDeleted] = $this->makeProductVariant('CSV-INE-5');
        [, $variantDeleted] = $this->makeProductVariant('CSV-INE-6');

        DB::table('products')->where('id', $productInactive)->update(['is_active' => 0]);
        DB::table('product_variants')->where('id', $variantInactive)->update(['is_active' => 0]);
        DB::table('products')->where('id', $productNoStock)->update(['tracks_stock' => 0]);
        DB::table('products')->where('id', $productCombo)->update(['type' => 'combo']);
        DB::table('products')->where('id', $productDeleted)->update(['deleted_at' => now()]);
        DB::table('product_variants')->where('id', $variantDeleted)->update(['deleted_at' => now()]);

        $branch = $this->makeBranch();
        $content = "sku;cantidad\nCSV-NO-EXISTE;1.000\nCSV-INE-1;1\nCSV-INE-2;1\nCSV-INE-3;1\nCSV-INE-4;1\nCSV-INE-5;1\nCSV-INE-6;1\n";

        $props = $this->pageProps($this->preview($branch->id, $content));

        $this->assertSame(0, $props['preview']['totals']['valid']);
        $this->assertSame(7, $props['preview']['totals']['invalid']);
        $this->assertTrue($props['preview']['has_errors']);
    }

    public function test_preview_valida_decimales_exactos_y_rechaza_formatos_invalidos(): void
    {
        $this->makeProductVariant('CSV-DEC-1');
        $this->makeProductVariant('CSV-DEC-2');
        $branch = $this->makeBranch();

        $valid = "sku;cantidad\nCSV-DEC-1;0.001\nCSV-DEC-2;999999999.999\n";
        $response = $this->preview($branch->id, $valid);
        $props = $this->pageProps($response);
        $this->assertSame(2, $props['preview']['totals']['valid']);
        $this->assertSame('0.001', $props['preview']['rows'][0]['quantity']);
        $this->assertSame('999999999.999', $props['preview']['rows'][1]['quantity']);
        $this->assertTrue($props['preview']['totals']['total_quantity'] === null);

        $invalidQuantities = ['0', '0.000', '-1', '+1', '1e3', '1,5', '.5', '5.', '1000000000', '1.2345', ''];

        foreach ($invalidQuantities as $quantity) {
            $props = $this->pageProps($this->preview($branch->id, "sku;cantidad\nCSV-DEC-1;{$quantity}\n"));
            $this->assertSame(0, $props['preview']['totals']['valid'], "Cantidad aceptada: {$quantity}");
            $this->assertSame(1, $props['preview']['totals']['invalid'], "Cantidad aceptada: {$quantity}");
        }
    }

    public function test_preview_rechaza_mas_de_500_filas_y_archivo_mayor_a_512_kb(): void
    {
        $branch = $this->makeBranch();
        $rows = ["sku;cantidad\n"];

        for ($i = 1; $i <= 501; $i++) {
            $rows[] = "CSV-MASS-{$i};1\n";
        }

        $props = $this->pageProps($this->preview($branch->id, implode('', $rows)));
        $this->assertTrue($props['preview']['has_errors']);
        $this->assertStringContainsString(
            '500',
            implode(' ', $props['preview']['global_errors']),
        );

        $oversized = $this->upload(str_repeat('A', 513 * 1024), 'demasiado-grande.csv');
        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/preview'), ['file' => $oversized])
            ->assertSessionHasErrors('file');
    }

    public function test_preview_rechaza_tipo_de_archivo_no_permitido(): void
    {
        $branch = $this->makeBranch();
        $pdf = UploadedFile::fake()->create('stock.pdf', 1, 'application/pdf');

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/preview'), ['file' => $pdf])
            ->assertSessionHasErrors('file');
    }

    public function test_preview_totales_exactos_de_filas_validas(): void
    {
        $this->makeProductVariant('CSV-TOT-1');
        $this->makeProductVariant('CSV-TOT-2');
        $branch = $this->makeBranch();

        $this->preview($branch->id, "sku;cantidad\nCSV-TOT-1;2.500\nCSV-TOT-2;3.250\n")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.totals.valid', 2)
                ->where('preview.totals.invalid', 0)
                ->where('preview.totals.total_quantity', '5.750')
                ->where('preview.has_errors', false)
            );
    }

    public function test_commit_exitoso_crea_movimientos_initial_auditoria_y_no_toca_reservado(): void
    {
        $branch = $this->makeBranch();
        [, $first] = $this->makeProductVariant('CSV-COMMIT-1');
        [, $second] = $this->makeProductVariant('CSV-COMMIT-2');
        $this->createStockRow($branch->id, $first, '0.000', '4.000');

        $content = "sku;cantidad\nCSV-COMMIT-1;5.250\nCSV-COMMIT-2;1.000\n";

        $response = $this->actingAs($this->admin)->post(
            $this->url($branch->id, '/commit'),
            [
                'file' => $this->upload($content),
                'branch_id' => $branch->id + 100,
                'user_id' => 999,
                'type' => 'waste',
                'source_type' => 'sale_item',
                'source_id' => 999,
                'on_hand' => '999.000',
                'quantity' => '999.000',
            ],
        );

        $response->assertRedirect(route('inventory.stock.index', ['branch' => $branch->id]));
        $response->assertSessionHas('success');

        $this->assertSame(2, DB::table('stock_movements')->where('branch_id', $branch->id)->count());
        $this->assertSame(0, DB::table('stock_movements')->where('type', '!=', 'initial')->count());

        foreach ([[$first, '5.250'], [$second, '1.000']] as [$variantId, $quantity]) {
            $movement = DB::table('stock_movements')
                ->where('branch_id', $branch->id)
                ->where('variant_id', $variantId)
                ->first();

            $this->assertNotNull($movement);
            $this->assertSame('initial', $movement->type);
            $this->assertSame('inventory_initial', $movement->source_type);
            $this->assertSame($variantId, (int) $movement->source_id);
            $this->assertSame((int) $this->admin->id, (int) $movement->user_id);
            $this->assertNull($movement->unit_cost);
            $this->assertQuantity($quantity, $movement->quantity);
        }

        $this->assertQuantity(
            '5.250',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $first)->value('on_hand'),
        );
        $this->assertQuantity(
            '4.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $first)->value('reserved'),
        );
        $this->assertQuantity(
            '1.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $second)->value('on_hand'),
        );
        $this->assertQuantity(
            '0.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $second)->value('reserved'),
        );

        $this->assertSame(2, DB::table('audit_logs')->where('event', 'inventory.movement_recorded')->count());
    }

    public function test_commit_reparsea_el_archivo_y_no_usa_la_vista_previa_del_cliente(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('CSV-STALE');

        $this->preview($branch->id, "sku;cantidad\nCSV-STALE;2.000\n")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('preview.totals.valid', 1));

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/commit'), [
                'file' => $this->upload("sku;cantidad\nCSV-STALE;7.000\n"),
                'preview' => ['totals' => ['total_quantity' => '2.000']],
            ])
            ->assertRedirect();

        $movement = DB::table('stock_movements')
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variantId)
            ->first();

        $this->assertNotNull($movement);
        $this->assertQuantity('7.000', $movement->quantity);
    }

    public function test_segundo_commit_del_mismo_archivo_falla_ya_inicializado_sin_efectos(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('CSV-SECOND');
        $content = "sku;cantidad\nCSV-SECOND;3.000\n";

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/commit'), ['file' => $this->upload($content)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/commit'), ['file' => $this->upload($content)])
            ->assertSessionHasErrors('csv');

        $this->assertSame(1, $this->movementCount($branch->id, $variantId));
        $this->assertQuantity(
            '3.000',
            DB::table('current_stock')->where('branch_id', $branch->id)->where('variant_id', $variantId)->value('on_hand'),
        );
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'inventory.movement_recorded')->count());
    }

    public function test_commit_con_filas_validas_e_invalidas_no_escribe_nada(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('CSV-MIXED');
        $content = "sku;cantidad\nCSV-MIXED;2.000\nCSV-MIXED-UNKNOWN;1.000\n";

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/commit'), ['file' => $this->upload($content)])
            ->assertSessionHasErrors('csv');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('current_stock')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('event', 'inventory.movement_recorded')->count());
    }

    public function test_rollback_atomico_si_falla_la_auditoria_de_una_fila_posterior(): void
    {
        $branch = $this->makeBranch();
        [, $first] = $this->makeProductVariant('CSV-ROLL-1');
        [, $second] = $this->makeProductVariant('CSV-ROLL-2');
        $content = "sku;cantidad\nCSV-ROLL-1;1.000\nCSV-ROLL-2;2.000\n";

        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            private int $calls = 0;

            public function record(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                ?int $branchId = null,
                ?array $old = null,
                ?array $new = null,
                string $moduleCode = 'core',
                bool $isSecurityEvent = false,
                bool $isPersonalDataAccess = false,
            ): void {
                $this->calls++;

                if ($this->calls > 1) {
                    throw new RuntimeException('fallo de auditoría simulado');
                }

                parent::record(
                    $event,
                    $auditableType,
                    $auditableId,
                    $branchId,
                    $old,
                    $new,
                    $moduleCode,
                    $isSecurityEvent,
                    $isPersonalDataAccess,
                );
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin)->post(
                $this->url($branch->id, '/commit'),
                ['file' => $this->upload($content)],
            );
            $this->fail('La importación debía fallar por auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fallo de auditoría simulado', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('current_stock')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->assertSame(0, $this->movementCount($branch->id, $first));
        $this->assertSame(0, $this->movementCount($branch->id, $second));
    }

    public function test_licencia_solo_lectura_permite_ver_y_plantilla_pero_no_importar(): void
    {
        $branch = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('CSV-RO');
        $this->setLicense('suspended', null);

        $this->actingAs($this->admin)
            ->get($this->url($branch->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('read_only', true));

        $this->actingAs($this->admin)
            ->get($this->url($branch->id, '/template'))
            ->assertOk();

        $file = $this->upload("sku;cantidad\nCSV-RO;1.000\n");

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/preview'), ['file' => $file])
            ->assertSessionHasErrors('read_only');

        $this->actingAs($this->admin)
            ->post($this->url($branch->id, '/commit'), ['file' => $this->upload("sku;cantidad\nCSV-RO;1.000\n")])
            ->assertSessionHasErrors('read_only');

        $this->assertSame(0, $this->movementCount($branch->id, $variantId));
        $this->assertSame(0, DB::table('current_stock')->count());
    }

    public function test_permisos_rbac_y_lectura_sin_ajuste(): void
    {
        $branch = $this->makeBranch();
        $this->makeProductVariant('CSV-RBAC');

        $viewer = $this->makeBranchUser(
            $branch,
            $this->makeRoleWithPermissions(['inventory.view']),
        );

        $this->actingAs($viewer)
            ->get($this->url($branch->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_view_inventory', true)
                ->where('can_adjust', false)
            );

        $this->actingAs($viewer)
            ->post($this->url($branch->id, '/preview'), ['file' => $this->upload("sku;cantidad\nCSV-RBAC;1.000\n")])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post($this->url($branch->id, '/commit'), ['file' => $this->upload("sku;cantidad\nCSV-RBAC;1.000\n")])
            ->assertForbidden();

        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_sucursal_inactiva_sin_acceso_y_modulo_deshabilitado_fallan_cerrado(): void
    {
        $active = $this->makeBranch();
        $inactive = $this->makeBranch(['is_active' => false]);

        $this->actingAs($this->admin)->get($this->url($inactive->id))->assertForbidden();

        $this->disableInventoryModule();
        $file = $this->upload("sku;cantidad\nCSV-X;1.000\n");

        $this->actingAs($this->admin)->get($this->url($active->id))->assertForbidden();
        $this->actingAs($this->admin)->get($this->url($active->id, '/template'))->assertForbidden();
        $this->actingAs($this->admin)
            ->post($this->url($active->id, '/preview'), ['file' => $file])
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post($this->url($active->id, '/commit'), ['file' => $file])
            ->assertForbidden();

        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_codigo_de_sucursal_1e0_no_amplia_el_alcance_autorizado(): void
    {
        $byId = $this->makeBranch(['code' => 'SUC-UNO']);
        $coded = $this->makeBranch(['code' => '1E0']);
        [, $variantId] = $this->makeProductVariant('CSV-1E0');

        $this->assertSame((int) $byId->id, (int) DB::table('branches')->min('id'));

        $this->actingAs($this->admin)
            ->get('/inventory/stock/1E0/import')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branch.id', $byId->id));

        $codedUser = $this->makeBranchUser($coded, 'warehouse_keeper');
        $this->actingAs($codedUser)
            ->get('/inventory/stock/1E0/import')
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post('/inventory/stock/1E0/import/commit', [
                'file' => $this->upload("sku;cantidad\nCSV-1E0;1.000\n"),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->movementCount($byId->id, $variantId));
        $this->assertSame(0, $this->movementCount($coded->id, $variantId));
    }

    public function test_historial_en_otra_sucursal_no_bloquea_y_las_sucursales_quedan_aisladas(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        [, $variantId] = $this->makeProductVariant('CSV-CROSS');

        $this->recordMovement($branchA->id, $variantId, 'adjustment', '1.000');

        $this->preview($branchB->id, "sku;cantidad\nCSV-CROSS;2.000\n")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.totals.valid', 1)
                ->where('preview.has_errors', false)
            );

        $this->actingAs($this->admin)
            ->post($this->url($branchB->id, '/commit'), [
                'file' => $this->upload("sku;cantidad\nCSV-CROSS;2.000\n"),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->movementCount($branchA->id, $variantId));
        $this->assertSame(1, $this->movementCount($branchB->id, $variantId));
        $this->assertSame(
            'initial',
            DB::table('stock_movements')->where('branch_id', $branchB->id)->where('variant_id', $variantId)->value('type'),
        );
    }

    public function test_skus_con_prefijo_de_formula_y_controles_son_rechazados(): void
    {
        $branch = $this->makeBranch();

        foreach (['=cmd', '+1', '-1', '@sum', "CSV\x07CTRL"] as $sku) {
            $props = $this->pageProps($this->preview($branch->id, "sku;cantidad\n{$sku};1.000\n"));

            $this->assertSame(0, $props['preview']['totals']['valid'], $sku);
            $this->assertSame(1, $props['preview']['totals']['invalid'], $sku);
            $this->assertStringNotContainsString('<script', json_encode($props, JSON_THROW_ON_ERROR));
        }
    }

    public function test_usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $branch = $this->makeBranch();

        $this->get($this->url($branch->id))->assertRedirect(route('login'));
        $this->get($this->url($branch->id, '/template'))->assertRedirect(route('login'));
    }

    public function test_no_crea_tablas_nuevas_ni_movimientos_de_ajuste(): void
    {
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('stock_imports'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('initial_stock_imports'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('stock_movements'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('current_stock'));
    }

    private function url(int|string $branch, string $suffix = ''): string
    {
        return "/inventory/stock/{$branch}/import{$suffix}";
    }

    private function upload(string $content, string $name = 'carga-inicial.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function preview(int|string $branch, string $content): TestResponse
    {
        return $this->actingAs($this->admin)->post(
            $this->url($branch, '/preview'),
            ['file' => $this->upload($content)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(TestResponse $response): array
    {
        $response->assertOk();

        return $response->viewData('page')['props'];
    }
}
