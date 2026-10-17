# FieldFlow Phase 6B.3, Governance, Rotation, Limits & Shadow Enforcement

Release: 2.2.384
Licensing backend target: 1.0.4

## Objective

Extend the certified Phase 6B.2 lifecycle with a central governance control plane for licences and installations, without enabling runtime blocking. Phase 6B.3 synchronizes plan policy to the Azure licensing service, exposes auditable suspend/resume/revoke/rotate operations, keeps the maximum installation ceiling enforced by the remote activation service, and evaluates future enforcement decisions in SHADOW.

## Safety state

The following controls remain deliberately non-blocking in 2.2.384:

- FieldFlow runtime blocking: OFF;
- remote revocation runtime enforcement: OFF;
- mandatory installation binding: OFF;
- governance enforcement mode: SHADOW;
- Azure outage behaviour: fail-open;
- Production Enabled: OFF;
- full operational payload delivery: OFF.

A remote status of `suspended`, `revoked`, `expired` or `rotated` is recorded as `shadow_would_block`, but the current FieldFlow runtime is not disabled by 6B.3.

## Central policy snapshot

For an issued 6B tenant, FieldFlow can synchronize to the licensing backend:

- plan slug;
- maximum activations / installations;
- optional expiry;
- plan capabilities;
- plan numeric limits.

Both WordPress and Azure calculate a canonical SHA-256 policy fingerprint. The certification gate only accepts the policy when both fingerprints match.

The backend refuses a policy change that would reduce `max_activations` below the number of currently active installations.

## Governance operations

The existing HMAC protected licensing route gains the administrative action `/license/manage`, additionally guarded by the configured Admin Secret.

Supported operations:

- `inspect`, read current governance state and policy;
- `sync_policy`, synchronize the plan snapshot;
- `suspend`, centrally suspend a licence;
- `resume`, restore a suspended licence;
- `revoke`, centrally revoke a licence and mark active activation records revoked;
- `rotate`, supersede an old licence with a newly generated one-time key.

All governance operations are written to the existing licensing audit log. No new Azure Table or SQL migration is required.

## Rotation safety

A rotation creates a replacement licence and marks the old licence `rotated`. Active activation records for the old licence become `rotation_required`.

The replacement raw key is returned once by Azure and kept only in PHP request memory for one-time presentation in the BO. The raw key is not written to WordPress options, transients, tenant registry, installation registry or governance ledger.

The administrator must explicitly place the replacement key on the destination installation. 2.2.384 does not silently replace the current site's key.

## Installation limit

The remote licensing service continues to enforce the existing maximum activation count during activation. 6B.3 exposes the central active count alongside the local tenant ceiling and makes a healthy `active <= max` relationship a certification requirement.

This limit is a control-plane constraint and is independent from runtime feature enforcement in WordPress.

## Grace model

A future enforcement grace period of 72 hours is modelled from the last successful governance contact. It is reported diagnostically in 2.2.384 but cannot block FieldFlow.

Turning this model into runtime enforcement requires a later explicit release/gate. No hidden switch in 2.2.384 activates it.

## Certification gate

Phase 6B.3 certification requires:

- Phase 6B.2 still certified;
- release manifest valid;
- remote API and Admin Secret ready;
- current local tenant resolved;
- `/license/manage` reachable;
- local and remote policy SHA-256 fingerprints equal;
- active remote installations not above the local tenant maximum;
- backend capability acknowledgement for inspect, policy sync, suspend, resume, revoke, rotate and max activation control;
- enforcement mode exactly `shadow`;
- runtime blocking false;
- remote revocation runtime enforcement false;
- grace model at least 24 hours;
- no raw rotation key persisted.

Destructive governance actions do not have to be executed against the current production installation to certify the phase. The backend contract is tested independently before deployment.

## Deferred to 6B.4 / final enforcement decision

6B.3 does not implement private plugin distribution or automatic private updates. It also does not switch revocation, suspension or expiry into runtime blocking. Phase 6B.4 is the final implementation subphase in the current Phase 6 roadmap and covers private distribution/update infrastructure. A final stable closure follows it.
