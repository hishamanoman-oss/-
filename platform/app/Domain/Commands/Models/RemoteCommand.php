<?php

declare(strict_types=1);

namespace App\Domain\Commands\Models;

use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RemoteCommand extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'connector_instance_id',
        'branch_id',
        'requested_by_user_id',
        'command_type',
        'target_entity_type',
        'target_internal_id',
        'target_external_id',
        'payload_json',
        'precondition_json',
        'risk_level',
        'requires_approval',
        'status',
        'idempotency_key',
        'signature_ed25519',
        'signing_key_id',
        'expires_at',
        'dispatched_at',
        'acknowledged_at',
        'reconciled_at',
        'result_json',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'precondition_json' => 'array',
            'result_json' => 'array',
            'requires_approval' => 'boolean',
            'expires_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }

    public function connectorInstance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CommandAttempt::class, 'remote_command_id')->orderBy('attempt_number');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CommandApproval::class, 'remote_command_id')->orderBy('decided_at');
    }

    /**
     * Canonical envelope array matching the Ed25519 signed structure.
     *
     * @return array<string, mixed>
     */
    public function toSignedEnvelope(): array
    {
        return [
            'command_id' => (int) $this->id,
            'tenant_id' => (int) $this->tenant_id,
            'data_source_id' => (int) $this->data_source_id,
            'command_type' => (string) $this->command_type,
            'target_external_id' => (string) $this->target_external_id,
            'payload' => $this->payload_json ?? [],
            'precondition' => $this->precondition_json ?? [],
            'idempotency_key' => (string) $this->idempotency_key,
            'expires_at' => $this->expires_at?->utc()->format('Y-m-d\TH:i:s\Z') ?? '',
        ];
    }
}
