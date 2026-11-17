<?php

namespace Tests\Feature\Setup;

use App\Support\Setup\SetupService;
use Database\Seeders\SystemConfigsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifica que el fixture global de pruebas siga representando una instalación
 * configurada y que el guard real funcione cuando se revierte.
 */
class SetupFixtureRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_suite_existente_arranca_con_setup_completado(): void
    {
        $setup = app(SetupService::class);

        $this->assertTrue($setup->schemaReady());
        $this->assertTrue($setup->isCompleted());
        $this->assertSame('1', $this->flag());
        $this->assertSame('bool', DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->value('value_type'));

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_mark_incomplete_activa_el_guard_real_y_mark_completed_lo_desactiva(): void
    {
        $setup = app(SetupService::class);
        $this->assertTrue($setup->isCompleted());

        $this->markSetupIncomplete();
        $this->assertFalse($setup->isCompleted());
        $this->get('/dashboard')->assertRedirect(route('setup.show'));

        $this->markSetupCompleted();
        $this->assertTrue($setup->isCompleted());
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_los_seeders_de_configuracion_no_dejan_la_suite_sin_setup(): void
    {
        $this->markSetupIncomplete();
        $this->assertSame('0', $this->flag());

        $this->seed(SystemConfigsSeeder::class);

        $this->assertSame('1', $this->flag());
        $this->assertTrue(app(SetupService::class)->isCompleted());
    }

    public function test_mark_setup_es_idempotente_y_tolera_valor_true(): void
    {
        $this->markSetupCompleted();
        $this->markSetupCompleted();

        DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->update(['config_value' => 'true']);

        $this->assertTrue(app(SetupService::class)->isCompleted());
        $this->assertSame(1, DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->count());
    }

    private function flag(): ?string
    {
        return DB::table('system_configs')
            ->where('config_key', 'is_setup_completed')
            ->value('config_value');
    }
}
