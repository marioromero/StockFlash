<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 10/20 · Parte 2 §4.10 (hash de un solo uso)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_recovery_codes');
    }
};
