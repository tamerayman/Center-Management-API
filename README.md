<h1 align="center">
🎓 Center Management API
</h1>

<p align="center">
A scalable, feature-rich, and secure RESTful API built with <b>Laravel 12</b> for managing educational centers. The platform handles academic workflows—courses, lessons, schedules, exams, materials, attendance, and student progress—alongside an advanced financial engine, a dedicated guardian portal, and an administrative control center.
</p>

<p align="center">

<img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white"/>

<img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white"/>

<img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white"/>

<img src="https://img.shields.io/badge/Sanctum-Dual--Guard_Auth-orange?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Architecture-Service_Layer_%26_Observers-blue?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Tests-200_Passing_%2F_973_Assertions-success?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Security-Multi--Key_Throttling-purple?style=for-the-badge"/>

<img src="https://img.shields.io/badge/Postman-Full_Collection_Included-FF6C37?style=for-the-badge&logo=postman&logoColor=white"/>

</p>

---

# 📖 Overview

**Center Management API** is a complete backend solution designed to power web, mobile, and desktop applications for private tutors, educational centers, and academies.

The system combines core academic management with enterprise-level infrastructure:
- **Core Academic Management:** Course catalogs, student self-enrollment, lesson scheduling (onsite/online), file handouts (PDFs, images), and automated attendance tracking.
- **Online Examination Engine:** Multiple-choice questions, server-enforced exam timers, dynamic passing percentages, and instant auto-grading.
- **Financial Subsystem:** Subscription contracts, monthly billing, multi-split and advance payments with FIFO student credits, price snapshot history, and full audit logs.
- **Guardian Portal:** A separate parent environment using token morphing for real-time, read-only tracking of attendance, grades, and payments.
- **Administrative Control:** Full overrides for student assignments, exam retakes, user role management, and system-wide broadcast alerts.
- **Quality & Reliability:** Built with the Service Layer Pattern, Model Observers, and backed by **200 automated tests and 973 assertions**.

---

# ✨ Feature Modules

| Module | Core & Advanced Capabilities |
|---|---|
| 🔐 **Authentication & Security** | Sanctum bearer tokens, multi-keyed rate limiting, HMAC-signed email verification URLs, and tokenized password resets. |
| 📚 **Courses & Enrollment** | Full course catalog, search and filters, teacher assignment, and student self-enrollment (`join` / `leave`). |
| 📖 **Lessons & Scheduling** | Scheduled classes with dates, start/end times, delivery types (`onsite` / `online`), classroom locations, and upcoming lesson filters. |
| 📎 **Lesson Materials** | Secure file attachments (PDFs, documents, images) associated with lessons, with download policies for enrolled students. |
| 📝 **Online Exams & Questions** | Timed examination system with per-student countdown timers, multiple-choice questions, and customizable pass mark percentages. |
| 📊 **Grading & Student Results** | Automated scoring on submission, attempt history, and admin override to reset results and grant retakes. |
| 📅 **Attendance Tracking** | Lesson-by-lesson attendance recording (`present`, `late`, `absent`) with weighted presence points. |
| 📈 **Student Progress Service** | Centralized progress calculation combining weighted attendance and exam averages, with automated flags for at-risk students. |
| 💰 **Financial Engine** | Course fees with historical snapshots, subscription management, automated monthly charges, credit overflow (advance payments), and full audit trails. |
| 👨‍👩‍👦 **Guardian Portal** | Decoupled parent portal with unique verification codes, multi-child linking, and read-only monitoring of academic and financial status. |
| 👑 **Admin Control Center** | Centralized oversight: user CRUD, role transitions, course reassignment, forced enrollments, and broadcast notifications. |
| 🔔 **Notifications** | Event-driven in-app notifications for enrollment, payments, exam dates, and attendance updates. |

---

# 🏗️ System Architecture

```
                             Client Applications
                  (Web Dashboard / Mobile App / Postman)
                                     │
                 ┌───────────────────┴───────────────────┐
                 │                                       │
           Bearer Token (User)                     Bearer Token (Guardian)
        auth:sanctum + EnsureUser               auth:guardian + EnsureGuardian
                 │                                       │
                 ▼                                       ▼
       ┌──────────────────┐                    ┌──────────────────┐
       │   Public & Core  │                    │ Guardian Portal  │
       │   API Gateway    │                    │ (Strict Read-Only)│
       └─────────┬────────┘                    └─────────┬────────┘
                 │                                       │
 ┌───────────────┼───────────────────────┐               │
 │               │                       │               │
 ▼               ▼                       ▼               │
Academic      Administrative          Financial          │
Subsystems    Control Center          Subsystem          │
 ├─ Courses    ├─ User CRUD            ├─ Historical Fees│
 ├─ Lessons    ├─ Force Enrollment     ├─ Subscriptions  │
 ├─ Materials  ├─ Result Resets        ├─ Monthly Charges│
 ├─ Exams      └─ Broadcast Alerts     ├─ Payments/Credit│
 ├─ Questions                          ├─ Ledger Invariant
 └─ Attendance                         └─ Audit Trail    │
 │                                       │               │
 └───────────────────────┬───────────────┴───────────────┘
                         │
                 Service Layer
      (SubscriptionService, PaymentService,
       ChargeService, ProgressService, GuardianNotifier)
                         │
                 Database Observers
      (CourseSubscriptionObserver, PaymentObserver,
       CourseFeeObserver, UserObserver)
                         │
                         ▼
             MySQL 8.0 / SQLite (Tests)
```

---

# 🚀 Deep-Dive into Core & Advanced Modules

### 1. 📚 Courses, Lessons & Learning Materials
* **Course Lifecycle:** Teachers can create, update, and manage their own courses, while students can browse catalogs, filter by instructor, and join or leave freely.
* **Smart Lesson Scheduling:** Lessons support structured scheduling with `lesson_date`, `start_time`, `end_time`, `delivery_type` (`onsite` or `online`), and `location`. Endpoints such as `GET /api/lessons/upcoming` filter future sessions tailored to each user role.
* **Handouts & Materials:** Teachers can attach lecture notes, PDF summaries, and reference images. Files are stored securely on the server disk with access controls verifying active course enrollment before download.

### 2. 📝 Timed Examination & Auto-Grading Engine
* **Server-Side Timers:** When a student begins an exam via `POST /api/exams/{id}/start`, the server records an immutable `ExamAttempt`. The submission deadline is enforced strictly on the backend ($\text{deadline} = \text{start\_time} + \text{duration}$), protecting against client-side clock manipulation.
* **Dynamic Pass Marks:** Teachers configure passing thresholds as a percentage (e.g., 60% or 75%) rather than a static default.
* **Single Attempt with Retake Control:** Students cannot submit multiple times for the same exam. If a student experiences an issue, administrators have the exclusive ability to delete the result to grant an immediate retake.

### 3. 📈 Attendance & Centralized Progress Tracking
* **Weighted Attendance:** Attendance is recorded per lesson (`present` = 1.0 point, `late` = 0.5 points, `absent` = 0 points).
* **Single Source of Truth (`ProgressService`):** Both overall and course-specific progress are computed dynamically:
$$\text{Attendance \%} = \frac{(\text{Present} \times 1.0) + (\text{Late} \times 0.5)}{\text{Total Recorded Sessions}} \times 100$$
$$\text{Exams Average} = \frac{\sum (\text{Student Score})}{\sum (\text{Total Available Marks})} \times 100$$
* **At-Risk Detection:** Teacher dashboards highlight `students_needing_attention` whenever a student's attendance or exam score drops below 50%.

### 4. 💰 Financial & Accounting Engine
* **Historical Price Immutability:** When a course fee updates, a new record is added to `course_fees`. Existing subscriptions and historical invoices retain their original price snapshot.
* **Subscription & Monthly Charges:** Joining a course opens an active financial subscription. Monthly invoices (`monthly_charges`) are generated automatically upon billing or payment, with unique constraints preventing duplicate charges for the same billing cycle.
* **Partial Payments & Credit Ledger:** Students can pay in installments (`unpaid → partially_paid → paid`). Any overpayment excess is safely allocated to `student_credits` (FIFO queue) and can be redeemed automatically against future invoices using `use_credit: true`.
* **Audit Trail:** Financial modifications and cancellations require documented reasons, logged to `financial_audit_logs` via model observers with before/after state captures.

### 5. 👨‍👩‍👦 Guardian Portal (Dual-Guard Architecture)
* **Isolated Authentication:** Guardians are stored in a dedicated `guardians` table and authenticate via a distinct Sanctum guard (`auth:guardian`). User tokens cannot access guardian endpoints, and guardian tokens cannot access user routes.
* **Child Linking via Verification Code:** Each student has a unique `guardian_code` in their profile. Guardians register using this code to bind to their children, supporting multiple siblings under a single parent account.
* **Read-Only Data Access:** Guardians have a read-only window into their children's upcoming classes, attendance records, exam results, and financial balances, with backend policies (`GuardianPolicy@viewChild`) preventing unauthorized access.

### 6. 👑 Administrator Control Center
* **Complete User Administration:** Manage staff and student accounts, reset passwords, update profiles, and adjust role permissions.
* **Academic Overrides:** Assign courses to any teacher, force-enroll or remove students, delete exam results, and broadcast announcements to the entire center or specific user roles.

---

# 👥 Role-Based Access Control (RBAC) Matrix

| Capability / Module | Admin 🔴 | Secretary 🔵 | Teacher 🟡 | Student 🟢 | Guardian 👨‍👩‍👦 |
|---|:---:|:---:|:---:|:---:|:---:|
| **Manage Users & Roles** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Create Courses & Force Enroll** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Manage Own Courses & Lessons** | ✅ Full | ❌ | ✅ Own only | ❌ | ❌ |
| **Upload Lesson Materials** | ✅ Full | ❌ | ✅ Own only | ❌ | ❌ |
| **Take Exams & View Results** | ❌ | ❌ | ❌ | ✅ Own only | ❌ |
| **Reset Exam Results (Allow Retake)**| ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Record Attendance** | ✅ Full | ❌ | ✅ Own only | ❌ | ❌ |
| **Record Payments** | ✅ Full | ✅ Full | ✅ Own courses | ❌ | ❌ |
| **Modify Fees & Discounts** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Cancel / Update Payments** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **View Financial Dashboard & Overdue**| ✅ Full | ✅ Full | ❌ | ❌ | ❌ |
| **Access Financial Audit Logs** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **View Child Academic & Financial Data**| ❌ | ❌ | ❌ | ❌ | ✅ Read-only |

---

# 🔎 Search, Filtering & Pagination

All index endpoints implement standardized pagination parameters (`?page=1&per_page=15`, capped at 100) and return unified pagination metadata (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`).

| Endpoint | Available Query Filters | Authorization Scope |
|---|---|---|
| `GET /api/courses` | `search` (title, description), `teacher_id` | Authenticated users |
| `GET /api/lessons/upcoming` | `course_id`, `date_from`, `date_to` | Role-scoped |
| `GET /api/attendance` | `student_id`, `course_id`, `lesson_id`, `status`, `date_from`, `date_to` | Role-scoped |
| `GET /api/results` | `student_id`, `exam_id`, `course_id`, `status`, `min_score`, `max_score`, `date_from`, `date_to` | Role-scoped |
| `GET /api/finance/payments` | `student_id`, `course_id`, `method`, `status`, `year`, `month`, `amount_min`, `amount_max`, `date_from`, `date_to` | Staff / Teacher-scoped |
| `GET /api/finance/subscriptions`| `student_id`, `course_id`, `status` | Staff / Teacher-scoped |
| `GET /api/admin/users` | `role`, `search` (name, email, phone) | Admin only |
| `GET /api/guardian/children/{id}/attendance` | `status`, `course_id`, `date_from`, `date_to` | Verified Guardian only |

---

# 📦 API Endpoints Directory

<details>
<summary><b>📚 1. Courses, Lessons, Materials & Exams (Click to expand)</b></summary>

| Method | Endpoint | Description | Access |
|---|---|---|---|
| `GET` | `/api/courses` | Browse public course catalog with filters | Authenticated |
| `GET` | `/api/my-courses` | Courses associated with the current user | Role-scoped |
| `GET` | `/api/my-courses/{id}` | Detailed course view with lessons and exams | Role-scoped |
| `POST` | `/api/courses` | Create a new course | Teacher / Admin |
| `PUT` | `/api/courses/{id}` | Update course title, description, or teacher | Teacher (Owner) / Admin |
| `DELETE`| `/api/courses/{id}` | Delete course and cascade dependent records | Teacher (Owner) / Admin |
| `POST` | `/api/courses/{id}/join` | Student joins course (creates active subscription) | Student |
| `DELETE`| `/api/courses/{id}/leave`| Student leaves course (cancels subscription) | Student |
| `GET` | `/api/my-lessons` | Scheduled lessons for enrolled courses | Role-scoped |
| `GET` | `/api/lessons/upcoming` | Upcoming lessons filtered by date and course | Role-scoped |
| `POST` | `/api/courses/{id}/lessons` | Schedule a lesson (date, time, onsite/online) | Teacher (Owner) / Admin |
| `PUT` | `/api/lessons/{id}` | Update lesson details and schedule | Teacher (Owner) / Admin |
| `DELETE`| `/api/lessons/{id}` | Remove lesson | Teacher (Owner) / Admin |
| `GET` | `/api/lessons/{id}/materials`| List attachments for a lesson | Enrolled Student / Staff |
| `POST` | `/api/lessons/{id}/materials`| Upload handout or material (PDF, image) | Teacher (Owner) / Admin |
| `GET` | `/api/materials/{id}/download`| Download material attachment | Enrolled Student / Staff |
| `POST` | `/api/courses/{id}/exams` | Create an exam with duration and pass mark | Teacher (Owner) / Admin |
| `POST` | `/api/exams/{id}/questions` | Add multiple-choice questions to exam | Teacher (Owner) / Admin |
| `POST` | `/api/exams/{id}/start` | Initialize student exam timer attempt | Student |
| `POST` | `/api/questions/{id}/answer` | Submit answer during an active attempt | Student |
| `POST` | `/api/exams/{id}/submit` | Finalize exam, evaluate score, and grade | Student |
| `GET` | `/api/my-results` | View past exam results and grades | Student |
| `POST` | `/api/lessons/{id}/attendance`| Record student attendance for a lesson | Teacher (Owner) / Admin |
| `GET` | `/api/progress` | View computed attendance and exam progress | Student |

</details>

<details>
<summary><b>💰 2. Financial Management Subsystem (Click to expand)</b></summary>

| Method | Endpoint | Description | Gate Protection |
|---|---|---|---|
| `GET` | `/api/finance/dashboard` | Key financial metrics (expected, collected, overdue) | `finance.viewAny` |
| `GET` | `/api/finance/subscriptions` | Paginated listing of course subscriptions | `finance.access` |
| `POST` | `/api/finance/subscriptions` | Create custom subscription (discounts, custom fees) | `finance.sensitive` |
| `PUT` | `/api/finance/subscriptions/{id}` | Pause, resume, update fee/discount, or cancel | `finance.sensitive` |
| `GET` | `/api/finance/charges` | List generated monthly charges and payment status | `finance.access` |
| `POST` | `/api/finance/charges/generate` | Batch-generate charges for an active billing cycle | `finance.sensitive` |
| `GET` | `/api/finance/payments` | Search, filter, and review payment history | `finance.access` |
| `POST` | `/api/finance/payments` | Record payment (supports cash, credit, advance excess) | `finance.recordPayment` |
| `PUT` | `/api/finance/payments/{id}` | Adjust payment amount (recalculates invoice balance) | `finance.sensitive` |
| `POST` | `/api/finance/payments/{id}/cancel` | Cancel payment with mandatory audit reason | `finance.sensitive` |
| `GET` | `/api/finance/overdue` | List students with outstanding unpaid balances | `finance.viewAny` |
| `GET` | `/api/finance/students/{id}` | Student financial profile (invoices, payments, credits) | `finance.viewStudent` |
| `GET` | `/api/finance/courses/{id}` | Course financial summary and collection rate | `finance.viewCourse` |
| `GET` | `/api/finance/courses/{id}/fees` | Historical fee pricing log for a course | `finance.sensitive` |
| `POST` | `/api/finance/courses/{id}/fees` | Update course tuition fee effective from date | `finance.sensitive` |
| `GET` | `/api/finance/reports/monthly` | Annual month-by-month revenue report | `finance.viewAny` |
| `GET` | `/api/finance/reports/collection` | Collection rate efficiency analysis | `finance.viewAny` |
| `GET` | `/api/finance/audit-logs` | Immutable audit trail of financial mutations | `finance.sensitive` |

</details>

<details>
<summary><b>👨‍👩‍👦 3. Guardian Portal (Click to expand)</b></summary>

| Method | Endpoint | Description | Auth Guard |
|---|---|---|---|
| `POST` | `/api/guardian/register` | Register guardian account linked via student code | `throttle:guardian-auth` |
| `POST` | `/api/guardian/login` | Authenticate guardian via phone + password | `throttle:guardian-auth` |
| `POST` | `/api/guardian/logout` | Revoke guardian authentication token | `auth:guardian` |
| `GET` | `/api/guardian/children` | List linked children with real-time academic status | `auth:guardian` |
| `POST` | `/api/guardian/children` | Link additional child using their guardian code | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}` | Complete child profile (courses, attendance, fees) | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/payments` | Payment receipts and history for child | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/progress` | Real-time weighted attendance and exam metrics | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/lessons/upcoming` | Child's upcoming scheduled sessions | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/attendance` | Detailed attendance log for child | `auth:guardian` |
| `GET` | `/api/guardian/dashboard` | Portal dashboard with unread notification badges | `auth:guardian` |
| `GET` | `/api/guardian/notifications` | In-app alerts (exam dates, attendance, receipts) | `auth:guardian` |
| `PATCH` | `/api/guardian/notifications/{id}/read` | Mark individual notification as read | `auth:guardian` |
| `PATCH` | `/api/guardian/notifications/read-all` | Mark all notifications as read | `auth:guardian` |

</details>

<details>
<summary><b>👑 4. Administrator Control Center (Click to expand)</b></summary>

| Method | Endpoint | Description | Access |
|---|---|---|---|
| `GET` | `/api/admin/users` | List and search all users with role filters | Admin |
| `POST` | `/api/admin/users` | Create user with explicit role assignment | Admin |
| `GET` | `/api/admin/users/{id}` | Inspect user profile, enrollment, and statistics | Admin |
| `PUT` | `/api/admin/users/{id}` | Update user details, credentials, or role | Admin |
| `DELETE` | `/api/admin/users/{id}` | Cascade delete user (safeguard against self-deletion) | Admin |
| `POST` | `/api/admin/courses` | Create a course assigned to any designated teacher | Admin |
| `GET` | `/api/admin/courses/{id}/students` | Inspect all enrolled students in a course | Admin |
| `POST` | `/api/admin/courses/{id}/students` | Force-enroll students in batch | Admin |
| `DELETE` | `/api/admin/courses/{id}/students/{student}` | Force-unenroll a student from a course | Admin |
| `GET` | `/api/admin/results` | View all exam results with performance filters | Admin |
| `DELETE` | `/api/admin/results/{id}` | Delete exam submission (enables student to retake) | Admin |
| `POST` | `/api/admin/notifications` | Send direct notification to a specific user | Admin |
| `POST` | `/api/admin/notifications/broadcast` | Broadcast announcement to all users or by role | Admin |

</details>

<details>
<summary><b>🔐 5. Authentication & Account Management (Click to expand)</b></summary>

| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `POST` | `/api/register` | Register new user (Student or Teacher) | 5 / min |
| `POST` | `/api/login` | Authenticate user & issue Sanctum Bearer token | 5 / min |
| `POST` | `/api/logout` | Revoke current access token | Standard |
| `POST` | `/api/forgot-password` | Request password reset email | 5 / min |
| `POST` | `/api/reset-password` | Reset password using email verification token | 5 / min |
| `POST` | `/api/email/verification-notification` | Resend email verification notification | 5 / min |
| `GET` | `/api/email/verify/{id}/{hash}` | Verify email address via signed URL | Signed URL |
| `GET` | `/api/profile` | View authenticated user profile | Standard |
| `PUT` | `/api/profile` | Update profile information | Standard |
| `POST` | `/api/profile/avatar` | Upload and update profile photo | Standard |
| `DELETE`| `/api/profile/avatar` | Remove profile photo | Standard |

</details>

---

# 🧪 Testing & Quality Assurance

The codebase is thoroughly tested using **PHPUnit** with SQLite in-memory test databases, validating business logic, permission gates, and data integrity.

```bash
php artisan test
```

### Test Suite Execution Output:
```text
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Feature\AdminTest
   PASS  Tests\Feature\AuthTest
   PASS  Tests\Feature\AuthorizationMatrixTest
   PASS  Tests\Feature\CourseTest
   PASS  Tests\Feature\EmailVerificationTest
   PASS  Tests\Feature\ExamFlowTest
   PASS  Tests\Feature\FinancialInvariantTest
   PASS  Tests\Feature\FinancialTest
   PASS  Tests\Feature\GuardianTest
   PASS  Tests\Feature\LessonAndAttendanceTest
   PASS  Tests\Feature\PasswordResetTest
   PASS  Tests\Feature\ProfileAndNotificationTest
   PASS  Tests\Feature\ProgressTest
   PASS  Tests\Feature\RateLimitingTest
   PASS  Tests\Feature\ScheduleTest
   PASS  Tests\Feature\SearchAndFilterTest

  Tests:    200 passed (973 assertions)
  Duration: 80.10s
```

---

# 🚀 Getting Started

### Prerequisites
- PHP `>= 8.2` (PDO, OpenSSL, Mbstring, Tokenizer extensions)
- Composer `>= 2.0`
- MySQL `>= 8.0`

### 1. Clone & Install Dependencies
```bash
git clone https://github.com/tamerayman/Center-Management-API.git
cd Center-Management-API
composer install
```

### 2. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

Set your database credentials in `.env`:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=center
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Run Migrations & Seed Database
```bash
php artisan migrate --seed
php artisan storage:link
```

The database seeder initializes ready-to-test accounts:
| Role | Email | Password |
|---|---|---|
| **Admin** | `admin@test.com` | `12345678` |
| **Secretary** | `secretary@test.com` | `12345678` |
| **Teacher** | `teacher@test.com` | `12345678` |
| **Student** | `student@test.com` | `12345678` |

### 4. Serve the API
```bash
php artisan serve
```
The API is available at `http://127.0.0.1:8000`.

### 5. Postman Collection
Import the complete Postman collection located at:
📁 [`docs/center-api-postman-collection.json`](docs/center-api-postman-collection.json)

---

# 📄 License

This project is open-source software licensed under the **[MIT License](LICENSE)**.

<p align="center">
Developed with ❤️ by <b>Tamer Ayman</b>
</p>
