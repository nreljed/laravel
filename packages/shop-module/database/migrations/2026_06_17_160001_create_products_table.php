<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_backorders')->default(false);
            $table->string('status')->default('draft'); // draft, published, archived
            $table->string('type')->default('simple'); // simple, variable, digital, service
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->string('tax_class')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->json('images')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->json('attributes')->nullable();
            $table->json('variants')->nullable();
            $table->json('seo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('sku');
            $table->index('is_featured');
        });

        Schema::create('product_category_product', function (Blueprint $table) {
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_product');
        Schema::dropIfExists('products');
    }
};
