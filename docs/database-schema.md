# Center Management API — Final Database (DBML)

> آخر تحديث: 2026-09-14 — يشمل كل الجداول: الأساسية + التعليمية + النظام المالي + Guardian Portal + الـ Auth + سجلات التدقيق المالي (Audit Logs).
> تحديث جدولة الدروس: `lessons` بقت فيها start_time / end_time / location / delivery_type (onsite, online).
> **طريقة الاستخدام**: انسخ كود الـ DBML كامل (اللي تحت) والصقه في dbdiagram.io بدل الكود القديم — فيه كل حاجة: الجداول + العلاقات + الـ indexes + الـ uniques.

## إحصائيات المخطط

- **26 جدول**: 8 أساسية + 5 تعليمية + 6 مالية + 3 guardian + 4 auth/infrastructure
- **العلاقات**: 38 Ref كاملة
- **كل أعمدة الفلوس DECIMAL(10,2)** — مفيش float
- **الـ enums موثقة** في كومنتات الأعمدة

## تغييرات آخر تحديث (مقارنة بالنسخة القديمة على dbdiagram)

1. `users`: إضافة `guardian_code` (unique — كود ربط ولي الأمر، يظهر للطالب فقط) + `role` بقيمة `secretary` الجديدة + `email_verified_at`
2. جداول النظام المالي كاملة: `course_fees` / `course_subscriptions` / `monthly_charges` / `payments` / `student_credits` / `financial_audit_logs` (تسجيل `cancellation_reason` و `actor_id` لكل عملية إلغاء اشتراك أو دفعة).
3. `exams`: إضافة `total_marks` + `pass_mark` + `duration` (لكل طالب)
4. `lesson_materials`: إضافة `file_size` (bigint)
5. جداول Guardian: `guardians` / `guardian_student` / `guardian_notifications`

## كود الـ DBML الكامل

```dbml
// =========================================================
// Center Management API — Full Database Schema
// roles: admin / secretary / teacher / student
// Guardians: كيان مستقل (مش user role) بـ Sanctum auth خاص
// =========================================================

// ----------------------------------------------------
// 1. Core Tables
// ----------------------------------------------------

Table users {
  id integer [primary key]
  name varchar
  email varchar
  password varchar
  role varchar // admin, secretary, teacher, student
  guardian_code varchar [unique, note: 'GUARD-XXXX-XXXX — كود ربط ولي الأمر، يظهر للطالب فقط']
  email_verified_at timestamp [null]
  remember_token varchar [null]
  created_at timestamp
  updated_at timestamp
}

Table profiles {
  id integer [primary key]
  user_id integer [unique]
  phone varchar [null]
  avatar_path varchar [null]
  bio text [null]
  date_of_birth date [null]
  gender varchar [null, note: 'male, female']
  country varchar [null]
  city varchar [null]
  github_url varchar [null]
  linkedin_url varchar [null]
  website_url varchar [null]
  created_at timestamp
  updated_at timestamp
}

Table courses {
  id integer [primary key]
  title varchar
  description text [null]
  teacher_id integer
  created_at timestamp
  updated_at timestamp

  indexes {
    teacher_id
  }
}

Table lessons {
  id integer [primary key]
  course_id integer
  title varchar
  description text [null]
  lesson_date datetime
  start_time time [null]
  end_time time [null]
  location varchar [null]
  delivery_type varchar [default: 'onsite', note: 'onsite, online']
  created_at timestamp
  updated_at timestamp

  indexes {
    course_id
    lesson_date
  }
}

Table lesson_materials {
  id integer [primary key]
  lesson_id integer
  title varchar
  type varchar [note: 'document, presentation, archive, image, youtube, link']
  path varchar [null, note: 'ملف مرفوع على السيرفر']
  external_url varchar [null, note: 'يوتيوب أو لينك خارجي']
  mime_type varchar [null]
  file_size bigint [null, note: 'بالبايت']
  created_at timestamp
  updated_at timestamp

  indexes {
    lesson_id
  }
}

Table exams {
  id integer [primary key]
  course_id integer
  title varchar
  description text [null]
  start_time timestamp
  end_time timestamp
  duration integer [note: 'مدة الامتحان لكل طالب بالدقائق']
  total_marks integer
  pass_mark integer [note: 'نسبة النجاح % لكل امتحان']
  created_at timestamp
  updated_at timestamp

  indexes {
    course_id
  }
}

Table questions {
  id integer [primary key]
  exam_id integer
  question_text text
  marks integer
  option_a varchar
  option_b varchar
  option_c varchar
  option_d varchar
  correct_answer char [note: 'a, b, c, d']
  created_at timestamp
  updated_at timestamp

  indexes {
    exam_id
  }
}

Table notifications {
  id integer [primary key]
  user_id integer
  title varchar
  message text
  is_read boolean [default: false]
  created_at timestamp
  updated_at timestamp

  indexes {
    user_id
  }
}

// ----------------------------------------------------
// 2. Learning Activity Tables
// ----------------------------------------------------

// اشتراك الطالب في الكورس — content access
Table course_student {
  id integer [primary key]
  course_id integer
  student_id integer
  created_at timestamp
  updated_at timestamp

  indexes {
    (course_id, student_id)
  }
}

Table attendances {
  id integer [primary key]
  student_id integer
  lesson_id integer
  status varchar [note: 'present, absent, late']
  created_at timestamp
  updated_at timestamp

  indexes {
    (student_id, lesson_id) [unique]
    lesson_id
    status
  }
}

Table student_answers {
  id integer [primary key]
  student_id integer
  question_id integer
  student_answer char [note: 'a, b, c, d']
  is_correct boolean [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (student_id, question_id) [unique]
    question_id
  }
}

Table student_results {
  id integer [primary key]
  student_id integer
  exam_id integer
  score integer
  total_marks integer
  status varchar [note: 'passed, failed']
  submitted_at timestamp [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (student_id, exam_id) [unique]
    exam_id
  }
}

// محاولة الطالب في الامتحان — وقت البدء والانتهاء لحساب الـ duration لكل طالب
Table exam_attempts {
  id integer [primary key]
  student_id integer
  exam_id integer
  started_at timestamp
  finished_at timestamp [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (student_id, exam_id) [unique]
    exam_id
  }
}

// ----------------------------------------------------
// 3. Financial & Course Subscription Management
// ----------------------------------------------------

// تاريخ أسعار الكورس — كل تغيير سعر = صف جديد (Immutable)
Table course_fees {
  id integer [primary key]
  course_id integer
  monthly_fee decimal(10,2)
  effective_from date [note: 'السعر يسري من هذا التاريخ']
  created_by integer [note: 'الأدمن الذي عيّن السعر']
  created_at timestamp
  updated_at timestamp

  indexes {
    (course_id, effective_from)
    created_by
  }
}

// الاشتراك المالي للطالب في الكورس
// snapshot للسعر والخصم — تغيير سعر الكورس لا يغير الاشتراكات القديمة
Table course_subscriptions {
  id integer [primary key]
  student_id integer
  course_id integer
  start_date date
  end_date date [null]
  monthly_fee decimal(10,2) [note: 'السعر المتفق عليه وقت الاشتراك']
  monthly_discount decimal(10,2) [note: 'خصم هذا الطالب']
  discount_reason varchar [null, note: 'منحة / أخو طالب ...']
  status varchar [note: 'active, paused, cancelled, completed']
  created_at timestamp
  updated_at timestamp

  indexes {
    (student_id, status)
    (course_id, status)
  }
}

// الفاتورة الشهرية — سجل مستقل لكل شهر لكل اشتراك
// amount = snapshot للسعر النهائي، paid_amount = derived (مدفوعات نشطة + credit)
Table monthly_charges {
  id integer [primary key]
  subscription_id integer
  student_id integer [note: 'denormalized للتقارير']
  course_id integer [note: 'denormalized للتقارير']
  period_year integer
  period_month integer [note: '1-12']
  amount decimal(10,2)
  paid_amount decimal(10,2)
  credit_applied decimal(10,2) [note: 'الجزء المطبق من رصيد الطالب']
  status varchar [note: 'unpaid, partially_paid, paid']
  due_date date [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (subscription_id, period_year, period_month) [unique]
    (student_id, course_id, period_year, period_month) [name: 'monthly_charges_reporting_index']
    (period_year, period_month, status) [name: 'monthly_charges_period_status_index']
  }
}

// دفعة — تسجل المبلغ الكامل الذي دفعه الطالب فعلياً
// allocated_amount = الجزء الذي ذهب للفاتورة، الباقي تحول student credit
// ممنوع الحذف الفيزيائي — الإلغاء cancel فقط بسبب مسجل
Table payments {
  id integer [primary key]
  student_id integer
  course_id integer
  monthly_charge_id integer [null]
  amount decimal(10,2) [note: 'المبلغ الفعلي الكامل']
  allocated_amount decimal(10,2) [note: 'الجزء المطبق على الفاتورة']
  paid_at date
  method varchar [note: 'cash, vodafone_cash, bank_transfer, card, other']
  status varchar [note: 'active, cancelled']
  created_by integer [note: 'الأدمن/السكرتير/المدرس الذي سجّل الدفعة']
  note varchar [null]
  cancelled_by integer [null]
  cancelled_at timestamp [null]
  cancellation_reason text [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (monthly_charge_id, status)
    (student_id, paid_at)
    (method, status)
    paid_at
    created_by
    cancelled_by
  }
}

// رصيد الدفع المقدم — نشأ من دفعة زادت عن المتبقي
// remaining_amount = المتبقي بعد استخدامه في شهور لاحقة
Table student_credits {
  id integer [primary key]
  student_id integer
  source_payment_id integer [null, note: 'الدفعة التي نشأ منها']
  amount decimal(10,2)
  remaining_amount decimal(10,2)
  note varchar [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (student_id, remaining_amount)
    source_payment_id
  }
}

// من عمل إيه وإمتى — لأي عملية مالية حساسة (Observers)
Table financial_audit_logs {
  id integer [primary key]
  actor_id integer [null, note: 'من نفذ العملية']
  action varchar [note: 'payment_created, payment_updated, payment_cancelled, subscription_created, subscription_cancelled, discount_updated, fee_changed, credit_used']
  auditable_type varchar [note: 'payment / course_fee / course_subscription / monthly_charge']
  auditable_id integer
  changes json [null, note: 'before / after']
  created_at timestamp

  indexes {
    (auditable_type, auditable_id)
    actor_id
    action
  }
}

// ----------------------------------------------------
// 4. Guardian Portal — كيان مستقل (ليس user role)
// ----------------------------------------------------

// ولي الأمر — حساب مستقل بـ Sanctum tokens (morph) — Read-Only Portal
Table guardians {
  id integer [primary key]
  name varchar
  phone varchar [unique, note: 'هوية الدخول']
  email varchar [null, unique]
  password varchar
  created_at timestamp
  updated_at timestamp
}

// علاقة ولي الأمر بالطالب — Many-to-Many
// أب وأم لنفس الطالب مسموحين، ونفس الـ guardian لأكثر من طفل
Table guardian_student {
  id integer [primary key]
  guardian_id integer
  student_id integer
  relationship varchar [note: 'father, mother, guardian']
  created_at timestamp
  updated_at timestamp

  indexes {
    (guardian_id, student_id) [unique]
    student_id
  }
}

// إشعارات أولياء الأمور — In-App — كل إشعار مرتبط بالطفل صاحبه
Table guardian_notifications {
  id integer [primary key]
  guardian_id integer
  student_id integer [note: 'الطفل صاحب الإشعار']
  title varchar
  message text
  is_read boolean [default: false]
  created_at timestamp
  updated_at timestamp

  indexes {
    (guardian_id, is_read)
    (guardian_id, student_id)
  }
}

// ----------------------------------------------------
// 5. Auth & Infrastructure Tables
// ----------------------------------------------------

Table password_reset_tokens {
  email varchar [primary key]
  token varchar
  created_at timestamp [null]
}

// Sanctum tokens — morph: tokenable = App\Models\User أو App\Models\Guardian
Table personal_access_tokens {
  id integer [primary key]
  tokenable_type varchar
  tokenable_id integer
  name varchar
  token varchar [unique]
  abilities text [null]
  last_used_at timestamp [null]
  expires_at timestamp [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (tokenable_type, tokenable_id)
  }
}

Table sessions {
  id varchar [primary key]
  user_id integer [null]
  ip_address varchar [null]
  user_agent text [null]
  payload longtext
  last_activity integer
}

// ----------------------------------------------------
// 6. Relationships (41 Ref)
// ----------------------------------------------------

// core
Ref: profiles.user_id > users.id
Ref: courses.teacher_id > users.id
Ref: lessons.course_id > courses.id
Ref: lesson_materials.lesson_id > lessons.id
Ref: exams.course_id > courses.id
Ref: questions.exam_id > exams.id
Ref: notifications.user_id > users.id

// learning activity
Ref: course_student.student_id > users.id
Ref: course_student.course_id > courses.id
Ref: attendances.student_id > users.id
Ref: attendances.lesson_id > lessons.id
Ref: student_answers.student_id > users.id
Ref: student_answers.question_id > questions.id
Ref: student_results.student_id > users.id
Ref: student_results.exam_id > exams.id
Ref: exam_attempts.student_id > users.id
Ref: exam_attempts.exam_id > exams.id

// financial
Ref: course_fees.course_id > courses.id
Ref: course_fees.created_by > users.id
Ref: course_subscriptions.student_id > users.id
Ref: course_subscriptions.course_id > courses.id
Ref: monthly_charges.subscription_id > course_subscriptions.id
Ref: monthly_charges.student_id > users.id
Ref: monthly_charges.course_id > courses.id
Ref: payments.student_id > users.id
Ref: payments.course_id > courses.id
Ref: payments.monthly_charge_id > monthly_charges.id
Ref: payments.created_by > users.id
Ref: payments.cancelled_by > users.id
Ref: student_credits.student_id > users.id
Ref: student_credits.source_payment_id > payments.id
Ref: financial_audit_logs.actor_id > users.id

// guardian
Ref: guardian_student.guardian_id > guardians.id
Ref: guardian_student.student_id > users.id
Ref: guardian_notifications.guardian_id > guardians.id
Ref: guardian_notifications.student_id > users.id

// auth & infrastructure — tokenable morph: User أو Guardian
Ref: personal_access_tokens.tokenable_id > users.id
Ref: sessions.user_id > users.id
```
