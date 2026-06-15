<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_rating_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('industry_id');
            $table->unsignedBigInteger('question_id');
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(true);

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            $table->primary(['industry_id', 'question_id']);
            $table->foreign('industry_id')->references('id')->on('industries')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('rating_questions')->onDelete('cascade');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_rating_questions');
    }
};
