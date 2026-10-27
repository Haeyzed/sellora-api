# Legal documents and acceptance

Legal documents define the current platform terms that a merchant must
accept when registering a store. The landlord owns the documents and the
acceptance records because the relationship is between the merchant and the
platform.

## How publishing works

Platform staff draft and revise a legal document with a type and version.
`PublishLegalDocument` publishes a draft immediately or at a future
effective time. Once published, that version cannot be edited. Publishing a
future version does not make the current version disappear before the new
one takes effect.

## How acceptance is recorded

At the start of store registration, the platform identifies the legal
documents in force and requires acceptance of exactly those versions.
`StartStoreRegistration` stores an acceptance record for each required
document, including who accepted and the available request context. The
acceptances first refer to the pending registration. When email verification
creates the store, the records are attached to the tenant and remain there
as the contract history.

This means the merchant cannot submit an older version after a newer version
has become effective while their registration form was open. It also means
the platform keeps the acceptance record after the temporary registration
row has been retired.

The platform keeps each legal document version with its text, status and
publication and effective dates, and each acceptance with the version, who
accepted it and when. See [Store registration](tenancy.md) for the complete
creation sequence.
