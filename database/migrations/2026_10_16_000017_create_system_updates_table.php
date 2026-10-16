<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 17/20 · Parte 2 §4.17 (→ backup_runs)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 20);
            $table->string('to_version', 20);
            $table->enum('status', ['started', 'ok', 'failed', 'rolled_back']);
            $table->char('package_hash', 64)->nullable();
            $table->unsignedTinyInteger('signature_valid')->default(0);
            $table->unsignedBigInteger('backup_run_id')->nullable();
            $table->foreign('backup_run_id')->references('id')->on('backup_runs')->nullOnDelete();
            $table->unsignedBigInteger('started_by_user_id')->nullable();
            $table->foreign('started_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
