<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Alerts\Models\NotificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRule>
 */
final class NotificationRuleFactory extends Factory
{
    protected $model = NotificationRule::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => 'Low Stock Alert',
            'rule_type' => 'low_stock',
            'threshold_json' => ['min_items' => 1],
            'channel_in_app' => true,
            'channel_email' => false,
            'recipient_roles_json' => ['tenant_owner', 'general_manager'],
            'is_active' => true,
        ];
    }
}
