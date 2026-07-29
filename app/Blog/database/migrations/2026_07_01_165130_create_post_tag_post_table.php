<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_tag_post', function (Blueprint $table) {
            $table->foreignId('post_tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_tag_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag_post');
    }
};
