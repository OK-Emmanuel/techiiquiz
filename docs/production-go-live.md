# TechiQuiz Production Go-Live Runbook

Use this runbook as a strict launch checklist.

## 1. Server and plugin prerequisites

1. Confirm WordPress is reachable over HTTPS.
2. Confirm WooCommerce is active.
3. Confirm TechiQuiz plugin is active.
4. Confirm permalinks are enabled (not Plain).
5. Confirm outgoing mail is configured (SMTP provider plugin recommended).

## 2. WooCommerce baseline setup

1. WooCommerce -> Settings -> General:
- Set store address and selling location.
2. WooCommerce -> Settings -> Payments:
- Enable the payment method(s) you will use in production.
- Run a live gateway test transaction.
3. WooCommerce -> Settings -> Accounts & Privacy:
- Allow account creation during checkout (recommended).
4. WooCommerce -> Settings -> Emails:
- Confirm sender name, sender email, and template branding.

## 3. Create Shop products for each class instance

1. WooCommerce -> Products -> Add New.
2. Create one product per class offering/date window (or per mapped instance strategy).
3. Set price, title, and publish.
4. Repeat for all upcoming classes.

## 4. Configure TechiQuiz classes and instances

1. TechiQuiz -> Booking Classes:
- Create class templates (name, course code, workbook URL, description).
2. TechiQuiz -> Class Instances:
- Add instance with class, start date, end date, max capacity.
- Map each instance to the correct Shop product.
- Save and verify row appears with product ID.
3. Use Shop quick links in Class Instances table to verify/edit pricing.

## 5. Create frontend pages

Create these WordPress pages:

1. Booking page
- Title: Booking
- Shortcode: [tq_booking_calendar]

2. My Bookings page
- Title: My Bookings
- Shortcode: [tq_my_bookings calendar_url="/booking/"]

3. Quiz pages (as needed)
- Example: [tq_quiz set="123" mode="study" class_id="1"]
- Example: [tq_quiz set="123" mode="practice" class_id="1"]

4. Workbook page (optional per class)
- Example: [tq_workbook class_id="1"]

## 6. Navigation and access

1. Add Booking and My Bookings to your main menu.
2. Ensure login/register links are visible.
3. Confirm student role can access Booking/My Bookings pages.
4. Confirm only entitled users can open quiz/workbook content.

## 7. End-to-end launch test (must pass)

Run this exact flow with a real or sandbox gateway:

1. Visit Booking page.
2. Select school/class and click Book now.
3. Confirm product is added to cart.
4. Complete checkout.
5. Verify TechiQuiz -> Provisioning Logs has success entries.
6. Verify user account exists (new user) or existing user reused.
7. Verify enrollments and entitlements created.
8. Verify booking confirmation email received (class dates/access/workbook/login).
9. Log in as student and open My Bookings.
10. Verify cancel booking action changes enrollment status to cancelled.
11. Verify cancelled booking deactivates entitlements.
12. Rebook through Booking page and verify new enrollment path.

## 8. Final production hardening

1. Disable maintenance mode only after end-to-end pass.
2. Backup database and files immediately before launch.
3. Enable uptime monitoring.
4. Add transactional email monitoring (delivery/open logs).
5. Add rollback plan:
- Keep previous plugin zip.
- Keep DB snapshot.

## 9. Day-1 operations checklist

1. Watch Provisioning Logs every 2-3 hours.
2. Check failed checkout/order statuses in WooCommerce.
3. Verify students are receiving credentials and booking confirmations.
4. Verify class capacities and mappings remain correct for upcoming dates.

## 10. Known behavior of current release

1. Change booking is implemented as:
- cancel current booking in My Bookings,
- then rebook from Booking calendar.
2. Refund execution remains managed in WooCommerce order workflows.
3. Pricing edits are managed in WooCommerce products (with quick links from TechiQuiz Class Instances).
