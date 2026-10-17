<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        });

        Schema::create('event_platform', function (Blueprint $table): void {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->primary(['event_id', 'platform_id']);
        });

        DB::table('platforms')->insert([
            ['slug' => 'pc', 'name' => 'PC (Windows)'],
            ['slug' => 'mac', 'name' => 'Mac'],
            ['slug' => 'linux', 'name' => 'Linux'],
            ['slug' => 'steam-deck', 'name' => 'Steam Deck'],
            ['slug' => 'playstation', 'name' => 'PlayStation'],
            ['slug' => 'xbox', 'name' => 'Xbox'],
            ['slug' => 'switch', 'name' => 'Nintendo Switch'],
            ['slug' => 'nintendo-other', 'name' => 'Other Nintendo console'],
            ['slug' => 'mobile', 'name' => 'Mobile (Android/iOS)'],
            ['slug' => 'vr', 'name' => 'VR'],
            ['slug' => 'other', 'name' => 'Other platform'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_platform');
        Schema::dropIfExists('platforms');
    }
};
