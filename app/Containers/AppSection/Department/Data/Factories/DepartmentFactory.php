<?php

namespace App\Containers\AppSection\Department\Data\Factories;

use App\Containers\AppSection\Department\Enums\DepartmentUnit;
use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Factories\Factory as ParentFactory;

/**
 * @template TModel of Department
 *
 * @extends ParentFactory<TModel>
 */
final class DepartmentFactory extends ParentFactory
{
    /** @var class-string<TModel> */
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'production_line_id' => null,
            'code'               => fake()->unique()->slug(2),
            'label'              => fake()->words(2, true),
            'label_en'           => fake()->words(2, true),
            'description'        => fake()->sentence(),
            'icon'               => null,
            'unit'               => fake()->randomElement(DepartmentUnit::cases())->value,
            'kpi_per_hour'       => fake()->numberBetween(50, 500),
            'sort_order'         => fake()->numberBetween(1, 100),
            'is_active'          => true,
            'productivity_type'  => fake()->randomElement(ProductivityType::cases())->value,
        ];
    }
}
