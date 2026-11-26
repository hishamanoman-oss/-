<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 150);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('sku', 80);
            $table->string('name', 180);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->decimal('base_price', 18, 4);
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 80);
            $table->string('name', 180);
            $table->json('attributes_json')->nullable();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
