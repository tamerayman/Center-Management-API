<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::inRandomOrder()->first()?->id,
            'question_text' => fake()->sentence(),
            'marks' => fake()->numberBetween(1, 10),
            'option_a' => fake()->word(),
            'option_b' => fake()->word(),
            'option_c' => fake()->word(),
            'option_d' => fake()->word(),
            'correct_answer' => 'A',
        ];
    }
}
