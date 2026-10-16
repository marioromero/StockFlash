<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Corrección QA-REVIEW 2026-10-16 (nota: el hallazgo 1 del review era
// erróneo — 000001 ya incluye updated_by_user_id, verificado en BD; solo se
// aplica el hallazgo 2 válido):
// AO/AO-P solo created_at (Parte 2 §0.1): fuera updated_at en ambas.

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorization_overrides', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable();
        });
        Schema::table('authorization_overrides', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable();
        });
    }
};
