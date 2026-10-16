<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 16/20 · Parte 2 §4.16
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['daily', 'weekly', 'pre_update', 'manual']);
            $table->enum('status', ['running', 'ok', 'failed'])->index();
            $table->string('disk', 30);
            $table->string('path', 500)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedTinyInteger('is_encrypted')->default(1);
            $table->unsignedTinyInteger('includes_erasure_ledger')->default(1);
            $table->dateTime('integrity_checked_at')->nullable();
            $table->unsignedTinyInteger('integrity_ok')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
