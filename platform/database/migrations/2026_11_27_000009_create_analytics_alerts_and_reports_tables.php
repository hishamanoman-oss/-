<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('metric_type', ['net_sales', 'orders_count', 'paid_revenue'])->index();
            $table->enum('period_type', ['daily', 'weekly', 'monthly'])->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 18, 4);
            $table->string('currency_code', 10)->default('SAR');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'period_type', 'period_start']);
        });

        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 150);
            $table->enum('rule_type', [
                'low_stock',
                'sales_drop_anomaly',
                'cancellation_spike',
                'sync_failure',
                'command_approval_needed',
            ])->index();
            $table->json('threshold_json');
            $table->boolean('channel_in_app')->default(true);
            $table->boolean('channel_email')->default(false);
            $table->json('recipient_roles_json');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'rule_type']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('notification_rule_id')->nullable()->constrained('notification_rules')->nullOnDelete();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info')->index();
            $table->string('title', 200);
            $table->text('body');
            $table->string('action_url', 255)->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('dedup_key', 150)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'user_id', 'read_at']);
            $table->index(['tenant_id', 'user_id', 'dedup_key']);
        });

        Schema::create('scheduled_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->enum('report_type', [
                'sales_summary',
                'branch_comparison',
                'inventory_health',
                'customer_ltv',
                'financial_statement',
                'audit_activity',
            ])->index();
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->enum('format', ['csv', 'xlsx', 'pdf'])->default('csv');
            $table->json('recipients_json');
            $table->json('filters_json')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('performance_targets');
    }
};
