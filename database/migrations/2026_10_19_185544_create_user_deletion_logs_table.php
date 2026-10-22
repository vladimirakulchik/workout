<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id')->index();
            $table->string('original_email');
            $table->foreignId('deleted_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->text('original_data');
            $table->timestamp('deleted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_deletion_logs');
    }
};
