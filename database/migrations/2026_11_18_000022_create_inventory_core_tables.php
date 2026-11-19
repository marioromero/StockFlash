<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// INVCORE-001 - Inventario P0 (Parte 2 §7.2 y §7.4).
//
// Solo se crean las tablas minimas aprobadas para INV-01/INV-02/INV-03:
// - current_stock: cache transaccional por (branch_id, variant_id) con la
//   columna generada `available = on_hand - reserved` calculada por el motor.
// - stock_movements: kardex fisico inmutable; se corrige con `reversal`.
//
// `stock_reservations` y `branch_variant_settings` NO se crean en esta ola.
// Por eso `stock_movements.reservation_id` existe como columna nula aprobada
// pero su FK queda diferida al paquete de reservas/ventas. No se inventa una
// tabla ni una relacion para satisfacer el esquema.
//
// `current_stock` no usa timestamps automaticos: `updated_at` es NOT NULL y la
// aplicacion lo fija dentro de la misma transaccion (Parte 2 §7.2).
// `stock_movements` es append-only: sin created_at/updated_at.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('current_stock', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->decimal('on_hand', 12, 3)->default(0);
            $table->decimal('reserved', 12, 3)->default(0);
            $table->decimal('available', 12, 3)->virtualAs('on_hand - reserved');
            $table->dateTime('last_movement_at')->nullable();
            $table->dateTime('updated_at');
            $table->primary(['branch_id', 'variant_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants');
            $table->enum('type', [
                'initial',
                'purchase_receipt',
                'adjustment',
                'count_adjustment',
                'waste',
                'sale_pos',
                'sale_online',
                'customer_return',
                'supplier_return',
                'transfer_out',
                'transfer_in',
                'reversal',
            ]);
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            // FK diferida: `stock_reservations` no existe en esta ola.
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->unsignedBigInteger('reverses_movement_id')->nullable();
            $table->foreign('reverses_movement_id')->references('id')->on('stock_movements');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('reason_code_id')->nullable();
            $table->foreign('reason_code_id')->references('id')->on('reason_codes')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->dateTime('occurred_at');

            $table->index(['branch_id', 'variant_id', 'id']);
            $table->index(['variant_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('current_stock');
    }
};
