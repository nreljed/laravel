<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('post_categories', 'depth')) {
                $table->unsignedSmallInteger('depth')->default(0)->after('parent_id');
            }
            if (! Schema::hasColumn('post_categories', 'meta_title')) {
                $table->string('meta_title', 70)->nullable()->after('description');
            }
            if (! Schema::hasColumn('post_categories', 'meta_description')) {
                $table->string('meta_description', 160)->nullable()->after('meta_title');
            }
        });

        $categories = DB::table('post_categories')->orderBy('id')->get();
        foreach ($categories as $category) {
            $depth = 0;
            $parentId = $category->parent_id;
            while ($parentId) {
                $depth++;
                $parent = DB::table('post_categories')->where('id', $parentId)->first();
                $parentId = $parent?->parent_id;
            }
            DB::table('post_categories')->where('id', $category->id)->update(['depth' => $depth]);
        }
    }

    public function down(): void
    {
        Schema::table('post_categories', function (Blueprint $table) {
            $table->dropColumn(['depth', 'meta_title', 'meta_description']);
        });
    }
};
