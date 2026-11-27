<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->foreignId('connector_instance_id')->nullable()->constrained('connector_instances')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();

            $table->string('command_type', 64);
            $table->string('target_entity_type', 40);
            $table->unsignedBigInteger('target_internal_id');
            $table->string('target_external_id', 120);

            $table->json('payload_json');
            $table->json('precondition_json');
            $table->string('risk_level', 20)->default('low');
            $table->boolean('requires_approval')->default(false);
            $table->string('status', 32)->default('pending');

            $table->string('idempotency_key', 120);
            $table->text('signature_ed25519');
            $table->string('signing_key_id', 64);

            $table->timestamp('expires_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->json('result_json')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'remote_commands_tenant_idempotency_unique');
            $table->index(['tenant_id', 'data_source_id', 'status'], 'remote_commands_tenant_source_status_idx');
            $table->index(['tenant_id', 'expires_at'], 'remote_commands_tenant_expires_idx');
        });

        Schema::create('command_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('remote_command_id')->constrained('remote_commands')->cascadeOnDelete();
            $table->foreignId('connector_instance_id')->nullable()->constrained('connector_instances')->nullOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status', 32)->default('dispatched');
            $table->json('agent_response_json')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['tenant_id', 'remote_command_id'], 'command_attempts_tenant_cmd_idx');
        });

        Schema::create('command_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('remote_command_id')->constrained('remote_commands')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');

            $table->index(['tenant_id', 'remote_command_id'], 'command_approvals_tenant_cmd_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_approvals');
        Schema::dropIfExists('command_attempts');
        Schema::dropIfExists('remote_commands');
    }
};
