<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->enum('connector_type', [
                'local_mysql_agent',
                'local_mysql_direct_test',
                'webhook_generic',
                'csv_import',
            ])->index();
            $table->enum('status', ['draft', 'active', 'paused', 'error', 'disconnected'])->default('draft')->index();
            $table->enum('freshness_status', ['live', 'delayed', 'disconnected', 'syncing', 'error'])->default('disconnected')->index();
            $table->enum('capture_mode', ['incremental_polling', 'cdc_binlog', 'webhooks', 'manual_csv'])->default('incremental_polling');
            $table->unsignedInteger('sync_interval_seconds')->default(30);
            $table->json('capabilities_json')->nullable();
            $table->text('config_encrypted')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->text('last_error_summary')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('connector_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('device_id', 120)->unique();
            $table->string('device_name', 150);
            $table->string('agent_version', 40)->nullable();
            $table->string('enrollment_code_hash', 64)->nullable()->index();
            $table->timestamp('enrollment_expires_at')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->text('public_key_ed25519')->nullable();
            $table->enum('status', ['pending_enrollment', 'active', 'revoked'])->default('pending_enrollment')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'data_source_id']);
        });

        Schema::create('field_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->enum('canonical_entity', [
                'branch',
                'warehouse',
                'customer',
                'product',
                'inventory_balance',
                'order',
                'order_item',
            ]);
            $table->string('source_table', 120);
            $table->string('source_column', 120);
            $table->string('canonical_field', 120);
            $table->enum('transform_rule', ['trim', 'decimal', 'iso_date', 'uppercase', 'map_status'])->nullable();
            $table->string('default_value', 120)->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'data_source_id', 'canonical_entity', 'canonical_field'], 'uq_field_map_target');
        });

        Schema::create('external_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('canonical_entity', 60);
            $table->string('external_id', 120);
            $table->unsignedBigInteger('internal_id');
            $table->string('source_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'data_source_id', 'canonical_entity', 'external_id'], 'uq_ext_map_source');
            $table->index(['tenant_id', 'canonical_entity', 'internal_id'], 'idx_ext_map_internal');
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->foreignId('connector_instance_id')->nullable()->constrained('connector_instances')->nullOnDelete();
            $table->enum('run_type', ['full', 'incremental', 'reconciliation', 'manual']);
            $table->enum('status', ['queued', 'running', 'succeeded', 'partial_failed', 'failed'])->default('queued')->index();
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_upserted')->default(0);
            $table->unsignedInteger('records_skipped_duplicate')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'data_source_id', 'created_at'], 'idx_sync_runs_lookup');
        });

        Schema::create('sync_cursors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('source_table', 120);
            $table->string('cursor_column', 120);
            $table->string('last_cursor_value', 120)->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['tenant_id', 'data_source_id', 'source_table'], 'uq_sync_cursor_table');
        });

        Schema::create('sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->string('event_id', 150);
            $table->string('entity_type', 60);
            $table->string('external_id', 120);
            $table->json('payload_json');
            $table->enum('status', ['received', 'processed', 'duplicate', 'failed'])->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'data_source_id', 'event_id'], 'uq_sync_event_idemp');
        });

        Schema::create('sync_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->string('entity_type', 60);
            $table->string('external_id', 120)->nullable();
            $table->string('error_code', 80);
            $table->text('error_message');
            $table->json('payload_excerpt_json')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('provider', 80);
            $table->string('delivery_id', 150);
            $table->boolean('signature_valid')->default(false);
            $table->json('payload_json');
            $table->enum('status', ['received', 'processed', 'duplicate', 'rejected', 'failed'])->default('received')->index();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'data_source_id', 'delivery_id'], 'uq_webhook_delivery');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('scope', 80);
            $table->string('key_hash', 64);
            $table->unsignedSmallInteger('response_code');
            $table->json('response_body_json');
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'scope', 'key_hash'], 'uq_idempotency_scope_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('sync_errors');
        Schema::dropIfExists('sync_events');
        Schema::dropIfExists('sync_cursors');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('external_mappings');
        Schema::dropIfExists('field_mappings');
        Schema::dropIfExists('connector_instances');
        Schema::dropIfExists('data_sources');
    }
};
