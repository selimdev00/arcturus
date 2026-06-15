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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('business_id')->unique();   // numeric Yandex org id
            $table->string('source_url');               // original card URL
            $table->string('name')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();   // кол-во оценок
            $table->unsignedInteger('reviews_count')->nullable();   // кол-во отзывов
            $table->string('parse_status')->default('pending');     // ParseStatus enum
            $table->timestamp('parsed_at')->nullable();             // last full parse
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
