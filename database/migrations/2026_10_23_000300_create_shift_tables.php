<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('open')->index();
            $table->bigInteger('opening_cash_minor')->default(0);
            $table->bigInteger('cash_sales_minor')->default(0);
            $table->bigInteger('cash_refunds_minor')->default(0);
            $table->bigInteger('cash_in_minor')->default(0);
            $table->bigInteger('cash_out_minor')->default(0);
            $table->bigInteger('expected_cash_minor')->nullable();
            $table->bigInteger('counted_cash_minor')->nullable();
            $table->bigInteger('difference_minor')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('opened_at');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->bigInteger('amount_minor');
            $table->string('reason');
            $table->timestamps();

            $table->index(['cashier_shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_shifts');
    }
};
