# Phase 7ZW - Typed Submission & Validation Core

Release: `2.2.481`

Contract: `fieldflow-typed-submission-validation-core-v1`

Esta fase cria um contrato de submissão e validação partilhado entre TypeScript e PHP. O browser usa o core tipado para required genérico e preparação do estado ativo, enquanto web e mobile passam a usar `SubmissionValidation` para a mesma semântica de pergunta ativa, required e persistência. Validações especializadas de Evidence, media, Cardex e Share Space permanecem nos serviços atuais.

A paridade PHP/TypeScript é certificada por fixtures partilhados. Um checkbox opcional `false` pode ser persistido como resposta explícita, enquanto um checkbox `required` só é válido quando confirmado. Perguntas escondidas por condição nunca entram no payload ativo nem bloqueiam a submissão.

O Release Manager bloqueia publicação se o core TypeScript, o mirror PHP, o FormRenderer, `Forms.php`, `Mobile/SubmissionService.php` e o verificador comportamental não estiverem ligados à mesma release.

No database migration. No IndexedDB migration. No REST contract change. No Flutter change. No React introduction.
