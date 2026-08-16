<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
      $start = fake()->dateTimeBetween('now', '+1 month');
    $end = (clone $start)->modify('+' . fake()->numberBetween(30, 180) . ' minutes');

   return [
    'course_id'   => Course::inRandomOrder()->first()?->id ?? Course::factory()->create()->id,
    'title'       => fake()->sentence(),
    'description' => fake()->optional()->paragraph(),
    'start_time'  => $start,
    'end_time'    => $end,
    'duration'    => $end->getTimestamp() - $start->getTimestamp(),
    'total_marks' => fake()->numberBetween(20, 100),
      ];

    }
}
