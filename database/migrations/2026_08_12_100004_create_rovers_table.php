<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name', 10);
            $table->string('rover_class', 20);
            $table->unsignedSmallInteger('capacity_kg');
            $table->unsignedSmallInteger('battery_capacity');
            $table->decimal('battery_level', 6, 2);
            $table->string('status', 20)->default('idle');
            $table->unsignedTinyInteger('repair_days_left')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rovers');
    }
};
