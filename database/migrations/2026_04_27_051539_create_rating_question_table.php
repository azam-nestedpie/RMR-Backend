<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_code', 50)->unique();
            $table->string('title_en', 255);
            $table->string('title_es', 255)->nullable();
            $table->string('title_pt', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_questions');
    }
};
