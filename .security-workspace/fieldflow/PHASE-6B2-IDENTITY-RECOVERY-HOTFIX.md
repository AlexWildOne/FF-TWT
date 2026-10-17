# Phase 6B.2 Installation Identity Recovery Hotfix, FieldFlow 2.2.387

## Problema observado

A instalacao `report.thewildtheory.com` tinha sido certificada com uma Installation UUID e Activation ID validos no Azure. Depois de upgrades sucessivos, a opcao local da identidade passou a apresentar uma UUID diferente, enquanto o Azure manteve corretamente a UUID originalmente ligada a mesma ativacao. O heartbeat respondeu HTTP 409 `installation identity mismatch`.

## Principio de seguranca

O backend Azure continua autoritativo. O hotfix nunca faz rebind remoto automatico. A recuperacao apenas restaura o cliente local para a identidade ja ligada no Azure.

A recuperacao exige cumulativamente:

1. HTTP 409 com mensagem exata `installation identity mismatch`;
2. License ID remoto igual ao License ID local;
3. Activation ID remoto igual ao Activation ID local;
4. dominio remoto igual ao dominio atual normalizado;
5. estado remoto `active` ou `trial`;
6. Installation UUID remota valida;
7. installation fingerprint remoto SHA-256 valido e exatamente igual ao fingerprint recalculado localmente para produto + UUID remota + dominio atual.

Se qualquer condicao falhar, nao existe recuperacao automatica.

## Comportamento

Depois de uma recuperacao valida, o FieldFlow grava localmente a UUID Azure, audita apenas hashes das identidades anterior/recuperada e repete o heartbeat uma unica vez.

Um heartbeat recusado deixa de substituir a ultima identidade aceite dentro do estado local da licenca.

## Invariantes

- Azure Licensing Backend permanece 1.0.4.
- Nao existe migration.
- Fase 5 permanece intacta.
- 6B.3 continua SHADOW.
- runtime blocking OFF.
- remote revocation nao e aplicada ao runtime.
- 11 jobs historicos 5B Pending permanecem intocados.
