# Identity

This product guide explains the people who operate a store dashboard and the
store's ownership handoff. This is separate from customer sign-in and driver
sign-in. Field-level detail is in the database's own column comments, not
here.

## Staff accounts

A staff member signs in with their email and a password, which is stored
only as a hash and never shown. An account can be deactivated, which stops
sign-in and rejects its tokens on the next request. Two-factor
authentication is available to staff, and the owner can require it; the
authenticator secret is stored encrypted and recovery codes only as hashes.

## Roles and permissions

Roles and permissions decide what a staff member may do. Permissions are
defined in code and synced into every store. Role names are unique ignoring
case. Besides roles, a staff member can be given extra permissions directly,
and the API shows where each of their permissions comes from. Nobody can
grant a permission they don't hold or change their own access. Permission
changes are audited, without passwords or two-factor secrets.

## Invitations

Staff join only by invitation: an emailed one-time link, stored only as a
hash, that expires after 7 days and carries the roles to grant. The person
chooses their own password when accepting. An invitation can be resent (the
old link stops working) or cancelled; an expired or cancelled one can't be
used.

## Ownership transfer

The owner starts a transfer to another active staff member and chooses which
roles they keep afterwards. Only one transfer can be pending per store.
Nothing changes until the chosen person accepts, while signed in, within
72 hours; the owner can cancel before then.

## Example

An owner invites a manager by email and selects the manager role. The manager
accepts the secure invitation and signs in to the store dashboard. The owner
can later change the role or deactivate the account. If ownership changes,
the current owner proposes another staff account; the new owner must accept,
and the old owner's retained roles follow the transfer configuration.

Staff accounts use the `staff` guard and are store-local. Customer accounts
use the `customer` guard; delivery drivers use the separate `driver` guard.
Two-factor authentication and permissions apply to staff, not customers.

## Action flow and domain handoffs

`InviteStaffMember` checks the inviter's authority to grant the requested
roles, the store's staff-account limit, and whether that email already has
an account or pending invitation. It emails a one-time invitation and records
the selected roles. `AcceptStaffInvitation` locks and consumes that
invitation, creates the staff account with the invitee's own password,
applies the roles and signs the new member in. Starting an invitation alone
does not create a staff account.

Staff authorization is the control boundary for merchant operations in
Catalog, Inventory, Customers, Orders, Payments, Shipping and Settings.
Identity also owns the owner-transfer workflow; the proposed owner must
accept before ownership changes. It does not authenticate shoppers or
drivers, which use Customers and Delivery respectively. See
[Sellora business flows](business-flows.md) for where staff actions enter
the order lifecycle.
