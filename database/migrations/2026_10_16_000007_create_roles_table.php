<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 07/20 · Parte 2 §4.7 (5 roles de sistema)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->unsignedTinyInteger('authority_level');
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->decimal('max_return_amount', 14, 2)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('is_system')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
