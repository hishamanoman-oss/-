<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'status' => 'active',
            'default_currency' => 'SAR',
            'default_timezone' => 'Asia/Riyadh',
            'default_locale' => 'ar',
        ];
    }
}
