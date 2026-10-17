> **Atualização 2.2.363:** o fluxo `/adminconsent` desta especificação foi substituído pelo browser-only tenant connection descrito em `PHASE-5C2-BROWSER-ONLY-TENANT-CONNECTION.md`. O cliente já não precisa de consentimento administrativo artificial para permissões não usadas, Cloud Shell ou subscrição Azure.

# FieldFlow 2.2.361 - Phase 5C.2 External Tenant Onboarding

## Objective

Extend the certified Microsoft Entra cross-tenant identity foundation from the TWT home tenant to customer tenants without introducing client secrets, certificates or access tokens into WordPress.

This release is a control-plane and identity-onboarding release only. Connector delivery remains locked in Shadow and `delivery_ready=false`.

## Certified baseline kept intact

- Azure TWT primary ingest path and HMAC v1.
- Phase 5B Client Azure queue/Relay runtime.
- Power BI DirectQuery/Entra SSO reporting foundation.
- Desktop, mobile/WebView, routes, visits, forms, briefings, stocks and client portal.
- Phase 5C.2 home-tenant token probe already certified with HTTP 200.

## External tenant flow

1. Configure a 5C destination with `entra_cross_tenant`, target tenant GUID and resource scope.
2. FieldFlow generates a one-hour onboarding state signed with the existing TWT HMAC trust.
3. FieldFlow builds a Microsoft tenant-specific admin-consent URL. The customer administrator can open that link without access to the TWT WordPress administration.
4. Microsoft returns to the dedicated TWT Entra Broker callback.
5. The Broker verifies the signed state, target tenant, expiry, nonce, scope and FieldFlow receipt callback.
6. A successful consent callback is not considered proof of readiness by itself. The Broker immediately performs the authoritative federated token probe for the target tenant and requested resource scope.
7. The Broker sends a small HMAC-signed control-plane receipt back to the FieldFlow REST endpoint. No access token, secret or business payload is returned.
8. FieldFlow verifies the receipt against the local destination identity and the stored SHA-256 nonce hash.
9. Only a successful token probe moves the destination to `READY, IDENTIDADE`.
10. If the broker-to-WordPress receipt is blocked by an intermediary, `Verificar tenant` repeats the safe token probe manually.

## State model

- `DRAFT`
- `CONSENT PENDING`
- `CONSENT RECEIVED`
- `TOKEN PROBE FAILED`
- `CONSENT DENIED`
- `TENANT MISMATCH`
- `READY, IDENTIDADE`

`READY, IDENTIDADE` means only that the FieldFlow broker can establish the Microsoft Entra identity in the target tenant for the configured resource scope. It does not mean the application has RBAC, SQL, Power BI or custom API authorization to read or write customer data.

## Security controls

- Consent state is HMAC signed and expires after one hour.
- State contains a cryptographically random nonce; WordPress persists only the SHA-256 hash of that nonce.
- Target tenant and resource scope are bound into the signed state.
- The WordPress receipt URL is bound into the signed state and must use HTTPS.
- Broker receipt is signed with FieldFlow HMAC v1 and checked for clock skew, body SHA-256, tenant ID, installation ID and event UUID.
- Client secrets and certificates are never requested or stored.
- Access tokens never leave the Azure Broker and are not logged.
- Callback errors expose only safe error codes and optional AADSTS identifiers.
- Operational delivery remains disabled.

## Azure Broker 1.1.0

The existing dedicated Function App is reused:

`func-fieldflow-entrabroker-prod-eb978f11`

The existing User Assigned Managed Identity, multitenant app registration and Key Vault HMAC reference are reused. The deployment does not recreate or modify the certified ingest Function or the Phase 5B Relay.

Health adds the following safe flags:

- `external_tenant_onboarding: true`
- `onboarding_callback_token_probe: true`
- `onboarding_receipt_hmac: true`
- `delivery_enabled: false`

## What is deliberately not included

- No connector delivery to an external tenant.
- No Azure SQL database user or RBAC assignment in a customer subscription.
- No Power BI tenant setting or workspace authorization.
- No API application-role assignment.
- No customer secret import/export.
- No automatic assumption that Microsoft admin-consent callback equals resource authorization.

Those resource-specific authorizations belong to the next controlled block.
