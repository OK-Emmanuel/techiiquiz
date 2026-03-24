# TechiQuiz Booking System Specification v1

**Date Created:** 2026-03-21  
**Phase:** Phase 4 (Credential Automation) + Phase 5 (Booking Integration)  
**Status:** SPECIFICATION (ready for implementation)

---

## Executive Summary

This spec defines how customers purchase classes via WooCommerce, receive WordPress accounts, and gain access to study materials (quizzes + workbooks) based on their enrollment.

**Core Principle:** Use WooCommerce for payment only. Everything else (booking, entitlements, access control, expiry) lives in the TechiQuiz plugin.

---

## 1. Terminology & Concepts

### Class vs Class Instance
- **Class** = A recurring course (e.g., "Drilling Supervisor Day 1")
  - Has: name, course code, workbook URL
  - Is: a template

- **Class Instance** = A specific offering on specific dates (e.g., "Drilling Supervisor Day 1, April 6-9, 2026")
  - Has: start date, end date, max capacity
  - Maps to: one WooCommerce product (so customers can buy that specific date)

### Entitlement
An entitlement grants access to a specific resource for a specific user within a specific time window.

- **Resource types:** "quiz" or "workbook"
- **Time window:** `access_start` to `access_end`
- **Status:** active (current date is within window) or expired

### Enrollment
A record that a customer bought a specific class instance.

- Links: user + class instance + WooCommerce order
- Used to: calculate which entitlements this user gets

---

## 2. Data Model

### Core Tables (Custom)

#### `tq_classes`
The class template.

```sql
CREATE TABLE tq_classes (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,              -- "Drilling Supervisor Day 1"
  course_code VARCHAR(50) NOT NULL,        -- "DR1"
  description TEXT,
  workbook_url VARCHAR(500),               -- PDF URL or file path
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `tq_class_instances`
Individual offerings (booking calendar).

```sql
CREATE TABLE tq_class_instances (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  class_id BIGINT NOT NULL,
  woocommerce_product_id BIGINT NOT NULL,  -- Which product to buy this instance
  start_date DATE NOT NULL,                 -- Monday
  end_date DATE NOT NULL,                   -- Thursday
  max_capacity INT DEFAULT 12,
  current_enrollment INT DEFAULT 0,        -- Manual counter (or computed at runtime)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (class_id) REFERENCES tq_classes(id),
  INDEX idx_dates (start_date, end_date),
  INDEX idx_product (woocommerce_product_id)
);
```

#### `tq_enrollments`
A purchase record linking customer + class instance + order.

```sql
CREATE TABLE tq_enrollments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,                 -- WordPress user
  class_instance_id BIGINT NOT NULL,
  woocommerce_order_id BIGINT NOT NULL,    -- On order_complete, we create this
  enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
  
  FOREIGN KEY (user_id) REFERENCES wp_users(ID),
  FOREIGN KEY (class_instance_id) REFERENCES tq_class_instances(id),
  INDEX idx_user (user_id),
  INDEX idx_class_instance (class_instance_id),
  INDEX idx_order (woocommerce_order_id),
  UNIQUE idx_unique_per_instance (user_id, class_instance_id)
);
```

#### `tq_entitlements`
Access permissions for a user to a resource within a time window.

```sql
CREATE TABLE tq_entitlements (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  enrollment_id BIGINT NOT NULL,
  resource_type ENUM('quiz', 'workbook') NOT NULL,
  access_start DATE NOT NULL,              -- When access grants (e.g., class start)
  access_end DATE NOT NULL,                -- When access expires (e.g., class end + 45 days)
  is_active BOOLEAN DEFAULT TRUE,          -- Soft delete / deactivate flag
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (enrollment_id) REFERENCES tq_enrollments(id),
  INDEX idx_user_resource (enrollment_id, resource_type),
  INDEX idx_access_window (access_start, access_end)
);
```

#### `tq_provisioning_logs` (Optional, for debugging)
Audit trail when orders complete and we provision access.

```sql
CREATE TABLE tq_provisioning_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  woocommerce_order_id BIGINT NOT NULL,
  user_id BIGINT,
  class_instance_id BIGINT,
  action VARCHAR(50) NOT NULL,             -- 'user_created', 'enrollment_created', 'entitlements_created'
  status ENUM('success', 'error') DEFAULT 'success',
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_order (woocommerce_order_id),
  INDEX idx_user (user_id)
);
```

### WordPress Native (No changes)
- `wp_users` – Standard WordPress user accounts
- `wp_usermeta` – User metadata (if needed for custom fields)
- `wp_posts` (post_type='shop_order') – WooCommerce orders

---

## 3. Workflows

### Admin Workflow: Set Up a Class

**Step 1: Create Class Template**
```
Admin → TechiQuiz Admin → Classes → Add New
├─ Name: "Drilling Supervisor Day 1"
├─ Course Code: "DR1"
├─ Workbook URL: "https://s3.../drilling-supervisor-workbook.pdf"
└─ Save
```

**Step 2: Create Class Instance (Booking Date)**
```
Admin → TechiQuiz Admin → Class Dates → Add New Instance
├─ Select Class: "Drilling Supervisor Day 1"
├─ Start Date: 2026-04-06
├─ End Date: 2026-04-09
├─ Max Capacity: 12
├─ Select WooCommerce Product: "[Drilling Supervisor] April 6-9, 2026 — $150"
└─ Save
  → System calculates access end = 2026-05-25 (end_date + 45 days)
```

**Result:**
- `tq_classes` has one row (DR1, Drilling Supervisor Day 1)
- `tq_class_instances` has one row (Apr 6-9, capacity 12, linked to WooCommerce product 99)
- When customer buys product 99 on WooCommerce, webhook fires → we provision access

---

### Customer Workflow: Purchase & Access

```
┌─ Customer visits shop
│  └─→ Sees "Drilling Supervisor Day 1 — April 6-9, 2026 — $150"
│      (This is the WooCommerce product created by admin)
│
├─ Customer adds to cart & checks out
│  └─→ Pays via Stripe/PayPal
│
├─ WooCommerce order completes
│  └─→ Webhook fires: POST /wp-json/tq/v1/webhook/order-completed
│
├─ TechiQuiz Plugin Webhook Handler
│  ├─ Fetch order details from WooCommerce
│  ├─ Identify which product was purchased (e.g., product 99)
│  ├─ Find tq_class_instances row where woocommerce_product_id = 99
│  │  └─→ Get class_instance_id, start_date, end_date
│  │
│  ├─ Check if user exists in WordPress
│  │  ├─ If NO:
│  │  │  ├─ Create WordPress user with email & temp password
│  │  │  ├─ Email temp credentials to customer
│  │  │  └─ user_id = newly created ID
│  │  └─ If YES: use existing user_id
│  │
│  ├─ Create tq_enrollments row
│  │  └─ {user_id, class_instance_id, order_id, enrollment_date=NOW(), status='active'}
│  │
│  ├─ Create 2x tq_entitlements rows
│  │  ├─ {enrollment_id: X, resource_type: 'quiz', access_start: start_date, access_end: end_date+45days}
│  │  └─ {enrollment_id: X, resource_type: 'workbook', access_start: start_date, access_end: end_date+45days}
│  │
│  └─ Log to tq_provisioning_logs
│
├─ Customer receives email
│  └─→ "Your access is ready! Login here: [link]
│       Username: [generated]
│       Temporary Password: [temp]
│       Please log in and change your password."
│
└─ Customer logs in to WordPress
   └─→ Can now see:
       ├─ Quiz shortcode for "Drilling Supervisor Day 1"
       ├─ Workbook download link
       └─ (Because we check entitlements before rendering these)
```

---

### Access Control: Quiz Shortcode

When student accesses quiz shortcode:

```php
// Pseudo-code for shortcode handler
function render_quiz_shortcode($atts) {
  $set_id = $atts['set']; // e.g., "dr1_practice"
  $user_id = get_current_user_id();
  
  // Get the class from set_id
  $class = get_class_by_set($set_id);
  
  // Check: Does user have active 'quiz' entitlement for this class?
  $has_access = user_has_entitlement($user_id, $class->id, 'quiz');
  
  if (!$has_access) {
    return '<p>Your access is not yet available or has expired.</p>';
  }
  
  // Render quiz
  return render_quiz_app($set_id);
}
```

**Entitlement Check Logic:**
```sql
SELECT e.* FROM tq_entitlements e
JOIN tq_enrollments enr ON e.enrollment_id = enr.id
WHERE enr.user_id = $user_id
  AND e.resource_type = 'quiz'
  AND DATE(NOW()) >= e.access_start
  AND DATE(NOW()) <= e.access_end
  AND e.is_active = TRUE
LIMIT 1;
```

If result: access granted.  
If no result: access denied.

---

### Expiry Logic

**When quizzes/workbooks "expire":**

On any access check, calculate:
```
today >= access_end → EXPIRED (deny access)
today < access_start → NOT_YET_AVAILABLE (deny access)
access_start <= today < access_end → ACTIVE (allow access)
```

**No background job needed.** Access is evaluated at runtime on each request.

**Example:**
- Class: April 6-9, 2026
- Access window: April 6 – May 25, 2026 (45 days)
- May 26, 2026: Access denied automatically (next login check fails)

---

## 4. WooCommerce Integration: Webhook Handler

### Webhook Trigger
**Event:** Order Status Changed to "Completed"  
**Fired by:** WooCommerce order completion (payment confirmed)  
**Endpoint:** `/wp-json/tq/v1/webhook/order-completed`

### Request Payload (from WooCommerce)
```json
{
  "order_id": 12345,
  "customer_id": 67,
  "customer_email": "student@example.com",
  "line_items": [
    {
      "product_id": 99,
      "quantity": 1
    }
  ],
  "order_date": "2026-03-21T14:30:00"
}
```

### Handler Pseudocode
```php
function handle_woocommerce_order_completed($request) {
  $order_id = $request['order_id'];
  $woo_customer_email = $request['customer_email'];
  $woo_product_id = $request['line_items'][0]['product_id'];
  
  // Step 1: Find class instance for this product
  $class_instance = $wpdb->get_row(
    "SELECT * FROM tq_class_instances WHERE woocommerce_product_id = $woo_product_id"
  );
  if (!$class_instance) {
    log_error("Product $woo_product_id not mapped to any class instance");
    return error_response("Product not found");
  }
  
  // Step 2: Find or create WordPress user
  $user = get_user_by('email', $woo_customer_email);
  if (!$user) {
    $temp_password = wp_generate_password(12, true);
    $user_id = wp_create_user($woo_customer_email, $temp_password, $woo_customer_email);
    // Email temp credentials
    send_onboarding_email($user_id, $woo_customer_email, $temp_password);
  } else {
    $user_id = $user->ID;
  }
  
  // Step 3: Create enrollment
  $enrollment_id = $wpdb->insert('tq_enrollments', [
    'user_id' => $user_id,
    'class_instance_id' => $class_instance->id,
    'woocommerce_order_id' => $order_id,
    'enrollment_date' => current_time('mysql'),
    'status' => 'active'
  ]);
  
  // Step 4: Create entitlements
  $access_start = $class_instance->start_date;
  $access_end = date('Y-m-d', strtotime($class_instance->end_date . ' +45 days'));
  
  $wpdb->insert('tq_entitlements', [
    'enrollment_id' => $enrollment_id,
    'resource_type' => 'quiz',
    'access_start' => $access_start,
    'access_end' => $access_end,
    'is_active' => 1
  ]);
  
  $wpdb->insert('tq_entitlements', [
    'enrollment_id' => $enrollment_id,
    'resource_type' => 'workbook',
    'access_start' => $access_start,
    'access_end' => $access_end,
    'is_active' => 1
  ]);
  
  // Step 5: Log
  $wpdb->insert('tq_provisioning_logs', [
    'woocommerce_order_id' => $order_id,
    'user_id' => $user_id,
    'class_instance_id' => $class_instance->id,
    'action' => 'provisioning_complete',
    'status' => 'success'
  ]);
  
  return success_response("Entitlements provisioned");
}
```

### Webhook Registration
```php
// In plugin activation or via admin UI
add_action('woocommerce_order_status_completed', 'trigger_tq_webhook');

function trigger_tq_webhook($order_id) {
  $order = wc_get_order($order_id);
  
  $payload = [
    'order_id' => $order_id,
    'customer_id' => $order->get_customer_id(),
    'customer_email' => $order->get_billing_email(),
    'line_items' => $order->get_items(),
    'order_date' => $order->get_date_created()->format('c')
  ];
  
  do_action('tq_order_completed', $payload);
}
```

---

## 5. Admin Features

### Admin Dashboard: Class Management
```
TechiQuiz Admin Menu
├─ Classes
│  ├─ List all classes with creation date
│  ├─ Add/Edit/Delete classes
│  └─ Each row shows: Name, Code, Workbook URL, # instances
│
├─ Class Dates (Instances)
│  ├─ List all instances with dates & capacity
│  ├─ Add/Edit/Delete instances
│  ├─ Map instance to WooCommerce product (dropdown)
│  └─ Each row shows: Class, Start, End, Capacity, Enrollments, Product ID
│
└─ Enrollment Reports
   ├─ View roster for specific class instance
   ├─ Show: User name, email, enrollment date, entitlement status
   ├─ Export to CSV
   └─ Optional: Delete enrollment (requires confirmation)
```

### Admin Settings: Question Limits
```
TechiQuiz Settings → Question Limits
├─ Questions in Study Mode: [100] (admin can edit)
├─ Questions in Practice Mode: [35] (admin can edit)
└─ Save
  → Stored in wp_options (tq_study_limit, tq_practice_limit)
  → Quiz payloads use these values at runtime
```

---

## 6. Public-Facing Features

### Quiz Shortcode (Existing, Access-Protected)
```
[tq_quiz set="dr1_practice"]

When rendered:
├─ Check user has 'quiz' entitlement
├─ Check entitlement is active (access_start <= today <= access_end)
├─ If both true: render quiz
└─ If either false: show "Access not available"
```

### Workbook Download Link
```
[tq_workbook class="dr1"]

When rendered:
├─ Get class workbook_url from tq_classes
├─ Check user has 'workbook' entitlement
├─ Check entitlement is active
├─ If both true: show download button -> redirect to PDF
└─ If either false: show "Access not available"
```

---

## 7. Edge Cases & Error Handling

### Edge Case 1: Customer buys same class instance twice
**Scenario:** Customer accidentally purchases the class twice.  
**Handling:** DB constraint `UNIQUE (user_id, class_instance_id)` prevents duplicate enrollment. Webhook returns error on second attempt. Manual override: admin can delete & recreate enrollment if refund issued.

### Edge Case 2: WooCommerce product not mapped
**Scenario:** Admin creates WooCommerce product but forgets to map it to a class instance.  
**Handling:** Webhook logs error. No entitlements created. Admin receives provisioning log alert. Customer contacts support.

### Edge Case 3: Customer's access expires mid-session
**Scenario:** Customer starts a quiz on day 45, session spans into day 46 (expired).  
**Handling:** Entitlements checked at session start. If expired at start, quiz blocked. No in-session expiry check (keep it simple for MVP). If customer completes session before expiry, scores are saved.

### Edge Case 4: Admin deletes class instance users are enrolled in
**Scenario:** Admin deletes a class instance that has active enrollments.  
**Handling:** Foreign key CASCADE delete will delete enrollments and entitlements. All affected students lose access immediately. Admin warned: "X enrollments will be deleted."

### Edge Case 5: Customer email already in system but different identity
**Scenario:** Email exists in WordPress under different account, customer attempts to create new account.  
**Handling:** Webhook finds existing user_id. Creates enrollment under existing account. Existing user now has access to new class. Note: clarify with client if this is desired or if we should prevent it.

### Error Logging
All webhook errors logged to:
- `tq_provisioning_logs` (status='error', message=description)
- WordPress error log (via `error_log()` call)
- Admin notice (optional dashboard widget)

---

## 8. Implementation Checklist

This breaks down Phase 4 + 5 into implementable steps:

### Phase 4a: Database Schema & Activation
- [ ] Create database migration in activator class
- [ ] Create `tq_classes`, `tq_class_instances`, `tq_enrollments`, `tq_entitlements`, `tq_provisioning_logs` tables
- [ ] Add indexes for performance
- [ ] Test: fresh activation creates tables with no errors

### Phase 4b: Admin UI - Classes
- [ ] Build admin page: Classes list
- [ ] Build form: Add/Edit class
- [ ] Build form: Delete class (with confirmation)
- [ ] Test: CRUD operations work end-to-end

### Phase 4c: Admin UI - Class Instances
- [ ] Build admin page: Class Instances list
- [ ] Build form: Add/Edit instance (with WooCommerce product dropdown)
- [ ] Build form: Delete instance (with confirmation + warning about cascading deletes)
- [ ] Test: Can map product to instance

### Phase 4d: Webhook Handler
- [ ] Create REST endpoint: `/wp-json/tq/v1/webhook/order-completed`
- [ ] Implement handler logic (user create/find, enrollment create, entitlements create)
- [ ] Add logging to provisioning_logs table
- [ ] Add email onboarding template
- [ ] Test: Manually fire webhook, verify entitlements created

### Phase 4e: Access Control - Quiz
- [ ] Modify quiz shortcode to check entitlements before rendering
- [ ] Create helper function: `user_has_active_entitlement($user_id, $class_id, $resource_type)`
- [ ] Return "Access not available" message if no entitlement
- [ ] Test: User without entitlement blocked; user with entitlement allowed

### Phase 4f: Access Control - Workbook
- [ ] Create workbook shortcode: `[tq_workbook class="dr1"]`
- [ ] Check entitlements before rendering download
- [ ] Test: Same as quiz

### Phase 5a: Admin UI - Reports
- [ ] Build admin page: Enrollment roster (filterable by instance)
- [ ] Add CSV export button
- [ ] Test: Export contains correct data

### Phase 5b: Admin UI - Question Settings
- [ ] Add Settings submenu under TechiQuiz
- [ ] Build form: Question limits (study / practice)
- [ ] Save to `wp_options`
- [ ] Modify quiz service to read from options (not hardcoded)
- [ ] Test: Quiz reflects admin-set limits

### Phase 5c: Integration Testing
- [ ] Full flow: Admin sets up class → Customer buys → Access granted → Can take quiz → Access expires
- [ ] Test all edge cases from section 7
- [ ] Test webhook failure recovery
- [ ] Test duplicate purchases

### Phase 5d: Documentation & Handoff
- [ ] Document API endpoints for external integrations
- [ ] Document webhook payload format
- [ ] Create admin quick-start guide
- [ ] Prepare support runbook for common issues

---

## 9. Notes & Future Considerations

### Not in Scope (MVP)
- Waitlist (full capacity handling → sorry, sold out)
- Refunds (customer initiated refund flow)
- Instructor dashboard (just admin for now)
- Custom entitlement rules (always 45 days, always both resources)
- Multi-day session spanning (sessions start/end same day)

### Nice-to-Haves (Post-MVP)
- SMS notifications for class start/reminders
- Automatic entitlement extension (if customer requests)
- Cohort dashboards (class progress analytics)
- Custom access windows (some classes 60 days, others 30)
- Bulk user imports (admin uploads CSV of users to grant access)

### Questions for Client (if phasing later)
1. If customer buys class but can't attend, can they transfer to another date?
2. Do we honor refunds? If so, what's the timeline?
3. Can one user have multiple active enrollments simultaneously?
4. Should we auto-email access reminders X days before expiry?
5. Do instructors need real-time class roster visibility, or is post-class report OK?

---

## 10. File Structure (Implementation Artifacts)

```
techiquiz/
├─ includes/
│  ├─ class-tq-db.php (add schema migration methods)
│  ├─ class-tq-booking.php (NEW: webhook handler + entitlement logic)
│  ├─ class-tq-enrollment-service.php (NEW: enrollment CRUD)
│  └─ class-tq-admin-booking.php (NEW: admin pages for classes/instances)
│
├─ admin/
│  ├─ class-tq-admin-booking-classes.php (NEW: classes list/form)
│  ├─ class-tq-admin-booking-instances.php (NEW: instances list/form)
│  ├─ class-tq-admin-enrollment-reports.php (NEW: roster/export)
│  └─ css/
│     └─ admin-booking.css (NEW: styling for booking admin pages)
│
├─ templates/
│  └─ (updated quiz/workbook shortcodes to check entitlements)
│
└─ docs/
   └─ booking-spec.md (THIS FILE)
```

