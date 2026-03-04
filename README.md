# TechiQuiz Plugin (Initial Core Domain)

## What is implemented

- Plugin bootstrap with activation hook
- Custom DB schema for quiz domain
- Admin module:
  - Courses CRUD
  - Sets CRUD
  - Question Bank CRUD (including choices and correct-answer selection)
- Importer module:
  - Admin importer screen with file upload
  - Dry-run validation mode
  - Upsert by `(set + display_order)`
  - CSV import supported by default
  - XLSX/XLS import supported when PhpSpreadsheet is available
  - Flexible column mapping for legacy quiz files (`Ct/CT`, variable question-column names, `(A)-(D)`, `Ans`)
  - Metadata inference from filename/source-group (`mode`, `day_label`, `course_slug`, `set_title`)
  - Dynamic choice validation supports 2, 3, or 4 options (C/D optional)
  - Missing `display_order` auto-generated from row sequence
- Core services:
  - quiz payload and answer evaluation
  - session lifecycle and practice scoring
- REST endpoints:
  - `GET /wp-json/techiquiz/v1/set/{set_id}`
  - `POST /wp-json/techiquiz/v1/session/start`
  - `POST /wp-json/techiquiz/v1/session/answer`
  - `POST /wp-json/techiquiz/v1/session/complete`
- Frontend shortcode:
  - `[tq_quiz set="123" mode="study"]`
  - `[tq_quiz set="123" mode="practice"]`
- Tailwind-style class-based template shell (build pipeline can be added next)

## Dynamic quiz support

Questions now include:
- `question_type` (`single_choice` or `objective_math`)
- `prompt_format` (`plain`, optional future support for equation formats)

Both types are objective radio-button questions at runtime.

## Next suggested step

Implement persistent import history/logging, then finalize review-mode UX enhancements.
