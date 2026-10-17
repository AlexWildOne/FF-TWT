# FieldFlow Entra Broker 1.7.0, Phase 5C.8

Code-only update for the existing TWT Entra Broker.

Adds the Production Delivery Gate 5C.8:

- guarded HTTPS target validation and DNS pinning;
- explicit event policy binding;
- metadata_v1 safe projection only;
- endpoint SHA-256 plus policy SHA-256 bound production token;
- signed HMAC receipts bound to destination, endpoint and policy;
- queue-path certification through the existing FieldFlow connector jobs table;
- TWT production-gate sink that never persists payloads;
- the same sink accepts the 5C.7 safe-target contract only to certify the exact endpoint prerequisite, still without persistence;
- authenticated production targets remain deferred to Phase 5D;
- general production activation remains blocked until 5D and 5E;
- full operational payload delivery remains blocked.

Deploy with:

```powershell
./02-deploy-phase-5c8-production-delivery-gate-code-only.ps1
```

The script does not create or modify Azure infrastructure, App Registration, federation, Managed Identity, Key Vault, HMAC, SQL, ingest or Relay resources.
