<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 06/20 · Parte 2 §4.6 · SD
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->enum('type', ['store', 'warehouse', 'online'])->default('store')->index();
            $table->string('address', 255)->nullable();
            $table->string('commune', 100)->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedTinyInteger('is_active')->default(1)->index();
            $table->unsignedTinyInteger('requires_cash_control')->default(1);
            $table->enum('sales_flow_mode', ['direct', 'two_step', 'both'])->nullable();
            $table->enum('seller_code_policy', ['none', 'optional', 'required'])->nullable();
            $table->unsignedTinyInteger('allow_negative_stock')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
