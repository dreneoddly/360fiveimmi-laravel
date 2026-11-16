<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('flag_image');
            $table->string('name', 100);
            $table->enum('category', ['Residence', 'Citizenship']);
            $table->decimal('minimum_investment', 12, 2);
            $table->string('key_benefit');
            $table->string('processing_time', 50);
            $table->text('benefits')->nullable();
            $table->text('requirements')->nullable();
            $table->text('steps')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
