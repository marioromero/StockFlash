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
        $this->assertStringContainsString('Manual HTML de usuarios', $agents);
        $this->assertStringContainsString('Continuidad ChatGPT', $agents);

        $sfRun = $this->file('.opencode/commands/sf-run.md');
        $this->assertStringContainsString('GUIA_PENDIENTE_CHATGPT', $sfRun);
        $this->assertStringContainsString('CONTEXTO-CHATGPT.md', $sfRun);

        $sfPublish = $this->file('.opencode/commands/sf-publish.md');
        $this->assertStringContainsString('GUIA_ACTUALIZADA', $sfPublish);
        $this->assertStringContainsString('GUIA_PENDIENTE_CHATGPT', $sfPublish);
    }

    public function test_harness_documentation_files_are_integrated(): void
    {
        foreach ([
            'public/docs/index.html',
            'docs/harness/GUIA-HTML-USUARIO.md',
            'docs/harness/CONTEXTO-CHATGPT.md',
            'docs/work/GUIA-HTML-ESTADO.md',
        ] as $path) {
            $this->assertFileExists(base_path($path), "Falta archivo de documentación: {$path}");
        }
    }

    public function test_users_001_is_not_advertised_as_available_in_the_manual(): void
    {
        $html = $this->manual();

        $this->assertStringNotContainsString('USERS-001', $html);
        $this->assertStringContainsString(
            'Se documentarán cuando estén implementadas y verificadas',
            $html
        );
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
