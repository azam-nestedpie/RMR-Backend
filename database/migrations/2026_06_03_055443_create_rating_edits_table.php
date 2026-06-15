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
        Schema::create('rating_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_id')->constrained()->cascadeOnDelete();
            $table->string('rater_firebase_uid', 128);
            $table->string('rep_firebase_uid', 128);
            $table->decimal('previous_average_score', 3, 2);
            $table->decimal('new_average_score', 3, 2);
            $table->timestamp('edited_at')->useCurrent();
            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            $table->index(['rep_firebase_uid', 'edited_at']);
            $table->index(['rater_firebase_uid', 'edited_at']);
            $table->index(['rating_id', 'edited_at']);

            $table->foreign('rater_firebase_uid')->references('firebase_uid')->on('users')->cascadeOnDelete();
            $table->foreign('rep_firebase_uid')->references('firebase_uid')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('firebase_uid')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_edits');
    }
};
