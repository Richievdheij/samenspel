<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->json('available_platforms')->nullable();
            $table->timestamp('catalog_synced_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropIndex(['catalog_synced_at']);
            $table->dropColumn(['available_platforms', 'catalog_synced_at']);
        });
    }
};
