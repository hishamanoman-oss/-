<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Alerts\Models\PlatformNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformNotification>
 */
final class PlatformNotificationFactory extends Factory
{
    protected $model = PlatformNotification::class;

    public function definition(): array
    {
        return [
            'severity' => 'info',
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->sentence(10),
            'action_url' => '/alerts',
            'metadata_json' => [],
            'read_at' => null,
        ];
    }
}
