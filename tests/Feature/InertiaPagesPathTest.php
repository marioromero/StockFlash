<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaPagesPathTest extends TestCase
{
    public function test_pages_path_uses_capitalized_pages_directory(): void
    {
        $configured = config('inertia.pages.paths');

        $this->assertSame([resource_path('js/Pages')], $configured);

        foreach ($configured as $path) {
            $normalized = str_replace('\\', '/', (string) $path);

            $this->assertStringEndsWith('/js/Pages', $normalized);
            $this->assertStringEndsNotWith('/js/pages', $normalized);
        }
    }

    public function test_pages_path_is_case_sensitive_not_lowercase(): void
    {
        $configured = config('inertia.pages.paths');

        $this->assertIsArray($configured);
        $this->assertCount(1, $configured);

        $path = (string) $configured[0];

        $this->assertNotSame(resource_path('js/pages'), $path);
        $this->assertSame(resource_path('js/Pages'), $path);
    }

    public function test_page_existence_checks_are_enabled(): void
    {
        $this->assertTrue((bool) config('inertia.pages.ensure_pages_exist'));
        $this->assertTrue((bool) config('inertia.testing.ensure_pages_exist'));
    }

    public function test_configured_pages_directory_exists_with_expected_case(): void
    {
        $configured = (string) config('inertia.pages.paths')[0];

        $this->assertDirectoryExists($configured);

        $segments = preg_split('#[\\\\/]#', $configured);
        $this->assertSame('Pages', end($segments));
    }

    public function test_inertia_components_exist_on_disk(): void
    {
        $components = [
            'Home',
            'Dashboard',
            'Auth/Login',
            'Auth/PasswordChange',
            'Branches/Index',
            'Branches/Form',
        ];

        foreach ($components as $component) {
            $file = resource_path('js/Pages/'.$component.'.vue');

            $this->assertFileExists($file, "Inertia component [{$component}] is missing at {$file}");
        }
    }
}
