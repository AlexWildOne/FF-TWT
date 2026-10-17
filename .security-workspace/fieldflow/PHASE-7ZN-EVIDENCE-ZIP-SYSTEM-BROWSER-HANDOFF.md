# Phase 7ZN - Evidence ZIP System Browser Handoff

Release: 2.2.472

## Objetivo

Eliminar a ultima dependencia do downloader Flutter generico no ZIP de evidencias. O ZIP continua a ser preparado no servidor e protegido por capability ticket, mas a entrega final no mobile passa a ser feita pelo handler externo ja existente da app e pelo browser/gestor de downloads do sistema.

## Causa confirmada

O handler `fieldflowDownloadRequest` podia devolver sucesso de handoff antes de a transferencia nativa terminar. O front recebia `handled=true` e mostrava `Download iniciado`, enquanto o shell Android falhava depois e apresentava `Nao foi possivel exportar o ficheiro`.

## Correcao

- `FieldFlowNativeBridge.downloadPrepared()` encaminha `evidence_zip` para `preparedEvidenceDownload()`.
- `preparedEvidenceDownload()` usa `fieldflowOpenExternal` com `systemBrowser=true` e a capability URL sem sessao.
- O ZIP de evidencias deixa de passar por `nativeDownload(meta)` e deixa de emitir `fieldflow.download.requested`.
- Eventos dedicados `fieldflow.evidence.download.requested/failed` evitam colisao com o pipeline de exportacoes gerais.
- Operational Analytics e Portal Cliente usam a mesma ponte.
- Fallback browser fica marcado para nao ser reintercetado pela camada global de downloads.
- Nenhuma alteracao Flutter, schema ou base de dados.
