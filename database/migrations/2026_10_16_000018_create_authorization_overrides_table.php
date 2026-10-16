<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 18/20 · Parte 2 §4.18 · AO (append-only: sin UPDATE/DELETE)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_overrides', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['discount_over_cap', 'price_override', 'below_cost', 'void_sale', 'void_item', 'return', 'no_sale', 'cash_movement', 'stock_adjustment', 'cash_difference', 'other'])->index();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->foreign('requested_by_user_id')->references('id')->on('users');
            $table->unsignedBigInteger('authorized_by_user_id');
            $table->foreign('authorized_by_user_id')->references('id')->on('users');
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->unsignedBigInteger('pos_station_id')->nullable();
            $table->foreign('pos_station_id')->references('id')->on('pos_stations')->nullOnDelete();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('old_value', 100)->nullable();
            $table->string('new_value', 100)->nullable();
            $table->unsignedBigInteger('reason_code_id')->nullable();
            $table->foreign('reason_code_id')->references('id')->on('reason_codes')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_overrides');
    }
};
