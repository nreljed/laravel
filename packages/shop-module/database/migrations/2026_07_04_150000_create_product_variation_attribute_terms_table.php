<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variation_attribute_term', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_term_id')->constrained('attribute_terms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_variation_id', 'attribute_term_id'], 'pv_at_unique');
            $table->index('attribute_term_id');
        });

        // Migrate existing JSON attribute data to the pivot table
        $variations = DB::table('product_variations')->whereNotNull('attributes')->get();

        foreach ($variations as $variation) {
            $attrs = json_decode($variation->attributes ?? '', true);

            if (! is_array($attrs) || empty($attrs)) {
                continue;
            }

            foreach ($attrs as $attr) {
                $attributeId = $attr['attribute_id'] ?? null;
                $termId = $attr['attribute_term_id'] ?? null;

                if (! $attributeId || ! $termId) {
                    continue;
                }

                $exists = DB::table('attributes')->where('id', $attributeId)->exists();
                $termExists = DB::table('attribute_terms')->where('id', $termId)->exists();

                if (! $exists || ! $termExists) {
                    continue;
                }

                DB::table('product_variation_attribute_term')->insert([
                    'product_variation_id' => $variation->id,
                    'attribute_id' => $attributeId,
                    'attribute_term_id' => $termId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variation_attribute_term');
    }
};
