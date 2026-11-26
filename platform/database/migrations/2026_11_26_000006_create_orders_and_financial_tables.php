<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->decimal('rate_percent', 8, 4);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->enum('discount_type', ['fixed', 'percent']);
            $table->decimal('value', 18, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('order_sequences', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('order_number', 64);
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'completed', 'cancelled'])->default('pending')->index();
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'partially_refunded', 'refunded'])->default('unpaid')->index();
            $table->enum('fulfillment_status', ['unfulfilled', 'partial', 'fulfilled', 'returned'])->default('unfulfilled')->index();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('gross_sales', 18, 4)->default(0);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('net_sales', 18, 4)->default(0);
            $table->decimal('paid_total', 18, 4)->default(0);
            $table->decimal('refunded_total', 18, 4)->default(0);
            $table->decimal('outstanding_total', 18, 4)->default(0);
            $table->decimal('cogs_total', 18, 4)->nullable();
            $table->boolean('has_complete_cost_data')->default(false);
            $table->timestamp('placed_at')->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'branch_id', 'placed_at'], 'idx_orders_branch_date');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku_snapshot', 80);
            $table->string('product_name_snapshot', 180);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price_snapshot', 18, 4);
            $table->decimal('unit_cost_snapshot', 18, 4)->nullable();
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->enum('status', ['active', 'fulfilled', 'released', 'expired'])->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payment_reference', 80);
            $table->string('method', 50);
            $table->decimal('amount', 18, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->enum('status', ['captured', 'voided'])->default('captured');
            $table->string('idempotency_key', 120);
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('reason', 255);
            $table->boolean('restock_inventory')->default(false);
            $table->string('idempotency_key', 120);
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('financial_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('snapshot_date');
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('gross_sales', 18, 4)->default(0);
            $table->decimal('discounts', 18, 4)->default(0);
            $table->decimal('refunds', 18, 4)->default(0);
            $table->decimal('net_sales', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('paid_total', 18, 4)->default(0);
            $table->decimal('outstanding_total', 18, 4)->default(0);
            $table->decimal('cogs_total', 18, 4)->nullable();
            $table->decimal('gross_profit', 18, 4)->nullable();
            $table->decimal('operating_expenses', 18, 4)->nullable();
            $table->decimal('net_profit', 18, 4)->nullable();
            $table->enum('profit_completeness_status', ['complete', 'missing_cost', 'missing_expenses', 'incomplete'])->default('incomplete');
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'snapshot_date', 'currency_code'], 'uq_fin_snapshots_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_snapshots');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_sequences');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('taxes');
    }
};
