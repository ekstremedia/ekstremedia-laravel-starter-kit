<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FileItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileItem>
 */
class FileItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'type' => FileItem::TYPE_FILE,
            'name' => fake()->unique()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1_000, 5_000_000),
        ];
    }

    public function folder(): static
    {
        return $this->state(fn () => [
            'type' => FileItem::TYPE_FOLDER,
            'mime_type' => null,
            'size' => 0,
            'name' => fake()->unique()->word(),
        ]);
    }
}
