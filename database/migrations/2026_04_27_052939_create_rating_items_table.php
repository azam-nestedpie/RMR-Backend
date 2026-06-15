<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_items', function (Blueprint $table) {
            $table->unsignedBigInteger('rating_id');
            $table->unsignedBigInteger('question_id');
            $table->decimal('score', 3, 2);

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            $table->primary(['rating_id', 'question_id']);
            $table->foreign('rating_id')->references('id')->on('ratings')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('rating_questions')->onDelete('cascade');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_items');
    }
};
