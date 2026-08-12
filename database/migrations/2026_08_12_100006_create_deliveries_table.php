<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rover_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('dispatched_day');
            $table->unsignedInteger('return_day');
            // Маршрут и все расчётные величины сохраняются на момент отправки,
            // чтобы потом было видно, что модель обещала и что вышло.
            $table->json('route');
            $table->decimal('route_cost', 6, 2);
            $table->decimal('battery_cost', 6, 2);
            $table->decimal('risk', 5, 4);
            $table->json('risk_breakdown');
            $table->unsignedBigInteger('seed');
            $table->string('status', 20)->default('in_transit');
            $table->string('outcome', 20)->nullable();
            $table->string('incident', 30)->nullable();
            // Отмечает, что задержка от инцидента уже учтена в return_day:
            // без этого флага рейс продлевался бы при каждой проверке.
            $table->boolean('delay_applied')->default(false);
            $table->unsignedInteger('resolved_day')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
