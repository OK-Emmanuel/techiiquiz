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
- [ ] Confirm plugin stack versions (WordPress, PHP, WooCommerce, MemberPress, WooCommerce Bookings)
- [ ] Define access matrix (membership/product -> quiz set entitlement)
- [ ] Complete technical spike for session + scoring logic
- [ ] Approve UI wireframe for Study and Practice screens

### Exit Criteria
- [ ] Scope and integration contracts approved

---

## Phase 1 — Core Data + Admin Foundation (4–6 days)

### Deliverables
- [ ] Database migrations run on plugin activation
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
- [ ] Admin can create and preview a complete quiz set manually

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
- [ ] Frontend quiz app with distinct Study and Practice modes
- [ ] Session create/resume/complete logic
- [ ] Accurate scoring and review rendering

### Execution Tasks
- [x] Implement protected shortcode route for Study mode
- [x] Implement protected shortcode route for Practice mode
- [x] Study mode: evaluate answer attempts per question
- [x] Study mode: block next question until correct answer selected
- [x] Practice mode: store answers without immediate correctness feedback
- [x] Practice mode: compute final score at completion
- [x] Practice mode: generate missed-question review list
- [x] Review mode: mark student incorrect selection with X
- [x] Review mode: emphasize correct option
- [x] Persist progress for interrupted sessions

### Exit Criteria
- [ ] Full end-to-end student flow works for one complete day set in both modes

---

## Phase 4 — Access, Checkout, and Credential Automation (3–5 days)

### Deliverables
- [ ] Entitlement checks tied to purchases/membership
- [ ] Student ID + temporary credential onboarding flow
- [ ] Email templates/events for provisioning

### Execution Tasks
- [ ] Add order completion hook handler
- [ ] Generate and assign student ID on successful completion
- [ ] Implement secure one-time onboarding/reset flow (no plain temp password storage)
- [ ] Add purchased class resource links to confirmation email
- [ ] Build admin provisioning diagnostics panel

### Exit Criteria
- [ ] Newly paid student can access entitled quiz sets immediately after provisioning

---

## Phase 5 — Booking and Learning Experience Integration (3–4 days)

### Deliverables
- [ ] Booking-to-learning linkage
- [ ] Post-booking curriculum/resource panel
- [ ] Class rules display component

### Execution Tasks
- [ ] Surface booking details and prep-material quick links
- [ ] Render class rules on booking/course pages
- [ ] Validate 3-month rolling calendar view via booking plugin configuration

### Exit Criteria
- [ ] Student can book class and directly access relevant prep resources and quiz links

---

## Phase 6 — Landing Page Video + UX Polish (1–2 days)

### Deliverables
- [ ] Background video block (autoplay, loop, muted)
- [ ] Readability-safe overlay treatment

### Execution Tasks
- [ ] Implement responsive video background section
- [ ] Add contrast layer for text legibility
- [ ] Validate mobile behavior and fallback behavior

### Exit Criteria
- [ ] Landing hero is readable and stable across target devices

---

## Phase 7 — QA, Security, and Launch Readiness (4–6 days)

### Deliverables
- [ ] Test scripts + UAT checklist
- [ ] Security hardening completed
- [ ] Production launch checklist finalized

### Execution Tasks
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
