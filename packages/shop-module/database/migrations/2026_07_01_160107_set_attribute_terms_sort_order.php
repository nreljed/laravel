<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $terms = DB::table('attribute_terms')->orderBy('attribute_id')->orderBy('id')->get();
        $grouped = $terms->groupBy('attribute_id');
        foreach ($grouped as $attributeId => $attributeTerms) {
            foreach ($attributeTerms as $index => $term) {
                DB::table('attribute_terms')->where('id', $term->id)->update(['sort_order' => $index]);
            }
        }
    }

    public function down(): void
    {
        DB::table('attribute_terms')->update(['sort_order' => 0]);
    }
};
