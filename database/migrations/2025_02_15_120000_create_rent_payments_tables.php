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
        Schema::create('rent_payment_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_month');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });

        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_payment_range_id')->nullable()->constrained('rent_payment_ranges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('paid_on');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'submitted', 'verified', 'rejected'])->default('pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('paid_on');
        });

        Schema::create('rent_payment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_payment_id')->constrained('rent_payments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 10, 2);
            $table->timestamp('reported_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default('verified');
            $table->string('report_reference')->nullable();
            $table->string('reported_to')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique('rent_payment_id');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rent_payment_reports');
        Schema::dropIfExists('rent_payments');
        Schema::dropIfExists('rent_payment_ranges');
    }
};
