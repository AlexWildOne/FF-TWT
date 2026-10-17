# FieldFlow 9D0A: historical 9CS certification recovery

**Certified predecessor:** 2.2.623. The 2.2.624 version was installed but its certification failed (9CS obsolete literal).

The attempted 2.2.624.1 candidate was validated locally but never published: the live Azure Publisher rejected the four-segment version before upload. Do not retry it. This artifact is historical documentation of the preserved 9CS runtime gate change.

The active 9CS gate now verifies the current locked persistence path with $row and persist(). The old source marker exists only in a non-executable compatibility comment. All budget reconciliation, auditing, manual inputs and safety checks remain unchanged.

**Successor:** 2.2.625 (9D0B) is the publisher-compatible certification recovery. The 2.2.624.1 build has never been certified, nor promoted to Stable.
