<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_player_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_profile_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('share_link_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamp('connected_at');
            $table->string('status', 10)->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['trainer_id', 'player_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_player_associations');
    }
};
