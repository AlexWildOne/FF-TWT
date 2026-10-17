# Phase 9BR, Certification Foundation Continuity

Contract: `fieldflow-certification-foundation-continuity-v1`

Release continuity: `SOURCE_RELEASE` is `2.2.588` and `STABLE_RELEASE` is `2.2.589`.

## Purpose

A private plugin update must not make the historical 6B.1 Foundation appear uncertified merely because the currently installed release still needs its own integrity revalidation. Release integrity remains mandatory, but it is owned by the current release gate rather than by the historical certification identity of 6B.1.

## Scope

- Preserve the 6B.1 certified state when its own fingerprint and remote licensing connectivity still match.
- Keep production-manifest integrity and version consistency as mandatory 6B.1 revalidation checks, without using them to erase the historical certification badge.
- Make `Revalidar 6B` explicitly validate and, when eligible, recertify 6B.1 before continuing with 6B.2, 6B.3 and 6B.4.
- Retry the read-only remote licensing probe only when a remote 6B.1 prerequisite is stale or missing.
- Return the exact failed 6B.1 check keys instead of the generic “pré-condições por concluir” message.
- Preserve SHADOW enforcement, no remote runtime blocking and no automatic revocation.

## Safety

No database schema change. No license key rotation. No activation change. No tenant mutation. No production activation. No routing, Google, form, mail, order-suggestion or visit-state behavior change.
