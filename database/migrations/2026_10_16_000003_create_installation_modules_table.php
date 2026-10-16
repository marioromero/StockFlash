<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 03/20 · Parte 2 §4.3
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 30)->unique();
            $table->unsignedTinyInteger('is_enabled')->default(0);
            $table->dateTime('enabled_at')->nullable();
            $table->dateTime('disabled_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_modules');
    }
};
