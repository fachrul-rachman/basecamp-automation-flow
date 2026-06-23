<?php

namespace Database\Factories;

use App\Core\Shared\Scheduling\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Holiday> */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'holiday_date' => fake()->unique()->date(),
            'name' => fake()->words(3, true),
            'source' => 'database',
        ];
    }
}
