<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_releases', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->unique();
            $table->string('status')->default('active')->index();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('deployed_by')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_releases');
    }
};
