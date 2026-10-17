FieldFlow Entra Broker 1.5.0, Phase 5C.6 Controlled External Delivery

Purpose
- Keeps all existing 1.4.1 identity and read-only resource probes.
- Adds POST /api/v1/controlled-delivery for synthetic 5C.6 certification only.
- The broker ignores arbitrary destination URLs and sends only to its own fixed controlled receiver route.
- Adds POST /api/v1/controlled-receiver, HMAC-authenticated, non-persistent, synthetic only.
- Returns HMAC-signed receipts bound to delivery_key, event_uuid and destination_uuid.
- Supports deterministic certification behaviours: success, fail_first, fail_always.
- delivery_enabled remains false for operational delivery.
- No client secret, access token, API key or destination secret is returned to WordPress.

Deployment
Run 02-deploy-phase-5c6-controlled-delivery-code-only.ps1 in Azure Cloud Shell PowerShell.
The script updates code only in the existing Broker Function App and validates safe health before and after deployment.
