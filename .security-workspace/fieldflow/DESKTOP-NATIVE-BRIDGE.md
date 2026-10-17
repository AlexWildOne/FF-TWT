# FieldFlow Desktop Native Bridge 1.0

O shell Web deteta um host WebView2 atraves de `window.chrome.webview.postMessage`. Sem host, todas as funcionalidades continuam com fallback Web.

## Mensagem base
```json
{
  "source": "fieldflow.desktop",
  "schema": 1,
  "type": "download.request",
  "version": "2.2.300",
  "user_id": 123,
  "profile": "admin",
  "payload": {}
}
```

## Web para Windows
- `bridge.ready`
- `module.selected`
- `module.loaded`
- `network.status`
- `download.request`
- `download.detected`
- `external.open`
- `print.request`
- `clipboard.write`
- `notification.permission.request`
- `notification.subscription`
- `notification.show`
- `activity.snapshot`

## Windows para Web
O futuro host pode enviar mensagens WebView2 com `type` e `payload`:
- `navigate`, `{ "url": "..." }`
- `reload`
- `notification.permission`, `{ "granted": true }`
- `download.completed`, `{ "filename": "..." }`
- `download.failed`
- `clipboard.written`

Este contrato e deliberadamente pequeno e versionado para permitir evoluir o executavel Windows sem acoplar o backend WordPress ao shell nativo.


## Release gate 2.2.300
- Navegacao recebida do host `navigate` e aceite apenas para HTTP/HTTPS same-origin.
- Protocolos inesperados sao rejeitados pelo shell antes de abrirem dentro do FieldFlow.
- O heartbeat valida `user_id`, `profile_key`, `permission_stamp` e versao Core.
- Alteracoes de perfil/permissoes durante uma sessao provocam refresh do shell e do mapa de modulos.
- `bridge.ready` anuncia `release`, `bridge_schema`, `max_upload_bytes` e `same_origin_navigation`.
- Drag and drop respeita `accept`, `multiple` e o limite de upload devolvido pelo servidor.
