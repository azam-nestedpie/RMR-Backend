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
        Schema::dropIfExists('external_rating_invites');
    }

    public function down(): void
    {
        Schema::create('external_rating_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('invite_uuid')->unique();
            $table->string('rep_firebase_uid', 128);
            $table->string('email', 191);
            $table->string('token', 128)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('status_id');

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            $table->index('rep_firebase_uid');
            $table->index('status_id');
            $table->index('email');

            $table->foreign('rep_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }
};
