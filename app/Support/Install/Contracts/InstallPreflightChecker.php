<?php

namespace App\Support\Install\Contracts;

/**
 * Verificación previa de requisitos técnicos del instalador.
 *
 * Devuelve únicamente checks técnicos sin secretos:
 * ['ok' => bool, 'checks' => list<array{id:string,label:string,status:string,detail:string}>].
 */
interface InstallPreflightChecker
{
    /**
     * @return array{ok: bool, checks: list<array{id: string, label: string, status: string, detail: string}>}
     */
    public function check(): array;
}
