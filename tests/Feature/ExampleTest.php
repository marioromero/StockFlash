<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * BOOT-001: la ruta raíz sirve la página Inertia Home sin DB.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Los tests HTTP no requieren el manifiesto de Vite; el smoke CI
        // comprueba la ruta con el frontend compilado por separado.
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('StockFlash', false);
        // Componente Inertia renderizado en el payload inicial.
        $response->assertSee('"component":"Home"', false);
    }
}
