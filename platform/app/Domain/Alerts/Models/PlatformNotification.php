<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Database\Factories\PlatformNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformNotification extends Model
{
    /** @use HasFactory<PlatformNotificationFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'notifications';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'notification_rule_id',
        'severity',
        'title',
        'body',
        'action_url',
        'metadata_json',
        'dedup_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PlatformNotificationFactory
    {
        return PlatformNotificationFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }
}
