<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->index('category_id', 'posts_category_id_index');
        });

        Schema::table('post_tag_post', function (Blueprint $table) {
            $table->index('post_id', 'post_tag_post_post_id_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('posts', 'posts_category_id_index')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropIndex('posts_category_id_index');
            });
        }

        if (Schema::hasIndex('post_tag_post', 'post_tag_post_post_id_index')) {
            Schema::table('post_tag_post', function (Blueprint $table) {
                $table->dropIndex('post_tag_post_post_id_index');
            });
        }
    }
};
