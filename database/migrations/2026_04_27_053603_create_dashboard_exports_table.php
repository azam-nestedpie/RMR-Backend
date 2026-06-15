<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_exports', function (Blueprint $table) {
            $table->id();
            $table->string('requested_by_firebase_uid', 128);
            $table->string('scope_type', 50);
            $table->string('scope_user_firebase_uid', 128)->nullable();
            $table->json('filters_json')->nullable();
            $table->text('file_url')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('requested_by_firebase_uid');
            $table->index('status_id');

            $table->foreign('requested_by_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('scope_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_exports');
    }
};
