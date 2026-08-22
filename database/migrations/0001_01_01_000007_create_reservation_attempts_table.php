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
        Schema::create('reservation_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'failed'])
                ->default('pending');
            $table->json('payload');
            $table->json('result')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_attempts');
    }
};
