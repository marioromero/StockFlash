<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 15/20 · Parte 2 §4.15 (una fila por integración)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_health', function (Blueprint $table) {
            $table->id();
            $table->string('integration_key', 60)->unique();
            $table->enum('type', ['channel', 'fiscal', 'license', 'backup']);
            $table->enum('status', ['ok', 'warning', 'error', 'unknown'])->default('unknown')->index();
            $table->dateTime('last_checked_at')->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->dateTime('last_error_at')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->unsignedInteger('pending_jobs')->default(0);
            $table->dateTime('oldest_pending_at')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_health');
    }
};
