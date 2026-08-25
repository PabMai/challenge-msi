<?php

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
        Schema::table('tables', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('location_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('location_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
