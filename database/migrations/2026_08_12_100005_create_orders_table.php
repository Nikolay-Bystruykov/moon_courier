<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outpost_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weight_kg');
            $table->unsignedInteger('reward');
            $table->unsignedInteger('deadline_day');
            $table->unsignedInteger('created_day');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
