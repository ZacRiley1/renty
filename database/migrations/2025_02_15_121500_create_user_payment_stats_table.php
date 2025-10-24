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
        Schema::create('user_payment_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('starting_score')->default(582);
            $table->unsignedSmallInteger('current_score')->default(582);
            $table->unsignedSmallInteger('goal_score')->default(760);
            $table->unsignedInteger('on_time_payments')->default(0);
            $table->unsignedInteger('payment_streak')->default(0);
            $table->unsignedInteger('reports_sent')->default(0);
            $table->timestamp('last_verified_payment_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_payment_stats');
    }
};
