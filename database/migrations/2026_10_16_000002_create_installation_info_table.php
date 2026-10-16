<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 02/20 · Parte 2 §4.2 (una sola fila, sin FK)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_info', function (Blueprint $table) {
            $table->id();
            $table->char('installation_id', 36)->unique();
            $table->string('app_version', 20);
            $table->string('schema_version', 40)->nullable();
            $table->enum('release_channel', ['stable', 'beta'])->default('stable');
            $table->dateTime('installed_at');
            $table->dateTime('last_update_at')->nullable();
            $table->enum('last_update_status', ['none', 'ok', 'failed', 'rolled_back'])->default('none');
            $table->dateTime('recovery_package_exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_info');
    }
};
