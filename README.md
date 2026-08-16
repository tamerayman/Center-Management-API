<h1 align="center">
🎓 Center Management API
</h1>

<p align="center">
A scalable and secure RESTful API built with Laravel for managing educational centers, courses, lessons, exams, attendance, and student progress.
</p>

<p align="center">

<img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white"/>

<img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white"/>

<img src="https://img.shields.io/badge/MySQL-8-4479A1?style=for-the-badge&logo=mysql&logoColor=white"/>

<img src="https://img.shields.io/badge/REST_API-005571?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Sanctum-Authentication-orange?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Eloquent_ORM-Laravel-red?style=for-the-badge"/>

<img src="https://img.shields.io/badge/API_Resources-JSON-success?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Form_Requests-Validation-blue?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Middleware-Authorization-green?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Policies-Access_Control-purple?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Factories-Seeding-yellow?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Postman-Test_API-orange?style=for-the-badge"/>

</p>

---

# 📖 Overview

Center Management API is a backend system built with **Laravel** to simplify the management of educational centers.

The project follows RESTful API architecture and provides authentication, authorization, course management, lesson management, online exams, attendance tracking, student profiles, lesson materials, dashboard statistics, notifications, and many additional educational services.

The goal of the project is to provide a scalable backend that can power Web, Mobile, or Desktop applications.

---

# ✨ Features

| Module | Description |
|----------|-------------|
| 🔐 Authentication | Register, Login, Logout using Laravel Sanctum |
| 👤 User Roles | Admin, Teacher and Student roles |
| 👤 User Profiles | Profile management with avatar upload |
| 📚 Courses | Create, Update, Delete and Browse Courses |
| 🎓 Enrollment | Students can Join and Leave Courses |
| 📖 Lessons | Lesson management with course association |
| 📎 Lesson Materials | File uploads (PDF, images) for lessons |
| 📝 Online Exams | Create exams with questions, time limits, and auto-grading |
| ❓ Questions | Multiple choice questions with correct answer tracking |
| 📊 Student Results | Automatic scoring and result tracking |
| ✍️ Student Answers | Answer submission and validation |
| 📅 Attendance | Attendance tracking per lesson |
| 🔔 Notifications | System notifications for users |
| 📈 Dashboard | Statistics and overview for teachers/admins |
| 🛡 Authorization | Laravel Policies & Middleware |
| 📦 API Resources | Clean JSON Responses |
| ✅ Validation | Laravel Form Requests |
| 🌱 Database Seeding | Factories & Seeders |

---

# 🏗 System Architecture

```
                    Client
      (Web / Mobile / Postman)

                  │
                  │
          Bearer Token
          Laravel Sanctum
                  │
                  ▼

             Laravel API

     Authentication
     Authorization
     Validation
     API Resources

                  │
      ┌───────────┼─────────────┐
      │           │             │
   Courses     Lessons      Exams
      │           │             │
   Enrollment  Materials    Questions
                Attendance  Answers/Results
      │           │             │
      └───────────┼─────────────┘
                  │
          ┌───────┴───────┐
          │               │
       Profiles     Notifications
       Dashboard
          │
     MySQL Database
```

---

# 📦 API Modules

## 🔐 Authentication

- Register
- Login
- Logout
- User Profile

---

## 👤 Profiles

- View Profile
- Update Profile
- Upload/Update Avatar

---

## 👨‍🏫 Courses

- List all Courses
- My Courses
- Course Details
- My Course Details
- Create Course
- Update Course
- Delete Course
- Join Course
- Leave Course

---

## 📖 Lessons

- Create Lesson
- Update Lesson
- Delete Lesson
- List Lessons
- My Lessons

---

## 📎 Lesson Materials

- Upload Material (PDF, images)
- Update Material
- Delete Material
- List Materials per Lesson

---

## 📝 Exams

- Create Exam
- Update Exam
- Delete Exam
- List Exams

---

## ❓ Questions

- Add Questions to Exam
- Update Question
- Delete Question

---

## ✍️ Student Answers

- Submit Answers
- View Answers

---

## 📊 Student Results

- View Results
- Auto-graded Scoring

---

## 📅 Attendance

- Record Attendance
- View Attendance per Lesson

---

## 🔔 Notifications

- View Notifications
- Mark as Read

---

## 📈 Dashboard

- Teacher/Admin Statistics
- Course and Student Overview

---

# 🛡 Authentication Flow

```
Register/Login

      │

      ▼

Laravel Sanctum

      │

Generate Token

      │

      ▼

Authorization Header

Bearer TOKEN

      │

      ▼

Protected API Endpoints
```

---

# 🗃 Database Design

Current database includes:

- Users
- Profiles
- Courses
- Lessons
- Lesson Materials
- Exams
- Questions
- Notifications
- Attendances
- Student Answers
- Student Results
- Course Student (Pivot)
- Personal Access Tokens

Relationships are implemented using Laravel Eloquent ORM.

---

# ⚙ Technologies Used

- Laravel 12
- PHP 8.2+
- MySQL
- Laravel Sanctum
- RESTful API
- Eloquent ORM
- API Resources
- Form Requests
- Middleware
- Policies
- Observers
- Factories
- Seeders
- Postman
- Git & GitHub

---

# 📂 Project Structure

```
app/
  Http/
    Controllers/
      Api/
        AuthController.php
        CourseController.php
      AttendanceController.php
      DashboardController.php
      ExamController.php
      LessonController.php
      LessonMaterialController.php
      NotificationController.php
      ProfileController.php
      QuestionController.php
      StudentAnswerController.php
      StudentResultController.php
    Middleware/
      SanitizeMethodOverride.php
    Requests/
    Resources/
  Models/
  Observers/
  Policies/
  Providers/

database/
  migrations/
  factories/
  seeders/

routes/
  api.php

config/
tests/
```

---

# 🚀 Installation

### 1. Clone the repository

```bash
git clone https://github.com/tamerayman/Center-Management-API.git
cd Center-Management-API
```

### 2. Install dependencies

```bash
composer install
```

### 3. Setup environment

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure database

Open `.env` and set your database credentials:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=center
DB_USERNAME=root
DB_PASSWORD=
```

Then create the database:

```sql
CREATE DATABASE center;
```

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Seed the database (optional)

```bash
php artisan db:seed
```

This creates sample users (admin, teachers, students), courses, lessons, exams, and questions.

### 7. Create storage link

```bash
php artisan storage:link
```

### 8. Start the server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`.

---

# 📈 Current Progress

| Module | Status |
|---------|--------|
| Authentication | ✅ Completed |
| Authorization | ✅ Completed |
| Sanctum | ✅ Completed |
| Courses | ✅ Completed |
| Lessons | ✅ Completed |
| Lesson Materials | ✅ Completed |
| Exams | ✅ Completed |
| Questions | ✅ Completed |
| Student Answers | ✅ Completed |
| Student Results | ✅ Completed |
| Attendance | ✅ Completed |
| Notifications | ✅ Completed |
| Profiles | ✅ Completed |
| Dashboard | ✅ Completed |
| API Documentation | ⏳ Planned |

---

# 🎯 Upcoming Features

- Swagger / OpenAPI Documentation
- Search & Filtering
- Pagination Improvements
- Unit & Feature Testing
- Performance Optimizations

---

# 🤝 Contributing

Contributions, suggestions and improvements are welcome.

Feel free to fork the repository and submit a Pull Request.

---

# 📄 License

This project is licensed under the MIT License.

---

<p align="center">

Developed with ❤️ by <b>Tamer Ayman</b>

</p>