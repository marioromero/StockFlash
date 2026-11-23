<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SELLERS-001 · Registro de vendedores y asignación por sucursal
// (Parte 1 §8.9 SEL-01; Parte 2 §6.5).
//
// Solo se crean las dos tablas aprobadas para esta ola. No se crean ventas,
// tickets, reservas, reportes, comisiones ganadas ni historial comercial.
//
// `sellers` es SD (deleted_at) y contiene PII; el código identifica, nunca
// autentica (RN-14). `branch_seller` usa PK compuesta y no tiene id ni
// timestamps, según la convención §0.1.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branch_seller', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('branch_id');
            $table->primary(['seller_id', 'branch_id']);
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_seller');
        Schema::dropIfExists('sellers');
    }
};
