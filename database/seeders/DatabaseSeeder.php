<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\RunsOnlyInLocalEnvironment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder {
    use RunsOnlyInLocalEnvironment;
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void {
        $this->assertLocalEnvironment();
    }
}
