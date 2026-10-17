# 2.2.628 9D3 | Catalog Category Picker (candidate)

2.2.627 is confirmed Stable by the user. 2.2.628 is a successor requiring separate Release Manager validation, private publication and certification. Only the product-catalog category picker and per-client manual-category registry change, with no extra Azure usage. See `PHASE-9D3-CATALOG-CATEGORY-PICKER.md`.

# 2.2.627 9D2 Catalog and Briefing Category Continuity candidate

The 2.2.627 successor of certified 2.2.626 implements catalog-derived categories, explicit manual category review, tenant/campaign-scoped persistence, conservative weekly briefing question inheritance by product identity and location mapping, and operative read-only category display. New 9D2 Release Gate and package checks verify the source contract. No calls to Azure OpenAI are added. No WordPress DB schema migration or historical data rewrite. See `PHASE-9D2-BRIEFING-CATEGORY-CONTINUITY.md` and `tools/test-briefing-category-9d2.php`.

# 2.2.626 9D1 Vision Intelligence II certification candidate

This 2.2.626 successor of the independently certified 2.2.625 preserves the 9CS/9D0 guards, adds conservative physical-image deduplication, and restricts previous visit snapshots to the exact tenant/store/form/question from linked manually confirmed closed visits. Human confirmation remains required. No paid calls are made by tests. Full contract and independent tests are in `PHASE-9D1-FRONTS-VISION-INTELLIGENCE-II.md`.

## 2.2.599 Locale Brand & Associated Client Scope Continuity 9CB

- Runtime, plugin header and Stable tag aligned to 2.2.599.
- SOURCE_RELEASE 2.2.598 and STABLE_RELEASE 2.2.599.
- Contract `fieldflow-locale-brand-scope-continuity-v1` keeps the React/TypeScript language globe synchronized with the active client scope through `routespro:scope-change`.
- Hover, focus, active, badge and menu states inherit the active portal brand variables instead of falling back to generic blue.
- The authenticated language endpoint remains guarded by `Permissions::is_allowed_client()`.
- 9BD, 9BZ and 9CA remain intact. No database schema changes and no FlutterFlow source modification is required.

## 2.2.565 Release Manager Publication Receipt Continuity 9AU

- Runtime, plugin header and Stable tag aligned to 2.2.565.
- SOURCE_RELEASE 2.2.564 and STABLE_RELEASE 2.2.565.
- Contract `fieldflow-release-manager-publication-receipt-v1` adds durable WordPress publication evidence using the canonical option, multisite mirror and existing audit log.
- Phase 6 accepts a recovered receipt only for the exact Stable version and only when its SHA-256 matches the private 6B.4 catalog.
- No bypass of Release Manager publication, private catalog validation, package SHA-256, licensing, heartbeat or governance checks.
- No database schema or SubmissionPipeline change.

## 2.2.486 React Backoffice Foundation

- Introduz `fieldflow-react-backoffice-foundation-v1` com React progressivo apenas no backoffice.
- Usa `wp-element` do WordPress, sem empacotar uma segunda cópia de React/ReactDOM.
- Primeiro piloto: `routespro-mobile`, island `mobile-release-status`, apenas leitura.
- O card PHP existente permanece como fallback se React não carregar ou estiver desativado.
- Kill switch: `FIELDFLOW_REACT_BACKOFFICE`.
- Release Manager exige `react_backoffice_foundation_ok`; Release Gate 1.37.0 certifica build, mount, wp-element e fallback.
- Sem migração de base de dados/IndexedDB, sem alteração REST, FlutterFlow ou runtime do formulário.

## 2.2.485 Submission Events & Side Effects

- A submissão durável termina no COMMIT antes das integrações secundárias.
- `RoutesPro\Forms\SubmissionSideEffectDispatcher` executa lanes internas isoladas, idempotentes por revisão e com retry por lane.
- Falha de Mail, EventBus/Azure gateway, Performance, notificações de rota ou hooks públicos não invalida uma visita já persistida nem repete lanes concluídas.
- Mobile trata observação de `MediaRegistry` e `AuditLog` pós-COMMIT como não bloqueante.
- Hooks públicos WordPress de submissão/visita são preservados numa lane de compatibilidade.
- Contrato: `fieldflow-submission-side-effects-v1`. Release Gate 1.36.0.
- Sem migração de base de dados/IndexedDB, sem alteração REST, FlutterFlow ou React.

## 2.2.484 Submission Application Service

- O caminho durável de submissão web/mobile passa a usar `RoutesPro\Forms\SubmissionApplicationService` para schema, transação, submissão, respostas e envelope pós-commit.
- A fronteira transacional legacy do mobile é preservada, evitando alteração silenciosa do comportamento de Cardex, histórico ou conclusão de visita.
- Mail, Analytics, Azure, exports e notificações de rota permanecem fora do serviço de persistência, reduzindo acoplamento entre gravação da visita e sistemas secundários.
- O Release Manager exige `submission_application_service_ok` e o Release Gate 1.35.0 valida `fieldflow-submission-application-service-v1`.
- Sem migração de base de dados/IndexedDB, sem alteração REST, FlutterFlow ou React.

## 2.2.475 Offline Runtime Hardening

- A identidade do runtime offline é vinculada à versão publicada e validada pelo Release Manager.
- Sincronização usa lease renovável com proteção intra-contexto e cross-tab, preservando idempotência e ACK server-side.
- Rede degradada deixa de equivaler a offline para o motor de sincronização.
- Dia operacional offline passa a ser derivado da autoridade WordPress/site timezone.
- Contrato: `fieldflow-offline-runtime-hardening-v1`. Release Gate 1.26.0.
- No database migration. Sem alteração Flutter ou quebra de contrato REST.

## 2.2.474 Order Suggestion Conditional Mail Guard

- A decisão de visibilidade condicional é persistida no reporte e reaproveitada pelo Mail Center.
- `order_suggestion` oculta não pode originar email automático, retry ou reenvio manual.
- Submissões legacy sem snapshot usam reavaliação conservadora do schema e respostas.
- Contrato: `fieldflow-order-suggestion-conditional-mail-v1`. Release Gate 1.25.0.
- Sem migração de base de dados e sem alteração Flutter.

## 2.2.473 Form Order Suggestion Placement

- `order_suggestion` emparelhada com Frentes nunca é renderizada antes da pergunta `product_matrix` fonte.
- Perguntas de encomenda legacy fora de `layout.steps` são recuperadas para o passo da fonte de Frentes em vez de gerar um passo autónomo inesperado.
- O Form Builder repara a mesma omissão de forma persistente no próximo Guardar, sem alterar chaves, respostas históricas ou bindings.
- Cardex, exceções por loja, valores manuais, zero-default de frentes e sincronização de referências permanecem inalterados.
- Contrato: `fieldflow-form-order-suggestion-placement-v1`. Release Gate 1.24.0.
- Sem migração de base de dados e sem alteração Flutter.

## 2.2.470 Evidence ZIP Ticket Continuity

- Final evidence download uses a short-lived opaque capability ticket instead of requiring a WordPress session and nonce in the native downloader.
- HEAD and repeated GET requests are safe during the ticket TTL.
- Prepared evidence files remain constrained to the dedicated export directory and retain the existing Analytics permission scope at preparation time.
- Contract: `fieldflow-evidence-zip-ticket-continuity-v1`.

Nota de release 2.2.469: Evidence ZIP Runtime v3. Uma reprodução real com apenas uma imagem continuava a devolver HTTP 500 na 2.2.468, demonstrando que o problema remanescente era estrutural no caminho create-and-download e não volume. O Analytics passa a preparar o arquivo via REST autenticado usando exclusivamente o token de evidências emitido pelo servidor para o grupo já autorizado. O ZIP é criado por um writer STORE portátil em PHP, validado de forma independente e entregue por um segundo token temporário num pedido que apenas transmite o ficheiro já pronto. Media Registry recupera caminhos locais históricos quando necessário. Release Gate 1.20.0, Release Manager, production manifest SHA-256 e Azure-backed Phase 6 permanecem obrigatórios. Não existe migração de base de dados nem alteração Flutter.

## Evidência de continuidade operacional, release 2.2.469

- Runtime, cabeçalho e Stable tag alinhados em 2.2.469.
- SOURCE_RELEASE 2.2.468 e STABLE_RELEASE 2.2.469.
- Contrato `fieldflow-evidence-zip-runtime-v3` validado no Release Gate e pelo verificador build-time dedicado.
- O browser nunca envia URLs arbitrários de evidências ao endpoint de preparação, apenas o token de grupo emitido pelo servidor e associado ao utilizador autenticado.
- O caminho normal separa preparação e download, o `admin-post.php` final transmite apenas o ZIP concluído e deixa de executar a geração pesada.
- Writer portátil, validação independente, Media Registry e fallbacks ZipArchive/PclZip preservam compatibilidade entre hostings sem alterar os bytes das evidências válidas.
- Analytics IA, formulários, rotas, Cardex, Mail Center, exports não-evidência e Flutter permanecem funcionalmente intactos.

Nota de release 2.2.468: Analytics Export & Evidence ZIP Resilience Hotfix. O ZIP de evidências deixa de duplicar todas as fotografias numa pasta de staging antes da compressão e passa a construir o arquivo diretamente a partir dos media locais válidos. Ficheiros vazios ou ilegíveis são ignorados, formatos já comprimidos não são recomprimidos, o ZIP é criado como `.part`, validado antes de ser promovido e transmitido em blocos de 1 MiB. A telemetria Media Shadow é bounded para não transformar centenas de imagens em centenas de operações de base de dados durante uma exportação. Excel/PDF/CSV mantêm o contrato existente e libertam o session lock em pedidos longos. Release Gate 1.19.0, Release Manager, production manifest SHA-256 e Azure-backed Phase 6 permanecem obrigatórios. Não existe migração de base de dados nem alteração Flutter.

## Evidência de continuidade operacional, release 2.2.468

- Runtime, cabeçalho e Stable tag alinhados em 2.2.468.
- SOURCE_RELEASE 2.2.467 e STABLE_RELEASE 2.2.468.
- Contrato `fieldflow-analytics-export-resilience-v1` validado no Release Gate e pelo verificador build-time dedicado.
- ZIP de evidências usa origem direta, validação atómica, streaming por blocos e limpeza de partials antigos sem alterar permissões ou conteúdo válido.
- Telemetria de media deixa de estar no caminho crítico de exportações integrais, preservando observabilidade por amostragem no front.
- CSV, XLSX, XLSX mobile e PDF preservam os filtros, formatos e âmbito Analytics existentes.

Nota de release 2.2.467: Operational Intelligence & Field Reliability Hardening. Analytics passa a distinguir passado fechado, hoje em progresso e futuro planeado sem penalizar trabalho ainda não devido. O Perguntar ao FieldFlow oculta o balão visual da pergunta e ganha ajuda funcional determinística. Sugestões de encomenda legadas vazias podem herdar o Cardex válido da pergunta de Frentes sem tocar em listas manuais explícitas. O fecho diário recebe um fallback idempotente pós-COMMIT para rotas concluídas por não visita justificada. Release Gate 1.18.0, Release Manager, production manifest SHA-256 e Azure-backed Phase 6 permanecem obrigatórios. Não existe migração de base de dados nem alteração Flutter.

## Evidência de continuidade operacional, release 2.2.467

- Runtime, cabeçalho e Stable tag alinhados em 2.2.467.
- SOURCE_RELEASE 2.2.466 e STABLE_RELEASE 2.2.467.
- Contrato `fieldflow-operational-intelligence-hardening-v1` validado no Release Gate e por verificador build-time dedicado.
- Métricas raw de dashboards preservadas, avaliação inteligente usa apenas dias passados fechados quando a janela inclui hoje/futuro.
- `route.completed` permanece canónico e os claims do Mail Center preservam idempotência no fallback pós-COMMIT.

Nota de release 2.2.466: Responsive Mail Rendering Hotfix. Os emails FieldFlow passam a usar um shell fluido que não depende de scroll horizontal nem de media queries para caber no viewport. Sugestões de encomenda deixam de usar flex/min-width e passam a cartões de produto email-safe; Shopping usa tabelas fixed-layout com quebra de texto; relatórios, notificações de rota, testes e reenvios manuais herdam o mesmo contrato. Release Gate 1.17.0, Release Manager, production manifest SHA-256 e Azure-backed Phase 6 permanecem obrigatórios. Não existe migração de base de dados.

## Evidência de continuidade operacional, release 2.2.466

- Runtime, cabeçalho e Stable tag alinhados em 2.2.466.
- `fieldflow-responsive-email-rendering-v1` valida shell fluido, ausência de flex/min-width no email de sugestão, fixed-layout em tabelas de dados e compatibilidade Outlook via MSO.
- O contrato aplica-se aos emails automáticos, testes e reenvios manuais sem alterar destinatários, anexos, métricas ou triggers operacionais.
- O artefacto mantém intacta a cadeia privada de publicação e certificação.

Nota de release 2.2.465: Mail Center Historical Recovery. O BO passa a permitir reenvio histórico de fechos diários e sugestões de encomenda persistidas para merchandiser, cliente, ambos ou email exclusivo de teste. Rotas antigas são reconciliadas com a semântica atual de não visita justificada, sem disparar um segundo email automático, e sugestões históricas regeneram Excel/PDF a partir da submissão original. Os envios manuais são auditados sem alterar métricas ou o estado operacional das automações. Release Gate 1.16.0, Release Manager, production manifest SHA-256 e Azure-backed Phase 6 permanecem obrigatórios. Não existe migração de base de dados.

## Evidência de continuidade operacional, release 2.2.465

- Runtime, cabeçalho e Stable tag alinhados em 2.2.465.
- `fieldflow-mailcenter-historical-recovery-v1` valida reenvio de rota, reenvio de sugestão, isolamento de destinatários, testes manuais e reconciliação sem duplicação do evento automático.
- O artefacto mantém intacta a cadeia privada de publicação e certificação.

Nota de release 2.2.464: Field Form Reliability Core. A recuperação de múltiplas fotografias passa a reconstruir todos os ordinais persistidos e a publicação de rascunho server-side deixa de assumir apenas o ordinal zero. Não visitas justificadas passam a concluir operacionalmente a rota sem serem contadas como visitas executadas, e o Mail Center reage ao evento canónico `route.completed`. Briefing, Cardex, formulários e bindings partilham uma revisão operacional de cache. Sugestões de encomenda entram nos exports Analytics e num fluxo Mail Center editável com Excel e PDF para o operativo e cliente, com idempotência e retry por destinatário. Release Manager, production manifest SHA-256, Azure-backed Phase 6 e Release Gate 1.15.0 permanecem obrigatórios. Não existe migração de base de dados.

## Evidência de continuidade operacional, release 2.2.464

- Runtime, cabeçalho e Stable tag alinhados em 2.2.464.
- Release Gate 1.15.0 acrescenta o contrato `fieldflow-form-reliability-core-v1` sem remover os gates anteriores.
- O verificador de build `tools/verify-field-form-reliability.php` cobre o fixture multi-foto, semântica terminal de falha justificada, validação server-side, revisão operacional, export/mail de sugestão, retry por destinatário e trigger canónico de fecho de rota.
- Excel, CSV, PDF e anexos de sugestão de encomenda derivam da mesma apresentação normalizada da submissão persistida.
- A cadeia privada de publicação e certificação Azure permanece inalterada.
- Não existe migração de base de dados nesta release.

Nota de release 2.2.463: auditoria de continuidade operacional ponta a ponta dos formulários. Respostas passam a ter checkpoint secundário em IndexedDB, fotografias de perguntas normais e de Briefing entram diretamente no outbox a partir do cofre local, rascunhos deixam de ser destruídos por alterações compatíveis de schema e a cópia local só é limpa depois de ACK verificável da submissão. O backup remoto de media de rascunho é incremental e a recuperação servidor/dispositivo preserva sempre a cópia local antes de substituição. O transporte final mantém blocos de 512 KiB, SHA-256, binding a utilizador/dispositivo/mutação e política sem teto artificial de MB. Release Manager, production manifest SHA-256 e Release Gate 1.14.3 permanecem obrigatórios. Não existe migração de base de dados.

Nota de release 2.2.352: estabilização pré-Fase 5 com exportação de rotas no Portal Cliente protegida por nonce, scope server-side e entitlements; correção responsiva de Campanhas PDV. Sem alterações Azure, Power BI, HMAC ou `src/Mobile`.

Nota de release 2.2.351: acrescenta apenas a dimensão operacional minimizada para reporting Azure/Power BI. O snapshot exclui contactos e credenciais, reutiliza a fila HMAC existente e mantém `src/Mobile` inalterado. O novo scope começa desligado por defeito para permitir rollout Azure-first.

Nota de release 2.2.346: fecha a Fase 3 do Azure Data Hub com Briefings e Stocks. A release reutiliza o endpoint HMAC-SHA256 v1, Key Vault, Managed Identity e fila Cloud já certificados, não cria novos endpoints públicos nem altera autenticação, autorização, sessões ou contratos Mobile. Os novos backfills são explícitos por tipo de dados e mantêm idempotência por Event UUID determinístico.

Nota de release 2.2.340: introduz a FieldFlow Cloud Foundation com identidade multi-tenant e fila persistente de sincronizacao externa. O Azure Cloud Sync fica desligado por defeito, os endpoints externos continuam protegidos por `OutboundHttp`, e a release nao altera autenticacao, sessoes, autorizacao, contratos mobile ou dados historicos.

Nota de release 2.2.339: endurece os fluxos de consentimento de câmara e localização para iOS. O front deixa de executar preflight nativo antes de `getUserMedia()` ou `navigator.geolocation`, não abre Definições e não instrui o utilizador a fazê-lo após uma recusa. A alteração não mexe em autenticação, autorização, sessão, MFA, SSRF, media protegida, respostas ou submissões.

Nota de release 2.2.338: corrige apenas o censo/contexto de Analytics e a desduplicação da família Comparativo de referências. Não altera controlos de autenticação, autorização, sessão, MFA, SSRF, media protegida ou dados de submissões.

## Evidência de continuidade operacional, release 2.2.463

- Runtime, cabeçalho e Stable tag alinhados em 2.2.463.
- Release Gate 1.14.3 inclui o contrato `fieldflow-end-to-end-form-recovery-v1`, além dos gates de media retomável, zero-loss offline, Shopping, licensing e integridade da release.
- O production manifest cobre 474 ficheiros da release, excluindo apenas o próprio manifesto, e valida SHA-256 por ficheiro e agregado.
- A cadeia de formulários mantém respostas e fotografias no dispositivo até `fieldflow-form-ack-v1`, com backup remoto de rascunho não destrutivo e media incremental.
- Perguntas geradas por Briefing usam o mesmo schema, cofre IndexedDB, outbox e validação final das restantes perguntas do formulário.
- A submissão canónica é persistida antes de histórico, Cardex, rota e integrações externas. Falhas posteriores não invalidam o reporte guardado.
- Não existe migração de base de dados nesta release.

# FieldFlow, OWASP ASVS 5.0.0 Level 2

## Estado da verificação interna

Baseline analisada: **FieldFlow 2.2.337**  
Alvo: **OWASP ASVS 5.0.0 Level 2**  
Estado: **pré-verificação interna, não constitui certificação externa**

Nota de release: a 2.2.337 acrescenta propriedade Analytics explícita, repara auto-saves legacy sem confirmação, cria uma família própria para Comparativo de referências e corrige o layout mobile dos widgets Analytics. A migração é não destrutiva e não modifica MFA, política de sessões, SSRF Guard, Protected Media ou outros controlos de segurança avaliados na 2.2.331.

Fonte normativa: OWASP Application Security Verification Standard 5.0.0, repositório oficial OWASP/ASVS.

### Legenda
- `PASS`: existe evidência suficiente no código ou configuração analisada para esta fase.
- `PARTIAL`: controlo existe parcialmente ou necessita validação dinâmica, documentação ou configuração externa.
- `FAIL`: requisito L1/L2 aplicável ainda não está implementado.
- `EXTERNAL`: depende do host, WordPress, IdP, WAF ou outra camada fora do plugin e ainda necessita evidência.
- `N/A`: requisito não se aplica à arquitetura atual.

## Resumo por área

| Área ASVS | Estado atual | Evidência / gap principal |
|---|---|---|
| V1 Encoding and Sanitization | PARTIAL | Sanitização WordPress, SQL preparado e UploadGuard fortes. 2.2.325 adiciona guard central de outbound/SSRF; continuam pendentes revisão transacional global e testes dinâmicos. |
| V2 Validation and Business Logic | PARTIAL | Validação server-side extensa. Operações críticas selecionadas são atómicas. Falta documentação formal de limites e revisão transacional global. |
| V3 Web Frontend Security | PARTIAL / EXTERNAL | Nonces e output escaping amplamente usados. CSP, HSTS, cookies e headers globais dependem também do deployment. |
| V4 API and Web Service | PARTIAL / EXTERNAL | REST usa permission callbacks e security middleware. Request smuggling, métodos HTTP e proxy headers exigem validação de infraestrutura. |
| V5 File Handling | PARTIAL | UploadGuard valida tipo, MIME, tamanho e arquivos comprimidos. Falta antivírus e Protected Media continua em Shadow Mode. |
| V6 Authentication | PARTIAL / ROLLOUT | 2.2.331 implementa TOTP para contas privilegiadas no BO/Desktop/front web, recovery codes, replay protection e rollout Observe/Enforce. Falta ativar Enforcement na instalação e validar dinamicamente; MFA mobile privilegiado permanece opt-in até UI na app. |
| V7 Session Management | PARTIAL / ROLLOUT | 2.2.331 adiciona idle e absolute lifetime para BO/Desktop privilegiado, mobile e WebView, com rollout Observe/Enforce e baseline de ativação para sessões existentes. Falta ativar Enforcement e executar testes dinâmicos de expiração/reutilização. |
| V8 Authorization | STRONG / PARTIAL | Scope, route ownership e assignments validados no servidor. 2.2.317 fecha AJAX legado. Falta terminar matriz de field-level access e testes cross-tenant dinâmicos. |
| V9 Self-contained Tokens | N/A | FieldFlow mobile usa tokens opacos de referência, não JWT/JWS próprios. |
| V10 OAuth and OIDC | N/A | A baseline analisada não implementa OAuth/OIDC como mecanismo de autenticação FieldFlow. |
| V11 Cryptography | PARTIAL | CSPRNG e hashing são usados nos tokens. Falta inventário criptográfico e política formal de lifecycle de chaves. |
| V12 Secure Communication | EXTERNAL / PARTIAL | HTTPS é pressuposto nos serviços externos, mas TLS/ciphers/certificados precisam de evidência do ambiente de produção. |
| V13 Configuration | PARTIAL | Configurações sensíveis são separadas da UI pública. 2.2.325 aplica allowlist central de egress; continua a faltar secrets manager formal e evidência de configuração de produção. |
| V14 Data Protection | PARTIAL | RGPD, retenção, Audit Sanitizer e Privacy Governance são fortes. Media ainda não tem enforcement de autorização ao nível HTTP. |
| V15 Secure Coding and Architecture | PARTIAL | Hardening incremental e validações existem. 2.2.325 centraliza outbound e bloqueia redirects por omissão; falta SBOM formal e dependency lifecycle completo. |
| V16 Security Logging and Error Handling | BLOCKED / PARTIAL | Audit Log, Security Events, correlação, redação e logging central existem. 2.2.321 separa sessão/autenticação, autorização, cross-scope, rate limit e CSRF/nonce; 2.2.323 adiciona drill-down por endpoint/reason code, 2.2.324 acrescenta exportação JSON sanitizada da evidência e 2.2.327 adiciona evidence pack sanitizado da Saúde do Sistema; 2.2.328 corrige a semântica de health signals para separar falhas ativas de histórico recuperado/4xx funcional e reforça a redação do evidence pack. Falta forwarding para sistema separado e cobertura dinâmica final. |
| V17 WebRTC | N/A | O FieldFlow não possui TURN, DTLS-SRTP ou signaling WebRTC. |

## Requisitos prioritários já verificados

| Requisito | Nível | Estado | Evidência atual |
|---|---:|---|---|
| v5.0.0-2.3.3 | L2 | PARTIAL | 2.2.317 torna a substituição de assignments atómica. Revisão transacional global ainda pendente. |
| v5.0.0-5.2.1 | L1 | PASS | Limites de upload por tipo configuráveis. |
| v5.0.0-5.2.2 | L1 | PASS / VERIFY | UploadGuard valida extensão e MIME/conteúdo nos fluxos FieldFlow. Falta confirmação dinâmica de todos os upload entrypoints. |
| v5.0.0-5.2.3 | L2 | PASS | ZIP/DOCX/XLSX têm limite de entradas, tamanho descomprimido e ratio de compressão. |
| v5.0.0-2.2.1 | L1 | PASS / VERIFY | 2.2.322 adiciona verificação de completude para uploads multi-foto: uma submissão não é persistida quando o cliente declarou mais ficheiros do que o servidor recebeu. Requer teste dinâmico em Android/iOS. |
| v5.0.0-5.4.3 | L2 | FAIL | Não existe integração antivírus para ficheiros de origem não confiável. |
| v5.0.0-6.3.1 | L1 | PASS / VERIFY | RateLimiter e SecurityService protegem autenticação; requer teste dinâmico de credential stuffing. |
| v5.0.0-6.3.3 | L2 | PARTIAL / ROLLOUT | 2.2.331 implementa TOTP obrigatório para contas `routespro_manage` no BO/Desktop/front web quando `mfa_mode=enforce`, com recovery codes e step-up pós-password. A release inicia em Observe; MFA mobile privilegiado permanece opt-in. Requer ativação operacional e teste antes de PASS. |
| v5.0.0-6.3.4 | L2 | PARTIAL / VERIFY | 2.2.331 centraliza política por risco entre WordPress/BO/Desktop, mobile e WebView. Mobile privilegiado suporta `mfa_code`, mas enforcement mobile fica OFF por defeito até UI correspondente. Requer teste end-to-end. |
| v5.0.0-7.3.1 | L2 | PARTIAL / ROLLOUT | 2.2.331 implementa inactivity timeout configurável: BO/Desktop privilegiado 480 min, mobile 7 dias e WebView 60 min por defeito. Inicia em Observe e requer Enforcement + teste dinâmico para PASS. |
| v5.0.0-7.3.2 | L2 | PARTIAL / ROLLOUT | 2.2.331 adiciona absolute lifetime: BO/Desktop privilegiado 12 h, mobile 30 dias e WebView 24 h por defeito; refresh mobile é limitado à baseline absoluta. Requer Enforcement e teste de expiração/reutilização para PASS. |
| v5.0.0-7.4.1 | L1 | PASS / VERIFY | Mobile possui revogação e logout; WordPress usa sessão core. Requer teste de reutilização após logout. |
| v5.0.0-8.2.1 | L1 | PASS / VERIFY | Capabilities FieldFlow e permission callbacks server-side. AJAX legado reforçado na 2.2.317. |
| v5.0.0-8.2.2 | L1 | PASS / VERIFY | Route scope, owner e assignment validados no servidor. Requer pentest IDOR/BOLA sistemático. |
| v5.0.0-8.2.3 | L2 | PARTIAL | Há minimização em vários endpoints, mas falta inventário completo de campos por role e objeto. |
| v5.0.0-8.3.1 | L1 | PASS / VERIFY | Decisões críticas são tomadas em PHP/REST/AJAX, não apenas no JavaScript. |
| v5.0.0-8.4.1 | L2 | PARTIAL | Scope client/project é aplicado amplamente. Requer suite cross-tenant completa antes de PASS. |
| v5.0.0-13.2.4 | L2 | PASS / VERIFY | 2.2.325 centraliza os dispatch HTTP em `OutboundHttp`. A 2.2.380 mantém `wp_safe_remote_request` por defeito e adiciona apenas uma exceção pinned para a Azure Function fixa de licenciamento, ainda com HTTPS, porta 443, host exato, TLS, zero redirects e controlos SSRF próprios. Requer DAST SSRF externo, incluindo teste específico ao bypass pinned. |
| v5.0.0-13.3.1 | L2 | PARTIAL / EXTERNAL | Segredos não são expostos ao front, mas não existe evidência de key vault/secrets manager dedicado. |
| v5.0.0-14.1.1 | L2 | PARTIAL | Privacy Governance e categorias de retenção existem; falta inventário formal consolidado de classificação. |
| v5.0.0-15.1.2 | L2 | FAIL / DOCUMENTATION | Ainda não existe SBOM formal e versionado para a release de certificação. |
| v5.0.0-15.3.2 | L2 | PASS / VERIFY | 2.2.325 desativa redirects por omissão em todos os clientes credential-bearing. O único fluxo com redirects permitidos é media remoto sem credenciais, com cada hop revalidado. Requer teste dinâmico. |
| v5.0.0-16.2.5 | L2 | PASS / VERIFY | Audit Sanitizer redige tokens, passwords, secrets, cookies e payloads sensíveis. |
| v5.0.0-16.3.1 | L2 | PARTIAL | Tentativas de autenticação são monitorizadas, mas é necessária prova de cobertura de todos os sucessos e falhas. |
| v5.0.0-16.3.2 | L2 | PASS / VERIFY | 2.2.321 observa centralmente recusas REST/AJAX/admin-post e distingue autorização real de sessão expirada, autenticação, cross-scope, rate limit e CSRF/nonce. A 2.2.323 acrescenta drill-down por endpoint/ação, status e reason code para investigar recusas reais sem payload funcional; a 2.2.324 permite exportar essa evidência em JSON sanitizado para revisão externa. Requer teste dinâmico final para confirmar que todos os handlers de autorização usam status HTTP correto. |
| v5.0.0-16.4.1 | L2 | PASS / VERIFY | Payloads de auditoria são sanitizados antes de persistir. |
| v5.0.0-16.4.3 | L2 | FAIL / EXTERNAL | Logs permanecem no mesmo ambiente lógico. É necessário forwarding seguro para sistema separado. |
| v5.0.0-16.5.1 | L2 | PASS / VERIFY | Endpoints principais devolvem erros funcionais sem stack traces. Requer DAST para confirmação. |

## MFA e política de sessões, 2.2.331

A 2.2.331 introduz uma fundação de autenticação forte com rollout deliberadamente não destrutivo. `mfa_mode` e `session_mode` começam em `observe`; instalar a release não bloqueia utilizadores nem encerra sessões existentes. O administrador deve primeiro configurar TOTP, guardar os recovery codes e testar o challenge antes de mudar MFA para `enforce`.

Para contas com `routespro_manage`, o challenge MFA é aplicado depois da autenticação por password e antes do acesso administrativo/privilegiado no BO, Desktop e front web. Isto preserva a página pública `/login/` usada pela instalação e evita acoplar o segundo fator a um formulário de login específico. A WebView nativa é excluída deste challenge porque possui sessão isolada própria.

Os segredos TOTP são gerados com CSPRNG e cifrados pelo `SecretVault`; recovery codes são persistidos apenas como hashes e consumidos uma única vez. Existe proteção contra replay do mesmo contador TOTP e rate limiting de tentativas. A ativação de Enforcement MFA só é aceite quando a conta que altera a política já tem MFA ativo e a sessão atual foi verificada.

A política de sessão cobre BO/Desktop privilegiado, mobile e WebView com idle timeout e absolute lifetime. O Enforcement de sessão regista o instante de ativação e usa-o como baseline mínima do limite absoluto para sessões já existentes, evitando logout em massa apenas por idade anterior à política. Sessões efetivamente inativas podem expirar quando ultrapassam o idle timeout.

O login mobile já aceita `mfa_code` para contas privilegiadas, mas `mobile_privileged_mfa` permanece OFF por defeito até existir UI correspondente no FlutterFlow. Por isso, a implementação da 2.2.331 não deve ser considerada evidência de MFA completo em todos os canais privilegiados enquanto essa opção não for ativada/testada ou o acesso mobile privilegiado não for restringido. Merchandisers normais não recebem MFA nesta fase.

## Classificação da telemetria de recusas

A partir da 2.2.321, `401`, `403` e `429` deixam de ser interpretados como uma única classe de "authorization denied". A telemetria distingue sessão ausente/expirada, falha de autenticação, autorização negada, tentativa cross-scope, rate limiting, CSRF/nonce, política/entitlement e outras recusas. A classificação usa apenas metadados técnicos já permitidos e não altera a resposta do endpoint. Registos agregados criados na 2.2.320 são classificados em leitura sem migração destrutiva.


## Drill-down das recusas de autorização

A partir da 2.2.323, recusas classificadas como `authorization_denied` podem ser analisadas no BO por superfície técnica, endpoint/ação, método, status HTTP, reason code sanitizado, ocorrências autenticadas/anónimas, número de atores distintos e janela temporal. O drill-down é apenas leitura sobre `routespro_security_events`, não reescreve o histórico e não mostra IDs individuais nem payloads funcionais. Registos antigos sem reason code são explicitamente marcados como legado para evitar conclusões indevidas. A 2.2.324 acrescenta exportação JSON sanitizada desta evidência para revisão externa, sem payloads brutos, credenciais, IPs, device IDs ou IDs individuais de utilizador.


## Outbound HTTP / SSRF / redirect policy

A partir da 2.2.325, os 21 pontos de dispatch HTTP identificados na baseline passam pelo `RoutesPro\Security\OutboundHttp`. O guard força HTTPS, TLS verification, porta autorizada, bloqueio de localhost e redes privadas/reservadas, host allowlist e limites de timeout/resposta. APIs oficiais usam hosts fixos; integrações configuráveis ficam vinculadas ao host exato guardado pelo administrador. Redirects são zero por omissão para pedidos com credenciais. O único caso com redirects permitidos é a leitura de media remoto para PDF Performance, sem credenciais, e cada hop é revalidado. Na 2.2.380 existe uma exceção estritamente limitada ao hostname fixo da Azure Function de licenciamento FieldFlow quando `wp_http_validate_url()` produz um falso bloqueio. Nesse caso continuam obrigatórios HTTPS, porta 443, host exato em allowlist interna, TLS, zero redirects e bloqueio de redes privadas/reservadas quando a resolução DNS está disponível. Endpoints configuráveis genéricos continuam no caminho `wp_safe_remote_request()`. Bloqueios são observados sem persistir URL completo, query string ou segredos.

## Integridade de navegação administrativa

A 2.2.326 fecha uma inconsistência de apresentação entre BO e Desktop. O submenu agrupado do WordPress passa a reordenar fisicamente os itens por grupo e existe um integrity pass tardio para as páginas críticas de Sistema/Governação. Esta alteração não muda autorização nem entitlement; melhora a descobribilidade e a consistência operacional das superfícies de controlo usadas durante a verificação ASVS.

## Evidence pack de Saúde do Sistema

A 2.2.327 adiciona uma exportação JSON sanitizada de Saúde do Sistema. O ficheiro correlaciona checks ativos, Offline First agregado, eventos críticos/warning de observabilidade, Load Readiness por categoria, queue health, processos overdue, cron, Production Manifest, Release Gate e estado do SSRF Guard. O export não contém payload funcional, credenciais, IPs, device IDs, IDs individuais de utilizador, argumentos de jobs ou erros brutos do dispositivo. Serve como evidência técnica e suporte de triagem, sem alterar o estado ou o comportamento dos subsistemas observados.

## Evidência de integridade da release

A partir da 2.2.320, `production-manifest.json` contém SHA-256 agregado e hashes individuais dos ficheiros do pacote. O Release Gate recalcula a instalação e falha em caso de versão, contagem, hash, ficheiro ou symlink inesperado divergente. O checksum SHA-256 do ZIP permanece uma evidência externa separada e deve ser conservado com o evidence pack.

## Bloqueadores antes de uma avaliação externa L2

1. MFA obrigatório ou arquitetura equivalente formalmente aceite para todos os acessos aplicáveis.
2. Política e implementação de inactivity timeout e absolute session lifetime.
3. Antivirus/malware scanning para ficheiros recebidos de fontes não confiáveis.
4. Protected Media sair de Shadow Mode para enforcement seguro, após análise das observações reais.
5. Log forwarding para SIEM ou sistema logicamente separado, com alerting.
6. Evidência do host para TLS 1.2/1.3, CSP, HSTS, cookies e restantes headers aplicáveis.
8. SBOM e política de vulnerabilidades/dependency lifecycle.
9. Inventário criptográfico, classificação de dados e documentação de controlos ASVS.
10. Testes dinâmicos cross-tenant, IDOR/BOLA/BOPLA, CSRF, rate limiting e session invalidation.

## Próxima sequência recomendada

1. preparar MFA e session policy;
2. adicionar malware scanning compatível com WordPress;
3. rever Shadow Mode e preparar Protected Media enforcement apenas para media novo;
4. gerar SBOM e evidence pack;
5. executar DAST/pentest, incluindo SSRF/outbound, e atualizar esta matriz requisito a requisito.

## Qualidade dos sinais de Saúde do Sistema, 2.2.328

A 2.2.328 não apaga evidência nem reduz severidades históricas. A camada de health/readiness passa a distinguir estado operacional corrente de histórico de auditoria: conflitos offline são separados de falhas reais, críticos antigos continuam abertos mas não representam automaticamente incidente ativo, 4xx são observados como `client_error` em vez de falha de servidor e falhas de processos recuperadas deixam de degradar o score corrente. O evidence pack também normaliza mensagens mobile e redige paths locais. Esta alteração melhora a fiabilidade da evidência usada em V16 sem converter histórico em falsos `PASS`.
## Compatibilidade de anexos de mensagens, 2.2.329

A 2.2.329 mantém os anexos de mensagens separados das exportações Analytics. Documentos usam URLs temporárias assinadas e uma cadeia de abertura compatível com WebView/nativo; imagens usam visualização inline assinada e download explícito. A resolução de URLs CDN/proxy continua confinada a ficheiros cujo `realpath` termina dentro do diretório local de uploads. Não há alteração de autorização, schema ou superfície REST.



## Compatibilidade de transporte binário de anexos, 2.2.330

A 2.2.330 muda apenas o transporte do link temporário já autorizado: novos anexos de Mensagens usam `admin-post.php` como endpoint binário canónico, mantendo HMAC, expiração e contenção local do ficheiro em uploads. O endpoint antigo na raiz permanece apenas para links temporários já emitidos. O streaming desativa transformações/compressão quando possível, envia `Content-Length` exato e documentos como `application/octet-stream`, reduzindo risco de corrupção por proxy sem ampliar a autorização nem a superfície de dados.

## Addendum 2.2.341, Azure Secure Connector Phase 2

A release 2.2.341 introduz autenticacao HMAC-SHA256 v1 no novo canal Cloud. Timestamp, tenant, instalacao, Event UUID e SHA-256 do corpo fazem parte da canonical string assinada. O endpoint Azure correspondente rejeita replay fora da janela configurada, alteracoes do body e divergencias entre headers assinados e envelope.

O segredo HMAC e protegido pelo `SecretVault` no WordPress e pelo Azure Key Vault no lado Cloud. Os campos sensiveis principais da pagina de Integracoes deixam de devolver o valor guardado ao HTML depois de persistidos, reduzindo exposicao acidental por screenshots, DOM ou autofill.

O Azure SQL da Fase 2 e desenhado para Microsoft Entra-only authentication. A Azure Function usa Managed Identity e recebe apenas permissao `EXECUTE` sobre as stored procedures de health e ingestao. O Cloud Sync permanece opt-in e desligado por defeito.

## Addendum 2.2.342, Azure Data Hub Phase 3A

A release 2.2.342 mantem o canal HMAC-SHA256 v1 e acrescenta materializacao de Submissoes no Azure SQL. O evento bruto continua preservado em `ff_ingest_events`, enquanto as tabelas analiticas recebem apenas o estado necessario para reporting.

O backfill historico e opt-in e executado em lotes. O processo nao volta a disparar hooks operacionais de submissao, reduzindo o risco de duplicar webhooks, faturacao, notificacoes ou outras integracoes. Event UUID deterministico e a constraint de idempotencia Azure permitem repeticao segura do processo.

A Azure Function continua sem acesso direto generico a tabelas. A identidade gerida permanece limitada a `EXECUTE` nas stored procedures autorizadas; a nova materializacao e chamada internamente por `sp_ff_ingest_event` sob ownership chaining do schema `dbo`.


## Addendum 2.2.343, Backfill Dispatcher Hardening

A release 2.2.343 não altera autenticação, endpoints, payload HMAC nem privilégios Azure SQL. O processamento manual do backfill passa a invocar de forma limitada o mesmo dispatcher `AzureSyncFoundation::dispatch` que já executa validação da configuração, envio HMAC, retries e transição de estado. Não é introduzido um segundo canal de transporte. O botão de recuperação exige o mesmo contexto administrativo e nonce do Data Hub 3A. O WP-Cron mantém-se como fallback e mecanismo de retry.

## Addendum 2.2.344, Azure Data Hub Phase 3B

A release 2.2.344 não cria novos canais externos. O backfill operacional e os eventos live de Visitas usam exclusivamente `AzureSyncFoundation` e `AzureConnectionManager`, preservando HMAC-SHA256 v1, Secret Vault, SSRF guard, retries e idempotência. O enriquecimento de `visit.completed` ocorre depois do EventBus central e apenas para a entrega Azure, evitando mudanças silenciosas nos contratos consumidos por Webhooks, Billing ou Usage. Os snapshots de Lojas excluem contactos pessoais e limitam-se a dados operacionais e geográficos necessários a reporting. A Managed Identity mantém privilégio mínimo, porque o schema 1.2.0 é materializado internamente por `sp_ff_ingest_event` sem conceder permissões diretas adicionais sobre tabelas.


## Addendum 2.2.345, Visit Snapshot Integrity Hardening

A release 2.2.345 endurece apenas o backfill operacional local de Visitas. Paragens históricas sem rota pai deixam de bloquear o cursor e são excluídas do universo elegível, com contagem explícita no BO. Não existe qualquer ampliação de permissões, endpoints, secrets ou dados transmitidos. O transporte HMAC, a Azure Function e o schema Azure 1.2.0 permanecem inalterados. A correção reduz risco operacional ao impedir loops de backfill causados por dados legados inconsistentes, preservando idempotência e ausência de efeitos laterais.
## Addendum 2.2.347, Phase 3 Final Stock Auto Runner

A 2.2.347 não introduz novos endpoints públicos. O Auto Runner utiliza apenas `wp_ajax_` autenticado, nonce dedicado e capability administrativa. O processamento mantém chunks limitados, lock exclusivo, cursor persistente, backpressure da fila e paragem automática perante jobs definitivos. O canal HMAC, secrets, Azure Managed Identity e schema Data Hub 1.3.0 permanecem inalterados.
## Addendum 2.2.348, iOS runtime hardening

- O upload de media em iOS fica associado a gesto explicito e ao file chooser nativo da WKWebView.
- O caminho AJAX nativo preserva nonce, cookies de sessao e cabecalho XMLHttpRequest.
- Respostas HTML inesperadas sao sanitizadas antes de apresentacao ao utilizador.
- A alteracao nao aumenta privilegios nem expoe novos endpoints publicos de dados; o handler reutiliza as validacoes do submit existente.
## Addendum 2.2.349, Team Inbox Transient DB Recovery

- Respostas HTML inesperadas de endpoints AJAX deixam de ser apresentadas ao utilizador final.
- Retry automático limitado a GET idempotente; POSTs não são repetidos automaticamente.
- Mensagens técnicas de infraestrutura são substituídas por erros controlados.



## 2.2.350 Phase 4A addendum
The reporting plane is separated from the ingestion plane. Azure SQL reporting access is exposed through a dedicated schema and Microsoft Entra identities with explicit scopes. No reporting credential is persisted in WordPress. This reduces credential exposure and prevents the Power BI integration from inheriting the Azure Function managed identity's ingestion permissions.

## Addendum 2.2.352, Client Route Export Scope Hardening

A release 2.2.352 abre o export de rotas já existente ao Portal Cliente sem abrir um novo canal anónimo. O endpoint continua autenticado via `admin-post.php`, usa nonce dedicado para o Portal Cliente e aplica `Permissions::scope_sql` no servidor. Os filtros de cliente, campanha e operativo apenas estreitam o conjunto permitido e nunca substituem o scope de autorização. Os entitlements/quota de exportação permanecem ativos. A alteração de layout em Campanhas PDV é exclusivamente de apresentação e não altera permissões, dados ou contratos Cloud.

## Addendum 2.2.353, Client Route Export Ticket Recovery

A release 2.2.353 corrige o transporte das exportações de rotas sem reduzir autorização. O ficheiro CSV/XLS/PDF é gerado apenas durante uma sessão WordPress autenticada, depois de validar nonce, scope, entitlement e quota. Desktop e WebView mobile recebem apenas um ticket opaco temporário para o ficheiro já autorizado; o downloader nativo não recebe cookies WordPress nem credenciais. O ticket expira em 10 minutos e o transient e ficheiro temporário são removidos após streaming. A validação BO/Portal Cliente passa a derivar do nonce recebido, evitando falhas quando um administrador abre o Portal Cliente sem aceitar um nonce de contexto diferente. Azure, Power BI, HMAC, Data Hub e os fluxos iOS de câmara/galeria permanecem inalterados.


## Release 2.2.471, Analytics Mobile Full-Width & Download State Continuity

- Contract: `fieldflow-analytics-mobile-fullwidth-download-state-v1`.
- Mobile Analytics full-width is scoped to the Analytics panel and does not alter Flutter or other front modules.
- Operational front and Client Portal Analytics share the same mobile full-width and page-owned vertical scroll policy.
- Prepared evidence ZIP downloads use the explicit mobile bridge path when available, with dashboard export classification disabled.
- Mobile nested vertical scroll regions are removed from Analytics reading surfaces while horizontal data rails remain bounded.
- Build-time verifier: `tools/verify-analytics-mobile-fullwidth-download-state.php`.

## Release 2.2.472, Evidence ZIP System Browser Handoff

- Evidence ZIPs prepared by Analytics no longer traverse the generic Flutter `fieldflowDownloadRequest` path.
- Mobile handoff uses the existing `fieldflowOpenExternal` system-browser bridge and the opaque capability-ticket URL issued by the server.
- Evidence downloads use dedicated `fieldflow.evidence.download.*` events instead of generic export events, preventing the shell from showing a false success followed by a native export failure.
- Operational Analytics and Client Portal share the same `downloadPrepared()` path.
- Browser fallbacks are explicitly marked to avoid global WebView re-interception.
- No Flutter code, database schema, form schema, licensing enforcement or route logic changes are introduced.
- Release Gate contract: `fieldflow-evidence-zip-system-handoff-v1`.

## 2.2.493 Modernization Stable Closure

The final modernization closure preserves ASVS-relevant trust boundaries while expanding progressive React. The React media surface invokes existing native capture controls; it does not own credentials, upload transport, IndexedDB, recovery, durable submission or post-COMMIT effects. Release Gate 1.44.0 and `fieldflow-modernization-stable-closure-v1` require the canonical ownership markers and the 2.2.492 → 2.2.493 continuity chain before Stable certification.
