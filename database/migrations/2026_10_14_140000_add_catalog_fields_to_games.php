<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->index('name');
            $table->string('normalized_name')->nullable()->unique();
            $table->string('source')->default('manual');
            $table->string('provider')->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('platform')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropIndex(['name']);
            $table->unique('name');
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropUnique(['normalized_name']);
            $table->dropColumn(['normalized_name', 'source', 'provider', 'provider_id', 'platform', 'release_year', 'cover_image_url']);
        });
    }
};
