<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 12/20 · Parte 2 §4.12 · Único (scope, code)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['override', 'stock_adjustment', 'waste', 'void', 'return', 'cash_movement']);
            $table->string('code', 30);
            $table->string('label', 120);
            $table->unsignedTinyInteger('requires_note')->default(0);
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['scope', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reason_codes');
    }
};
