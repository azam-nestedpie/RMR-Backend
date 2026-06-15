<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->string('user_firebase_uid', 128);
            $table->string('favorite_user_firebase_uid', 128);

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            $table->primary(['user_firebase_uid', 'favorite_user_firebase_uid']);
            $table->foreign('user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('favorite_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
