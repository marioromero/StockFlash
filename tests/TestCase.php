<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los tests PHP se ejecutan sin compilar Vite en su job de CI.
        // Evita depender de public/build/manifest.json al renderizar Inertia.
        $this->withoutVite();
    }
}
