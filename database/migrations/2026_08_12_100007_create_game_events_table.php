<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('day');
            $table->string('type', 30);
            $table->string('message', 500);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
