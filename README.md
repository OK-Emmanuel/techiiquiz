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

## Plugin updates from GitHub

The plugin now supports WordPress admin updates via GitHub releases.

### 1) Point to your real GitHub repo

Update the plugin header in `techiquiz.php`:

- `Update URI: https://github.com/OK-Emmanuelg/techiiquiz`

Update the default repo URL in `includes/class-tq-updater.php`:

- `https://github.com/your-org/techiiquiz`

### 2) Install dependencies

Run Composer in the plugin root so `vendor/` contains Plugin Update Checker:

- `composer install --no-dev`

### 3) Create a release whenever you bump version

- Increase `Version:` in plugin header (`techiquiz.php`)
- Commit and push
- Create a GitHub Release (tag like `v0.1.1`)
- Attach a plugin zip as a release asset, or let the release source zip be used

WordPress sites with the plugin installed will then see the update in Plugins and can click Update.

### 4) Optional: private repo token

If the GitHub repo is private, inject a token from another plugin or mu-plugin:

- Filter: `tq_updater_github_token`

Other available filters:

- `tq_updater_repo_url`
- `tq_updater_branch`
- `tq_updater_slug`
