# Phase 6B.3 Policy Fingerprint Canonicalization Hotfix, FieldFlow 2.2.388

A release corrige exclusivamente a representacao canonical de mapas vazios no fingerprint da policy 6B.3.

WordPress/PHP representava um mapa vazio como `[]`; o backend Node/Azure trata `capabilities` e `limits` como mapas e representa o mesmo valor como `{}`. Como o fingerprint e SHA-256 do JSON canonical, essa diferenca estrutural criava mismatch apesar de a policy ter sido recebida corretamente.

A 2.2.388 alinha a semantica local com o backend 1.0.4. Nao existe alteracao de policy, storage, activation binding, heartbeat, governance operations ou enforcement.
