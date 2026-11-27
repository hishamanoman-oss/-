<?php

declare(strict_types=1);

namespace App\Domain\Commands\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommandApproval extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'remote_command_id',
        'approver_user_id',
        'decision',
        'comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function remoteCommand(): BelongsTo
    {
        return $this->belongsTo(RemoteCommand::class, 'remote_command_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
