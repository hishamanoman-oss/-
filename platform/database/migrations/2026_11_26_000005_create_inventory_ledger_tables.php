<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('on_hand', 18, 4)->default(0);
            $table->decimal('reserved', 18, 4)->default(0);
            $table->decimal('available', 18, 4)->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['tenant_id', 'warehouse_id', 'product_id', 'variant_id'], 'uq_inv_balances_scope');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->enum('movement_type', [
                'opening',
                'receipt',
                'sale_deduction',
                'reservation',
                'release_reservation',
                'return_restock',
                'transfer_out',
                'transfer_in',
                'adjustment',
            ])->index();
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('idempotency_key', 120);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'idempotency_key'], 'uq_stock_movements_idempotency');
            $table->index(['tenant_id', 'warehouse_id', 'product_id'], 'idx_stock_mov_lookup');
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('transfer_number', 60);
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('status', ['draft', 'in_transit', 'completed', 'cancelled'])->default('draft')->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'transfer_number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_balances');
    }
};
