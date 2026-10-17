# Phase 7ZC - Zero-byte Media Placeholder Hotfix

Release: FieldFlow 2.2.461
Release Gate: 1.14.1

## Incidente

Alguns browsers Android, Chrome e WebView representam um campo `<input type="file">` não selecionado como um objeto `File` de 0 bytes dentro de `FormData`. A 2.2.460 tratava qualquer `Blob` como fotografia e podia iniciar uma sessão retomável com `size: 0`. O servidor rejeitava corretamente esse objeto com `fieldflow_resumable_media_size_invalid`.

## Correção

- `fieldflow-zero-byte-file-placeholder-filter-v1` distingue media real de placeholders vazios.
- Blobs de 0 bytes são removidos na serialização inicial, no manifesto, no transporte retomável e no multipart final.
- A contagem de índices para `skipBlobIndexes` considera apenas blobs reais, evitando duplicação ou desalinhamento de fotografias.
- Antes de sincronizar um item já protegido, o runtime saneia placeholders de 0 bytes e reconstrói o manifesto.
- Itens 2.2.460 em estado `failed` com a mensagem específica de fotografia sem dados são automaticamente devolvidos a `pending`.
- Não é introduzido qualquer limite fixo de MB para fotografias reais.

## Certificação

Phase 6 Stable avança de 2.2.460 para 2.2.461. O Release Gate 1.14.1 exige o marcador de política, o saneamento e o mecanismo de recuperação antes de declarar o contrato de media retomável válido. O manifesto SHA-256 integral continua obrigatório.
