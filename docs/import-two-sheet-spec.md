# Excel Two-Sheet Import Spec (Draft)

Date: 2026-03-04

## Goal

Support Excel files containing:
1. Standard single-choice sheet
2. Mathematical objective sheet (still radio-button objective answers)

## Required import fields

- `course_slug`
- `set_title`
- `day_label`
- `mode` (`study|practice`)
- `question_text`
- `choice_a`
- `choice_b`
- `choice_c`
- `choice_d`
- `correct_choice` (`A|B|C|D`)
- `display_order`

## Optional fields

- `question_type` (`single_choice|objective_math`)
- `prompt_format` (`plain|latex|mixed`)
- `explanation`

## Sheet behavior

- Sheet names matching `math`, `calculation`, `formula` default `question_type=objective_math` when value is absent.
- Other sheets default `question_type=single_choice`.

## Validation rules

- Exactly one correct choice
- `correct_choice` must exist among provided options
- `display_order` positive integer
- Duplicate `(set, mode, display_order)` rejected unless `upsert=true`
