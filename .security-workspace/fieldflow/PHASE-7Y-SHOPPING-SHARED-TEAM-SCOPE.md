# FieldFlow 2.2.457 - Phase 7Y Shopping Shared Team Scope

## Objective
Shopping is an insignia-level operational workflow, not a route-owned or merchandiser-owned task. Every operational user with access to the project must see the same active Shopping and may select any valid store of that insignia within the project.

## Runtime contract
- Shopping assignments are always team-wide (`assigned_user_id` is NULL).
- Project access remains the security boundary. Users outside the project do not gain access.
- Campaign/store `assigned_to` is deliberately ignored by Shopping store selection.
- The executor is still recorded on each submission (`user_id`) for audit and analytics.
- Historical submissions, prices, store and email evidence are preserved.

## Certification
Release Gate 1.11.0 checks Shopping schema 1.2.1 and fails if any active/draft assignment remains restricted to an individual merchandiser.
