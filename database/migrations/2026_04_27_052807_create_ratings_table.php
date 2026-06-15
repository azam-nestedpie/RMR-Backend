<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('firebase_uuid')->unique();
            $table->string('rater_firebase_uid', 128);
            $table->string('rep_firebase_uid', 128);
            $table->unsignedBigInteger('rating_request_id')->nullable();
            $table->boolean('from_external_link')->default(false);
            $table->timestamp('rated_at')->useCurrent();
            $table->decimal('average_score', 3, 2);

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('rater_firebase_uid');
            $table->index('rep_firebase_uid');
            $table->index('rating_request_id');

            $table->foreign('rater_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('rep_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('rating_request_id')->references('id')->on('rating_requests')->onDelete('set null');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
