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
            $table->string('location')->nullable()->change();
            $table->string('location_reference')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('events')->whereNull('location')->update(['location' => 'Not announced']);

        Schema::table('events', function (Blueprint $table): void {
            $table->string('location')->nullable(false)->change();
            $table->dropColumn('location_reference');
        });
    }
};
