<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Exam;
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
        $start = $this->faker->dateTimeBetween('now', '+1 month');

        return [
            'course_id' => Course::inRandomOrder()->first()?->id ?? Course::factory()->create()->id,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->optional()->paragraph(),
            'start_time' => $start,
            'end_time' => (clone $start)->modify('+'.$this->faker->numberBetween(2, 4).' hours'),
            'duration' => $this->faker->numberBetween(60, 120), // بالدقائق
            'total_marks' => $this->faker->numberBetween(20, 100),
            'pass_mark' => 50,
        ];
    }

    /**
     * امتحان مفتوح حالياً (بدأ ولسه بينتهي).
     */
    public function open(): static
    {
        return $this->state(fn () => [
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
        ]);
    }

    /**
     * امتحان لسه ما بدأش.
     */
    public function upcoming(): static
    {
        return $this->state(fn () => [
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    /**
     * امتحان خلص.
     */
    public function ended(): static
    {
        return $this->state(fn () => [
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);
    }
}
