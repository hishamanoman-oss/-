<?php

declare(strict_types=1);

namespace App\Domain\Commands\Models;

use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommandAttempt extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'remote_command_id',
        'connector_instance_id',
        'attempt_number',
        'status',
        'agent_response_json',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_response_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function remoteCommand(): BelongsTo
    {
        return $this->belongsTo(RemoteCommand::class, 'remote_command_id');
    }

    public function connectorInstance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }
}
