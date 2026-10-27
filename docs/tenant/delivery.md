# Delivery

## What exists today

The Delivery area has the store's own driver accounts: staff managing them,
and drivers signing in to the driver app. Field-level detail is in the
database's own column comments. Do not confuse this with Shipping: Shipping
configures customer-facing delivery choices and prices; Orders records
shipments.

A driver has a name, a phone number in international format that is unique
in the store and is used to sign in, and a 6-digit PIN in place of a
password. The PIN is stored only as a hash and is never returned or
exported. Active drivers count towards the plan's `drivers` limit (the free
plan allows 2; a plan without the limit allows none).

### Staff never set or know a PIN

Staff with `drivers.manage` add a driver with a name and phone number. The
driver has no PIN yet: the response holds a one-time 8-digit setup code,
shown only then. Staff give it to the driver, who enters their phone, the
code and a PIN of their own choosing in the driver app; that signs them in.

- A code lasts 72 hours, works once, and is voided after 5 wrong tries.
  Wrong, used, expired and missing codes all give the same error.
- Only a keyed hash of the code is stored.
- For a driver who forgot their PIN or lost their phone, staff issue a new
  code. That at once clears the old PIN, signs the driver out everywhere and
  voids any earlier code. Every issued code goes in the activity log.
- Easy PINs (all one digit, or a straight run such as 123456) are refused.
- A signed-in driver can change their PIN with the current one; their other
  devices are signed out.

### Deactivating, changing and erasing drivers

- Deactivating a driver signs them out everywhere at once, voids any setup
  code and frees their place in the plan limit. Reactivating needs room in
  the limit.
- A new phone number clears the PIN and signs the driver out, so staff issue
  a new code.
- Staff answer a driver's data-protection request: see everything held about
  them, or erase them with their own password. Erasing clears the name, phone
  and PIN and keeps the row, so past deliveries still point at someone.

Sign-in is phone plus PIN. Repeated wrong attempts lock sign-in for that
phone in that store for a while, so a short PIN is hard to guess. A
deactivated driver can't sign in. The access token works only on driver
routes.

## Planned (Step 17, Delivery)

Not built yet:

- assigning deliveries to drivers, and a driver seeing only their own;
- delivery status updates (picked up, on the way, delivered, failed), proof
  of delivery (photo or signature, recipient name) and cash on delivery;
- driver location during an active delivery.

## Action flow and domain handoffs

`CreateDriver` checks the plan limit under a lock, creates the driver
without a PIN and calls `IssueDriverSetupCode`. `ChooseDriverPin` checks the
code under a lock on it, counting wrong tries, then sets the PIN and issues a
driver-scoped access token. `SignInDriver` checks the phone and PIN, applies
the lockout, rejects deactivated drivers and issues a token.

Orders emits shipment lifecycle events and its `MoveShipment` action changes
shipment state and commits stock at the applicable handoff point. The
Delivery area has no shipment listener or assignment workflow yet. See
[Shipping](shipping.md) for customer-facing delivery choices and
[Sellora business flows](business-flows.md) for the current fulfillment path.
