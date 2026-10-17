# FieldFlow 9CX: Fronts SKU Identity Recovery

Contract: `fieldflow-fronts-sku-identity-9cx-v1`.

Candidate: `2.2.620 -> 2.2.621`. This is **not a production certification receipt**.

## Reported field failure

A real photograph shows one Jameson Original front. The 2.2.620 reader returned a P4
model detection, but rejected it as `brand_unverified`. The exact Cardex name
`WHISKY IRLANDES JAMESON 70CL` was incorrectly tokenised with the brand
`IRLANDES`, instead of `JAMESON`. Correcting the Azure model or changing credentials
would not solve this deterministic PHP parser error.

## Changes

1. Normalise commercial taxonomy prefixes (including `WHISKY IRLANDES`) before
   comparing the actual product brand. Distinct variants such as Orange, Black
   Barrel and Caskmates remain independent and must have readable variant evidence.
2. Normalise Cardex capacities 70cl, 700ml and 1L to consistent centilitres.
   A front-facing label may not show capacity; in that case preserve
   `model_fronts` for the operative but leave `fronts` blank.
3. The app explicitly displays the tentative 1-front suggestion and a per-SKU
   button to accept it **only after manual inspection**. Uncertain variants or
   brands never receive that shortcut. The normal final confirmation remains
   mandatory.
4. Duplicate spatial boxes still block two otherwise supported SKUs, but an
   unsupported phantom variant no longer cancels a separately justified Original.
5. Portrait photo boxes are drawn relative to the actual image, not its letterbox
   padding. Amber dashed boxes mean model-proposed/uncertain, not approved.
6. No new call to Azure, fallback provider, authentication change, database
   migration, or change to budget reserves, form mapping, deletion safety, offline
   state or photo retention.

## Validation

`php tools/test-fronts-sku-identity-9cx.php` reproduces the six SKUs from the
real screenshot, confirms Original, prevents fabricated Black Barrel, checks 1L
versus 70CL, pending capacity, and the two-positive spatial collision case.

`node tools/test-fronts-sku-identity-9cx.mjs` verifies the user-visible review
and explicit one-tap acceptance controls and single paid model call contract.

All photographs and actual Azure credentials stay outside the release ZIP.
This deterministic regression uses a simulated model response, so real-device
accuracy still requires validation with the photographed shelf.

## Release

Do not install 2.2.621 until 2.2.620 has been successfully certified Stable.
Follow the complete Release Manager, private updater, 6B, Phase 6, and Stable
certification gates. The current certified baseline is never changed by building
this candidate.
