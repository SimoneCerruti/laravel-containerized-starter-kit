<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
final class PermissionFactory extends Factory {
    protected $model = Permission::class;

    /**
     * @return array<model-property<Permission>, mixed>
     */
    public function definition(): array {
        return [
            'name' => fake()->unique()->slug(2),
            'guard_name' => 'web',
        ];
    }
}
