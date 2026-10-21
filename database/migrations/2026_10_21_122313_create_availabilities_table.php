<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->string('availabilityable_type');
            $table->unsignedBigInteger('availabilityable_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['availabilityable_type', 'availabilityable_id', 'day_of_week'], 'availabilities_owner_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
