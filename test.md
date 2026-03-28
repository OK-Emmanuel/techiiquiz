Step 1: Admin Setup
├─ Go to TechiQuiz → Classes
├─ Create Class: "Drilling Supervisor Day 1" (code: DR1)
└─ Go to Class Instances
  ├─ Create Instance: Select class, set dates (e.g., April 6-9, 2026)
  ├─ Set max capacity: 12
  └─ Map to WooCommerce product: Select a product from dropdown

Step 2: Trigger Order
├─ In WooCommerce: Create/complete an order with the mapped product
├─ Include a customer email
└─ Mark order as "Completed"

Step 3: Verify Provisioning
├─ Check WordPress Users: New user should exist (or existing updated)
├─ Query DB: tq_enrollments should have new enrollment record
├─ Query DB: tq_entitlements should have 2 records (quiz + workbook)
├─ Check access_start = class start_date, access_end = class end_date + 45 days
├─ Check email: Customer should receive onboarding email
└─ Check tq_provisioning_logs: Log entries for each action

Step 4: Test Access Control
├─ Place [tq_quiz set="123" mode="study"] shortcode on a page
├─ Log in as the enrolled user
└─ Verify quiz loads (user has active entitlement)

Step 5: Test Entitlement Expiry
├─ Query DB: Manually set entitlement access_end to past date
├─ Refresh quiz page as user
└─ User should see "Your access is not yet available or has expired"