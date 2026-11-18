<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

class DocsIntegrationTest extends TestCase
{
    public function test_docs_route_serves_the_user_manual(): void
    {
        $response = $this->get('/docs/');

        $response->assertOk();
        $this->assertStringContainsString(
            'text/html',
            (string) $response->headers->get('content-type')
        );
        $response->assertSee('Guía de usuario | StockFlash', false);
        $response->assertSee('id="search"', false);
        $response->assertSee('id="topics-nav"', false);
    }

    public function test_docs_without_trailing_slash_also_serves_the_manual(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();
        $response->assertSee('Guía de usuario | StockFlash', false);
    }

    public function test_manual_is_self_contained_accessible_and_responsive(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('lang="es-CL"', $html);
        $this->assertStringContainsString('id="search"', $html);
        $this->assertStringContainsString('id="topics-nav"', $html);
        $this->assertStringContainsString('class="sidebar"', $html);
        $this->assertStringContainsString('details class="accordion"', $html);
        $this->assertStringContainsString('aria-label', $html);
        $this->assertStringContainsString('@media(max-width:820px)', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);

        $this->assertStringNotContainsString('<script src=', $html);
        $this->assertStringNotContainsString('fetch(', $html);
        $this->assertStringNotContainsString('XMLHttpRequest', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
        $this->assertStringNotContainsString('type="password"', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_dashboard_has_visible_help_link_to_docs(): void
    {
        $dashboard = $this->file('resources/js/Pages/Dashboard.vue');

        $this->assertStringContainsString('href="/docs/"', $dashboard);
        $this->assertStringContainsString('Ayuda', $dashboard);
        $this->assertStringContainsString('rel="noopener noreferrer"', $dashboard);
        $this->assertStringContainsString('aria-label="Abrir la guía de ayuda en una nueva pestaña"', $dashboard);
    }

    public function test_guide_ownership_and_coverage_tracking_are_present(): void
    {
        $state = $this->file('docs/work/GUIA-HTML-ESTADO.md');

        $this->assertStringContainsString('content_owner: ChatGPT', $state);
        $this->assertStringContainsString('guide_status: INTEGRADA_EN_MAIN', $state);
        $this->assertStringContainsString('AUTH-001 VERIFIED', $state);
        $this->assertStringContainsString('BRANCH-001 VERIFIED', $state);
        $this->assertStringContainsString('pending_docs:', $state);
        // El estado de USERS-001 avanza de DRAFT a APPROVED/VERIFIED: no fijar un estado histórico.
        $this->assertStringContainsString('USERS-001', $state);
    }

    public function test_agents_and_harness_commands_define_documentation_handoff(): void
    {
        $agents = $this->file('AGENTS.md');
        $this->assertStringContainsString('public/docs/index.html', $agents);
        $this->assertStringContainsString('docs/harness/CONTEXTO-CHATGPT.md', $agents);
        $this->assertStringContainsString('All agent instructions', $agents);

        // Check the canonical English ownership contract rather than duplicating
        // exact Spanish paragraphs in every OpenCode command.
        $guidePolicy = $this->file('docs/harness/GUIA-HTML-USUARIO.md');
        $this->assertStringContainsString('exclusive author', $guidePolicy);
        $this->assertStringContainsString('GUIA_PENDIENTE_CHATGPT', $guidePolicy);
        $this->assertStringContainsString('public/docs/index.html', $guidePolicy);

        $gptPolicy = $this->file('docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md');
        $this->assertStringContainsString('English', $gptPolicy);
        $this->assertStringContainsString('OpenCode MAY READ but MUST NOT EDIT', $gptPolicy);

        $sfRun = $this->file('.opencode/commands/sf-run.md');
        $this->assertStringContainsString('stockflash-orchestrator', $sfRun);
        $this->assertStringContainsString('public/docs/index.html', $sfRun);

        $sfPublish = $this->file('.opencode/commands/sf-publish.md');
        $this->assertStringContainsString('stockflash-publisher', $sfPublish);
        $this->assertStringContainsString('GPT', $sfPublish);
    }

    public function test_harness_documentation_files_are_integrated(): void
    {
        foreach ([
            'public/docs/index.html',
            'docs/harness/GUIA-HTML-USUARIO.md',
            'docs/harness/CONTEXTO-CHATGPT.md',
            'docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md',
            'docs/work/GUIA-HTML-ESTADO.md',
        ] as $path) {
            $this->assertFileExists(base_path($path), "Falta archivo de documentación: {$path}");
        }
    }

    public function test_users_001_is_documented_as_verified_without_claiming_deploy(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="usuarios"', $html);
        $this->assertStringContainsString('USERS-001', $html);
        $this->assertStringContainsString('VERIFIED técnicamente', $html);
        $this->assertStringContainsString('No implica despliegue a clientes', $html);
    }

    public function test_cfg_001_receipt_settings_are_explained_without_fiscal_claims(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="comprobante"', $html);
        $this->assertStringContainsString('Ajustes de comprobante', $html);
        $this->assertStringContainsString('settings.manage', $html);
        $this->assertStringContainsString('no tributario', $html);
        $this->assertStringContainsString('Sin despliegue comercial', $html);
    }

    public function test_cfg_002_business_settings_are_explained_without_sii_claims(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="datos-comercio"', $html);
        $this->assertStringContainsString('Datos del comercio', $html);
        $this->assertStringContainsString('settings.manage', $html);
        $this->assertStringContainsString('no valida el RUT ante el SII', $html);
        $this->assertStringContainsString('Sin despliegue comercial', $html);
    }

    public function test_cfg_003_general_preferences_are_explained_without_tax_or_sales_claims(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="preferencias"', $html);
        $this->assertStringContainsString('America/Santiago', $html);
        $this->assertStringContainsString('settings.manage', $html);
        $this->assertStringContainsString('no modifica documentos anteriores', $html);
    }

    public function test_corelic_001_license_notices_are_documented_without_deploy_claims(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="estado-licencia"', $html);
        $this->assertStringContainsString('Modo solo lectura', $html);
        $this->assertStringContainsString('No es una pantalla para comprar', $html);
        $this->assertStringContainsString('sin despliegue comercial', $html);
    }

    public function test_catmast_001_catalog_masters_guide_explains_permissions_and_scope(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="catalogo-maestros"', $html);
        $this->assertStringContainsString('/catalog/masters', $html);
        $this->assertStringContainsString('catalog.view', $html);
        $this->assertStringContainsString('catalog.manage', $html);
        $this->assertStringContainsString('no emite boletas', $html);
    }

    public function test_manual_topic_counter_and_navigation_match_real_sections(): void
    {
        $html = $this->manual();

        preg_match_all('/<section class="topic" id="([^"]+)"/', $html, $sections);
        preg_match_all('/<li><a href="#([^"]+)"/', $html, $navigation);

        $sectionIds = $sections[1];
        $navigationIds = $navigation[1];

        $this->assertNotEmpty($sectionIds);
        $this->assertCount(count($sectionIds), array_unique($sectionIds), 'Hay secciones con ID duplicado.');
        $this->assertCount(count($navigationIds), array_unique($navigationIds), 'Hay enlaces del índice duplicados.');

        sort($sectionIds);
        sort($navigationIds);

        $this->assertSame($sectionIds, $navigationIds, 'El índice debe incluir exactamente los temas existentes.');
        $this->assertStringContainsString(count($sectionIds).' temas disponibles.', $html);
    }

    public function test_catprod_001_products_variants_and_cost_permissions_are_documented(): void
    {
        $html = $this->manual();

        $this->assertStringContainsString('id="catalogo-productos"', $html);
        $this->assertStringContainsString('/catalog/products', $html);
        $this->assertStringContainsString('catalog.view_cost', $html);
        $this->assertStringContainsStringIgnoringCase('calculadora de precio', $html);
        $this->assertStringContainsString('sin despliegue comercial', $html);
    }

    private function manual(): string
    {
        return $this->file('public/docs/index.html');
    }

    private function file(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }
}
