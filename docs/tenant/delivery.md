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

### Deliveries

Staff with `delivery.manage` give one of the store's own deliveries (a
shipment sent with the store's drivers that hasn't left) to an active
driver. A shipment has at most one delivery under way at a time, which the
database guarantees.

- A delivery goes assigned → picked up → on the way → delivered, or failed
  on the way. The driver moves their own deliveries in the driver app; staff
  can move one for a driver who can't. Every change, and who made it, is
  kept in an append-only history.
- Picking up ships the shipment: its stock leaves the shelf. Delivering needs
  the name of who took the goods and proof (below), and delivers the
  shipment.
- Failing needs a reason: recipient not available, address not found,
  refused, unsafe, recipient asked to reschedule, or other (with a note). The
  goods stay with the driver until staff send the delivery out again (the
  same driver, as a new attempt) or take the goods back. Taking them back
  returns the shipment: its stock is back on the shelf, held for the order,
  which can ship again (perhaps with another driver) or be cancelled. An
  unpaid order's payment deadline starts again from the return.
- Before pick-up, staff can cancel a delivery or give it to another driver.
  Cancelling the shipment, or its order, takes the delivery back.
- While a driver has a shipment, staff can't mark it shipped or delivered in
  Orders; without a driver, staff still can.
- A driver who has goods (a delivery picked up, on the way, or failed and not
  yet resolved) can't be deactivated or erased; deliveries only assigned to
  them are taken back.
- A driver sees only their own deliveries: where to collect them, what they
  hold, where they go, and how much cash to collect. The recipient's address
  and phone are shown only while the delivery is under way.

### Proof of delivery

Handing a delivery over always needs a photo of the goods handed over, the
recipient's signature (as an image), or both, sent as multipart form data
with the recipient's name. Staff moving a delivery to delivered for a driver
need the same.

- Images are JPEG, PNG or WebP, never SVG, at most 5 MB and 4096 pixels on
  either side (`config/media.php`). Dimensions are read from the file's
  header before it is ever decoded.
- Each image is decoded and encoded again, which drops its metadata: camera,
  GPS position, time taken, comments. A photo its camera marked as turned is
  turned upright first. It is kept as `photo` or `signature`, never under the
  name it was uploaded with.
- The files are personal data. They go on the store's private disk, count
  towards the plan's storage (checked under its lock, so two uploads can't
  both take the last space), and are saved in the same transaction as the
  delivery: if anything fails, nothing is delivered and no file stays behind.
- Staff with `delivery.view` see which proof a delivery has and fetch each
  file through the API, never cached; there is no public link. Drivers and
  customers never see proof.
- The files are purged 180 days after the delivery
  (`RETENTION_PROOF_OF_DELIVERY_DAYS`); the delivery and who took the goods
  stay. Until then they are in the store export.

### Cash held by drivers

For an order paid in cash on delivery, the driver app shows how much cash to
collect. Handing the delivery over needs the amount collected (and an
`Idempotency-Key`): it pays the order's pending cash payment, confirmed by
the driver, and adds the cash to the driver's cash ledger, in one
transaction with the delivery. Less leaves the rest to collect; more is
recorded and owed back. A test order is never paid in cash, so its cash never
enters the ledger.

- The ledger is append-only: a trigger refuses edits and deletions.
- What a driver holds in a currency is what they collected in it less what
  they handed over in it, in minor units. Currencies are never added
  together. Staff with `delivery.view` see each driver's balances and
  entries; a driver sees their own.
- Staff with `delivery.cash.manage` record cash a driver hands over (with an
  `Idempotency-Key`). It can never be more than the driver holds in that
  currency, checked under the driver's lock, so two handovers at once can't
  take the same cash.
- The ledger is in the driver's personal data export, and is kept as a
  business record when the driver is erased, still pointing at them.

Sign-in is phone plus PIN. Repeated wrong attempts lock sign-in for that
phone in that store for a while, so a short PIN is hard to guess. A
deactivated driver can't sign in. The access token works only on driver
routes.

## Planned (Step 17, Delivery)

Not built yet:

- driver location during an active delivery, and customers following it.

## Action flow and domain handoffs

`CreateDriver` checks the plan limit under a lock, creates the driver
without a PIN and calls `IssueDriverSetupCode`. `ChooseDriverPin` checks the
code under a lock on it, counting wrong tries, then sets the PIN and issues a
driver-scoped access token. `SignInDriver` checks the phone and PIN, applies
the lockout, rejects deactivated drivers and issues a token.

Orders emits shipment lifecycle events and its `MoveShipment` action changes
shipment state and commits stock at the applicable handoff point. Delivery
calls it through `MoveDelivery` (ship on pick-up, deliver on delivery) and
`ResolveFailedDelivery` (return to store), and cancels a delivery when its
shipment is cancelled. Orders asks Delivery, through its `ShipmentDrivers`
contract, whether a driver has a shipment before staff ship or deliver it.
Locks go driver, then delivery, then order, then shipment, then payment,
then the storage limit's advisory lock for proof of delivery. See
[Shipping](shipping.md) for customer-facing delivery choices and
[Sellora business flows](business-flows.md) for the current fulfillment path.
