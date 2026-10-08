<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A game night or LAN session. user_id is the organiser.
     *
     * Deleting an account removes the events it organised. A game or category
     * that an event still uses cannot be deleted: the admin has to move or remove
     * those events first, rather than leave them pointing at nothing.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->dateTime('starts_at')->index();
            $table->string('location');
            $table->unsignedSmallInteger('max_participants');
            $table->string('status')->default('open')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
