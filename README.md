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

The project follows RESTful API architecture and provides authentication, authorization, course management, lesson management, online exams, attendance tracking, notifications, and many additional educational services.

The goal of the project is to provide a scalable backend that can power Web, Mobile, or Desktop applications.

---

# ✨ Current Features

| Module | Description |
|----------|-------------|
| 🔐 Authentication | Register, Login, Logout using Laravel Sanctum |
| 👤 User Roles | Admin, Teacher and Student roles |
| 📚 Courses | Create, Update, Delete and Browse Courses |
| 🎓 Enrollment | Students can Join and Leave Courses |
| 📖 Lessons | Lesson Management |
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

      └───────────┼─────────────┘
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

## 📝 Exams (In Progress)

- Create Exam

- Update Exam

- Delete Exam

- Add Questions

- Submit Answers

- Student Results

---

## 🔔 Notifications (Planned)

- Attendance Notifications

- Lesson Notifications

- System Notifications

---

## 📅 Attendance (Planned)

- Attendance Tracking

- Attendance Reports

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
- Courses
- Lessons
- Exams
- Questions
- Notifications
- Attendances
- Student Answers
- Student Results
- Course Student (Pivot)

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

- Factories

- Seeders

- Postman

- Git

- GitHub

---

# 📂 Project Structure

```
app/

 Controllers/

 Middleware/

 Models/

 Policies/

 Requests/

 Resources/

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

Clone the repository

```bash
git clone https://github.com/tamerayman/Center-Management-API.git
```

Move into the project

```bash
cd Center-Management-API
```

Install dependencies

```bash
composer install
```

Copy environment file

```bash
cp .env.example .env
```

Generate application key

```bash
php artisan key:generate
```

Run migrations

```bash
php artisan migrate
```

(Optional)

```bash
php artisan db:seed
```

Serve the project

```bash
php artisan serve
```

---

# 📈 Current Progress

| Module | Status |
|---------|--------|
| Authentication | ✅ Completed |
| Authorization | ✅ Completed |
| Sanctum | ✅ Completed |
| Courses | ✅ Completed |
| Lessons | 🚧 In Progress |
| Exams | 🚧 In Progress |
| Notifications | ⏳ Planned |
| Attendance | ⏳ Planned |
| Student Results | ⏳ Planned |
| API Documentation | ⏳ Planned |

---

# 🎯 Upcoming Features

- Online Exams

- Student Results

- Attendance Reports

- Notification System

- File Uploads

- Dashboard Statistics

- Search & Filtering

- Pagination

- Swagger Documentation

- Unit & Feature Testing

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