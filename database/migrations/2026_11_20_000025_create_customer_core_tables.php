<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CUSCORE-001 · Clientes P0 (Parte 2 §6.1 y §6.2 · CUS-01/02/03).
//
// Solo se crean las dos tablas aprobadas para esta ola: los grupos y el
// registro de clientes. No se crean direcciones, consentimientos, ventas,
// reservas ni historial de compras.
//
// Ambas tablas usan la convención SD: id autoincremental, timestamps de
// Laravel y deleted_at. Los endpoints de la aplicación no borran filas: solo
// alternan is_active; deleted_at queda reservado para el borrado lógico.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['person', 'company'])->default('person');
            $table->string('name', 150);
            $table->string('trade_name', 150)->nullable();
            $table->string('fiscal_id_encrypted', 255)->nullable();
            $table->char('fiscal_id_hash', 64)->nullable()->unique();
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('business_activity', 150)->nullable();
            $table->string('fiscal_address', 255)->nullable();
            $table->string('fiscal_commune', 100)->nullable();
            $table->string('fiscal_city', 100)->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->decimal('default_discount_percent', 5, 2)->nullable();
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->unsignedTinyInteger('marketing_opt_in')->default(0);
            $table->enum('origin', ['pos', 'online', 'import'])->default('pos');
            $table->dateTime('last_activity_at')->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->dateTime('blocked_at')->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
