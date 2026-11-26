<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'BR-'.fake()->unique()->numberBetween(100, 9999),
            'name' => 'Branch '.fake()->city(),
            'city' => fake()->city(),
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
        ];
    }
}
