<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 04/20 · Parte 2 §4.4 (caché local de licencia, una fila vigente)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_states', function (Blueprint $table) {
            $table->id();
            $table->string('installation_key', 100)->unique();
            $table->enum('status', ['active', 'expiring_soon', 'suspended'])->default('active')->index();
            $table->string('plan_code', 30)->default('demo')->index();
            $table->unsignedSmallInteger('max_branches')->default(0);
            $table->unsignedSmallInteger('max_pos_stations')->default(0);
            $table->unsignedSmallInteger('max_channels')->default(0);
            $table->unsignedSmallInteger('max_users')->default(0);
            $table->unsignedInteger('max_products')->default(0);
            $table->unsignedInteger('max_monthly_sales')->default(0);
            $table->json('products_enabled')->default('["*"]');
            $table->json('modules_enabled')->default('["*"]');
            $table->string('latest_version', 20)->nullable();
            $table->string('min_supported_version', 20)->nullable();
            $table->date('expires_at')->nullable();
            $table->date('grace_until')->nullable();
            $table->string('message', 255)->nullable();
            $table->text('signed_payload')->nullable();
            $table->string('signature', 255)->nullable();
            $table->unsignedTinyInteger('is_simulated')->default(1);
            $table->unsignedTinyInteger('notified_expiry_warning')->default(0);
            $table->dateTime('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_states');
    }
};
