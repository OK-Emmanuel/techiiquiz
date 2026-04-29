# TechiQuiz Phase Checklist Tracker

Date created: 2026-03-03
Based on: `docs/implementation-plan.md`

Use this document as the working execution tracker. Mark checkboxes as work completes.

## Checklist Governance Rule (Mandatory)

- [x] Rule acknowledged: every implemented feature must update this checklist in the same change set
- [x] Rule acknowledged: only fully completed tasks are checked; partial work keeps exit criteria unchecked
- [x] Rule acknowledged: scope/assumption changes are recorded under Notes / Risks / Decisions

## Overall Progress

- [ ] Phase 0 complete
- [ ] Phase 1 complete
- [ ] Phase 2 complete
- [ ] Phase 3 complete
- [ ] Phase 4 complete
- [ ] Phase 5 complete
- [ ] Phase 6 complete
- [ ] Phase 7 complete

---

## Phase 0 — Discovery and Setup (2–3 days)

### Deliverables
- [ ] Final requirement baseline approved (scope lock)
- [ ] Plugin architecture skeleton approved
- [ ] Local/staging environment checklist completed

### Execution Tasks
- [x] Confirm plugin stack versions (WordPress, PHP, WooCommerce, Custom Techiquiz plugin)
- [ ] Define access matrix (membership/product -> quiz set entitlement)
- [ ] Complete technical spike for session + scoring logic
- [ ] Approve UI wireframe for Study and Practice screens

### Exit Criteria
- [ ] Scope and integration contracts approved

---

## Phase 1 — Core Data + Admin Foundation (4–6 days)

### Deliverables
- [x] Database migrations run on plugin activation
- [x] Admin menus and CRUD for courses/sets/questions
- [x] Basic import staging screen available

### Execution Tasks
- [x] Implement activator/deactivator classes
- [x] Create schema for required custom tables
- [x] Build admin page: Courses
- [x] Build admin page: Sets (day/mode)
- [x] Build admin page: Question bank
- [x] Add validation: exactly one correct choice per question
- [x] Add validation: minimum required choices per question
- [x] Add capability checks (`manage_options` or custom capabilities)

### Exit Criteria
- [x] Admin can create and preview a complete quiz set manually

---

## Phase 2 — Import Pipeline (Excel to Quiz) (4–5 days)

### Deliverables
- [x] CSV/XLSX ingestion workflow
- [x] Duplicate and format validation report
- [x] Import history/logging

### Execution Tasks
- [ ] Freeze import template columns:
  - [ ] `course_slug`
  - [ ] `set_title`
  - [ ] `day_label`
  - [ ] `mode`
  - [ ] `question_text`
  - [ ] `choice_a`, `choice_b`, `choice_c`, `choice_d`
  - [ ] `correct_choice`
  - [ ] `explanation` (optional)
  - [ ] `display_order`
- [x] Implement parser with dry-run validator
- [x] Implement upsert strategy (`set + display_order` or external key)
- [x] Build import summary (created / updated / failed)

### Exit Criteria
- [ ] Subsea and updated question bank import cleanly with zero blocker errors

---

## Phase 3 — Quiz Runtime (Study + Practice) (6–8 days)

### Deliverables
- [x] Frontend quiz app with distinct Study and Practice modes
- [x] Session create/resume/complete logic
- [x] Accurate scoring and review rendering

### Execution Tasks
- [x] Implement protected shortcode route for Study mode
- [x] Implement protected shortcode route for Practice mode
- [x] Study mode: evaluate answer attempts per question
- [x] Study mode: on incorrect answers, prompt retry without revealing the correct option
- [x] Study mode: block next question until correct answer selected
- [x] Practice mode: store answers without immediate correctness feedback
- [x] Practice mode: compute final score at completion
- [x] Practice mode: generate missed-question review list
- [x] Runtime: mode-based quiz length limits and progress text (35 test / 100 practice)
- [x] Runtime: show per-question identifier below prompt for issue reporting
- [x] Runtime: reduce answer-submit latency by removing extra lookup and keeping immediate submit feedback
- [x] Review mode: mark student incorrect selection with X
- [x] Review mode: emphasize correct option
- [x] Persist progress for interrupted sessions

### Exit Criteria
- [ ] Full end-to-end student flow works for one complete day set in both modes

---

## Phase 4 — Booking Schema & Admin (6–8 days)

### Deliverables
- [ ] Database schema for classes, instances, enrollments, entitlements
- [ ] Admin UI for class management (create, edit, delete)
- [ ] Admin UI for class instance/booking calendar (create, edit, delete)
- [ ] WooCommerce webhook handler for order completion

### Notes
- Frontend booking calendar implementation has started with a new `[tq_booking_calendar]` shortcode and a client-side rolling calendar that reuses the existing class-instance and WooCommerce product mapping data.
- Added `[tq_my_bookings]` shortcode with self-service cancel action (marks enrollment as `cancelled` and deactivates entitlements).
- Class Instances admin table now includes Shop quick links for direct WooCommerce price editing.
- Booking provisioning now sends a dedicated confirmation email with class dates, access window, workbook link, and login details.
- Refactored booking frontend to Tailwind-first markup/components with minimal fallback CSS.
- Added `docs/production-go-live.md` with granular setup and launch checklist for immediate production rollout.

### Execution Tasks

#### 4a: Database Schema & Activation
- [x] Create DB migration in activator: `tq_classes` table
- [x] Create DB migration in activator: `tq_class_instances` table
- [x] Create DB migration in activator: `tq_enrollments` table
- [x] Create DB migration in activator: `tq_entitlements` table
- [x] Create DB migration in activator: `tq_provisioning_logs` table (for debugging)
- [x] Add indexes for performance (user, class_instance, product_id, access window)
- [x] Test: Fresh activation creates tables with no errors

#### 4b: Admin UI — Classes
- [x] Build admin page: Classes list (name, code, workbook URL, # instances)
- [x] Build form: Add new class (name, code, workbook URL)
- [x] Build form: Edit class
- [x] Build form: Delete class (with confirmation)
- [ ] Test: CRUD operations work end-to-end

#### 4c: Admin UI — Class Instances (Booking Calendar)
- [x] Build admin page: Class instances list (class name, dates, capacity, enrollments)
- [x] Build form: Add new instance (select class, start date, end date, max capacity)
- [x] Build form: Map instance to WooCommerce product (dropdown of products)
- [x] Build form: Edit instance
- [x] Build form: Delete instance (with confirmation + cascade warning)
- [x] Display: Calculated access_end date based on end_date + 45 days
- [ ] Test: Can create, map to product, edit, delete instances

#### 4d: WooCommerce Webhook Handler
- [x] Create REST endpoint: `POST /wp-json/tq/v1/webhook/order-completed`
- [x] Add admin simulation action (run provisioning by WooCommerce order ID from Provisioning Logs)
- [ ] Implement handler logic:
  - [x] Receive order_id, customer_email, line_items from WooCommerce
  - [x] Find class instance by product_id
  - [x] Find or create WordPress user by email (with temp password if new)
  - [x] Create enrollment record
  - [x] Create entitlements (quiz + workbook, both with same access window)
  - [x] Log to provisioning_logs table
  - [x] Send onboarding email with login credentials
- [x] Add webhook registration (hook into `woocommerce_order_status_completed`)
- [ ] Test: Manually trigger order completion, verify entitlements created in DB
- [ ] Test: New user created and email sent
- [ ] Test: Existing user reused on second purchase

### Exit Criteria
- [ ] Admin can create a class template and instance
- [ ] Admin can map instance to WooCommerce product
- [ ] When order completes, webhook fires and creates entitlements
- [ ] Customer receives onboarding email
- [ ] Database contains enrollment and entitlement records

---

## Phase 5 — Access Control & Enrollment Reports (4–5 days)

### Deliverables
- [ ] Quiz shortcode protected by entitlement checks
- [ ] Workbook download shortcode (also protected)
- [ ] Admin enrollment roster with CSV export
- [ ] Admin settings page for question limits (dynamically configurable)
- [ ] Full integration test (end-to-end flow)

### Execution Tasks

#### 5a: Access Control — Quiz Shortcode
- [x] Modify quiz shortcode handler to check entitlements
- [x] Create helper function: `user_has_active_entitlement($user_id, $class_id, $resource_type)`
  - [x] Query entitlements for user + resource
  - [x] Check: access_start <= TODAY <= access_end
  - [x] Check: is_active = true
  - [x] Return boolean
- [x] If no entitlement: show "Your access is not yet available or has expired"
- [x] If entitlement active: render quiz as before
- [ ] Test: User without entitlement blocked
- [ ] Test: User with entitlement allowed
- [ ] Test: After expiry date, user blocked

#### 5b: Access Control — Workbook Shortcode
- [x] Create new shortcode: `[tq_workbook class="dr1"]`
- [x] Query `tq_classes` by course code
- [x] Get `workbook_url` from class
- [x] Check entitlements (same logic as quiz)
- [x] If active: show download button → redirect to PDF URL
- [x] If inactive: show "Your access is not yet available or has expired"
- [ ] Test: Same as quiz shortcode tests

#### 5c: Admin UI — Enrollment Reports
- [x] Build admin page: Enrollment roster
  - [x] Dropdown: Select class instance
  - [x] Display table: user name, email, enrollment date, entitlement status (active/expired)
  - [x] Add button: Export to CSV
- [x] CSV export includes: user email, class name, start date, end date, access_end date, status
- [ ] Test: Export produces valid CSV
- [ ] Test: Filter by instance works

#### 5d: Admin UI — Question Limits Settings
- [x] Add new admin submenu: TechiQuiz → Settings
- [x] Build form:
  - [x] Input: Study mode question limit (default 100)
  - [x] Input: Practice mode question limit (default 35)
  - [x] Save button
- [x] Store in `wp_options` (keys: `tq_study_limit`, `tq_practice_limit`)
- [x] Modify `TQ_Quiz_Service::get_question_limit_for_mode()` to read from options
- [ ] Test: Update settings, quiz reflects new limits

#### 5e: Integration Testing (Full End-to-End)
- [x] Added admin Integration QA page (live diagnostics + persisted scenario runner checklist)
- [ ] Test scenario: Admin creates class → Instance → Maps to product
- [ ] Test scenario: Customer buys on WooCommerce
- [ ] Test scenario: Webhook fires, user created, entitlements created
- [ ] Test scenario: Customer logs in, can access quiz
- [ ] Test scenario: Customer can download workbook
- [ ] Test scenario: After 45 days, access denied
- [ ] Test scenario: Admin can view roster and export
- [ ] Test edge case: Customer buys same class twice (unique constraint prevents duplicate)
- [ ] Test edge case: Product not mapped to instance (webhook logs error)
- [ ] Test edge case: Existing user email purchases again (reuse user, create new enrollment)

### Exit Criteria
- [ ] Full end-to-end flow works: purchase → account creation → quiz access → expiry
- [ ] Quiz and workbook protected by entitlements
- [ ] Admin can manage classes, instances, and view enrollments
- [ ] All edge cases handled with proper error logging

---

## Phase 6 — Landing Page Video + UX Polish (1–2 days)

### Deliverables
- [x] Background video block (autoplay, loop, muted)
- [x] Readability-safe overlay treatment

### Execution Tasks
- [x] Implement responsive video background section
- [x] Add contrast layer for text legibility
- [ ] Validate mobile behavior and fallback behavior

### Exit Criteria
- [ ] Landing hero is readable and stable across target devices

---

## Phase 7 — QA, Security, and Launch Readiness (4–6 days)

### Deliverables
- [x] Test scripts + UAT checklist
- [ ] Security hardening completed
- [x] Production launch checklist finalized

### Execution Tasks
- [x] Add admin Launch Readiness tracker page with persisted QA/Security/Operations checklist
- [ ] Functional QA: Study mode retry behavior
- [ ] Functional QA: Practice scoring and review correctness
- [ ] Access QA: unauthorized users blocked
- [ ] Access QA: entitled users allowed
- [ ] Security QA: nonce + capability enforcement
- [ ] Security QA: sanitize input and escape output
- [ ] Security QA: evaluate endpoint rate limiting needs
- [ ] Performance QA: question load strategy/pagination
- [ ] Performance QA: database index review and tuning
- [ ] Prepare backup and rollback plan
- [ ] Prepare monitoring/logging checklist

### Exit Criteria
- [ ] UAT signoff completed and production deployment approved

---

## Milestone Tracker

- [ ] MVP complete (Phases 0–4)
- [ ] End-to-end learner journey complete (Phases 0–5)
- [ ] Production-ready release complete (Phases 0–7)

## Notes / Risks / Decisions

- [ ] Integration adapters finalized
- [ ] Entitlement mapping approved by business owner
- [ ] Content import format signed off by content team
- [ ] Launch date agreed
- [x] Initial core domain implemented (bootstrap, DB schema, REST, shortcode runtime); Phase 1 admin CRUD and full review UX remain open
- [x] Admin CRUD implemented for Courses, Sets, and Question Bank with branded red/blue admin styling
- [x] Importer implemented with dry-run and upsert; XLSX/XLS path requires PhpSpreadsheet dependency when used
- [x] Git workflow rule active: create feature branches for new features and commit only stable, lint-clean checkpoints
- [x] Flexible legacy importer mapping implemented (`Ct/CT`, variable question column, `(A)-(D)`, `Ans`) with filename/source-group metadata inference
- [x] Importer validation now supports 2–4 choices, auto display-order fallback, and stronger math/prompt column inference
- [x] Persistent import logs implemented with importer history table + admin history view
- [x] Phase 3 runtime upgraded with session resume index and full missed-question review annotation (X + correct emphasis)
- [x] Admin Question Bank pagination added to reduce heavy single-page rendering on large sets
- [x] Importer now auto-reindexes `display_order` collisions across sheets on first import when prompts differ (upsert still required for true re-import updates)
- [x] Phase 3 runtime: fixed quiz numbering to show mode-based limits (35 test, 100 practice) not full question bank; added per-question identifiers for error reporting; optimized query performance (N+1 → batch query)
- [x] **ARCHITECTURAL DECISION**: Booking system built entirely within TechiQuiz plugin (not as adapter abstraction). WooCommerce used for payment/checkout only. All booking logic (calendar, capacity, entitlements, access control) owned by plugin. Custom tables: `tq_classes`, `tq_class_instances`, `tq_enrollments`, `tq_entitlements`.
- [x] **ACCESS MODEL DECISION**: Entitlements table is source-of-truth for access. Access granted by creation of entitlement records on successful WooCommerce order completion (webhook). No background jobs or complex rules engine. Simple date-range check (access_start <= today <= access_end) on each resource request.
- [x] **SCOPE REDUCTION APPROVED**: MVP excludes waitlist, refunds, and instructor dashboard. Core flow only: purchase → account creation → entitlements → quiz/workbook access → 45-day expiry.
- [x] Phase 5e support tooling added: Integration QA admin page with live snapshot metrics, quick links, and saved scenario progress markers (`tq_integration_checks`).
- [x] Phase 7 support tooling added: Launch Readiness admin page with persisted QA/security/performance/operations checklist, launch date field, and blocker notes (`tq_launch_readiness`).
