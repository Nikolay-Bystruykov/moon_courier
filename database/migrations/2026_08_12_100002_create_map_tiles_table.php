<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_tiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->string('terrain', 20);

            $table->unique(['game_id', 'x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_tiles');
    }
};
