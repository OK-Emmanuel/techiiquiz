# Copilot Instructions for TechiQuiz

## Scope and current state
- This is a custom WordPress plugin focused on quiz runtime; admin CRUD/import and external commerce adapters are planned but mostly not implemented yet.
- Treat `README.md` as implemented-state summary and `docs/phase-checklist.md` as the execution tracker.
- Mandatory project rule: when implementing any feature, update `docs/phase-checklist.md` in the same change set (see `docs/agent.md`).

## Core architecture (read these first)
- Plugin bootstrap and service wiring: `techiquiz.php`.
- Persistence layer and schema: `includes/class-tq-db.php` (custom `wp_tq_*` tables via `dbDelta`).
- Domain logic split:
  - Quiz payload/scoring: `includes/class-tq-quiz-service.php`
  - Session lifecycle/mode behavior: `includes/class-tq-session-service.php`
- Transport boundary: REST routes in `includes/class-tq-rest.php`.
- UI boundary:
  - Shortcode entry `[tq_quiz set="123" mode="study|practice"]` in `public/class-tq-shortcodes.php`
  - Markup shell in `templates/quiz-shell.php`
  - Browser runtime in `public/js/quiz-app.js`

## Data and behavior conventions
- Use `TQ_DB` for quiz-domain queries; avoid adding direct SQL in shortcode/REST/UI classes.
- Preserve mode semantics:
  - `study`: immediate correctness feedback, cannot advance until correct.
  - `practice`: answers saved silently, score/missed list only at completion.
- API payloads intentionally hide correctness in set fetch (`get_set_payload` removes `is_correct`).
- Keep question model compatible with both sheet types: `question_type` (`single_choice|objective_math`) + `prompt_format`.
- Use WordPress sanitization/escaping patterns already present (`sanitize_key`, `sanitize_text_field`, `esc_attr`, `esc_html`).

## REST and frontend flow
- Current REST endpoints:
  - `GET techiquiz/v1/set/{set_id}`
  - `POST techiquiz/v1/session/start`
  - `POST techiquiz/v1/session/answer`
  - `POST techiquiz/v1/session/complete`
- `public/js/quiz-app.js` sends `X-WP-Nonce` using localized `TQQuiz.nonce`; keep this pattern for new mutations.
- Access gate is currently `is_user_logged_in()` in REST permission callbacks.

## Events and integration boundary
- Existing internal hooks: `tq/quiz_session_started`, `tq/quiz_session_completed` in `TQ_Session_Service`.
- For WooCommerce/MemberPress/Bookings work, follow adapter direction in `docs/adapter-architecture.md`: keep third-party logic in adapters/contracts, not quiz services.

## Developer workflow (this repo)
- No JS build pipeline or automated test harness is configured yet; `public/css/quiz.css` is static and minimal.
- Primary verification is manual in a local WordPress environment:
  1. Activate plugin to run table creation.
  2. Place shortcode on a page and test both `study` and `practice` modes.
  3. Validate REST behavior via browser/network or `wp-json/techiquiz/v1/*` calls.
- If behavior/architecture changes, update docs under `docs/` and `README.md` alongside code.

## Change guidance for agents
- Prefer minimal, surgical changes in existing classes over adding new framework layers.
- Keep business rules in services (`TQ_Quiz_Service`, `TQ_Session_Service`), not in templates or JS.
- Do not mark roadmap checklist items complete unless code is fully implemented and runnable.
