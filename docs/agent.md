# TechiQuiz Agent Guide

Date: 2026-03-04
Purpose: Transfer-ready guide for any incoming AI engineer working on this plugin.

## 1) Product Vision

TechiQuiz exists to deliver the platform’s unique value: a high-quality, exam-style, interactive quiz experience for well-control training.

Core value is in:
- Study mode (retry until correct)
- Practice mode (exam simulation, scoring, missed-question review)
- Fast content updates from Excel
- Reliable learning experience across mobile and desktop

Commodity features should stay external and replaceable via adapters:
- Payments/checkout
- Membership/access control
- Booking/calendar

## 2) Current Architecture (as of this date)

Implemented foundation:
- Plugin bootstrap + activation flow
- Custom quiz-domain DB schema
- Core services for quiz logic and sessions
- REST endpoints for loading sets, starting sessions, submitting answers, and completing practice tests
- Frontend shortcode shell with Tailwind utility-style markup
- Dynamic question support through `question_type`:
  - `single_choice`
  - `objective_math` (still objective/radio-answer based)

Primary files:
- `techiquiz.php`
- `includes/class-tq-db.php`
- `includes/class-tq-quiz-service.php`
- `includes/class-tq-session-service.php`
- `includes/class-tq-rest.php`
- `public/class-tq-shortcodes.php`
- `templates/quiz-shell.php`

## 3) Engineering Rules for Future Work

1. Keep business logic in domain services, not in adapters.
2. Treat third-party plugin APIs as replaceable integration layers.
3. Use smallest safe changes; avoid broad refactors unless explicitly approved.
4. Preserve objective-question reliability over UI complexity.
5. Prioritize data integrity and scoring correctness first.
6. Never store plain temporary passwords.
7. For every new feature, create a dedicated git branch.
8. Commit at functional intervals with clean, focused commit messages.
9. Do not commit buggy code; run at least lint/manual sanity checks before committing.

## 4) Mandatory Accountability Rule (Checklist)

After implementing any feature, update `docs/phase-checklist.md` in the same change set:

- Mark exact completed tasks only (no optimistic checking).
- If implementation is partial, leave exit criteria unchecked.
- Add short notes under “Notes / Risks / Decisions” when scope or assumptions change.
- If a new feature is outside listed tasks, append a new checkbox under the relevant phase.

This rule is mandatory for all agents to maintain transparent progress.

## 5) Domain Model Guidance

Question model must support both Excel sheet types:
- Normal single-choice questions
- Math-style objective questions (still radio buttons)

Use one engine with type metadata:
- `question_type` for behavior/analytics
- `prompt_format` for rendering mode (`plain|latex|mixed`)

Do not split into separate runtime engines unless objectively required.

## 6) Tailwind UI Guidance

UI direction is Tailwind-first.

Current state:
- Templates already use utility-style class conventions.
- Full Tailwind build pipeline is not yet wired.

Next UI step:
- Add `tailwind.config.js`, source CSS, and build output workflow.
- Keep generated CSS scoped to plugin frontend components.

## 7) Recommended Next Implementation Sequence

1. Phase 1 completion: admin CRUD for courses/sets/questions.
2. Phase 2: Excel importer with two-sheet auto-detection and validation.
3. Phase 3 completion: review-mode UX (mark wrong answer with X, highlight correct answer).
4. Adapter contracts and provider registry integration.
5. Access automation and booking-linked learning flow.

## 8) Definition of “Done” for Any Feature

A feature is only done when all are true:
- Code implemented
- Minimal validation/lint completed
- Checklist updated
- Docs updated if behavior/architecture changed

## 9) Handoff Protocol

Before handing to another agent:
1. Update `docs/phase-checklist.md`.
2. Update `README.md` with implemented capabilities.
3. Add/refresh any relevant doc in `docs/` for design decisions.
4. Leave a short “next action” recommendation with dependencies/risks.
5. Ensure branch name and commit history are clean and traceable.
