<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Database\Factories\NotificationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NotificationRule extends Model
{
    /** @use HasFactory<NotificationRuleFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'rule_type',
        'threshold_json',
        'channel_in_app',
        'channel_email',
        'recipient_roles_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_json' => 'array',
            'recipient_roles_json' => 'array',
            'channel_in_app' => 'boolean',
            'channel_email' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): NotificationRuleFactory
    {
        return NotificationRuleFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PlatformNotification::class, 'notification_rule_id');
    }
}
