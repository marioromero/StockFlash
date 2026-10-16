<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 13/20 · Parte 2 §4.13 · SD
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_stations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->enum('printer_profile', ['thermal58', 'thermal80', 'a4'])->nullable();
            $table->string('device_token_hash', 64)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->unsignedTinyInteger('is_active')->default(1)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_stations');
    }
};
