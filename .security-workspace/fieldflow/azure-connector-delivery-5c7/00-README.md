# FieldFlow Entra Broker 1.6.0, Phase 5C.7 Safe Live Pilot

Code-only update for the existing production Entra Broker Function.

Safety boundary:
- preserves Workload Identity Federation, HMAC, 5C.2 onboarding, 5C.3 read-only probes and 5C.6 certification receiver;
- adds a stateless prepare token for an exact HTTPS pilot target;
- validates DNS and blocks local/private/reserved targets, IP literals, non-443 ports, redirects and oversized bodies;
- pins the validated public DNS resolution into the HTTPS connection to reduce DNS rebinding risk and caps the remote response body;
- the pilot target is bound to destination UUID + endpoint SHA-256 + expiry by HMAC;
- only a strict safe metadata projection can be sent, never form answers, photos, uploads, API keys, tokens or client secrets;
- full operational payload delivery remains blocked and `delivery_enabled` stays false;
- includes a non-persistent TWT pilot sink for end-to-end certification without a third-party receiver.

Deploy in Azure Cloud Shell PowerShell:

```powershell
cd /home/alexandre
Remove-Item ./ff-broker-160 -Recurse -Force -ErrorAction SilentlyContinue
Expand-Archive ./fieldflow-entra-broker-1.6.0-safe-live-pilot-code-only.zip -DestinationPath ./ff-broker-160 -Force
cd ./ff-broker-160
./02-deploy-phase-5c7-safe-live-pilot-code-only.ps1
```

Expected final state: Broker 1.6.0 READY, 5C.6 preserved, 5C.7 Safe Live Pilot READY, full operational payload delivery BLOCKED.
