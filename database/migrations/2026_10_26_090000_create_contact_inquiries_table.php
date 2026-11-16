<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('program_interest', 100);
            $table->string('preferred_contact_method', 20);
            $table->text('message')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('telephone', 50);
            $table->string('email');
            $table->string('nationality', 100)->nullable();
            $table->string('residence_country', 150);
            $table->boolean('consent')->default(false);
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
    }
};
