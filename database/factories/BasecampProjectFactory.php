<?php

namespace Database\Factories;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BasecampProject> */
class BasecampProjectFactory extends Factory
{
    protected $model = BasecampProject::class;

    public function definition(): array
    {
        return [
            'basecamp_account_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'basecamp_project_id' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'name' => fake()->company(),
            'workflow_type' => 'kpus_ga_hw',
            'notion_database_id' => fake()->uuid(),
            'active' => true,
        ];
    }
}
