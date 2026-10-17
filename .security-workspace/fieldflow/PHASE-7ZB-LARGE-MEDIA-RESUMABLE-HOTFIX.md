# Phase 7ZB, Large Media Resumable Hotfix

Release: FieldFlow 2.2.460

## Objetivo

Eliminar limites artificiais de tamanho das fotografias de evidência sem enfraquecer a integridade, a autenticação, o zero-loss offline ou a certificação da instalação.

## Alterações

- As imagens FieldFlow deixam de ser rejeitadas pelo `UploadGuard` com base num valor fixo de MB.
- Documentos continuam a respeitar o limite configurado e as extensões perigosas continuam bloqueadas.
- O transporte `fieldflow-resumable-media-v1` deixa de herdar `wp_max_upload_size()` e os limites PHP de um pedido multipart.
- A admissão de uma fotografia depende apenas de existir capacidade real de armazenamento temporário suficiente para blocos, reconstrução e publicação.
- O limite operacional de blocos é elevado para um envelope de segurança muito acima de qualquer fotografia normal de dispositivo móvel.
- Formulários dinâmicos standalone passam a usar o mesmo outbox protegido + envio retomável dos fluxos Quick Report e Focused Visit sempre que o runtime FieldFlow está disponível.
- A optimização de imagens no dispositivo passa a ser sequencial, uma fotografia de cada vez, evitando picos de memória com seleções de 12 ou mais fotografias de alta resolução.
- A cópia local permanece a autoridade de recuperação até existir ACK verificável do servidor.

## Segurança preservada

- sessão WordPress autenticada;
- vínculo user + device + mutation + field + file index;
- SHA-256 do ficheiro completo e dos blocos;
- tamanho exato reconstruído no servidor;
- MIME e extensões autorizadas;
- rate limits e bloqueio de extensões executáveis;
- armazenamento temporário privado;
- Release Manager, package SHA-256, production manifest e cadeia Azure 6B sem bypass.

## Certificação

Release Gate 1.14.0 exige os marcadores `no-fixed-image-limit`, `storage-capacity-only` e o controlador standalone resumável. Phase 6 Stable avança de 2.2.459 para 2.2.460, pelo que a certificação da instalação continua alinhada com a versão instalada e publicada.

Sem migração de base de dados.
