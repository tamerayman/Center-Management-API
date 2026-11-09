<h1 align="center">
🎓 Center Management API
</h1>

<p align="center">
An Enterprise-Grade, High-Concurrency RESTful Backend Engine built with Laravel 12 for Educational Centers, featuring an Invariant-Tested Financial Subsystem, Dual-Guard Guardian Portal, Timed Examination Engine, Role-Based Access Control, and Intelligent Caching & Rate Limiting.
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

**Center Management API** is not a simple CRUD application. It is a robust, production-ready backend platform architected to manage the full operational, academic, and financial lifecycle of educational institutions and private centers.

Designed with **Domain-Driven Design (DDD)** principles and a **Service Layer Pattern**, the system handles complex business logic:
- **Financial Invariance:** A concurrency-safe accounting engine that prevents race conditions, manages historical price snapshots, tracks partial/advance payments via a FIFO credit ledger, and maintains a strict audit trail.
- **Dual-Token Morph Authorization:** An isolated Guardian portal with zero privilege escalation risks between staff/students and parents.
- **Server-Enforced Exam Timer:** State-managed exam sessions impervious to client-side clock tampering.
- **Centralized Metrics Calculation:** Single-source-of-truth progress calculations with automated student intervention heuristics.
- **Exhaustive Testing:** Backed by **200 automated tests and 973 assertions** covering concurrency locks, database transaction rollbacks, invariant constraints, and complete RBAC matrices.

---

# 🏛️ Architecture & Advanced Engineering Highlights

### 1. 💰 Financial & Accounting Engine (Zero-Drift Invariants)
Educational centers handle complex cash flows, varying student discounts, and partial payments. The financial engine is modeled around strict ledger principles:
* **Historical Price Immutability:** When a course changes its tuition fee, old subscriptions and historical billing records remain untouched through a temporal snapshot pattern (`course_fees`).
* **Concurrency & Race Condition Protection:** High-frequency payment attempts and simultaneous subscriptions are serialized using database row locks (`lockForUpdate()`) and atomic DB transactions, preventing duplicate charges or corrupted balances.
* **Partial Payments & FIFO Credit Allocation:** A monthly charge can transition across `unpaid → partially_paid → paid`. Any overpayment is automatically segregated into `student_credits`, which can be redeemed seamlessly in future billing cycles.
* **Audit Trail via Model Observers:** Financial records are never physically destroyed. Cancellations and modifications require auditable reasons, automatically logged into `financial_audit_logs` (actor, before/after states, timestamp).
* **Automated Invariant Testing:** Verified through `FinancialInvariantTest` ensuring that $\text{Total Invoiced} = \text{Total Collected} + \text{Remaining Balance}$ holds true under any concurrent multi-student lifecycle.

### 2. 👨‍👩‍👦 Dual-Guard Guardian Portal (Zero Privilege Escalation)
Parents need visibility into their children's progress without compromising the system's security perimeter:
* **Polymorphic Token Separation:** Guardians exist in an independent `guardians` table with their own authentication guard (`auth:guardian`). A guardian token cannot invoke internal staff endpoints, and user tokens are strictly rejected on guardian routes via custom middleware (`EnsureGuardian` / `EnsureUserTokenable`).
* **Cryptographic Child Binding:** Students receive an immutable, unique `guardian_code`. Parents use this token for verification during self-registration and multi-child linking.
* **Strict IDOR Prevention:** Every child request is filtered through `GuardianPolicy@viewChild` to guarantee that parents can **only** inspect their linked wards.
* **Strict Read-Only Enforcement:** No academic or financial records can be mutated through the portal.

### 3. ⏱️ Server-Side Timed Examination Engine
* **Anti-Tampering Exam Attempts:** When a student begins an exam, a server-side `ExamAttempt` timestamp is recorded. The deadline is computed on the backend ($\text{deadline} = \text{start\_time} + \text{duration}$). Submissions or answers received after the deadline are blocked automatically regardless of client time.
* **Dynamic Per-Exam Pass Mark:** Exams define configurable passing thresholds rather than fixed percentages.
* **One-Take Enforcement with Admin Override:** Prevents duplicate submissions while allowing administrators to delete a submission to grant a retake.

### 4. 📈 Centralized Progress Service (Single Source of Truth)
Instead of storing denormalized and fragile progress columns, metrics are computed on the fly through a dedicated `ProgressService`:
$$\text{Attendance \%} = \frac{(\text{Present} \times 1.0) + (\text{Late} \times 0.5)}{\text{Total Recorded Sessions}} \times 100$$
$$\text{Exams Average} = \frac{\sum (\text{Student Score})}{\sum (\text{Total Available Marks})} \times 100$$
* **Intelligent At-Risk Alerting:** Teacher dashboards automatically aggregate `students_needing_attention` if a student's attendance or exam average dips below 50%.

### 5. 🛡️ Multi-Keyed Rate Limiting & Security Controls
* **Authentication Throttling:** `throttle:auth` restricts brute-force attempts to **5 requests/minute** keyed by `IP + Email`.
* **Guardian Auth Throttling:** `throttle:guardian-auth` keys limits by `Phone + IP`.
* **API Throttling:** `throttle:api` enforces a generous **120 requests/minute** keyed per authenticated user ID.
* **Email Verification & Password Reset:** HMAC-signed URLs (`signed` middleware) and cryptographic password reset tokens.

---

# 📊 System Architecture Diagram

```
                             Client Applications
                  (React / Vue / Mobile Apps / Postman)
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
Module        Control Center          Subsystem          │
 ├─ Courses    ├─ User CRUD            ├─ Historical Fees│
 ├─ Lessons    ├─ Force Enrollment     ├─ Subscriptions  │
 ├─ Materials  ├─ Result Resets        ├─ Monthly Charges│
 ├─ Exams      └─ Broadcast Alerts     ├─ Payments/Credit│
 ├─ Attempts                           ├─ Invariant Engine
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

# 👥 Role-Based Access Control (RBAC) Matrix

| Capability / Resource | Admin 🔴 | Secretary 🔵 | Teacher 🟡 | Student 🟢 | Guardian 👨‍👩‍👦 |
|---|:---:|:---:|:---:|:---:|:---:|
| **Manage Users & System Roles** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Create Courses & Force Enroll** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Manage Own Courses & Lessons** | ✅ Override | ❌ | ✅ Own only | ❌ | ❌ |
| **Upload Lesson Materials & Schedule** | ✅ Override | ❌ | ✅ Own only | ❌ | ❌ |
| **Take Exams & View Own Grades** | ❌ | ❌ | ❌ | ✅ Own only | ❌ |
| **Reset Exam Results (Allow Retake)** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Record Attendance** | ✅ Override | ❌ | ✅ Own only | ❌ | ❌ |
| **Record Payments** | ✅ Full | ✅ Full | ✅ Own courses | ❌ | ❌ |
| **Historical Fees & Discount Config** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Cancel / Update Payments** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **View Financial Dashboard & Overdue** | ✅ Full | ✅ Full | ❌ | ❌ | ❌ |
| **Access Financial Audit Logs** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **View Ward Academic & Financial Data**| ❌ | ❌ | ❌ | ❌ | ✅ Read-only |

---

# 🔎 Advanced Search, Filtering & Pagination

All collection endpoints support standardized pagination query parameters (`?page=1&per_page=15`, capped at 100) returning structured pagination metadata (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`).

| Resource Endpoint | Supported Query Filters | Authorization Scope |
|---|---|---|
| `GET /api/admin/users` | `role`, `search` (name, email, phone) | Admin only |
| `GET /api/courses` | `search` (title, description), `teacher_id` | Authenticated users |
| `GET /api/attendance` | `student_id`, `course_id`, `lesson_id`, `status`, `date_from`, `date_to` | Scoped by role |
| `GET /api/finance/payments` | `student_id`, `course_id`, `method`, `status`, `year`, `month`, `amount_min`, `amount_max`, `date_from`, `date_to` | Staff / Teacher-scoped |
| `GET /api/finance/subscriptions`| `student_id`, `course_id`, `status` | Staff / Teacher-scoped |
| `GET /api/results` | `student_id`, `exam_id`, `course_id`, `status`, `min_score`, `max_score`, `date_from`, `date_to` | Scoped by role |
| `GET /api/lessons/upcoming` | `course_id`, `date_from`, `date_to` | Scoped by role |
| `GET /api/guardian/children/{id}/attendance` | `status`, `course_id`, `date_from`, `date_to` | Verified Guardian only |

---

# 📦 API Endpoints Directory

<details>
<summary><b>🔐 1. Authentication & Security (Click to expand)</b></summary>

| Method | Endpoint | Description | Throttling |
|---|---|---|---|
| `POST` | `/api/register` | Register new user (Student/Teacher) | 5 / min |
| `POST` | `/api/login` | Authenticate user & issue Sanctum Bearer token | 5 / min |
| `POST` | `/api/logout` | Revoke current access token | Standard |
| `POST` | `/api/forgot-password` | Request password reset token | 5 / min |
| `POST` | `/api/reset-password` | Reset password using valid email token | 5 / min |
| `POST` | `/api/email/verification-notification` | Resend email verification notification | 5 / min |
| `GET` | `/api/email/verify/{id}/{hash}` | Verify email address via cryptographic signed URL | Signed URL |

</details>

<details>
<summary><b>👑 2. Administrator Control Center (Click to expand)</b></summary>

| Method | Endpoint | Description | Access |
|---|---|---|---|
| `GET` | `/api/admin/users` | List and search all users with pagination & role filters | Admin |
| `POST` | `/api/admin/users` | Create user with explicit role assignment | Admin |
| `GET` | `/api/admin/users/{id}` | Inspect user profile, enrollment, and statistics | Admin |
| `PUT` | `/api/admin/users/{id}` | Update user details, credentials, or role | Admin |
| `DELETE` | `/api/admin/users/{id}` | Cascade delete user (with safety guard against self-deletion) | Admin |
| `POST` | `/api/admin/courses` | Create a course assigned to any designated teacher | Admin |
| `GET` | `/api/admin/courses/{id}/students` | Inspect all enrolled students in a course | Admin |
| `POST` | `/api/admin/courses/{id}/students` | Force-enroll students in batch | Admin |
| `DELETE` | `/api/admin/courses/{id}/students/{student}` | Force-unenroll a student from a course | Admin |
| `GET` | `/api/admin/results` | View all exam results with performance filters | Admin |
| `DELETE` | `/api/admin/results/{id}` | Delete exam submission (enables student to retake) | Admin |
| `POST` | `/api/admin/notifications` | Send direct notification to a specific user | Admin |
| `POST` | `/api/admin/notifications/broadcast` | Broadcast notification to all users or filtered by role | Admin |

</details>

<details>
<summary><b>💰 3. Financial Management Subsystem (Click to expand)</b></summary>

| Method | Endpoint | Description | Gate Protection |
|---|---|---|---|
| `GET` | `/api/finance/dashboard` | High-level metrics (expected, collected, remaining, rate) | `finance.viewAny` |
| `GET` | `/api/finance/subscriptions` | Paginated listing of student course subscriptions | `finance.access` |
| `POST` | `/api/finance/subscriptions` | Create custom subscription (custom fee/discount/dates) | `finance.sensitive` |
| `PUT` | `/api/finance/subscriptions/{id}` | Pause, resume, update fee/discount, or cancel subscription | `finance.sensitive` |
| `GET` | `/api/finance/charges` | List generated monthly charges and payment statuses | `finance.access` |
| `POST` | `/api/finance/charges/generate` | Batch-generate charges for an active subscription cycle | `finance.sensitive` |
| `GET` | `/api/finance/payments` | Search and filter payment records | `finance.access` |
| `POST` | `/api/finance/payments` | Record payment (supports cash, credit deduction, overflow) | `finance.recordPayment` |
| `PUT` | `/api/finance/payments/{id}` | Adjust recorded payment amount (recalculates ledger) | `finance.sensitive` |
| `POST` | `/api/finance/payments/{id}/cancel` | Cancel payment with audit reason (reverses balance) | `finance.sensitive` |
| `GET` | `/api/finance/overdue` | List students with outstanding unpaid balances | `finance.viewAny` |
| `GET` | `/api/finance/students/{id}` | Full student financial dossier (charges, payments, credits) | `finance.viewStudent` |
| `GET` | `/api/finance/courses/{id}` | Course financial analytics (revenue, collection rate) | `finance.viewCourse` |
| `GET` | `/api/finance/courses/{id}/fees` | Historical pricing change log for a course | `finance.sensitive` |
| `POST` | `/api/finance/courses/{id}/fees` | Post new price update effective from given date | `finance.sensitive` |
| `GET` | `/api/finance/reports/monthly` | Annual month-by-month revenue aggregation | `finance.viewAny` |
| `GET` | `/api/finance/reports/collection` | Collection rate performance breakdown | `finance.viewAny` |
| `GET` | `/api/finance/audit-logs` | Immutable log of all financial mutations | `finance.sensitive` |

</details>

<details>
<summary><b>👨‍👩‍👦 4. Guardian Portal (Click to expand)</b></summary>

| Method | Endpoint | Description | Auth Guard |
|---|---|---|---|
| `POST` | `/api/guardian/register` | Register guardian account linked via student guardian code | `throttle:guardian-auth` |
| `POST` | `/api/guardian/login` | Authenticate guardian via phone + password | `throttle:guardian-auth` |
| `POST` | `/api/guardian/logout` | Revoke guardian authentication token | `auth:guardian` |
| `GET` | `/api/guardian/children` | List all linked children with real-time academic summaries | `auth:guardian` |
| `POST` | `/api/guardian/children` | Link additional child using their guardian code | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}` | Deep-dive child profile (courses, attendance, exams, fees) | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/payments` | Historical payment receipts for the child | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/progress` | Real-time weighted attendance and exam progress | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/lessons/upcoming` | Child's upcoming scheduled lessons | `auth:guardian` |
| `GET` | `/api/guardian/children/{student}/attendance` | Detailed attendance log for the child | `auth:guardian` |
| `GET` | `/api/guardian/dashboard` | Portal overview with unread notifications counter | `auth:guardian` |
| `GET` | `/api/guardian/notifications` | In-app alerts (exam dates, attendance alerts, receipts) | `auth:guardian` |
| `PATCH` | `/api/guardian/notifications/{id}/read` | Mark individual notification as read | `auth:guardian` |
| `PATCH` | `/api/guardian/notifications/read-all` | Mark all child-related notifications as read | `auth:guardian` |

</details>

<details>
<summary><b>📚 5. Courses, Lessons, Materials & Exams (Click to expand)</b></summary>

| Method | Endpoint | Description | Role Scope |
|---|---|---|---|
| `GET` | `/api/courses` | Searchable course catalog | Authenticated |
| `GET` | `/api/my-courses` | Courses associated with current user | Role-scoped |
| `GET` | `/api/my-courses/{id}` | Deep course details including lesson & exam roster | Role-scoped |
| `POST` | `/api/courses` | Create new course | Teacher / Admin |
| `PUT` | `/api/courses/{id}` | Update course metadata | Teacher (Owner) / Admin |
| `DELETE`| `/api/courses/{id}` | Remove course | Teacher (Owner) / Admin |
| `POST` | `/api/courses/{id}/join` | Student course self-enrollment (opens subscription) | Student |
| `DELETE`| `/api/courses/{id}/leave`| Student unenrollment (closes subscription) | Student |
| `GET` | `/api/my-lessons` | Retrieve scheduled lessons | Role-scoped |
| `GET` | `/api/lessons/upcoming` | Filter upcoming scheduled sessions | Role-scoped |
| `POST` | `/api/courses/{id}/lessons` | Schedule lesson (onsite/online, date, duration, room) | Teacher (Owner) / Admin |
| `POST` | `/api/lessons/{id}/materials` | Upload lesson handouts/attachments (PDF, images) | Teacher (Owner) / Admin |
| `GET` | `/api/materials/{id}/download` | Secure file download endpoint | Enrolled Student / Staff |
| `POST` | `/api/courses/{id}/exams` | Configure exam (duration, start/end dates, pass mark) | Teacher (Owner) / Admin |
| `POST` | `/api/exams/{id}/questions` | Add multiple-choice questions with answer choices | Teacher (Owner) / Admin |
| `POST` | `/api/exams/{id}/start` | Initialize server-side exam timer attempt | Student |
| `POST` | `/api/questions/{id}/answer` | Record student question answer during active attempt | Student |
| `POST` | `/api/exams/{id}/submit` | Finalize exam, evaluate score, compute pass/fail | Student |
| `GET` | `/api/my-results` | Student exam history and scores | Student |
| `POST` | `/api/lessons/{id}/attendance`| Record lesson attendance (present, late, absent) | Teacher (Owner) / Admin |
| `GET` | `/api/progress` | Get computed progress metrics (attendance & exams) | Student |

</details>

---

# 🧪 Testing & Quality Assurance

The codebase maintains an uncompromising test-driven standard, validated via **PHPUnit** and SQLite in-memory suites.

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

### Highlights of Test Coverage:
* **`FinancialInvariantTest`:** Validates invariant mathematical conservation of balances through multi-stage payment lifecycles, discounts, and concurrent charges.
* **`AuthorizationMatrixTest`:** Comprehensive permission checks guaranteeing that no role can breach horizontal or vertical boundaries.
* **`ExamFlowTest`:** Enforces timer expiration, anti-double-submission constraints, and automated grading precision.
* **`RateLimitingTest`:** Asserts HTTP `429 Too Many Requests` responses under brute-force attacks across all key throttlers.

---

# 🚀 Getting Started

### Prerequisites
- PHP `>= 8.2` with PDO, OpenSSL, Mbstring, and Tokenizer extensions
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

Configure your MySQL connection in `.env`:
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

The database seeder automatically initializes realistic demo accounts:
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
The REST API will be live at `http://127.0.0.1:8000`.

### 5. Postman Collection
Import the ready-to-test Postman workspace located at:
📁 [`docs/center-api-postman-collection.json`](docs/center-api-postman-collection.json)

---

# 📄 License

This project is open-source software licensed under the **[MIT License](LICENSE)**.

<p align="center">
Architected & Maintained with passion by <b>Tamer Ayman</b>
</p>
