<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB-001 · Lote 01/20 · Parte 2 §4.1
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 100)->unique();
            $table->text('config_value')->nullable();
            $table->enum('value_type', ['string', 'int', 'decimal', 'bool', 'json', 'text'])->default('string');
            $table->string('module_code', 30)->nullable()->index();
            $table->unsignedTinyInteger('is_secret')->default(0);
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
