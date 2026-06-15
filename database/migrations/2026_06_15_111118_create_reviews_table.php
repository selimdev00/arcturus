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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('review_id');                 // Yandex reviewId (stable)
            $table->string('author')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->timestamp('reviewed_at')->nullable(); // updatedTime from Yandex
            $table->timestamps();

            $table->unique(['organization_id', 'review_id']);
            $table->index(['organization_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
