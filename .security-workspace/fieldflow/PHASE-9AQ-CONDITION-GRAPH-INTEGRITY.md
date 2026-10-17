# Phase 9AQ — Condition Graph Integrity

Contract: `fieldflow-condition-graph-integrity-v1`

`SOURCE_RELEASE` is `2.2.560` and `STABLE_RELEASE` is `2.2.561`.

## Purpose

Protect the visual form builder from silent conditional-graph mistakes without changing the certified conditional runtime semantics.

The builder now:

- shows **Controlada por** on every conditional target;
- explains that multiple incoming rules use OR semantics;
- audits cross-domain gates such as Conquistas → Encomenda;
- highlights the exact source rule and affected questions;
- keeps warnings non-blocking so intentional business rules remain possible;
- repeats the audit server-side on save, including schemas edited through advanced JSON.

The canonical `ConditionalFlow` visibility engine is unchanged. This release is an authoring-integrity layer only.

## Regression case

A schema where `Fizeste alguma conquista? = Não` is the only gate to `Foi efetuada uma sugestão de encomenda?` must be flagged as a suspicious `conquest → order` dependency.

After removing that edge, the Conquistas branch and the Order branch are independent and the audit must be clean.

Schema remains `2.2.529`; SubmissionPipeline remains `2.2.485`.
