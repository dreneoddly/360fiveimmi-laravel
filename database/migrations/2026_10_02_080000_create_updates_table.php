<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('updates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->date('published_at')->nullable();
            $table->string('image');
            $table->text('description');
            $table->json('paragraphs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('updates');
    }
};
