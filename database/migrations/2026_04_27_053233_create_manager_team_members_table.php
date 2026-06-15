<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_team_members', function (Blueprint $table) {
            $table->id();
            $table->string('manager_firebase_uid', 128);
            $table->string('member_firebase_uid', 128);
            $table->unsignedBigInteger('manager_type_role_id');
            $table->boolean('active')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('active');
            $table->index('manager_type_role_id');
            $table->unique(['manager_firebase_uid', 'member_firebase_uid'], 'manager_team_members_manager_member_unique');

            $table->foreign('manager_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('member_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('manager_type_role_id')->references('id')->on('roles');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_team_members');
    }
};
