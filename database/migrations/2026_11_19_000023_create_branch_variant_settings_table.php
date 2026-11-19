<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// INVREORDER-001 - Configuración de reposición por sucursal y variante
// (Parte 2 §7.1, INV-07). Tabla mínima aprobada: PK compuesta
// (branch_id, variant_id), sin id ni timestamps automáticos.
//
// `safety_stock` y `location_label` se conservan con el esquema contractual
// para fases P1/P2, pero esta ola no los edita ni los publica. No se crean
// índices, columnas ni disparadores adicionales.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_variant_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->decimal('reorder_qty', 12, 3)->nullable();
            $table->decimal('max_stock', 12, 3)->nullable();
            $table->decimal('safety_stock', 12, 3)->default(0);
            $table->string('location_label', 50)->nullable();
            $table->primary(['branch_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_variant_settings');
    }
};
