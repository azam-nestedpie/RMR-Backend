<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('firebase_uuid')->unique();
            $table->string('to_user_firebase_uid', 128);
            $table->string('from_user_firebase_uid', 128)->nullable();
            $table->text('message');
            $table->string('screen', 100)->nullable();
            $table->integer('tab_index')->nullable();
            $table->boolean('is_for_external_rating')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->useCurrent();

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('to_user_firebase_uid');
            $table->index('from_user_firebase_uid');
            $table->index('is_read');

            $table->foreign('to_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('from_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
