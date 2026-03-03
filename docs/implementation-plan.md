# TechiQuiz Implementation Plan

Date: 2026-03-03
Source reviewed: `techiquiz/project.md`

## 1) Objective and Scope

Build a custom WordPress plugin (`techiquiz`) that delivers the platform’s unique value through a robust, exam-style quiz system for well control training, while integrating with proven third-party plugins for commodity features:

- Payments and checkout: WooCommerce
- Accounts/access control: MemberPress
- Course scheduling/booking: WooCommerce Bookings

### In Scope (Custom Build)

1. Quiz engine for **Study Guide** mode (retry-until-correct flow)
2. Quiz engine for **Practice Test** mode (end-of-test scoring + missed-question review)
3. Quiz content management and import pipeline (Excel-first)
4. Student quiz session tracking, scoring, and reporting
5. Branded exam-like UX matching IADC sample exam feel (no graphics/math-input requirements)
6. Student credential generation trigger after successful purchase (ID + temp password orchestration)
7. Landing page video section support (background autoplay loop with readability overlay)

### Out of Scope (Handled by Third-Party)

- Full e-commerce cart/checkout implementation logic (WooCommerce)
- Membership/account lifecycle and core access permissions (MemberPress)
- Core booking calendar/availability/seat management logic (WooCommerce Bookings)

## 2) Product Requirements Breakdown

## 2.1 Quiz Features

### Study Guide Mode

- ~100 questions per set/day/topic
- Multiple-choice only
- Immediate feedback when wrong: “Incorrect — please select again.”
- User must retry until correct before moving to next question

### Practice Test Mode

- Typical 33–35 questions per test
- No immediate correctness feedback
- Score shown at completion
- Review list of missed questions
- In review:
  - Student’s incorrect answer marked with X
  - Correct answer highlighted

### Content Sets (initial)

- Drilling Supervisor Day sets
- Subsea (Day 3) with 100 Study + 35 Practice
- Future: OGOR and additional courses

## 2.2 Supporting Pages/Flows (integrated, not reinvented)

- Landing page: looping background video with contrast-safe text
- Booking page: calendar with 3-month rolling view and seat limits (via Bookings)
- Checkout completion: trigger student ID + temporary password + confirmation email workflow

## 3) Technical Architecture

## 3.1 Plugin Structure (Recommended)

`techiquiz/`

- `techiquiz.php` (Tailwind)
- `includes/`
  - `class-tq-loader.php`
  - `class-tq-activator.php`
  - `class-tq-deactivator.php`
  - `class-tq-db.php`
  - `class-tq-access.php`
  - `class-tq-quiz-service.php`
  - `class-tq-session-service.php`
  - `class-tq-import-service.php`
  - `class-tq-wc-integration.php`
  - `class-tq-memberpress-integration.php`
  - `class-tq-bookings-integration.php`
  - `class-tq-rest.php`
- `admin/`
  - `class-tq-admin-menu.php`
  - `views/` (question bank, imports, sets, diagnostics)
- `public/`
  - `class-tq-shortcodes.php`
  - `class-tq-assets.php`
  - `js/quiz-app.js`
  - `css/quiz.css`
- `templates/`
  - `quiz-study.php`
  - `quiz-practice.php`
  - `quiz-review.php`

## 3.2 Data Model (Custom Tables Preferred)

Use dedicated tables for performance, query control, and versioned content.

- `wp_tq_courses`
  - `id`, `slug`, `title`, `active`
- `wp_tq_sets`
  - `id`, `course_id`, `day_label`, `mode` (`study|practice`), `title`, `question_count`, `version`, `active`
- `wp_tq_questions`
  - `id`, `set_id`, `prompt`, `explanation` (optional), `display_order`, `active`
- `wp_tq_choices`
  - `id`, `question_id`, `choice_key` (`A/B/C/D`), `choice_text`, `is_correct`
- `wp_tq_sessions`
  - `id`, `user_id`, `set_id`, `mode`, `status` (`in_progress|completed`), `started_at`, `completed_at`, `score_percent`
- `wp_tq_session_answers`
  - `id`, `session_id`, `question_id`, `selected_choice_id`, `is_correct`, `attempt_no`, `answered_at`
- `wp_tq_user_credentials` (if storing generated student ID metadata)
  - `id`, `user_id`, `student_id`, `temp_password_hash`, `generated_at`, `expires_at`

Notes:
- Do not store plain temp passwords.
- Store hashed values and send one-time reset/onboarding links where possible.

## 3.3 Integration Boundaries

### WooCommerce

- Hook into order completion (`woocommerce_order_status_completed`)
- Trigger:
  1. Student ID generation
  2. Temp password/onboarding flow
  3. Access entitlement sync callback

### MemberPress

- Use membership level/rule checks before quiz access
- Map purchased products/memberships to quiz set permissions

### WooCommerce Bookings

- Reuse booking products for scheduling and seat availability
- Add links to quiz/study resources after booking confirmation

## 4) Execution Plan (Phased)

## Phase 0 — Discovery and Setup (2–3 days)

Deliverables:
- Final requirement baseline (this doc + scope lock)
- Plugin architecture skeleton
- Local/staging environment checklist

Tasks:
1. Confirm required plugin stack versions (WP, PHP, WC, MemberPress, Bookings).
2. Define access matrix (which membership/product unlocks which quiz sets).
3. Create technical spike for session and scoring logic.
4. Approve exam-style UI wireframe (study and practice views).

Exit criteria:
- Scope and integration contracts approved.

## Phase 1 — Core Data + Admin Foundation (4–6 days)

Deliverables:
- Database migrations on plugin activation
- Admin menus and CRUD for courses/sets/questions
- Basic import staging screen

Tasks:
1. Implement activation/deactivation classes and schema creation.
2. Build admin pages for:
   - Courses
   - Sets (day/mode)
   - Question bank
3. Add validation rules:
   - Exactly one correct choice per question
   - Required minimum choices
4. Add capability checks (`manage_options` or custom caps).

Exit criteria:
- Admin can create a complete set manually and preview it.

## Phase 2 — Import Pipeline (Excel to Quiz) (4–5 days)

Deliverables:
- CSV/XLSX ingestion workflow
- Duplicate and format validation report
- Import history/logs

Tasks:
1. Define import template columns:
   - `course_slug`, `set_title`, `day_label`, `mode`, `question_text`, `choice_a..d`, `correct_choice`, `explanation` (optional), `display_order`
2. Implement parser + dry-run validator.
3. Support upsert strategy by `(set, display_order)` or external question key.
4. Generate import result summary (created/updated/failed rows).

Exit criteria:
- Current Subsea/updated bank imports cleanly.

## Phase 3 — Quiz Runtime (Study + Practice) (6–8 days)

Deliverables:
- Frontend quiz app with two distinct modes
- Session creation/resume/completion logic
- Accurate scoring and review rendering

Tasks:
1. Implement protected routes/shortcodes:
   - `[tq_quiz set="slug" mode="study"]`
   - `[tq_quiz set="slug" mode="practice"]`
2. Study mode behavior:
   - Evaluate per attempt
   - Block next question until correct
3. Practice mode behavior:
   - Store answers with no immediate feedback
   - Compute end score
   - Build missed-question review list
4. Review mode rendering:
   - Incorrect selected option marked with X
   - Correct option visually emphasized
5. Add progress persistence for interrupted sessions.

Exit criteria:
- End-to-end student flow works for one full Day set in both modes.

## Phase 4 — Access, Checkout, and Credential Automation (3–5 days)

Deliverables:
- Entitlement checks tied to purchases/membership
- Student ID + temporary credential onboarding flow
- Email templates/events

Tasks:
1. Order completion hook: generate/assign student ID.
2. Create secure one-time onboarding/reset link flow.
3. Attach purchased class resources links in confirmation email.
4. Add admin troubleshooting panel (recent provisioning events).

Exit criteria:
- New paid student can log in and access entitled quiz sets immediately.

## Phase 5 — Booking and Learning Experience Integration (3–4 days)

Deliverables:
- Booking-to-learning linkage
- Curriculum/resource panel after booking
- Class rules display component

Tasks:
1. Surface booking details and quick links to prep materials.
2. Render class rules block on booking/course pages.
3. Validate 3-month rolling display with Bookings configuration.

Exit criteria:
- Student can book and immediately find relevant prep resources + quiz links.

## Phase 6 — Landing Page Video + UX Polish (1–2 days)

Deliverables:
- Background video block with autoplay/loop/mute
- Readability-safe overlay treatment

Tasks:
1. Implement responsive video background section.
2. Add contrast layer and accessibility checks (text readability).
3. Verify mobile behavior and loading fallback.

Exit criteria:
- Landing hero meets visual and readability requirements across devices.

## Phase 7 — QA, Security, and Launch Readiness (4–6 days)

Deliverables:
- Test scripts + UAT checklist
- Security hardening pass
- Production launch checklist

Tasks:
1. Functional QA:
   - Study mode retry logic
   - Practice mode scoring/review correctness
2. Access QA:
   - Unauthorized user blocked
   - Entitled user admitted
3. Security QA:
   - Nonce/capability checks
   - Input sanitization/output escaping
   - Rate limiting for quiz endpoints if needed
4. Performance QA:
   - Question pagination/load strategy
   - DB index tuning
5. Launch checklist:
   - Backup/rollback plan
   - Monitoring hooks/logging

Exit criteria:
- UAT signoff and production deployment approval.

## 5) Detailed Backlog (Actionable Tasks)

## 5.1 Must-Have (MVP)

1. Custom tables + migrations
2. Admin question/set management
3. Excel import with validation
4. Study mode runtime
5. Practice mode runtime + score + review
6. Session persistence
7. Access gates using MemberPress signals
8. WooCommerce order-complete automation (student ID workflow)

## 5.2 Should-Have (Post-MVP)

1. Advanced analytics dashboard (most-missed questions, pass trends)
2. Question versioning with change history
3. Content publish workflow (draft/review/publish)

## 5.3 Could-Have

1. Optional AI tutoring assistant for concept support
2. Adaptive practice sets based on weak topics
3. Multi-location and multi-course admin scaling enhancements

## 6) Security, Compliance, and Reliability Requirements

- Enforce least-privilege access controls in admin.
- Sanitize all imported content and escape output in templates.
- Protect all mutations with nonce + capability checks.
- Avoid plain text storage of temporary credentials.
- Keep audit logs for imports, provisioning, and critical access events.
- Build idempotent provisioning handlers for order-related hooks.

## 7) Testing Strategy

- Unit tests (where feasible): scoring, selection evaluation, import validators.
- Integration tests: WooCommerce completion -> access entitlement -> quiz access.
- Manual UAT scripts for each role:
  - Admin (content import/edit)
  - Student (study/practice flows)
  - Support (provisioning troubleshooting)

Key acceptance tests:
1. Wrong answer in Study mode cannot proceed until corrected.
2. Practice mode shows final percentage and accurate missed-question review.
3. Access denied for users without membership/purchase.
4. Access granted automatically after successful payment/order completion.

## 8) Delivery Timeline (Estimate)

- Total MVP: ~27 to 39 working days
- Recommended cadence: 2-week sprints

Suggested sprint allocation:
- Sprint 1: Phases 0–1
- Sprint 2: Phase 2 + Study mode core
- Sprint 3: Practice mode + review + session persistence
- Sprint 4: Integrations (WooCommerce/MemberPress/Bookings) + QA hardening

## 9) Immediate Next Steps (This Week)

1. Freeze the import schema and map existing Excel columns.
2. Implement plugin scaffold and DB migration layer.
3. Build admin CRUD for one pilot set (Subsea Day 3).
4. Implement Study mode end-to-end for pilot set.
5. Run pilot UAT with 5–10 real users and gather correction feedback.

## 10) Definition of Done (Final Objective)

The objective is achieved when:

1. Students can purchase/book classes through WooCommerce + Bookings.
2. Membership/access is enforced via MemberPress rules.
3. Entitled students can log in and use custom quiz sets in Study and Practice modes.
4. Practice tests produce reliable grading and clear missed-question review.
5. Admin can import and maintain quiz content from Excel without developer intervention.
6. Landing/booking/checkout-connected learning flow is functional and production-ready.
