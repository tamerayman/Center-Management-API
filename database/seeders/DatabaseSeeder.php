<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);
        User::create([
            'name' => 'Secretary',
            'email' => 'secretary@test.com',
            'password' => bcrypt('12345678'),
            'role' => 'secretary',
        ]);
        User::factory()
            ->count(5)
            ->create([
                'role' => 'teacher',
            ]);
        User::factory()
            ->count(10)
            ->create([
                'role' => 'student',
            ]);
        Course::factory()
            ->count(5)
            ->create();
        Lesson::factory()
            ->count(20)
            ->create();
        Exam::factory()
            ->count(10)
            ->create();
        Question::factory()
            ->count(9)
            ->create();

    }
}
