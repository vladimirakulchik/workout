<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_user_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->string('gender', 20);
            $table->string('skill_level')->nullable();
            $table->string('school')->nullable();
            $table->string('jersey_number', 5)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_child')->default(false);
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone')->nullable();
            $table->boolean('allow_token_spending')->default(false);
            $table->timestamps();

            $table->index(['parent_user_id', 'is_child']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_profiles');
    }
};
