<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Exam;

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
            'role' => 'admin'
        ]);
        User::factory()
            ->count(5)
            ->create([
                'role' => 'teacher'
            ]);
        User::factory()
            ->count(20)
            ->create([
                'role' => 'student'
            ]);
        Course::factory()
            ->count(10)
            ->create();
        Lesson::factory()
            ->count(30)
            ->create();
        Exam::factory()
            ->count(20)
            ->create();
        Question::factory()
            ->count(100)
            ->create();

            
    }
}
