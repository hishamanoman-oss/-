<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Services\RbacCatalog;
use Illuminate\Database\Seeder;

final class RbacSeeder extends Seeder
{
    public function run(): void
    {
        RbacCatalog::seed();
    }
}
