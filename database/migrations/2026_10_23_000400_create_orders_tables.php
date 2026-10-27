<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->string('order_number')->unique();
            $table->date('sequence_date')->index();
            $table->unsignedInteger('daily_sequence');
            $table->string('order_type')->index();
            $table->string('status')->index();
            $table->string('payment_status')->default('unpaid')->index();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->text('customer_address')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->text('notes')->nullable();

            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('item_discount_minor')->default(0);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('order_discount_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->bigInteger('service_charge_minor')->default(0);
            $table->bigInteger('delivery_fee_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('refunded_minor')->default(0);
            $table->bigInteger('cogs_minor')->nullable();

            $table->integer('tax_rate_bp')->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->string('tax_label')->nullable();
            $table->string('service_charge_label')->nullable();
            $table->boolean('kitchen_required')->default(true);

            $table->timestamp('inventory_deducted_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->unique(['sequence_date', 'daily_sequence']);
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['restaurant_table_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('category_name')->nullable();
            $table->string('variant_name')->nullable();
            $table->bigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->bigInteger('modifiers_total_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('line_net_minor');
            $table->boolean('is_taxable')->default(true);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('category_id');
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained('modifiers')->nullOnDelete();
            $table->foreignId('modifier_group_id')->nullable()->constrained('modifier_groups')->nullOnDelete();
            $table->string('group_name');
            $table->string('name');
            $table->bigInteger('price_minor')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method')->index();
            $table->string('status')->default('completed')->index();
            $table->bigInteger('amount_minor');
            $table->bigInteger('tendered_minor')->nullable();
            $table->bigInteger('change_minor')->default(0);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['paid_at', 'method']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->string('refund_number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method')->index();
            $table->bigInteger('amount_minor');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('service_charge_minor')->default(0);
            $table->bigInteger('delivery_fee_minor')->default(0);
            $table->text('reason');
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
