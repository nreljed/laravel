<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('variation')->default(false);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        // Migrate existing attribute_product pivot data to product_attributes
        $rows = DB::table('attribute_product')->get();
        foreach ($rows as $row) {
            $attrName = DB::table('attributes')->where('id', $row->attribute_id)->value('name');
            if (! $attrName) {
                continue;
            }

            $existing = DB::table('product_attributes')
                ->where('product_id', $row->product_id)
                ->where('attribute_id', $row->attribute_id)
                ->first();

            if ($existing) {
                continue;
            }

            DB::table('product_attributes')->insert([
                'product_id' => $row->product_id,
                'attribute_id' => $row->attribute_id,
                'name' => $attrName,
                'visible' => true,
                'variation' => false,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
