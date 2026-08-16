<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::inRandomOrder()->first()?->id,
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
             'lesson_date' => fake()->dateTimeBetween('now', '+30 days'),

        ];
    }
}
