<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outposts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            // Стоимость маршрута от базы считается один раз при создании
            // партии: она не меняется и участвует в расчёте награды.
            // Приведённая стоимость взвешена по местности, длина в клетках —
            // физическая, из неё получается расстояние в километрах.
            $table->decimal('route_cost', 6, 2);
            $table->unsignedSmallInteger('route_tiles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outposts');
    }
};
