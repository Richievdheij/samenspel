<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sign-ups: one row per user per event. created_at is the moment of
     * signing up.
     *
     * The unique pair makes a double sign-up impossible in the database itself,
     * even when the validation in front of it is bypassed.
     */
    public function up(): void
    {
        Schema::create('event_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
    }
};
