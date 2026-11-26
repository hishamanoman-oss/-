<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
final class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attrs) => Branch::query()->findOrFail($attrs['branch_id'])->tenant_id,
            'branch_id' => Branch::factory(),
            'code' => 'WH-'.fake()->unique()->numberBetween(100, 9999),
            'name' => 'Warehouse '.fake()->word(),
            'is_default' => false,
            'status' => 'active',
        ];
    }
}
