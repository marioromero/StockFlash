<?php

namespace Tests\Feature\Install\Support;

use App\Support\Install\Contracts\InstallPreflightChecker;

final class FakePreflightChecker implements InstallPreflightChecker
{
    /**
     * @var array{ok: bool, checks: list<array{id: string, label: string, status: string, detail: string}>}
     */
    public array $result = [
        'ok' => true,
        'checks' => [
            [
                'id' => 'php_version',
                'label' => 'Versión de PHP',
                'status' => 'ok',
                'detail' => 'PHP de prueba.',
            ],
            [
                'id' => 'extensions',
                'label' => 'Extensiones de PHP',
                'status' => 'ok',
                'detail' => 'Extensiones de prueba.',
            ],
            [
                'id' => 'dependencies',
                'label' => 'Dependencias del proyecto',
                'status' => 'ok',
                'detail' => 'Dependencias de prueba.',
            ],
            [
                'id' => 'writable',
                'label' => 'Permisos de escritura',
                'status' => 'ok',
                'detail' => 'Permisos de prueba.',
            ],
        ],
    ];

    public int $calls = 0;

    public function check(): array
    {
        $this->calls++;

        return $this->result;
    }

    public function fail(string $detail = 'Requisito de prueba incumplido.'): void
    {
        $this->result = [
            'ok' => false,
            'checks' => [
                [
                    'id' => 'writable',
                    'label' => 'Permisos de escritura',
                    'status' => 'fail',
                    'detail' => $detail,
                ],
            ],
        ];
    }
}
