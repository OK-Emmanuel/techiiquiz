# TechiQuiz Integration Adapter Architecture

Date: 2026-03-04
Status: Approved design baseline for implementation

## 1) Goal

Design TechiQuiz so core quiz features remain stable while third-party systems (payments, booking, memberships) can be swapped with minimal refactoring.

## 2) Design Principles

1. **Core-first domain**: Quiz domain does not import or directly depend on WooCommerce/MemberPress/Bookings APIs.
2. **Ports and adapters**: Third-party plugins are accessed via internal interfaces (ports).
3. **Event-driven integration**: External plugin hooks are translated into TechiQuiz domain events.
4. **Provider registry**: Active integration provider is configured via plugin settings.
5. **Graceful fallback**: Missing provider fails safely with clear admin diagnostics.

## 3) Integration Ports (Interfaces)

Create these interfaces under `includes/integrations/contracts/`:

### Payment Port

`TQ_Payment_Provider_Interface`

- `is_available(): bool`
- `get_order_reference_from_context(array $context): ?string`
- `get_payment_status(string $orderReference): string`
- `get_customer_user_id(string $orderReference): ?int`
- `get_purchased_items(string $orderReference): array`

### Membership/Access Port

`TQ_Access_Provider_Interface`

- `is_available(): bool`
- `user_has_access_to_set(int $userId, int $setId): bool`
- `grant_access_for_entitlement(int $userId, string $entitlementKey): bool`
- `revoke_access_for_entitlement(int $userId, string $entitlementKey): bool`

### Booking Port

`TQ_Booking_Provider_Interface`

- `is_available(): bool`
- `get_upcoming_bookings(int $userId): array`
- `get_booking_details(string $bookingReference): array`
- `attach_learning_resources(string $bookingReference, array $resourceLinks): bool`

## 4) Domain Events

Core plugin emits and listens internally for these events:

- `EnrollmentPurchased`
- `PaymentCompleted`
- `BookingConfirmed`
- `AccessGranted`
- `QuizSessionStarted`
- `QuizSessionCompleted`

Implementation note:
- In WordPress, events can be represented using `do_action('tq/event_name', $payload)`.

## 5) Adapter Implementations (v1)

Under `includes/integrations/providers/`:

1. `class-tq-payment-woocommerce.php`
2. `class-tq-access-memberpress.php`
3. `class-tq-booking-woocommerce-bookings.php`

Each adapter:
- Checks plugin availability with class/function detection.
- Maps external hook payloads into domain events.
- Avoids domain logic (only translation/orchestration).

## 6) Provider Registry

Create `TQ_Provider_Registry` to resolve active providers:

- Reads settings option `tq_active_providers` (e.g., `payment=woocommerce`, `access=memberpress`, `booking=woocommerce_bookings`).
- Instantiates matching adapter classes.
- Returns `NullProvider` when integration is disabled/unavailable.

Null providers should:
- Return deterministic safe defaults (usually deny/empty).
- Log admin notice through diagnostics service.

## 7) Switching Providers Later

To switch booking/payment/access plugin:

1. Install and activate new third-party plugin.
2. Add a new adapter class implementing the same interface.
3. Register it in `TQ_Provider_Registry`.
4. Update provider setting (no core quiz refactor required).
5. Run adapter verification checklist.

## 8) Why Not Build Payments/Booking/Membership In-House

Not recommended for this project stage because:

- Payments require high compliance/security burden.
- Booking engines need substantial edge-case handling (timezones, seat inventory, cancellations, conflicts).
- Membership systems require mature role/capability management and account lifecycle controls.

Better strategy:
- Keep these as replaceable adapters.
- Invest engineering effort in unique quiz domain features.

## 9) Dynamic Quiz Support (Two Excel Sheet Types)

Observed input pattern:
- Sheet A: standard single-choice quiz
- Sheet B: math-style objective questions

Architecture decision:
- Support both via one question model using `question_type`:
  - `single_choice`
  - `objective_math`
- Both are still objective radio-button answers in runtime.
- UI difference is only in prompt formatting and optional equation rendering.

Data model additions:
- `question_type` (required)
- `prompt_format` (`plain|latex|mixed`, optional)
- `prompt_meta` (JSON for structured equation metadata, optional)

Import behavior:
- Add `question_type` and optional `prompt_format` columns.
- If sheet name indicates math type, default `question_type=objective_math`.

## 10) Tailwind UI Strategy

Frontend should be Tailwind-first:

- Use Tailwind utility classes in templates/components.
- Add a plugin-local build pipeline later (`tailwind.config.js`, input CSS, compiled output).
- During early prototype, allow fallback to minimal CSS if Tailwind build is not yet wired.

## 11) Implementation Sequence (Immediate)

1. Create core domain layer (questions, choices, sessions, scoring).
2. Add import parser supporting `single_choice` + `objective_math`.
3. Add provider interfaces + registry scaffolding.
4. Implement v1 adapters for WooCommerce/MemberPress/Bookings.
5. Add diagnostics page for provider health and hook events.

## 12) Adapter Readiness Checklist

- [ ] Interface contracts created for payment/access/booking
- [ ] Provider registry implemented
- [ ] Null providers implemented
- [ ] WooCommerce payment adapter implemented
- [ ] MemberPress access adapter implemented
- [ ] WooCommerce Bookings adapter implemented
- [ ] Event mapping tests completed
- [ ] Provider switching test completed in staging
