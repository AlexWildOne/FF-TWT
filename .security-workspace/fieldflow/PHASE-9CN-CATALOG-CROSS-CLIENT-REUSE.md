# Phase 9CN - Catalog Cross-Client Reuse

Contract: `fieldflow-catalog-cross-client-reuse-v1`

`SOURCE_RELEASE` is `2.2.610` and `STABLE_RELEASE` is `2.2.611`.

## Purpose

The 9CL visual catalog is per client. The same products (brands, competitors, images) are often needed by several clients. 9CN lets the back office reuse products of another client's catalog without merging catalogs.

## Back office

In `Cardex e Catálogo` → `Catálogo visual`, `Reutilizar de outro cliente` lists the catalog of another client (filters by type, images and name) with a `Já existe` / `Novo` indicator for the current client. The selected products are copied as `Manter o tipo`, `Marca` or `Concorrência`.

- Each copy is an independent product of the current client: editing it never changes the source.
- Copies share the source image attachments; no media file is duplicated.
- A product that already exists in the current client (same reference, or name and capacity) is not duplicated. It is linked to the source and receives the source images only when it has none; its name, type and data are kept.
- Briefing label mappings stay per client and are not copied.
- The product editor shows `Reutilizado de` and `Atualizar imagens da origem`, which brings the current source images first and keeps the images added only to the copy.
- Reused copies can be deleted; Cardex products still cannot.

The catalog search box now filters by name (the back office sent the search as `s`, which the 9CL query ignored).

## Security

All actions require `routespro_manage` and a nonce.

## Non-regression

- 9CL catalog, Cardex sync, briefing label mapping and 9CM fronts visuals are unchanged.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
