<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create', function () {
    $command = app(\App\Console\Commands\AdminCreateCommand::class);
    return $command->handle();
})->purpose('Crea el administrador inicial en entorno de desarrollo (DEV solo)');
