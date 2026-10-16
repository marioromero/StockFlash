<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 05/20 · Parte 2 §4.5 (catálogo local informativo de planes)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('max_branches')->default(0);
            $table->unsignedSmallInteger('max_pos_stations')->default(0);
            $table->unsignedSmallInteger('max_channels')->default(0);
            $table->unsignedSmallInteger('max_users')->default(0);
            $table->unsignedInteger('max_products')->default(0);
            $table->unsignedInteger('max_monthly_sales')->default(0);
            $table->json('products_enabled');
            $table->json('modules_enabled');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_definitions');
    }
};
