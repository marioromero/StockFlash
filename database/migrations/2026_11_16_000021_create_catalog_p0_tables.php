<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CATDB-001 - Catalogo P0 (Parte 2 5.1-5.6). Lote 04, etapa R3.
// Orden de creacion: tax_rates, categories, brands, tags, attributes,
// attribute_values, products, product_variants, product_barcodes, product_tag,
// product_variant_attribute_values. down() revierte en orden inverso.
// FULLTEXT de products.name solo se declara en MariaDB/MySQL; SQLite no lo
// simula porque el contrato no lo exige para el motor de pruebas.

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->decimal('rate', 5, 2);
            $table->unsignedTinyInteger('is_exempt')->default(0);
            $table->unsignedTinyInteger('is_default')->default(0);
            $table->string('fiscal_code', 10)->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('default_tax_rate_id')->nullable();
            $table->foreign('default_tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('description', 255)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->string('value', 100);
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('products', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->enum('type', ['simple', 'variable', 'combo'])->default('simple')->index();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('internal_code', 60)->nullable()->unique();
            $table->string('image_url', 500)->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            $table->unsignedTinyInteger('tracks_stock')->default(1);
            $table->unsignedTinyInteger('is_sellable')->default(1);
            $table->unsignedTinyInteger('is_active')->default(1)->index();
            $table->enum('origin', ['pos', 'channel', 'import'])->default('pos');
            $table->timestamps();
            $table->softDeletes();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->fullText('name');
            }
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('sku', 100)->unique();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->decimal('last_cost', 14, 4)->nullable();
            $table->enum('unit', ['unit', 'kg', 'g', 'lt', 'ml', 'm', 'box', 'other'])->default('unit');
            $table->unsignedTinyInteger('sold_by_weight')->default(0);
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->decimal('min_price', 14, 2)->nullable();
            $table->unsignedTinyInteger('allow_price_override')->default(1);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            $table->unsignedTinyInteger('combo_deducts_components')->default(1);
            $table->unsignedTinyInteger('is_active')->default(1)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->string('barcode', 64)->unique();
            $table->enum('type', ['ean13', 'ean8', 'upc', 'code128', 'internal', 'other'])->default('ean13');
            $table->unsignedTinyInteger('is_primary')->default(0);
            $table->timestamps();
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unsignedBigInteger('tag_id');
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('product_variant_attribute_values', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('attribute_value_id');
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values')->cascadeOnDelete();
            $table->primary(['variant_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('tax_rates');
    }
};
