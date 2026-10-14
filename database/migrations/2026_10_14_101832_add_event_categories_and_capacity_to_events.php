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
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_participants')->nullable();
            $table->unsignedSmallInteger('max_participants')->nullable()->change();
        });

        Schema::create('category_event', function (Blueprint $table): void {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->primary(['event_id', 'category_id']);
        });

        DB::statement('INSERT INTO category_event (event_id, category_id) SELECT id, category_id FROM events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_event');

        DB::table('events')->whereNull('max_participants')->update(['max_participants' => 65535]);

        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_participants')->nullable(false)->change();
            $table->dropColumn('min_participants');
        });
    }
};
