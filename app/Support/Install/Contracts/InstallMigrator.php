<?php

namespace App\Support\Install\Contracts;

/**
 * Migraciones y semillas normales del sistema.
 *
 * Nunca expone `fresh`, `wipe`, reset ni operaciones destructivas. Las
 * pruebas inyectan un doble para simular éxito o fallo sin tocar una BD.
 */
interface InstallMigrator
{
    public function migrate(): void;

    public function seed(): void;

    /**
     * Nombre acotado de la última migración aplicada, o null si no se puede
     * determinar.
     */
    public function schemaVersion(): ?string;
}
