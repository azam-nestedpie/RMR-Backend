<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->string('scope_type', 50);
            $table->string('scope_user_firebase_uid', 128)->nullable();
            $table->string('metric_key', 100);
            $table->decimal('metric_value', 15, 2)->default(0);
            $table->json('metric_json')->nullable();

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index(['snapshot_date', 'metric_key']);
            $table->index('scope_user_firebase_uid');

            $table->foreign('scope_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
    }
};
