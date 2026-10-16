<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 09/20 · Parte 2 §4.9 · SD · PII (nombre/correo, hash, MFA cifrado)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->dateTime('password_changed_at')->nullable();
            $table->unsignedBigInteger('global_role_id')->nullable();
            $table->foreign('global_role_id')->references('id')->on('roles');
            $table->unsignedBigInteger('default_branch_id')->nullable();
            $table->foreign('default_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->string('pin_hash', 255)->nullable();
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
            $table->dateTime('pin_locked_until')->nullable();
            $table->unsignedTinyInteger('mfa_enabled')->default(0);
            $table->text('mfa_secret_encrypted')->nullable();
            $table->dateTime('mfa_confirmed_at')->nullable();
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->decimal('max_return_amount', 14, 2)->nullable();
            $table->unsignedTinyInteger('is_active')->default(1)->index();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->unsignedTinyInteger('must_change_password')->default(0);
            $table->dateTime('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
