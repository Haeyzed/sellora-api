# Delivery

The Delivery area runs a store's own delivery fleet: driver accounts, giving
shipments to drivers, the driver app's view of its deliveries, proof of
delivery, cash collected on delivery and the cash each driver holds, driver
location while a delivery is under way, and the buyer following it.
Field-level detail is in the database's own column comments.

Don't confuse it with Shipping: [Shipping](shipping.md) configures the
delivery choices shoppers see and what they cost, and [Orders](orders.md)
records shipments. Delivery only moves "delivered by the store" shipments
along, through Orders' own actions.

## A delivery from start to finish

1. The shopper picks a shipping method delivered by the store (own
   delivery). Staff create the shipment in Orders as usual.
2. Staff with `delivery.manage` give the pending shipment to one of the
   store's active drivers. From then on, staff can't mark it shipped or
   delivered in Orders: the driver's delivery moves it.
3. The driver sees the delivery in the driver app: where to collect it, what
   to take, where it goes, the recipient's contact, and how much cash to
   collect. They pick it up: the shipment ships and its stock leaves the
   shelf.
4. They set off. While the delivery is picked up or on the way, the app
   sends the driver's location; once it is on the way, the buyer can follow
   it (the driver's first name and latest position).
5. They hand it over with the recipient's name and a photo, a signature or
   both. For cash on delivery they say how much they collected: it pays the
   order and goes into their cash ledger. The shipment is delivered; the
   order completes once it is paid.
6. Or the delivery fails, with a reason. The driver keeps the goods until
   staff send them out again or take them back (see "When a delivery
   fails").
7. Later, staff record the cash each driver hands over.

## Drivers

A driver has a name, a phone number in international format that is unique
in the store and is used to sign in, and a 6-digit PIN in place of a
password. The PIN is stored only as a hash and is never returned or
exported. A driver belongs to one store; someone who drives for two stores
has two accounts. Active drivers count towards the plan's `drivers` limit
(the free plan allows 2; a plan without the limit allows none).

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

### Signing in

Sign-in is phone plus PIN. Repeated wrong attempts lock sign-in for that
phone in that store for a while, so a short PIN is hard to guess. A
deactivated driver can't sign in. The access token works only on driver
routes.

### Deactivating and changing drivers

- Deactivating a driver signs them out everywhere at once, voids any setup
  code and frees their place in the plan limit. Reactivating needs room in
  the limit.
- A driver who has goods (a delivery picked up, on the way, or failed and
  not yet resolved) can't be deactivated or erased; deliveries only assigned
  to them are taken back.
- A new phone number clears the PIN and signs the driver out, so staff issue
  a new code.

## Deliveries

A delivery is one attempt by one driver to take one shipment to its buyer.
A shipment has at most one delivery under way at a time, which the database
guarantees.

| Status | Who moves it there | What else happens |
|---|---|---|
| assigned | staff (`delivery.manage`) | staff can no longer ship or deliver the shipment in Orders |
| picked up | the driver, or staff for them | the shipment ships: its stock leaves the shelf |
| on the way | the driver, or staff for them | the buyer can follow it |
| delivered | the driver, or staff for them | needs who took the goods and proof; any cash collected pays the order; the shipment is delivered |
| failed | the driver, or staff for them | needs a reason; the driver keeps the goods |
| cancelled | staff, before pick-up only | the shipment can be given to a driver again |

- Staff move a delivery for a driver who can't use the app, such as one
  whose phone died; the change is recorded as theirs. Every change, and who
  made it, is kept in an append-only history.
- Before pick-up, staff can cancel a delivery or give it to another driver.
  Cancelling the shipment, or its order, takes the delivery back.
- Marking a delivery delivered, and staff status changes, need an
  `Idempotency-Key`, so a retried request never records cash twice.
- A driver sees only their own deliveries; another driver's is not found.
  The recipient's address and phone are shown only while the delivery is
  under way. A driver never sees prices or totals, the customer's email or
  account, other deliveries or store settings.

### When a delivery fails

Failing needs a reason: recipient not available, address not found, refused,
unsafe, recipient asked to reschedule, or other (with a note). The goods stay
with the driver until staff choose one of two:

- **Send it out again:** the same driver, who still has the goods, tries
  again as a new attempt, starting picked up.
- **Take the goods back:** the driver brought them back. The shipment is
  returned: its stock is back on the shelf, still held for the order. An
  unpaid order's payment deadline starts again from the return.

There is no direct swap of drivers while the first one has the goods. To
give a failed delivery to a different driver, staff take the goods back
first, then create a new shipment for the order in Orders and give that to
the other driver. There is no maximum number of attempts and nothing returns
on its own: staff decide.

## Proof of delivery

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

## Cash held by drivers

For an order paid in cash on delivery, the driver app shows how much cash to
collect. Handing the delivery over needs the amount collected: it pays the
order's pending cash payment, confirmed by the driver, and adds the cash to
the driver's cash ledger, in one transaction with the delivery. Less leaves
the rest to collect; more is recorded and owed back. A test order is never
paid in cash, so its cash never enters the ledger.

- The ledger is append-only: a trigger refuses edits and deletions.
- What a driver holds in a currency is what they collected in it less what
  they handed over in it, in minor units. Currencies are never added
  together. Staff with `delivery.view` see each driver's balances and
  entries; a driver sees their own.
- Staff with `delivery.cash.manage` record cash a driver hands over (with an
  `Idempotency-Key`). It can never be more than the driver holds in that
  currency, checked under the driver's lock, so two handovers at once can't
  take the same cash.

## Driver location and following a delivery

The driver app sends where the driver is while a delivery is picked up or on
the way, at most 12 points a minute per driver. At any other time, or for
another driver's delivery, nothing is stored: a delivery that isn't under
way answers 409 `delivery_not_under_way`, and a point sent while the
delivery is being delivered waits for it and is then refused, so no point is
ever kept after a delivery ends.

- Staff with `delivery.view` see a delivery's whole trail, oldest first.
- The order's buyer (a signed-in customer, or a guest with the order's token
  in the `X-Order-Token` header, never the URL) polls the order's delivery
  tracking. It shows only deliveries on the way right now, each with the
  driver's first name and latest point: never the trail, the driver's full
  name or phone, or proof of delivery. It is limited per customer or order
  token, under a ceiling per address.

## Privacy and retention

**Drivers.** Staff answer a driver's data-protection request: see everything
held about them (their account, their deliveries without anything about the
buyers, the status changes they made, their locations and their cash
ledger), or erase them with their own password. Erasing clears the name,
phone and PIN and deletes their locations; the row, deliveries and cash
ledger stay as business records, still pointing at the erased driver.

**Buyers.** When an order is anonymised (its customer or guest buyer is
erased, or its retention ends), its deliveries lose who took the goods,
failure notes, proof of delivery and the driver's locations, in the same
erasure run; the deliveries keep their statuses and times and are marked
anonymised. Proof files are deleted once that run commits, so one that
rolls back loses nothing. A delivered delivery without who took the goods is
refused by the database unless it has been anonymised.

| What | Kept for | Setting |
|---|---|---|
| Driver locations | 30 days after the delivery ends (delivered, or failed and resolved) | `RETENTION_DRIVER_LOCATIONS_DAYS` |
| Proof of delivery files | 180 days after the delivery | `RETENTION_PROOF_OF_DELIVERY_DAYS` |
| Used or expired setup codes | 1 day after they expire | `RETENTION_DRIVER_SETUP_CODES_DAYS` |
| Deliveries, their history, the cash ledger | as business records | — |

Every Delivery table and column is classified for the store export (PIN and
code hashes are left out as secrets); proof files are in it until purged.

## Permissions

| Permission | Lets staff |
|---|---|
| `drivers.manage` | add, change, deactivate and reactivate drivers, issue setup codes, and answer a driver's data-protection request (export or erase) |
| `delivery.view` | see drivers, deliveries, their proof and locations, and drivers' cash balances and ledgers |
| `delivery.manage` | give shipments to drivers, give a delivery to another driver, cancel, move a delivery for its driver, send it out again, take the goods back |
| `delivery.cash.manage` | record cash a driver hands over |

A driver's own access is by assignment, never by staff permissions.

## Endpoints

Every endpoint has a cURL example in [API testing](api-testing.md).

| Who | Endpoints |
|---|---|
| Staff, under `/api/v1/staff/delivery` | `drivers` (list, add, show, change), `drivers/{driver}/deactivate`, `/reactivate`, `/setup-codes`, `/personal-data`, `/erasure`, `/cash`, `/cash-entries`, `/cash-handovers`; `deliveries` (list, show), `shipments/{shipment}/deliveries`, `deliveries/{delivery}/reassign`, `/cancel`, `/status`, `/retry`, `/return`, `/proof/{photo or signature}`, `/locations` |
| Driver app, under `/api/v1/driver` | `auth/tokens`, `auth/pin`, `auth/me`, `auth/tokens/current`; `deliveries` (list, show), `deliveries/{delivery}/pick-up`, `/on-the-way`, `/delivered`, `/failed`, `/locations`; `cash` |
| Buyer | `customer/orders/{order}/delivery-tracking`, `guest/orders/{order}/delivery-tracking` |

## Configuration

| Setting | Default | What it does |
|---|---|---|
| `PROOF_OF_DELIVERY_DISK` | `local` | the private disk proof is kept on |
| `RATE_LIMIT_DRIVER_LOCATION` | 12 | locations a driver may send a minute |
| `RATE_LIMIT_DELIVERY_TRACKING` | 30 | tracking polls a minute per customer or order token |
| `RATE_LIMIT_DELIVERY_TRACKING_IP_CEILING` | 300 | tracking polls a minute per address |
| `RETENTION_DRIVER_LOCATIONS_DAYS` | 30 | see Privacy and retention |
| `RETENTION_PROOF_OF_DELIVERY_DAYS` | 180 | see Privacy and retention |
| `RETENTION_DRIVER_SETUP_CODES_DAYS` | 1 | see Privacy and retention |

## Action flow and domain handoffs

`CreateDriver` checks the plan limit under a lock, creates the driver
without a PIN and calls `IssueDriverSetupCode`. `ChooseDriverPin` checks the
code under a lock on it, counting wrong tries, then sets the PIN and issues a
driver-scoped access token. `SignInDriver` checks the phone and PIN, applies
the lockout, rejects deactivated drivers and issues a token.

`AssignDriver` gives a pending own-delivery shipment to an active driver.
`MoveDelivery` moves a delivery along and calls Orders' `MoveShipment` (ship
on pick-up, deliver on hand-over); on hand-over it also calls Payments'
`RecordCashCollectedOnDelivery`, writes the driver's cash ledger and keeps
the proof. `ResolveFailedDelivery` sends a failed delivery out again or
returns its shipment (`MoveShipment::returnToStore`, which puts the stock
back through Inventory's `ReturnCommittedStock`). `RecordCashHandover`
records cash a driver hands over; `RecordDriverLocation` keeps a location
point.

Delivery listens to Orders' `ShipmentCancelled` (to take the delivery back)
and `OrderAnonymised` (to clear the buyers' data). Orders asks Delivery,
through its `ShipmentDrivers` contract, whether a driver has a shipment
before staff ship or deliver it. Locks go driver, then delivery, then order,
then shipment, then payment, then the storage limit's advisory lock for
proof of delivery; a location point only locks its delivery, for share.
Delivery events for Messaging come with Step 20. See
[Sellora business flows](business-flows.md) for the whole fulfilment path.
