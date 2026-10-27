# Delivery

## What exists today

The Delivery area currently has the store's driver accounts and driver
sign-in only. Field-level detail is in the database's own column comments.
Do not confuse this with Shipping: Shipping configures customer-facing
delivery choices and prices; Orders records shipments.

A driver has a name, a phone number in international format that is unique
in the store and is used to sign in, and a PIN in place of a password. The
PIN is stored only as a hash and is never returned or exported. A driver can
be deactivated, which stops sign-in.

The driver signs in to the driver app with phone and PIN. Repeated wrong
attempts lock sign-in for a while, so a short PIN is hard to guess. A
deactivated driver can't sign in. The access token works only on driver
routes.

## Planned (Step 17, Delivery)

Not built yet:

- staff creating and managing drivers, resetting a PIN and deactivating a
  driver (today a driver can only be created directly in the database, as
  the tests do);
- assigning deliveries to drivers, and a driver seeing only their own;
- delivery status updates (picked up, on the way, delivered, failed), proof
  of delivery (photo or signature, recipient name) and cash on delivery;
- driver location during an active delivery.

## Action flow and domain handoffs

The current Delivery action is `SignInDriver`: it checks the driver's
phone/PIN, applies temporary lockout behavior for repeated incorrect
credentials, rejects deactivated drivers, records the successful sign-in
time, and issues a driver-scoped access token.

Orders emits shipment lifecycle events and its `MoveShipment` action changes
shipment state and commits stock at the applicable handoff point. However,
the current Delivery area has no shipment listener, assignment action, or
driver-to-shipment workflow. The `ShipmentCreated` event description
mentions a future Delivery assignment, but that behavior should not be
treated as implemented. See [Shipping](shipping.md) for customer-facing
delivery choices and [Sellora business flows](business-flows.md) for the
current fulfillment path.
