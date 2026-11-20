<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// INVTRANSFER-001 - Traspasos entre sucursales (Parte 1 §7.4 RN-26 y §8.3
// INV-06; Parte 2 §7.5). Solo se crean las dos tablas canonicas aprobadas.
//
// `number` es NOT NULL y unico por sucursal de origen. El numero canonico se
// asigna al pasar de borrador a solicitado con DocumentSequenceAllocator; un
// borrador usa un marcador interno `DRAFT-` + 24 hex mayusculas (30 chars) que
// la UI y los servicios de consulta ocultan. No se acepta numero del cliente.
//
// Sin created_at/updated_at: no estan aprobados para estas tablas. Los tiempos
// de negocio son requested_at/shipped_at/received_at y los fija el servidor.
// No se agregan indices, columnas ni estados fuera del contrato.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30);
            $table->unsignedBigInteger('from_branch_id');
            $table->foreign('from_branch_id')->references('id')->on('branches');
            $table->unsignedBigInteger('to_branch_id');
            $table->foreign('to_branch_id')->references('id')->on('branches');
            $table->enum('status', [
                'draft',
                'requested',
                'in_transit',
                'received',
                'partially_received',
                'cancelled',
            ])->default('draft');
            $table->index('status');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('shipped_by_user_id')->nullable();
            $table->foreign('shipped_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->foreign('received_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->string('notes', 500)->nullable();

            $table->unique(['from_branch_id', 'number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->foreign('transfer_id')->references('id')->on('stock_transfers')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants');
            $table->decimal('requested_qty', 12, 3)->default(0);
            $table->decimal('shipped_qty', 12, 3)->default(0);
            $table->decimal('received_qty', 12, 3)->default(0);
            $table->decimal('unit_cost_snapshot', 14, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
