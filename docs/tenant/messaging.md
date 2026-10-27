# Messaging

## Planned (Step 20, Messaging, email channel first)

Not built yet: the Messaging domain has no models, actions, controllers or
migrations.

Today each email is sent by the domain whose workflow triggers it, such as
Customers for account verification, Identity for staff invitations or Orders
for order events. Notification providers (SMS, WhatsApp, push) are to be
integrations. Step 20 starts with the email channel; this guide will describe
it once it is built. Until then the cross-domain journeys are in
[Sellora business flows](business-flows.md).
