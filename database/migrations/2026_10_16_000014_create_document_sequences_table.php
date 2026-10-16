<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 14/20 · Parte 2 §4.14 · Único (doc_type, scope_type, scope_id)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->enum('doc_type', ['ticket', 'sale', 'return', 'transfer', 'count', 'goods_receipt', 'cash_session', 'purchase_order']);
            $table->enum('scope_type', ['installation', 'branch']);
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('prefix', 10)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->timestamps();
            $table->unique(['doc_type', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
