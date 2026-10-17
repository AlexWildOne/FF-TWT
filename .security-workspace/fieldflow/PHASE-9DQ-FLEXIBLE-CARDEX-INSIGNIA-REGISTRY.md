# FieldFlow 9DQ | Flexible Cardex Insignia Registry

Contract: `fieldflow-flexible-cardex-insignia-registry-9dq-v1`
Release: 2.2.651, successor of the installed and certified 2.2.650 Stable.

## Root cause

2.2.650 exposed `CardexCloneService::insignias()` as a hardcoded list and used `supportedInsignia()` to block all unknown commercial labels. That made it impossible to create cloned assortments for retailers such as Poupança, Garcias, Malaquias and Central Cash.

## Behaviour

- In Backoffice Cardex > Duplicar, the destination field is a searchable text input with suggestions (`datalist`). An administrator can choose a known retailer or enter a new commercial retailer name.
- New retailers are persisted as suggestions only after a successful clone transaction (WordPress option). Previously recorded retailers are also discovered from existing Cardex headers.
- Built-in canonical identities and Sonae subtypes remain protected. Ambiguous generic labels, product brand guesses, isolated merchandiser M and known brand prefixes with store names are not accepted as new retailer identities.
- A successful clone copies only active base SKU rows with fresh IDs and retains original client/project, stays in draft with no store links, adjustments, stock or historical records. Publishing remains a separate, explicit action.
- For custom retailers, commercial PDV identity requires a dedicated retailer field in store metadata with one consistent value, or the retailer's complete name at the start of the PDV name (optionally after 'Loja'). Never use numeric code, locality, generic tags or item-level product brand as matching evidence.
- A PDV whose name or recorded family explicitly identifies another known retailer is rejected. A dedicated metadata conflict rejects the PDV even when the name is similar. Client, project, scope and publication duplicate protections continue.
- Cloned Cardex editing and fixed-form/auto operational resolution use this same strict commercial matcher. No historic automatic mapping, routes, stocks, photos, emails or Portal dock are changed.

## Validation

- `php tools/test-flexible-cardex-insignia-registry-9dq.php`
- `php tools/test-release-gate-runtime-9dq.php`
- Previous tests and gates 9AI, 9DN, 9DO and 9DP.
- PHP syntax, frontend build and types, manifest, stable Package Validator against 2.2.650, new self-validator and SHA-256.

## Limitation

Adding a retailer does not automatically identify stores without trustworthy commercial identity. The cardex remains a draft or pending review until its store scope is validated. No legacy cardex clean-up or historical SKU remapping is performed.
