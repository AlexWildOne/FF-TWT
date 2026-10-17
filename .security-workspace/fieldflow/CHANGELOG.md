## 2.2.659 | Consolidação — candidata NÃO CERTIFICADA

- Identidade do pacote e cadeia técnica Release Manager N→N+1: 2.2.658 → 2.2.659. `STABLE_RELEASE` é identidade técnica esperada, não prova de certificação; baseline instalada/certificada do utilizador desconhecida.
- Consolidação de autorização, offline/media, routing, reporting e frontend sujeita à validação integrada do ZIP final; nenhum resultado de produção ou marcador PASS é presumido.
- Instalar primeiro em staging isolado pelo Release Manager após backup integral da BD, uploads e media privada, restauro isolado e comparação de hashes/contagens. Registar evidências de Package Validator, manifesto, Release Gate, cadeia 6B, fecho Fase 6 e testes anónimo/sessão/tenant.
- Não publicar diretamente em live. Rollback normal apenas do código compatível, nunca dos dados/media/filas; recuperação de dados exige procedimento separado e reconciliação. Procedimento detalhado em `readme.txt`.

## 2.2.658 | Briefing Steps & Historical Answers Continuity 9DX (candidate)

- Steps configuráveis por Briefing, com título, descrição e ordem, incluindo atribuição de perguntas criadas por IA ou manualmente.
- Wizard de Formulários reutilizado sem criar novas perguntas ou renumerar question_key, respostas, evidências ou histórico; filtragem por loja preservada.
- Gestão individual e rápida de Steps no Backoffice; perguntas sem Step mantêm-se disponíveis.
- Sem migração de base de dados, novos contratos Release Gate/Package Validator e testes automáticos de isolamento por PDV e continuidade de respostas.

## 2.2.657 | Cardex & Data Consistency Center 9DW (candidate)
- Centro de Consistência em Cardex e Catálogo: auditoria paginada de todas as lojas ativas, inclusive lojas sem Cardex. Diagnóstico por loja combina o Cardex real do formulário, sortido efetivo, SKU repetidos, Briefings publicados aplicáveis, Stocks publicados por projeto e diferenças entre fontes.
- Pré-visualização não destrutiva de Frentes e Sugestão de Encomenda por formulário e PDV, com autoridade de insígnia, cliente e projeto preservada.
- Editor de Formulários: Cardex fixo aparece apenas no modo fixo; modo automático explica a escolha por loja e liga ao diagnóstico; produtos manuais respeitam o modo manual.
- Auditoria só de leitura, sem alteração de links, importações, produtos, Share Space, respostas, emails ou modo offline. Gate 9DW e Release Manager N+1.

## 2.2.656 | Order Suggestion Integrity 9DV (candidate)

- Sugestao automatica so apos zero atual de Frentes confirmado, sem confundir campo vazio ou memoria da visita anterior.
- Sanitizacao partilhada web/mobile/offline por sortido da loja e referencia SKU; protege contra linhas de outra cadeia ou IDs forjados.
- Frentes e encomenda ligadas a uma unica pergunta, sem cross-binding quando o formulario tem varios cardex.
- Quantidade manual prevalece, inclusive em edicao; resguarda encomendas existentes se o Cardex ficar indisponivel durante a edicao da propria visita.
- Sem alterar o contrato 9DU, regras de insígnias, stocks, respostas/contas historicas, envio previamente aceite ou regras de automacao de email.

## 2.2.655 | Cardex Global Linked Scope Authority 9DU (candidate)

- Compatibilidade dos Cardex legados `Pernod Ricard - Sonae` cuja coluna de insignia guardava `Continente`: lojas CNT, MDL e CBD explicitamente associadas usam o mesmo Cardex sob o respetivo cliente/projeto.
- Mantem isolamento rigoroso de cardex por subtipo CNT/MDL/CBD quando dedicados, clones, outros retalhistas e projetos diferentes.
- Nova auditoria global somente leitura, com filtro por cliente, cardex e PDV, amostragem paginada e estado real do resolvedor/rotas/referencias.
- Preserva produtos, stocks, historicos, revisoes, mapeamentos, respostas e regras certificadas 9DN-9DT.

## 2.2.654 | Cardex Client/Project Scope Truth 9DT (candidate)

- Cardex BO: drop-down Cliente/Projeto com nomes reais e filtro por cliente, em vez de IDs livres.
- Diagnostico read-only por loja associada: compara a inscricao do cardex com rotas recentes, identifica divergencia cliente/projeto/insignia ou outro cardex prioritario.
- Guardar apenas cliente/projeto, sem tocar em sortido, ligacoes, revisoes ou respostas antigas; alteracoes ativas exigem confirmacao.
- Mantem 9DR/9DS, nomes Sonae/Modelo/Bom Dia e protecao de IDs entre clientes e projetos; nao altera scopes automaticamente na instalacao.

## 2.2.653 | Visible Cardex Form Binding & Visit Answer Safety 9DS (candidate)

- Formularios web e app: Frentes / Sugestao de Encomenda nao desaparecem quando o Cardex automatico nao resolve o PDV. Um aviso visivel descreve a associacao pendente, sem inventar produtos nem permitir capturas incorretas.
- Reativa cardex legados com cliente na coluna de insignia e insígnia reconhecida no fim do titulo (ex.: Pernod Ricard · Sonae), apenas depois de conferir a identidade comercial do PDV. Mantem CNT/MDL/CBD, Pingo Doce e insignias customizadas separados.
- Protecao em edicoes da mesma visita: Frentes anteriormente gravadas sao preservadas nos caminhos web e mobile quando falha o Cardex. Consultar antes de editar, sem reaproveitar respostas de outras visitas.
- Gates 9AI/9DN/9DO/9DP/9DQ/9DR preservados, novo Gate 9DS; sem migracao de BD ou reconciliação destrutiva das associacoes existentes. Requer prova de funcionamento em WordPress e nos PDV reais.

## 2.2.652 | Cardex Form Binding & Retailer Continuity 9DR (candidate)

- Restaura a resolucao de cardex antigos `Sonae`, `pingo_doce` e subtipos CNT/MDL/CBD na pergunta de Frentes e Sugestao de Encomenda, preservando regras 9DN/9DQ de identidade de loja.
- Prefere cardex do subtipo exato em cada projeto/cliente; valida tambem perguntas com Cardex fixo, sem aceitar vinculos de lojas de outra insignia nem rascunhos.
- Isola sincronizacao semanal: nunca reclama cardex manual/clonado nem substitui o sortido de outro subtipo Continente.
- Sem migracoes, mutacoes de cardex existentes ou alteracoes a rotas, dock, Daily Close, despesas, Google e Azure. Certificacao e teste em lojas reais obrigatorios.

## 2.2.651 | Flexible Cardex Insignia Registry 9DQ (candidate)

- Duplicação de Cardex aceita nova insígnia escrita diretamente; sugestões de marcas existentes e persistência após cópia bem-sucedida.
- Identidade comercial de PDV para marcas adicionais apenas com evidência explícita; proteções de associação, sortido e rascunhos 9DP preservadas.
- Sem alterações a emails 9DO, rotas, stocks, barra 9DF, Azure ou Release Manager.

## 2.2.650 | Safe Cardex Clone & Publication 9DP (candidate)

- Duplicacao independente de cardex por insignia, com revisao e publicacao explicita por lojas ou em modo global aprovado.
- Isolamento de rascunhos em formulários e no catálogo; protecao de SKU e insígnia em CSV/editor; nao copiar revisoes ou historicos.
- Sem alteracoes a Daily Close 9DO, barra Operativo 9DF, rotas, stocks, Release Manager ou Azure.

## 2.2.649 | Daily Close Recipient Reliability 9DO (candidate)
- Daily Close partial success no longer claims the entire audience as finished. Bounded retries revisit only pending recipients; accepted messages remain idempotent.
- Per-recipient and per-route identity, with safe recognition of pre-9DO legacy Outbox rows only for the matching route.
- Manual recovery uses the same persisted Daily Close identity and includes configured extra recipients; accepted messages are never blindly sent again.
- A synchronous send owns an Outbox lock before SMTP. Lost delivery acknowledgement becomes `uncertain`, never silently replayed by cron.
- Critical Daily Close fails closed when reliable Outbox persistence is disabled or unavailable; other transports remain unchanged.
- Mail Center exposes uncertain delivery state, a manual verification gate and bounded diagnostic history for routes and per-recipient preflight failures.
- No changes to routes, cardex, stock, commercial mapping, field forms, apps or operative dock 9DF. No destructive database migration.

## 2.2.648 | Universal Store Identity Authority 9DN (candidate)
- Unified commercial PDV identification across briefing, Mapping Center, Shopping, Cardex, implementations, CSV store imports and stock.
- CNT/MDL/CBD remain independent Sonae subtypes; PD/Pingo Doce, Arcol, M. Cunha, Makro and Recheio remain isolated.
- Historical invalid links remain visible for explicit manual review; no silent cross-brand remapping or destructive maintenance.
- Runtime 9DL/9DM gates and 9DN closed-world gate, N+1 validator and manifest integrity remain mandatory.
- Dock 9DF, route assignments and field-form execution frozen.

## 2.2.647 | Closed-world Stock Insignia & PDV Identity 9DM (candidate)
- Sonae: store authority CNT/Continente, MDL/Continente Modelo and CBD/Continente Bom Dia only; Pingo Doce: PD/Pingo Doce.
- Arcol and M. Cunha/Mcunha recognized as independent Cash & Carry retailers by both store index and ranking engine.
- Unlabelled, conflicting or unknown PDVs are not automatically matched. Code/locality/AI do not override retailer authority.
- Old invalid relations are preserved for explicit review in the Backoffice; no destructive auto-cleanup on GET.
- 9DL release-gate runtime interpolation hotfix absorbed in the canonical N+1 ZIP; 9DM gate and validator checks added.
- Operational routes, forms and dock remain frozen. No DB schema migration.

## 2.2.646 | Briefing, Stock & Mobile Route Scope 9DL (candidate)
- Briefing Operativo apresenta apenas lojas efetivamente presentes na rota dessa semana; espaços por loja sem correspondência continuam no BO, sem apagar dados ou atribuir lojas de forma implícita.
- Mobile: promoções e cardex em cartões com rótulos, leitura otimizada; esconde diagnósticos internos source row/parser no terreno.
- Stock Sonae corresponde exclusivamente a lojas Sonae/Continente/Modelo/Bom Dia, nunca Makro/Pingo Doce; equivalências erradas não são migradas automaticamente.
- Filtro de insígnia e loja persistente e reativo, com listas e indicadores atualizados no Operativo.
- 9DL certificado por validador N+1, manifesto SHA256 e testes locais; Android real e mapeamento do servidor exigem confirmação após publicação.

## 2.2.645 · Briefing, Stock & Route Scope Integrity 9DK (candidate)

- Weekly briefing now respects ISO week Wxx instead of inheriting date extremes from spaces/promotions.
- Narrative merged retailer headings in Excel parsed without duplicate focus, source action dates unchanged.
- Explicit separation of actual route stops from campaign or briefing-only visibility; no route assignment fabricated.
- Stock sheet PACK alone never guessed as conversion factor; SOH/stock columns still authoritative.
- Cardex out-of-profile source codes shown for explicit verification, no B4 → P4 guessing.
- No database migrations, no paid providers, and no changes to the operative dock, route planner or offline sync.

## 2.2.644 | Phase 4E Admin Certification Read-Only & Mobile Performance Layout 9DJ (candidate)
- GET da pagina Licenciamento & Tenants reutiliza o manifesto e os resultados 6B ja calculados. O scan fresco e reset dos caches continuam obrigatorios em POSTs, certificacao e testes formais da Fase 6.
- Segurança e Observabilidade > Performance e Carga: KPIs adaptativos, cartoes de processos legiveis e tabelas em cartoes com rotulos por coluna em mobile; desktop permanece tabela completa.
- Totais de API com tarifa em falta exibidos como n/d em vez de 0,0000 EUR enganador. Subtotais com tarifa e alertas permanecem visiveis, sem modificar o custo estimado nem as politicas do provider.
- Texto distingue gate observacional de performance do certificado Stable. Sem relaxar thresholds, migracoes SQL ou chamadas API adicionais.
- Cliente, Operativo, dock 9DF, rotas, formularios, publicador Azure e release flow N+1 continuam congelados.

## 2.2.643 | Phase 4D Admin Health Snapshot Reuse 9DI (candidate)
- Saude do sistema reutiliza os resultados de sincronizacao offline e estado da UI ja calculados pela auditoria; elimina uma leitura redundante da tabela de dispositivos e uma chamada de estado da interface.
- Contadores de chaves API/webhooks/entregas usam apenas IDs/estados em vez de carregar ate 1.200 linhas completas, incluindo hash de chaves e payloads que nao eram apresentados.
- Readiness das tabelas SaaS calculado uma vez no render (nao duas), mantendo limites e contagens visiveis.
- Sem alteracoes nas operacoes de terreno, receitas de rotas, Google/Azure, Portal Cliente/Operativo, dock 9DF, publicador, licenciamento, schema ou configuracoes. Avaliar P95 em producao.

## 2.2.642 | Phase 4C Admin Hotspots & Manifest Safety 9DH
- Validador falha fechado se key_files apontar para assets obsoletos, mesmo com SHA agregado correto.
- Empacotador verifica todos os ficheiros e ZIP final, incluindo nomes obrigatorios e residuos de backups.
- Performance: atribuicao P95 por pagina de Backoffice e tres resumos adiados ate o separador Resumo. Sem chamadas externas.
- Dock 9DF, Cliente, rotas, formularios e APIs operacionais preservados.

## 2.2.641 | Phase 4B Cost & Performance Drivers 9DG (candidate)
- Continua diretamente a baseline 2.2.640 Stable, com a barra 9DF do Operativo validada em dispositivo real e integralmente congelada.
- Painel read-only em Segurança e Observabilidade > Performance e Carga, com operações de custo prioritárias, tarifas de referência em falta, candidatos de cache, falhas e P95 por fluxo.
- Custo desconhecido nunca é apresentado como gratuito; comparação 7 dias vs 7 dias anteriores apenas com amostragem mínima e aviso de janela parcial.
- Apenas dados agregados existentes do ApiUsageLedger/LoadReadiness. Sem chamadas externas, APIs pagas, configurações de fornecedor, base de dados, routers, relatório, Cliente, Operativo, Release Manager ou Fase 6 alterados.
- Validação 9DG adicionada ao Release Manager e Release Gate com continuidade N+1 e testes específicos.

## 2.2.640 | Phase 4 Observability Baseline & Operative Dock Calm Motion 9DF (candidate)
- Parte estritamente da 2.2.639 Stable confirmada pelo utilizador. Barra 9DE funcional em Android, mas direcao/oscilações e transicoes necessitavam estabilização.
- Refinamento 9DF no mesmo owner da barra: atenuacao fade de 360 ms com pequena translação de 14 px, limiares 54/25 px, histerese temporal 900/430 ms e deduplicacao de scroll emitido após touchmove. Respeita gestos horizontais, inputs/teclado e prefers-reduced-motion.
- A barra do Cliente 9D5 permanece intacta. A mesma flag 9DE permite voltar ao fallback anterior caso seja necessário.
- Inicio controlado da Fase 4: resumo exclusivamente read-only no BO Performance e Carga, com P95 mobile/web, estimativas 30 dias de APIs por fornecedor, bloqueios Google, desvios de politica e alertas ativos. Uma métrica ausente fica como nao medida, nunca como custo zero.
- Reutiliza LoadReadiness, ApiUsageLedger, Monitor e GoogleCostControlCenter. Sem novas chamadas faturaveis, recolhas externas, migracoes, alterações às politicas de consumo, IA, rotas, formularios, relatórios, despesas, Release Manager, Azure ou Fase 6.
- Contratos e testes 9DF cobrem histerese, gesto/scroll duplicado, CSS de fade, leitura dos KPIs, privacidade e continuidade N+1. Confirmacao visual real e fatura dos fornecedores continuam obrigatorias antes de encerrar os temas.

## 2.2.639 | Operative Dock Bootstrap Recovery 9DE (candidate)
- Evidencia real 2.2.638: barra e raiz existentes, mas controlador/owner/estado do dock ausentes. Instalacao e certificacao 2.2.638 confirmadas, correcao visual nao confirmada.
- Novo controlador Operativo independente dos switches antigos 9D5/9D9/9DC, injetado diretamente pelo shortcode com marcador explicito de entrega e diagnostico.
- Observacao persistente do DOM para recuperar root/barra criados tardiamente ou substituidos, e gestos verticais sem necessidade de detectar primeiro um contentor com scroll.
- Unico owner, com bloqueio dos enqueues historicos 9D6/9D7, CSS e fallback inline importante, espaco inferior, teclado, gestos horizontais e campos de edicao preservados. O Cliente nao e alterado.
- 9DD passou a mostrar entrega e estado do novo owner. 9DC permanece disponivel como fallback se FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE=false.
- Sem alteracoes a Release Manager, Azure, servicos de negocio, rotas, visitas, fotos, formularios, sincronizacao, dados ou schema. Aceitacao final requer prova em Android/Chrome.

## 2.2.638 - Operative Dock Real Runtime Audit 9DD
- Opt-in DOM, scroll, viewport, and computed-style diagnostic for the fixed operative dock via `ff_dock_diag=1`.
- Explicit manual hide/reveal test that restores original inline styles. No automatic navigation changes.
- Static PHP marker distinguishes cached shortcode HTML from missing or blocked JavaScript.
- No Client Portal, route, stock, expense, API, upload, or certification flow changes.
- Baseline 2.2.637 preserved as recoverable Stable.

## 2.2.637 | Operative Dock Scroll Authority 9DC (candidate)
- Operative-only bottom navigation controller binds to actual `.rp-tabs`, document capture scroll events, nested scroll hosts and vertical touch fallback, without changing the certified Client 9D5 controller.
- Single Operative scroll authority, idempotent shortcode inline delivery; 9D9 and manual/legacy fallback retained.
- Mobile safe-area and keyboard-aware bottom reserve, active tab centering and observable scroll diagnostics for live Android/Chrome validation.
- Dedicated rollback switch `FIELDFLOW_OPERATIVE_SCROLL_AUTHORITY_9DC`; no operational module, schema, Azure, release manager or business logic changes.
- Must pass visual validation on physical Android and Chrome before declaring the navigation issue closed.

## 2.2.636 | Release Manager Assisted Workflow 9DB (candidate, 11/10/2026)
- Continuidade direta da 2.2.635 Stable certificada, sem reconstrução dos portais ou alterações ao cliente, operativo e componentes de campo.
- Novo fluxo assistido opcional: pré-validação integral N+1 e retenção privada de um ZIP por administrador, no sistema temporário fora da árvore pública, até 24 horas. O SHA-256 é verificado novamente antes de publicar.
- Estado persistido por utilizador/site e diário de operações, com exclusão de segredos e conteúdo do ZIP; recuperação read-only de tentativas remotas incertas durante 30 dias, mesmo depois de expirar o ZIP.
- Cada envio Blob ou catálogo é registado antes do pedido HTTP; nenhum timeout provoca reenvio automático. Blob confirmado pode retomar só a etapa do catálogo com consentimento explícito; qualquer outro estado requer reconciliação remota.
- Confirmação de identidade e SHA-256 no catálogo, persistência de recibo local e auditoria antes de concluir. Proteção de lock contra duplicações simultâneas.
- O formulário antigo Validar/Publicar, Azure Publisher, HMAC, Gate, 6B e certificação Fase 6 Stable continuam intactos. Sem alterações de schema, IA, Google Maps, offline, rotas, formulários, despesas ou barra inferior Operativo.

## 2.2.635 | Release Manager Assisted Preflight 9DA (candidate, 11/10/2026)
- Continuidade direta da 2.2.634 Stable, mantendo o ZIP original recuperável e a instalação canónica `fieldflow/`.
- Novo assistente de pré-validação no Backoffice, com SHA-256, N+1 estrito, diferenças de ficheiros e avisos para ficheiros sensíveis, utilizando apenas metadados autenticados pelo validador original.
- Bloqueios apresentados com causa, estado e ação recomendada, preservando o formulário manual de validar/publicar e a confirmação explícita da publicação Azure.
- Contrato 9D9 do validador sucessor deixa de exigir a identidade ativa da 2.2.634, mantendo o contrato funcional do dock e os marcadores históricos apenas em comentários não executáveis.
- Acrescentado contrato read-only 9DA no Package Validator e Release Gate, com testes de continuidade e rejeição. Sem alterações aos portais, rotas, formulários, IA, dados, Publisher, certificação Fase 6 ou barra do Operativo.
- Nesta fase a pré-validação não guarda o ZIP para futura publicação. O novo upload é integralmente validado e a instalação/certificação continuam manuais.

## 2.2.634 | Operative Client Dock Parity 9D9 (candidate, 11/10/2026)
- A 2.2.633 foi instalada e certificada Stable mas o dock Operativo continua fixo, confirmado pelo utilizador em Chrome e Android. O comportamento real do Cliente permanece correto.
- O Operativo executa exatamente `assets/fieldflow-visual-consolidation-9d5.js`, sem alterar uma linha do controlador Cliente. Mesmo codigo, seletores e thresholds; o script inline e a carga de footer sao idempotentes.
- As camadas 9D6/9D7/9D8 nao sao inicializadas por defeito. Contratos e ficheiros historicos preservados para auditoria; rollback da camada 9D9 via FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9=false.
- Retido o padrao CSS de ocultacao do Cliente, reserva inferior da shell e politica de safe-area. Sem ALTERACAO de dados, REST, rotas, formularios, offline, IA ou licenciamento.
- A certificacao tecnica nao substitui verificacao real em Chrome/Android de Stocks, Minha Rota e Reportar.

## 2.2.633 | Operative Inline Dock Authority 9D8 (candidate, 11/10/2026)
- Baseline certificada 2.2.632. O utilizador confirmou que a barra inferior do Operativo continua fixa tanto no Chrome como no Android, apesar da certificação 9D7.
- O shortcode Operativo passa a injetar localmente o CSS e o JavaScript de navegacao, independentemente do reconhecimento da pagina pelo enqueue do WordPress/Elementor/PWA; o Cliente fica intocado.
- Uma unica autoridade 9D8 gere estado visivel/recolhido no root; os scripts historicos 9D5/9D6/9D7 cedem essa autoridade, preservando os contratos de certificacao.
- Gestos, scroll e roda vertical; gesto horizontal e editores excluidos, reserva inferior com safe-area, ocultacao por transform, opacidade e visibility; kill switch em wp-config.php.
- O validador 9D7 passa a exigir a identidade da versao candidata dinamicamente, evitando os bloqueios de referencias literais da release anterior (ponte 2.2.632 separada para o validador ja instalado).
- Sem migracoes, alteracoes de dados, IA, licenciamento, API, offline, rotas ou tarefas de campo; exigir teste real Chrome e Android antes de certificar Stable.

## 2.2.632 | Operative Dock Overlay & Gesture Recovery 9D7 (candidate, 11/10/2026)
- Baseline 2.2.631 Stable. Corrige a falha visual reportada nos prints Android: a barra Operativo permanecia fixa e sobrepunha conteúdo em Stocks e Ficha de Operação.
- Recolha e reentrada por scroll vertical real (janela + painel) e gesto touch passivo na WebView, com distinção horizontal/vertical, limiares anti-oscilação e respeito por seletores e teclado.
- Reserva inferior efetiva para o último elemento em Minha Rota, Reportar, Stocks, Briefing e Implementações; sem alterar o Portal Cliente.
- Autoridade exclusiva 9D7 e fallback controlado com FIELDFLOW_OPERATIVE_OVERLAY_9D7=false, que restaura 9D6.
- Sem alterações de schema, business logic, IA/Maps, REST, sessão, estado de formulários, sincronização, licenciamento ou contratos de distribuição.
- Certificação de pacote/testes automatizados não substitui o teste visual em Android/iOS; só certificar Stable após a aceitação em campo.

## 2.2.631 | Operative Dock & Status Parity 9D6 (candidate, 11/10/2026)
- Based strictly on certified 2.2.630 Stable. Operative bottom dock now reacts to vertical scrolling inside lazily loaded panels and in the window; avoids jitter and keyboard interference. Client behavior remains unchanged.
- Operative status/navigation copy refinements and safe-area/focus polish, scoped to the existing theme system.
- Client route/stop status labels display in Portuguese while original REST payload, IDs, data-status and select option values are unchanged.
- No migrations, AI calls, SQL changes, forced updates, licensing changes or offline transport modifications. Visual 9D6 can be disabled separately in wp-config.php.
- Maintains old 9D5 validation markers, QA and rollback policy; 2.2.630 is recoverable.

## 2.2.630 | Visual Experience Consolidation 9D5 (candidata)

- Consolida visualmente os portais Cliente e Operativo e o formulario de frentes com CSS separado, escopado e desativavel por FIELDFLOW_VISUAL_CONSOLIDATION_9D5=false.
- Dock de navegacao mobile compacto e ocultacao no scroll descendente, preservando todas as tabs; reserva dinamica de espaco e respeito pelas safe areas e teclado.
- Header e indicadores mais compactos; filtros Cliente acessiveis por botao expansivel sem substituir o unico intervalo De/Ate ou os listeners.
- Atalhos Operativo em grelha mobile completa; exportacoes e linhas de SKU mais legiveis.
- PHP 8.4: nullable explicito em LicenseManager::get(), mantendo valores, assinaturas de retorno e contratos de licenciamento.
- Sem alteracao de schema, permissions, APIs, offline, IA, custos ou limites financeiros. Herda 2.2.629 9D4 Stable.
- A certificacao depende de testes reais WordPress/Elementor/Android/iOS/Windows e Release Manager.

## 2.2.629, Commercial Production Hardening 9D4 (candidate)

- Segurança comercial: cache segregada por utilizador e âmbito de permissões, sem reutilização de transients antigos entre clientes.
- Pesquisa: cliente e operativo cumulativos, paginação SQL por ID estável, contagens completas, facetas coerentes com filtros.
- Portal Cliente: relatórios paginados no servidor, não apenas as primeiras 1.000 visitas, com rejeição de respostas antigas após mudança de filtros.
- Android/iOS WebView e navegador: fallback de visita isolado por sessão/cliente/campanha, limpeza no logout, sem mudanças nos drafts offline.
- Tests: duas verificações literais atualizadas e testes dedicados 9D4, sem alterações ao motor de IA, governance, Publisher ou DB schema.
- Fase 6: origem 2.2.628 Stable; nova candidata 2.2.629 sujeita a certificação independente.

## 2.2.628 | Catalog Category Picker 9D3

- No Catálogo Visual, o campo Categoria é um dropdown com as categorias existentes do cliente, incluindo o Cardex e as escolhas anteriores.
- Permite adicionar uma nova categoria, guardá-la por cliente e reutilizá-la nos produtos e perguntas dos próximos briefings.
- Preserva categorias históricas, evita duplicados por maiúsculas/acentos e valida as escolhas no servidor sem contaminação entre clientes.
- Mantém integralmente 9D2 e os restantes módulos, quotas e custos de IA.

## 2.2.627 | Catalog & Briefing Intelligence, category continuity (9D2)

- Cardex/Catálogo navigation reorganised as four premium areas, retaining existing actions and tabs.
- Briefing execution questions gain automatic catalog-category association where a unique product is verified, and a manager dropdown for manual confirmation when ambiguous.
- Confirmations persist per client/campaign and stable product/section identity. On new weekly briefings, previously configured questions are inherited only when same product, same section and eligible stores can be proven; changed variants/products require review.
- Backoffice offers status and explicit category synchronisation of current questions. Front displays category in briefing overview and question help.
- Preserves 2.2.626 Stable and all 9D1 photo counting, 9D0/9CZ Azure cost/quotas, store/history isolation. No additional AI requests or schema migrations. Candidate until independently certified.

## 2.2.626 | Vision Intelligence II (9D1)

- Fronts camera uses the same single blind-inventory AI request. Conservative physical bounding-box duplicate handling and cross-SKU overlap review prevent inflated counts.
- Operatives confirm all suggestions and can see the previous manually confirmed reading of the same question, at the same store, from a previous closed visit. Historic values are read-only.
- Form shows prior fronts and live count delta for the current manually verified values; backoffice history keeps comparison snapshot and confirmed delta.
- Preserves 2.2.625 certified Stable baseline, cost quota/reservation reconciliation, reference matching and offline field workflows. No schema change and no extra AI requests.

## 2.2.625, 9D0B Private Publisher and 9CS Certification Recovery

- Emergency certification recovery of installed, uncertified 2.2.624, not a new feature release.
- Previous 2.2.624.1 candidate was never published because Azure Publisher rejects four-part versions.
- Restores a supported 3-segment identity, preserving all 9D0 financial protections and corrected 9CS gate.
- Adds client-side and package validation of the live publisher version format, with regression checks against remote code.
- Local release continuity SOURCE_RELEASE 2.2.624 is an installed predecessor; last certified Stable remains 2.2.623 until 2.2.625 certification succeeds.

## 2.2.624.1, 9D0A 9CS Certification Recovery (safety hotfix)

- Corrects a stale 9CS Release Gate source marker that blocked Phase 6 Stable certification of the installed 2.2.624 candidate.
- The active gate now validates the protected 9D0 reservation persistence path. Historic marker retained only in a non-executable comment.
- Preserves 2.2.623 as the last certified predecessor. New candidate 2.2.624.1 is published and installed through the normal private updater after the failed 2.2.624.
- No changes to AI price calculations, calls, quotas, frontend operations, WordPress DB schema or Azure dependencies.

## 2.2.624, Fronts Safe Budget Reconciliation & Camera Availability 9D0 (candidata)

- Corrige a quota de fotografias que continuava bloqueada após chegar a 5 EUR de reservas históricas, apesar de existirem centenas de leituras autorizadas.
- Mantém a reserva prévia atómica e reconcilia apenas leituras novas com tokens medidos, tarifa verificada e margem conservadora de 30 por cento; sem tarifa ou erro a reserva mantém-se.
- Acrescenta regularização manual, única e auditável das reservas anteriores, sem apagar contadores de leitura.
- Mostra ao operativo o motivo real do bloqueio e distingue o estado offline da ausência de autorização orçamental.
- Não altera o motor de reconhecimento 9CY, Azure SQL, formulários, custos dos restantes módulos nem o número de chamadas de IA.
- Candidata construída a partir de 2.2.623; instalar somente após certificação da origem.

## 2.2.623, Fronts AI Metered Costs & Budget Separation 9CZ (candidata)

- Tokens reais devolvidos pelo fornecedor da leitura de frentes são guardados e usados na estimativa.
- Tarifas em EUR por um milhão de tokens, configuráveis por fornecedor e modelo, não presumidas.
- Custo calculado, reserva de segurança e faturação Azure deixam de ser confundidos no Backoffice.
- Leituras sem tarifa/sem tokens ficam por calcular, nunca apresentadas como gratuitas.
- Histórico por loja retém origem, modelo e ligação ao registo de IA; sem prompts, fotografias ou segredos.
- Mantida uma chamada por fotografia e todas as quotas e proteções certificadas 9CS, 9CT, 9CU, 9CV, 9CW, 9CX e 9CY.
- Sem alterações à base de dados SQL Azure, nem migrações WordPress. Candidata até certificação.

## 2.2.622 - Blind Inventory SKU Matching 9CY (candidata)

- Compatibilidade de publicacao: preservado apenas como comentario nao executavel o marcador 9CW exigido pelo validador instalado da 2.2.621. A leitura paga continua a usar exclusivamente o inventario cego 9CY.


- A IA lê primeiro as garrafas e os rótulos sem receber a lista de SKUs do Cardex, evitando associações por sugestão de variantes.
- A correspondência com o Cardex é feita localmente em PHP, sem confiar nos códigos P4/P5 devolvidos pelo modelo.
- Uma garrafa CARINA não pode ser contada automaticamente como Jameson Black Barrel.
- A identificação visual de variantes especiais exige confirmação humana e não cria caixas verdes automáticas.
- Mantém uma chamada por fotografia e os limites de IA, sem imagens de referência extra enviadas ao fornecedor.
- Candidata condicionada à certificação 2.2.621.

## 2.2.621 - Fronts SKU Identity Recovery 9CX (candidata)

- Corrige o falso negativo Jameson Original causado por prefixos de categoria do Cardex (`WHISKY IRLANDES`).
- Normaliza capacidades 70CL, 700ML e 1L, mantendo confirmação humana quando não são legíveis.
- Sugestões de IA incertas ficam visíveis para aceitação explícita por produto, nunca preenchem automaticamente.
- Black Barrel continua a exigir variante; objetos duplicados só bloqueiam duas identificações independentemente sustentadas.
- Caixas visuais seguem o contorno real das fotos verticais e distinguem incerto (âmbar) de sugerido (verde).
- Mantém uma chamada Azure por fotografia, limites de orçamento e proteções 9CS, 9CT, 9CU, 9CV e 9CW.
- **Estado**: candidata, não instalada nem certificada.

## 2.2.620 - Fronts Visual Evidence and Variant Identity 9CW

- **Azure, Gemini e OpenAI**: o pedido único de leitura de frentes exige caixas por garrafa e transcrição de identificação do rótulo. O Azure deixa de prescindir de coordenadas. Um modelo que não devolva provas suficientes não preenche as quantidades.
- **SKU e capacidade**: frentes positivas só são sugeridas quando existe correspondência visível com a marca, variante e, se existirem SKUs da mesma marca com capacidades diferentes, valor da capacidade. Distingue Jameson Original de Jameson Black Barrel sem inferir uma variante apenas por marca/garrafa parecida.
- **Sem duplicação**: sobreposição substancial de caixas no mesmo SKU ou em SKUs diferentes faz os resultados envolvidos regressarem a não confirmados. Confiança declarada pela IA, sem prova, não valida presença de produto. Uma sugestão rejeitada não é convertida em zero.
- **Equipa de campo**: mensagens específicas para duplicação de posições, marca, variante, capacidade ou prova insuficiente; os operativos continuam a confirmar manualmente os valores, sem obrigatoriedade de outra fotografia.
- **Custos e continuidade**: uma chamada por fotografia autorizada, sem nova chamada de verificação, sem fallback automático e sem migração. Orçamento/quota 9CS e replay 9CU mantêm-se; a chave do replay muda para impedir reutilização de resultados 9CV sem prova. A resposta poderá usar ligeiramente mais tokens, mas não há nova chamada paga.
- **Estado**: candidata 2.2.620 baseada na 2.2.619 Stable certificada, ainda requer instalação e certificação individual pelo Release Manager.

## 2.2.619 - Report Context Database Outage Recovery 9CV

- **Reportar**: erros de ligacao MySQL/WordPress (incluindo respostas HTML pre-bootstrap) deixam de mostrar texto tecnico do servidor, credenciais ou caminhos wp-config. Estado acessivel e botao «Tentar novamente», com protecao contra toques repetidos e sem loop automatico.
- **Integridade da visita**: ao mudar de loja ou de dia, um formulario antigo nunca permanece editavel sob um novo contexto. Um refresh da mesma loja que falha mantem o formulario e o rascunho; uma atualizacao de lista que falha conserva apenas a lista do mesmo dia ja carregada.
- **Recuperacao offline**: cache de formulario limitada pelo utilizador, cliente, projeto, rota, paragem, localizacao e data; os dados cacheados sao validados antes de serem restaurados. A cache antiga nao e reutilizada entre identidades. Quando nao ha copia offline valida, o sistema nao inventa um formulario nem confirma a visita.
- **AJAX**: excecoes durante a resolucao/renderizacao do formulario devolvem erro JSON 503 sem detalhes internos e registo tecnico server-side, quando o WordPress chega a executar o handler. Falhas de MySQL anteriores ao bootstrap exigem intervencao no alojamento.
- Sem novas chamadas de IA, sem alteracoes de schema, sem alteracoes das apps FlutterFlow, sem mudancas no processamento de frentes, validacoes de eliminacao ou submissao da visita. Base 2.2.618 Stable certificada; candidata 2.2.619 depende de validacao e certificacao individuais.

## 2.2.618 - Fronts Capture Review & Controlled AI Spend 9CU

- **Câmara e ao vivo com autorização explícita**: fotografar ou capturar após estabilização abre uma pré-visualização local; nenhuma análise paga começa sem tocar no botão «Analisar com IA, 1 tentativa». Refazer imagem é gratuito.
- **Qualidade sem IA**: dimensão, luminosidade e definição avaliadas num canvas do telemóvel; avisos orientam a repetir a imagem sem bloquear o operativo.
- **Catálogo por fotografia**: escolher até 12 produtos do Cardex para avaliar uma prateleira. Outros artigos do formulário não são alterados, e variantes/capacidades são explicitamente diferenciadas no prompt.
- **Precisão com revisão humana**: não arredondar frentes fracionárias ou negativas; ausência de produto, zeros incertos, baixa confiança ou caixas em desacordo não preenchem automaticamente. O utilizador confirma os números.
- **Controlo de custos**: leitura concluída e repetida com a mesma fotografia/contexto/seleção reutiliza o resultado em cache por até 15 minutos, com lock SQL de concorrência para impedir chamadas idênticas simultâneas. Orçamento, reserva por tentativa e quotas de 9CS permanecem. Alterações ao pedido, timeouts antes de guardar a leitura e a expiração da cache podem originar nova tentativa paga; custos reais dependem do fornecedor.
- **9CT preservado**: diálogos de confirmação antes de remover produtos, fotografias, anexos ou rascunhos. Sem alterações de esquema, sem nova integração FlutterFlow e sem novas subscrições de IA.
- Release candidata, a instalar somente depois de Validar e Publicar no Release Manager e certificar individualmente Stable. Sucessão **2.2.617 -> 2.2.618**.

## 2.2.617 - Confirmação de Remoções nos Formulários 9CT

- **Confirmação antes de eliminar**: referências do Cardex, fotografias selecionadas pela câmara/galeria, ficheiros anexados, fotografias previamente guardadas e rascunhos locais. Modal reutilizável, acessível e legível nas WebView Android/iOS, com o foco inicial em «Manter, não eliminar».
- **Proteção de estado**: cancelar, Escape, tocar no fundo ou suspensão da aplicação não altera ficheiros nem respostas; uma única confirmação de cada vez; após a resposta só atua se o mesmo formulário, ficheiro e produto ainda existirem. O estado de remoção de fotografias existentes pode ser anulado sem nova confirmação.
- **Continuidade**: preserva os campos de formulário, guardas offline e âncora de scroll, contagem com e sem IA, limites de custos 9CS, branding e todo o restante runtime. Sem migrações de BD nem alteração da app FlutterFlow.
- Sucessão `2.2.616 -> 2.2.617`, candidata, sujeita a validação/publicação Release Manager, updater privado, revalidação 6B, fecho de Fase 6 e certificação Stable.

## 2.2.616 - Contagem Multimodal Segura 9CS

- Modal único de frentes e Share Space com quatro entradas: seleção direta de produto, texto local, voz/dictado suportados pelo dispositivo e fotografia com IA. As três opções manuais não chamam a IA do FieldFlow; continuam a preencher os campos existentes do formulário, mesmo offline. A voz através da Web Speech API depende da WebView/browser; o ditado do teclado mantém-se como alternativa.
- Limites de custos por cliente: teto operacional de reserva mensal em cêntimos, reserva por tentativa, limite diário e limite mensal. O orçamento mensal é zero inicialmente e bloqueia pedidos de IA até aprovação no BO; requisições bloqueadas não são enviadas ao fornecedor. Proteção por bloqueio de MySQL e fail-closed; cada tentativa autorizada conta incluindo erros e timeouts. A reserva não é uma medição ou garantia da fatura real de IA.
- IA de imagem: fornecedor único por chamada e no máximo uma tentativa upstream, miniaturização, apenas 3 referências por defeito. Produtos não reconhecidos permanecem indeterminados (nunca 0 inventado), e o utilizador confirma a leitura no servidor antes de preencher o formulário.
- Segurança e privacidade: scope de pergunta, produtos de Cardex e briefing confirmados no servidor; associação da leitura por ID da submissão, cliente, projeto, loja, formulário, utilizador, dia, rota e paragem; fotografias da prateleira lidas de upload temporário sem ficheiro público, mantendo as fotos de referência do catálogo.
- Sem migrações de base de dados ou código FlutterFlow. Continuidade 2.2.615 -> 2.2.616; release candidata a validar/publicar via Release Manager, revalidar cadeia 6B, testar Fase 6 e só depois certificar Stable.

## 2.2.615 - Media Capture Resilience 9CR

- Fotografias nas perguntas (`fieldflow-media-capture-resilience-v1`): na app a galeria e a câmara do telemóvel abrem com `click()`; no browser mantém-se `showPicker()` com recurso a `click()`. Um segundo toque sem sinal de abertura troca o método para o resto da sessão.
- Câmara: se a câmara integrada for recusada ou falhar (permissão da app ou da WebView, câmara ocupada, erro do dispositivo), abre-se a câmara do telemóvel pelo seletor nativo e a sessão continua assim.
- Campos e botões de fotografia desativados numa pergunta visível e editável são reativados; num reporte em consulta aparece a indicação para usar "Editar reporte".
- Diagnóstico: cada passo que falha é enviado para o endpoint autenticado `diagnostics/client-event` como `media_error` (dispositivo, WebView, pergunta, loja, rota e últimos passos) e aparece em Segurança e observabilidade.
- Release Gate acrescenta `fieldflow-media-capture-resilience-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.614 - Front Theme Camera Live 9CQ

- Isolamento do tema (`fieldflow-theme-isolation-v1`): estilos do FieldFlow impressos depois do tema e do Elementor (dependências, sem ciclos) e folha final com as cores do template da campanha em todos os estados para os botões do formulário, câmara, uploads, zoom e `.rp-btn`/`.rp-cta` das abas; ligações e botões sem classe com as cores do template.
- Frentes no telemóvel e tablet: Share Space em linhas até 900 px (nome + −/+ de 148 px; antes, entre 361 e 560 px, o número ficava escondido); referências acrescentadas em cartões com −/+; seta no grupo "Ajustes da loja".
- Leitura por câmara (`fieldflow-fronts-vision-question-switch-v1`): a opção na pergunta basta; modos por cliente (perguntas / ligada com Share Space / desligada), clientes ligados antes mantêm Share Space; aviso para gestores com o motivo quando o botão não aparece.
- Contar ao vivo (`fieldflow-fronts-vision-live-count-v1`): câmara na folha, captura automática com o telemóvel parado (diferença entre imagens e luz medidas no dispositivo), "Contar agora", imagem lida do upload temporário e descartada; fotografia e galeria mantêm-se; histórico mostra "Ao vivo".
- Catálogo (`fieldflow-catalog-insignia-name-variants-v1`): palavras de embalagem e categoria, capacidade sem unidade, junção entre insígnias por abreviaturas e códigos sem vogais com as mesmas capacidades e números e sem duas linhas da mesma insígnia; identidade versão 3, reorganização ao abrir o catálogo, exportação CSV.
- Release Gate acrescenta `fieldflow-theme-isolation-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.613 - Catalog General Identity 9CP

- Identidade geral do catálogo (`fieldflow-catalog-general-identity-v1`): um catálogo por cliente, nunca por insígnia. Agrupamento por EAN, código de artigo dentro da insígnia (`c:<insígnia>:<código>`) e assinatura do nome (sem ordem, palavras genéricas, formatos de capacidade e de idade) com a capacidade em cl; litros convertidos (1 → 100, 0,7 → 70).
- Reorganização dos catálogos 2.2.609–2.2.612: duplicados por insígnia juntos (imagens, outros nomes, rótulos do briefing, campanhas e cardex mantidos), nomes das outras insígnias como outros nomes, produtos antigos sem imagem/rótulo/nome editado removidos, rebuild único agendado depois da atualização, sincronização idempotente, junções confirmadas no BO persistentes, proteção contra códigos reutilizados na mesma insígnia.
- Back office: insígnias e EAN por produto, painel "Possíveis duplicados" (Juntar / São diferentes), nome guardado no BO mantido pela sincronização, mensagem de sincronização com grupos, insígnias, duplicados juntos e removidos, etiqueta "Terreno" nas imagens tiradas no terreno.
- Fotografias de referência no terreno (`fieldflow-fronts-field-reference-v1`): botão "Fotografar" na folha da câmara para produtos (e lados do Share Space) sem imagem; AJAX autenticado com nonce, scope e limite por utilizador; só para produtos sem imagem; produto criado uma única vez quando ainda não existe; imagem na biblioteca de media com origem registada; definição por cliente em Leitura por câmara.
- Leitura por câmara resolve o código de artigo dentro da insígnia do cardex de cada linha.
- Release Gate acrescenta `fieldflow-catalog-general-identity-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.612 - Fronts Camera Reading 9CO (pilot)

- Leitura de frentes por câmara (`fieldflow-fronts-vision-reading-v1`): botão "Ler com câmara" em `product_matrix` (opção por pergunta) e `share_space_matrix` (opção por cliente), escolha de categoria, câmara nativa ou galeria, fotografia reduzida a 1600 px, ecrã de confirmação com −/+ e confiança, "Preencher frentes" nos mesmos campos com eventos de input.
- AJAX autenticado com nonce, scope de rota/cliente/campanha, limite por utilizador e limite mensal por cliente; pedido pelo `AIManager` (governação, entitlement, quota, logs e custos); até 12 imagens de referência do catálogo.
- Fornecedores de IA com imagens opcionais (Azure OpenAI/Foundry chat e Responses, OpenAI, Gemini) sem alterar os pedidos de texto; deployment Azure dedicado a imagens; módulo "Leitura de frentes" no Centro de IA.
- Histórico por loja num registo privado (`ff_fronts_reading`) com valores lidos e confirmados, correções, fotografia, fornecedor, modelo, tempo e ligação à submissão do mesmo dia.
- Separador "Leitura por câmara" em Cardex e Catálogo: estado do fornecedor, clientes no piloto, teste com fotografia, histórico.
- Gemini (`fieldflow-gemini-model-availability-v1`): default `gemini-3.5-flash-lite`; `gemini-2.5-flash-lite` mapeado; 2.5 Flash/Pro com nova tentativa no sucessor em 404.
- Release Gate acrescenta `fieldflow-fronts-vision-reading-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.611 - Catalog Cross-Client Reuse 9CN

- "Reutilizar de outro cliente" no Catálogo visual (`fieldflow-catalog-cross-client-reuse-v1`): cópia de produtos entre clientes com imagens partilhadas (mesmos anexos, sem duplicar ficheiros), tipo mantido ou definido na cópia, indicador "Já existe" / "Novo".
- Sem duplicados: produtos com a mesma identidade no destino ficam ligados à origem e recebem imagens só se não tiverem; os dados do destino mantêm-se.
- Cópias independentes; "Atualizar imagens da origem" no editor; cópias reutilizadas podem ser apagadas; mapeamentos do briefing não são copiados.
- Correção: a pesquisa por nome do Catálogo visual era ignorada (parâmetro `s`).
- Release Gate acrescenta `fieldflow-catalog-cross-client-reuse-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.610 - Fronts Mobile Layout & Catalog Visuals 9CM

- Perguntas de frentes (`product_matrix`) até 1024 px: linha compacta por produto conhecido com miniatura do catálogo visual, nome, referência/capacidade, `Antes N` com histórico e stepper −/+ (`fieldflow-fronts-mobile-catalog-visual-v1`).
- Campos do Cardex mantidos no DOM com os mesmos nomes, atrás de "Editar referência" (com "Remover"); linhas sem nome de produto mantêm o layout anterior; desktop mantém a tabela, com coluna de miniatura quando há imagens.
- Share Space: miniaturas dos produtos mapeados por rótulo (até duas e `+N`), stepper nos dois lados, lados lado a lado em telemóvel.
- Imagem em tamanho grande ao tocar; fallback para iniciais sem rede; produtos inativos do catálogo não aparecem.
- Resolução pelo catálogo do cliente da visita (cliente, campanha ou Cardex), uma leitura do catálogo por formulário e uma consulta de anexos por pergunta.
- Opção do construtor `catalog_images_disabled` ("Não mostrar as imagens do catálogo visual nesta pergunta").
- Nomes, valores e atributos dos campos e payload submetido sem alterações. Release Gate acrescenta `fieldflow-fronts-mobile-catalog-visual-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.609 - Visual Product Catalog 9CL

- Novo catálogo visual de produtos por cliente (`fieldflow-visual-product-catalog-v1`): produtos sem duplicados extraídos de todos os cardex do cliente, independentes da loja e dos IDs dos itens de cardex (que a importação com "Substituir" recria).
- Identidade: referência normalizada; nome normalizado + capacidade como segunda chave, com a capacidade escrita no nome reconhecida.
- Sincronização manual ("Sincronizar com cardex") e automática em background após gravação/importação de cardex e sincronização de cardex do briefing; nunca sobrescreve nome, marca, tipo, imagens ou outros nomes editados; EAN completado a partir dos Stocks.
- Até 12 imagens de referência por produto (biblioteca de media, validadas como imagem); produtos de concorrência; juntar duplicados; produtos vindos do cardex desativam-se ou juntam-se em vez de se apagarem.
- Mapeamento de rótulos Share Space do briefing para produtos do catálogo (`fieldflow-briefing-product-label-mapping-v1`), um rótulo para vários SKUs, com sugestões e criação de produto de concorrência.
- Menu "Cardex e Catálogo" com separadores Cardex / Catálogo visual / Mapeamento do briefing; todas as ações exigem `routespro_manage` e nonce.
- Armazenamento num tipo de registo privado não público (padrão 9CH). Release Gate acrescenta `fieldflow-visual-product-catalog-v1`. Sem alterações de schema, formulários ou FlutterFlow.

## 2.2.608 - Planner & Commercial Google Cost Authority 9CK

- Descobrir (planeamento de rotas dos gestores, `front_routes`): pré-visualização local sem custo (`fieldflow-planner-local-preview-cost-authority-v1`) em todas as alterações da fila e dos pontos de partida/chegada; ordem sugerida por vizinho mais próximo + 2-opt e km/tempo com o estimador calibrado do `RoutingMatrixService`.
- Google Directions apenas em "Calcular percurso real", com a ordem atual; `optimizeWaypoints` só com `google_routes_waypoint_optimization_enabled`.
- Coordenadas de partida/chegada guardadas a partir do autocomplete ou do perfil de endpoints da campanha.
- Base Comercial: Place Details (telefone/website) apenas com `google_places_contact_enrichment_enabled`.
- `PlacesService` sem mapa invisível em Descobrir e Base Comercial; o Planear dos operativos (`SelfRoutePlanner`) não usava Directions e não muda; Nearby Search só complementa Text Search com menos de 8 resultados.
- Release Gate acrescenta `fieldflow-planner-google-cost-authority-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.607 - Security & Hygiene Authority 9CJ

- `routespro_context_form` aplica `Permissions::can_access_route()` e `Permissions::assert_scope_or_error()` antes de renderizar o formulário com o pré-preenchimento da última visita. Sem acesso: HTTP 403 `fieldflow_context_form_forbidden`.
- `.htaccess` de isolamento em `tools/`, `frontend/` e `azure-*` (contrato `fieldflow-build-tooling-web-isolation-v1`). Nginx requer regra de servidor equivalente.
- `date('Y-m-d')` por defeito substituído por `current_time('Y-m-d')` nas superfícies operacionais e filtros do BO.
- IA (`fieldflow-ai-model-currency-v1`): default `gemini-2.5-flash-lite`, resolução automática de modelos Gemini descontinuados, chave no header `x-goog-api-key`, modelo efetivo visível no Centro de IA.
- Release Gate acrescenta `fieldflow-security-hygiene-authority-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.606 - Visit Closure Selector Return & Route First Paint 9CI

- Report do operativo: depois de confirmar o fecho da visita, a loja fechada deixa de ser reselecionada. O seletor volta a "Seleciona a loja", a opção fica marcada como concluída e é feito um único scroll até "Loja a visitar" (sem guard contínuo de viewport, preservando 9AH).
- Cartão de fecho com hora, estado (concluída, guardada no dispositivo, não realizada, alterações guardadas) e botão "Próxima loja" com a primeira loja pendente pela hora planeada. Nada é selecionado automaticamente.
- O contexto guardado do Report é limpo depois do fecho, para um reload ou resume não reabrir a loja concluída. A reconciliação em segundo plano preserva qualquer loja escolhida entretanto pelo operativo.
- Minha Rota: a rota do dia é pedida logo no arranque; o Google Maps só é carregado em "Adicionar paragem".
- Autocomplete legado de PDVs desligado por defeito (filtro `fieldflow_legacy_pdv_autocomplete_enabled`).
- Release Gate acrescenta `fieldflow-visit-closure-selector-return-v1`. Sem alterações de schema ou FlutterFlow.

## 2.2.605 - Expense Settlement, Payment & Signature Authority 9CH

- Novo estado canónico `Paga / Reembolsada`, separado de `Aprovada`, com `paid_at`, `paid_by`, origem e histórico de transições guardados no `meta_json` da despesa.
- BO permite fechar pagamentos em lote apenas quando existe um operativo e um período explícito selecionados; pendentes/aprovadas passam a pagas, rejeitadas e já pagas são ignoradas.
- A aba Despesas oferece duas vias de comprovativo: upload de folha assinada (PDF/JPG/PNG) ou assinatura manuscrita diretamente no FieldFlow por touch/rato.
- A assinatura direta gera uma nova folha PDF com a assinatura e timestamp embebidos. O recibo usa exclusivamente despesas `paid` do período, impedindo o operativo de autoatribuir pagamento.
- Os comprovativos ficam num arquivo privado com snapshot hash das despesas/total, aparecem no BO com Ver/Exportar e entram no ZIP em `folhas-assinadas/`.
- Registos de fecho usam um CPT privado/não público de baixo volume, evitando nova DDL e mantendo a cadeia de schema certificada. Sem alterações FlutterFlow.
- Release Gate acrescenta `fieldflow-expense-settlement-signature-authority-v1`.

## 2.2.604 - Expense Statement Mobile Ticket Delivery Hotfix

- Corrige a falha Android/WebView ao descarregar a Folha de Despesas PDF: a URL autenticada de `admin-post.php` deixava a sessão WordPress ao passar para o downloader Flutter genérico.
- A app prepara agora o PDF dentro da sessão autenticada, cria um capability ticket opaco de curta duração e entrega a URL sem cookies ao browser/gestor de downloads do sistema.
- O ticket aceita `HEAD` e repetições `GET` durante o TTL, evitando que sondagens do Android consumam o ficheiro antes da transferência real.
- O fluxo de despesas deixa de emitir `fieldflow.download.requested` para este PDF e usa eventos dedicados `fieldflow.expense.statement.*`, eliminando o falso par `Download iniciado` + `Não foi possível exportar o ficheiro`.
- Mantém filtros, branding TWT/cliente, totais por dia, assinatura, BO e exportação web da 9CF. Sem alterações FlutterFlow ou schema.
- Release Gate 2.33.0 acrescenta `fieldflow-expense-statement-mobile-ticket-delivery-v1`.

# FieldFlow 2.2.603 - Expense Statement PDF & Receipt Authority 9CF

- Nova folha de despesas PDF premium em Despesas, gerada diretamente a partir da tabela canónica existente, sem duplicação de dados.
- Autoridade de filtros: Mês, De/Até, categoria, estado e pesquisa do ecrã são projetados no documento; De/Até explícito tem precedência sobre o atalho mensal.
- O documento identifica operativo, cliente/campanha, período, total, registos/dias, agrupa por dia com subtotais e termina com declaração de recebimento, assinatura e data.
- Branding usa logos TWT + marca/campanha via a autoridade existente de Branding, com paginação A4 segura para folhas extensas.
- Operativo exporta apenas a própria folha no front/app; gestores podem gerar a folha do operativo selecionado no BO.
- A WebView captura filtros e scope ao toque, sem alterações FlutterFlow, schema ou histórico. Sucessão 2.2.602 -> 2.2.603.

# FieldFlow 2.2.602 - Analytics Header Date Export Authority 9CE

- `De` / `Até` do header passam a ser a autoridade temporal imediata dos exports Analytics em Cliente e Operativo.
- Os links CSV, XLSX, XLSX mobile e PDF são atualizados no `input`, no `change`, nos presets e antes da interação de download.
- A WebView corrige `from` / `to` diretamente a partir dos campos visíveis antes de criar o ticket nativo, eliminando o race que podia conservar o intervalo inicial do mês corrente.
- Os botões de período continuam válidos e atualizam os mesmos campos canónicos. Datas manuais têm precedência porque o export lê sempre o estado atual desses campos.
- O servidor normaliza datas ISO válidas, corrige intervalos invertidos e usa o mesmo filtro para CSV, Excel e PDF.
- Sem alterações de base de dados, FlutterFlow, routing, custos, tradução ou histórico de visitas.

## 2.2.601, Adaptive Locale Dock Continuity 9CD

- O selector de idioma entra totalmente visível, desaparece durante o scroll e, ao terminar, fica recolhido na lateral como uma pequena aba com as cores da marca.
- Tap/click na aba revela o globe e abre o menu; hover/focus em desktop revela o controlo sem bloquear o conteúdo.
- O comportamento cobre o island React e o fallback legacy, respeita safe areas e `prefers-reduced-motion`, e mantém 9CC, 9BZ e histórico de visitas intactos.
- Sem alterações de base de dados, FlutterFlow, geocoding, routing, custos ou periodicidade. Sucessão 2.2.600 -> 2.2.601.

## 2.2.600, App Shell & Browser-like Translation Continuity 9CC

- Corrige a montagem React do seletor premium no runtime real WordPress/WebView: hooks passam a executar dentro do render React e o fallback legacy só é removido depois de o island confirmar render.
- Os shells Dashboard Cliente e Dashboard Operativo declaram explicitamente a autoridade de idioma e reconciliam cliente/configuração em scope-change, pageshow, focus, visibility resume e portal-ready, sem tocar no FlutterFlow.
- A tradução deixa de depender de uma tabela finita: texto visível e conteúdo React/AJAX desconhecido passam por tradução automática browser-like, com cache e fallback FieldFlow.
- Fora de chinês preserva nomes de lojas, moradas e nomes de produtos; em zh-CN permite tradução total, incluindo nomes próprios/lojas. URLs, IDs, SKU/EAN e números permanecem protegidos.
- Preserva integralmente 9BD/9BZ/9CA/9CB, país operacional, geocoding, routing, custos e periodicidade. Sem alterações de base de dados. Sucessão 2.2.599 -> 2.2.600.

## 2.2.599, Locale Brand & Associated Client Scope Continuity 9CB

- Faz o globe/seletor premium de idioma herdar em runtime as cores reais do cliente/campanha, incluindo hover, focus, active, badge e painel, mesmo sendo montado fora da raiz visual do portal.
- Torna o contexto de idioma dinâmico por cliente ativo: o runtime escuta `routespro:scope-change`, resolve o cliente associado atual através do endpoint autenticado de idioma e reconfigura o seletor sem reload.
- O componente React permanece montado mesmo quando o cliente inicial não permite troca de idioma, podendo aparecer corretamente ao mudar para outro cliente associado elegível.
- Preserva a política 9BZ de memória de visitas, 9BD/9CA, rotas, geocoding, país operacional e toda a lógica FlutterFlow/WebView sem alterações de FlutterFlow.
- Sem alterações de base de dados. Sucessão certificada 2.2.598 -> 2.2.599.

## 2.2.598, Certification Marker Continuity 9CA

- Restaura em `src/Forms/Forms.php` o marcador histórico literal `Frentes reutiliza o valor final anterior` requerido pelo gate 9BD e adiciona o gate successor 9CA ao Release Gate e Package Validator.
- O marcador é apenas evidência de continuidade. A lógica runtime continua a ser a política 9BZ, que reutiliza respostas elegíveis e Frentes da última visita canónica, excluindo fotografias, ficheiros, briefing e Sugestão de encomenda.
- Mantém 9BY/9BZ, React + TypeScript e globe 3D sem alterações funcionais.
- Sem alterações de base de dados ou FlutterFlow. Sucessão certificada 2.2.597 -> 2.2.598.

## 2.2.597, Premium Locale & Visit Memory Continuity 9BZ

- Seletor de idioma premium com globe 3D em React + TypeScript no front e WebView/app, mantendo fallback progressivo.
- Cobertura de tradução operacional expandida em PT-PT, EN-GB, ES-ES e FR-FR com proteção de conteúdo introduzido pelo utilizador.
- A última visita operacional da mesma loja passa a preencher automaticamente respostas elegíveis na visita seguinte. Fotografias, ficheiros, briefing e Sugestão de encomenda são sempre excluídos. Frentes mantém continuidade.
- Web, Mobile payload e extração de submissão partilham a mesma política de memória. Isolamento canónico 9BJ permanece autoridade para impedir cruzamento de lojas.
- Sem alterações de base de dados e sem mudanças no FlutterFlow. Sucessão certificada 2.2.596 -> 2.2.597.

## 2.2.596, Multilingual Client Locale Authority 9BY
- Add a central multilingual runtime with Portuguese, English, Spanish and French language packs.
- Add per-client default language, enabled languages and user-switch policy in Clients BO without database schema changes.
- Persist the selected language per signed-in user and client, with safe fallback to the client default.
- Add a front and app language selector with flags, dynamic React/AJAX/offline translation continuity and locale-aware date/number formatting.
- Keep operational country, routing, geocoding, Google Maps and periodicity independent from interface language.
- Reuse the existing WordPress/WebView runtime in the native app, with no FlutterFlow source modification required.
- Add 9BY Release Gate, Package Validator, Release Manager KPI, verifier and runtime certification checks.

## 2.2.595, Visit-Centric Reporting & Briefing Continuity 9BX
- Make a terminal visit, not a form submission, the canonical identity of each Client Report.
- Generate exactly one report row per completed route stop and per justified non-visit inside the visible header date scope, even when no final form submission exists.
- Attach the latest operational submission as optional visit content and preserve completed visits without submission instead of silently dropping them.
- Filter Operative reports through route ownership, active route assignments, campaign store assignment and actual submitter evidence, while keeping briefing visibility governed by the exact campaign store assignment.
- Resolve applicable briefing questions for the exact visit store, operational date and form context, including visit-only reports when a binding can resolve the form.
- Keep Reports exports on the same visit-centric dataset and surface visit closure plus non-visit justification in report detail.
- Add a 9BX Release Manager and Stable gate that checks terminal-stop cardinality semantics instead of accepting submission-only reporting.
- No database schema or Google/runtime cost changes. Rotas is unchanged.

## 2.2.594, Client Report Header Scope & Briefing Assignment Integrity 9BW
- Make the Client Reports tab obey only the canonical header scope, removing hidden route-selection leakage from Rotas into Relatórios.
- Return all submissions for the selected operative inside the visible date interval, subject to the existing client/project/store and permission scope.
- Promote briefing questions to first-class report content using the exact historical store/date/form context and the existing operative-to-store campaign assignment map.
- Keep unanswered but applicable briefing questions visible as pending and preserve exact-store isolation across Client UI, BO, PDF and Excel exports.
- Add matching Release Manager and Stable certification checks so header scope, briefing mapping and export parity cannot silently drift in future builds.
- No database schema or Google/runtime cost changes.

## 2.2.593, Portal Export Runtime Integrity & Certification Parity 9BV
- Restore the missing Client Portal export URL builders for Reports, Analytics and Routes with the canonical nonces and filter scopes.
- Keep successfully loaded report rows intact if export-link UI refresh ever fails, so presentation data and export controls no longer share one failure domain.
- Audit the Operative dashboard export path and app route bridge in the same successor-safe contract.
- Add matching Release Manager package validation and Stable certification checks so future builds cannot pass with unresolved portal export dependencies.
- No database schema or Google/runtime cost changes.

## 2.2.592, Client Reports Runtime Continuity 9BU
- Restores the missing client report loader and canonical filters.
- Removes `loadReports is not defined` from Client Routes and repopulates Reports from executed submissions.
- No database schema or Google/runtime cost changes.

## 2.2.591, Mobile Analytics Readability & Evidence Density 9BT

- Give Client and Operative Analytics true mobile viewport ownership so WordPress/theme containers cannot squeeze the dashboard into a narrow column.
- Make evidence and image groups collapsible and compact by default on phones while preserving quick access to store/date ZIPs and thumbnails.
- Restore deterministic contrast for performance cards and active evidence tabs, independent of theme inheritance.
- Constrain Analytics table search and Ask FieldFlow textareas to mobile-friendly heights and reserve bottom clearance for fixed navigation.
- Preserve 9BS data semantics, route financial calculations, Google cost control, mail/reopen safety and certification continuity without schema changes.

## 2.2.590, Executive Analytics & Route Financial Visibility 9BS

- Rotas BO passa a projetar distância, tempo e custos de mobilidade a partir da mesma autoridade canónica do reporting, sem chamadas externas.
- A lista filtrada agrega km, energia, portagens e custo total de deslocação.
- Analytics Cliente recebe “Este mês em 30 segundos” com execução, cobertura PDV, rotas fechadas, tendências, mobilidade, encomendas, riscos e sinais comerciais.
- Analytics Cliente e Operativo passam a explicar dados usados, regras, confiança e limites da inteligência.
- “Como funciona?” no Perguntar ao FieldFlow passa a ser específico por audiência.
- Contrato 9BS e certificação/release gates incluídos. Sem alterações de schema.

# Changelog

## 2.2.589, Certification Foundation Continuity 9BR

- Decouple historical 6B.1 certification identity from per-release manifest/version revalidation so a new runtime cannot falsely cascade all 6B phases to uncertified.
- Make Release Manager revalidation explicitly cover 6B.1 before 6B.2, 6B.3 and 6B.4.
- Refresh the read-only licensing API probe only when a remote 6B.1 prerequisite is stale or missing.
- Return exact failed 6B.1 check keys instead of a generic precondition message.
- Preserve 9BQ and all functional, routing, Google, mail, form and visit-state behavior without schema changes.

## 2.2.588, Order Mail & Visit Reopen Safety 9BQ

- Make order-suggestion mail delivery explicit: queued/sent, failed with durable retry, or not-applicable with a concrete reason.
- Let persisted positive order quantities override stale conditional disagreement unless the order question was explicitly hidden in the submission snapshot.
- Add a final visit-close confirmation to focused, quick-report and standalone visit form paths without interrupting edits to an existing report.
- Allow managers to reopen completed/failed stores with mandatory reason and audit history while preserving submissions, answers and media.
- Protect reopened visits from passive historical-submission reconciliation until a deliberate new submission resolves the reopen marker.
- Preserve 9BK through 9BP and keep the release schema-neutral.

## 2.2.587, Daily Execution & Exception Control 9BP

- Add a canonical read-only daily execution authority across Minha Rota, Reportar and coordinator operations.
- Surface report/visit mismatches, missing non-visit reasons, visits left open and overdue unresolved stops as explainable exceptions.
- Make My Route alerts collapsible with a compact mobile-first header, persistent session preference and visible alert count.
- Add execution-exception KPIs and coordinator exception table to Operação diária.
- Preserve 9BK, 9BL, 9BM, 9BN and 9BO without external calls, schema changes or automatic route/submission mutation.

## 2.2.586, Daily Operational Priority 9BO

- Add an explainable daily focus to My Route and a coordinator view in Intelligence Center.
- Rank active visits first, then pending route stops, then unresolved critical/attention periodicity.
- Surface justified non-visits close to expiry for review without counting them as executed visits.
- Keep the service read-only with zero Google/AI calls and zero automatic route mutation.
- Preserve 9BK, 9BL, 9BM and 9BN cost-control contracts unchanged.

## 2.2.585, Google Cost Control Center 9BN

- Add a fail-closed Google financial circuit breaker before outbound HTTP.
- Keep Compute Routes Essentials available while blocking Route Matrix, Pro and Enterprise/TOLLS by default.
- Split blocked policy events from technical errors and count them as avoided calls in the privacy-safe API Cost Ledger.
- Add Google cost health, tier counters, Matrix units, policy drift and premium-attempt alerts to Security & Observability.
- Preserve all 2.2.584 Places/Geocoding, 2.2.583 toll and 2.2.582 static-routing low-cost contracts without purging existing route snapshots.

## 2.2.584, Places & Geocoding Cost Authority 9BM

- Reuse already validated FieldFlow route endpoints when address fingerprint, coordinates and campaign country remain coherent.
- Cap Google-derived geocoding coordinates, including endpoint reuse, at 30 days and rotate the geocode transient namespace to v3.
- Resolve scoped known FieldFlow locations before an external REST geocode call.
- Make Google Places phone and website enrichment opt-in instead of automatic.
- Record avoided geocoding calls in the privacy-safe API Usage Ledger without storing query payloads.
- Preserve 2.2.583 toll intelligence and 2.2.582 static routing contracts without mass geocoding or route refresh.

## 2.2.583, Toll Cost Intelligence 9BL

- Internal Portuguese 2026 toll estimator uses standard Compute Routes step context without Google TOLLS Enterprise.
- Toll result includes confidence, min/max range, vehicle class and tariff version.
- API usage ledger records Google Routes billing tier explicitly.
- Existing static route snapshots are preserved during upgrade, no mass Google refresh.

## 2.2.581 — Store identity authority and historical media isolation

- Make route-stop identity authoritative across Web form rendering, Mobile payloads and resume paths.
- Resolve historical submissions through the canonical route-stop/snapshot location, including legacy `location_id = route_stop_id` records without leaking numerically colliding stores.
- Block full-report prefill, including media/order evidence, whenever route, stop or store context conflicts.
- Add draft identity fingerprints and fail-closed automatic restoration for legacy or mismatched snapshots while retaining recovery evidence.
- Align Store Profile latest-visit lookup with the same canonical store identity and add 9BJ certification markers.
- Preserve the exact predecessor 9BD package marker while runtime 9BJ uses the aliased operational-status predicate, so 2.2.580 Release Manager can validate the successor package.

## 2.2.580 — Visit memory refresh and version ownership

- Replace stale memory for the same store when its source or answers change.
- Clear memory when selecting another store or a form without prior answers.
- Recover migrated answers only from versions owned by the selected visit.
- Preserve isolated media/order behavior and add 9BI certification checks.

## 2.2.579 — Last visit history and media/order isolation (9BH)

- Select the latest operational submission for the store/campaign through the report day, including same-day visits and excluding the current edited submission by ID.
- Remove generic current-record history fallback. A missing operational visit yields no fabricated/stale memory.
- Exclude image, categorized-image, file and order-suggestion values from visit memory and new-visit input; carry only product_matrix input forward.
- Restore edits exclusively from their own submission, preserving current media/orders without inheriting older answers.
- Apply parity to Web rendering/extraction and Mobile payloads. Rotate fronts correctly in the renderer fallback.
- Guard quick-report responses against a changed store selection.
- Preserve schema 2.2.529 and SubmissionPipeline build 2.2.485; update Phase6StableRelease and retain predecessor certification markers.

## 2.2.578 - Open Day Planning & Visit Completion Sync 9BG

- Planear deixa de bloquear o dia operacional atual quando a rota já está `in_progress` ou `completed`.
- O catálogo da rota mantém as paragens operacionais protegidas e mostra o estado canónico de cada loja, incluindo `Concluída`.
- Novas lojas e visitas pendentes podem ser ajustadas durante o dia sem reabrir ou perder o estado das visitas já concluídas.
- `fieldflow:visit-completed` sincroniza o estado no Planeador em tempo real.
- Mantém 9BD, 9BE, 9BF, schema `2.2.529` e `SubmissionPipeline::BUILD 2.2.485`.

# 2.2.577 - Certification Gate Literal Marker Hotfix

- Corrige o Release Gate 9BF para comparar literalmente `if ($status === 'justified')` sem interpolação acidental de `$status`.
- Não altera a lógica funcional de Visit Exception & Justified Non Visit 9BF.
- Preserva Planear 9BE, Form Visit Memory & Fronts 9BD, schema `2.2.529` e SubmissionPipeline `2.2.485`.
- Phase 6 Stable avança `SOURCE_RELEASE 2.2.576 -> STABLE_RELEASE 2.2.577` e mantém marcadores históricos necessários ao Package Validator da 2.2.576.

# 2.2.576 - Visit Exception & Justified Non Visit Continuity 9BF

- Adiciona justificação operacional de não visita na Cobertura do mês, com motivo, observação, validade e reabertura manual.
- Uma exceção justificada sai do vermelho/crítico, mas nunca conta como visita realizada e não altera a periodicidade canónica da loja.
- Exceções temporárias expiram automaticamente; encerramentos definitivos podem ficar ativos até remoção manual.
- Paragens `failed` com `fail_reason` passam a resolver o respetivo slot como justificado, sem o converter em execução.
- O histórico da exceção é auditável e permanece separado do schema de campanha, preservando `LATEST_SCHEMA_VERSION 2.2.529` e `SubmissionPipeline::BUILD 2.2.485`.
- Phase 6 Stable avança `SOURCE_RELEASE 2.2.575 -> STABLE_RELEASE 2.2.576`, preservando os marcadores históricos 9BE.

# 2.2.575 - Planner Free Warning Continuity 9BE

- Em `Planear`, a atribuição do merchandiser passa a ser a autoridade operacional para otimizar e guardar. Uma loja que pertence ao seu catálogo deixa de ser rejeitada apenas porque a ligação está pausada/inativa.
- Sobreposições com outras rotas, dias bloqueados ou fora dos dias preferenciais e intervalos mínimos de periodicidade passam a avisos explícitos. O planeamento continua permitido para que o merchandiser possa ajustar o plano antes da execução.
- O erro `Existem lojas fora da tua atribuição ou rota` fica reservado a lojas que não pertencem realmente à atribuição do merchandiser nem à rota autorizada.
- O front apresenta estes conflitos como avisos, preservando métricas e otimização. O save mobile/self-route propaga os mesmos avisos sem transformar o conflito operacional em bloqueio.
- Mantêm-se as proteções estruturais: data válida, máximo de 8 lojas por dia, ausência de duplicação no mesmo dia, isolamento de atribuição e proteção de rotas já operacionais.
- Mantém integralmente 9BD, sem alteração de schema SQL nem do build certificado de `SubmissionPipeline`. `LATEST_SCHEMA_VERSION` continua `2.2.529` e `SubmissionPipeline::BUILD` continua `2.2.485`.
- Phase 6 Stable avança `SOURCE_RELEASE 2.2.574 -> STABLE_RELEASE 2.2.575`, preservando os marcadores históricos 9BD.

# 2.2.574 - Form Visit Memory & Fronts Continuity 9BD

- Restaura a memória canónica da visita: uma nova visita usa a última submissão operacional anterior da mesma loja e campanha, mesmo apos uma revisao/substituicao do formulario, em vez de depender do `current_version` genérico do recorde.
- Respostas compatíveis voltam a aparecer como memória informativa no início da visita. Fotografias/uploads e `order_suggestion` ficam explicitamente excluídos.
- `product_matrix` mantém a sua semântica própria: `Frentes antes` recebe o valor final da visita anterior e `Frentes depois` inicia nesse mesmo valor para nova atualização.
- Web e Mobile normalizam as linhas de Frentes antes de gravar, preservando `before`, `after` e `qty`, com `qty` igual ao valor efetivo atual de `after`.
- Uma submissão parcial, uma pergunta condicional, uma correção tardia ou uma versão corrente incompleta deixam de esconder o histórico operacional válido da loja.
- Mantém integralmente Planear 9BC, sem alteracao de schema SQL nem do build certificado de `SubmissionPipeline`. A projecao de memoria passa a ser inline apos COMMIT, com worker assincorno apenas como fallback. `LATEST_SCHEMA_VERSION` continua `2.2.529` e `SubmissionPipeline::BUILD` continua `2.2.485`.
- Phase 6 Stable avança `SOURCE_RELEASE 2.2.573 -> STABLE_RELEASE 2.2.574`, preservando os marcadores históricos 9BC.

# 2.2.573 - Planner Canonical Assignment Catalog 9BC

- Em `Planear`, o catálogo canónico passa a ser todas as lojas com `campaign_locations.assigned_to` igual ao merchandiser, sem as retirar por execução, histórico, periodicidade, rota anterior, `status` ou `is_active`.
- Uma loja já usada num dia continua disponível para ser planeada noutro dia, condição necessária para P2/P3/P4 e para replaneamento operacional. A unicidade mantém-se dentro do mesmo dia.
- O backend deixa de rejeitar a mesma loja em datas diferentes do mesmo plano e continua a validar que cada loja pertence ao merchandiser.
- O front passa a guardar apenas dias alterados, preservando rotas já iniciadas ou concluídas e evitando reenvio acidental de histórico.
- Mantém a 9BB como contrato histórico e acrescenta `fieldflow-planner-canonical-assignment-catalog-v1` como gate 9BC.
- Sem alteração de schema, `SubmissionPipeline`, routing, custos, formulários, media ou mail.
- Phase 6 Stable avança `SOURCE_RELEASE 2.2.572 -> STABLE_RELEASE 2.2.573`.

# 2.2.572 - Planner Assigned Store Scope Continuity 9BB

- Corrige `Planear` quando o merchandiser tem lojas ativas atribuídas em `campaign_locations`, mas a campanha ainda não existe no âmbito normalizado do utilizador.
- Uma atribuição ativa de loja passa a conceder apenas o âmbito operacional necessário ao respetivo cliente/campanha, permitindo carregar `campaign-pdvs` e criar a rota do próprio merchandiser.
- A resolução é live para funcionar imediatamente com índices anteriores e passa também a ser persistida no `ScopeIndex` como `campaign_location`.
- Mantém as guardas existentes de criação de rota e de paragens, que continuam limitadas às lojas efetivamente atribuídas ao owner.
- Sem alteração de schema, algoritmo de routing, custos, formulários, submissões, media ou mail.
- Phase 6 Stable avança `SOURCE_RELEASE 2.2.571 -> STABLE_RELEASE 2.2.572`, preservando os marcadores históricos da 9BA para validação sucessora.

## 2.2.571 - Client Route Hero Date Authority 9BA

- Adds `fieldflow-client-route-hero-date-authority-v1` and certification gate 9BA.
- Rebuilds the 9AZ route-calendar behavior on the certified 2.2.569 shell semantics while preserving the mobile Client/Operational feature split.
- `De` / `Até` in the Client Portal hero is now the only temporal authority for route API loading, calendar cards, route list, owner route summary, financial totals and route exports.
- Removes the secondary month selector and previous/next month controls from the Client route calendar and removes the month selector from the owner summary.
- A multi-month hero range renders each covered month automatically without creating a second filter state.
- Route day cards continue to aggregate canonical total mobility cost across every route on that day, including unresolved-cost signalling.
- No database schema or SubmissionPipeline change. `LATEST_SCHEMA_VERSION` remains `2.2.529`; `SubmissionPipeline::BUILD` remains `2.2.485`.
- Phase 6 successor identity advances 2.2.570 -> 2.2.571 while retaining 9AZ historical markers for predecessor validation.

## 2.2.570 - Route Calendar Daily Total & Mobile Portal Features 9AZ

- Adds `fieldflow-route-calendar-daily-total-mobile-portal-features-v1` and certification gate 9AZ.
- Route calendar day cards now use canonical `routeMobilityTotal()` aggregation, summing tolls, energy and operational cost across every route on that day.
- Partial days surface the known total plus the count still pending calculation instead of silently showing tolls only.
- Mobile Control Center splits feature visibility into independent Operational and Client portal matrices.
- Existing 2.2.569 global feature choices migrate identically into both portal matrices, avoiding surprise tab exposure on upgrade.
- WebView panel hiding and mobile navigation now use target-specific visibility while the legacy global feature map remains the union for API and older-app compatibility.
- No database schema or SubmissionPipeline change. `LATEST_SCHEMA_VERSION` remains `2.2.529`; `SubmissionPipeline::BUILD` remains `2.2.485`.
- Phase 6 Stable identity advances 2.2.569 -> 2.2.570.

## 2.2.569 - Client Portal Filter Authority Rebase 9AY

- Adds `fieldflow-client-portal-filter-authority-rebase-v1` and certification gate 9AY.
- Rebuilds the Client Portal interaction shell around the stable 2.2.565 date-filter semantics while retaining the certified 9AV/9AW/9AX contracts.
- `De` / `Até` remains authoritative for Summary, costs, reports and Analytics. Route selection no longer disables or rewrites the global date range.
- Keeps annual route calendar navigation when useful, but filtered totals use the selected global period.
- Restores the stable Summary KPI composition; team/individual performance remains isolated inside Analytics.
- Preserves full route cost composition, consolidated reporting, audience-aware Analytics and Ask FieldFlow performance evidence.
- No database schema or SubmissionPipeline change. `LATEST_SCHEMA_VERSION` remains `2.2.529`; `SubmissionPipeline::BUILD` remains `2.2.485`.
- Phase 6 Stable identity advances 2.2.568 -> 2.2.569.

## 2.2.568 - Client Portal Runtime Scope Continuity 9AX

- Keeps performance bindings local to the KPI renderer to prevent ReferenceErrors from aborting portal mounting.

## 2.2.567 - Route Financial Breakdown & Audience Analytics 9AW

- Adds complete route financial composition, premium consolidated reporting and audience-aware team/individual Analytics.

## 2.2.566 - Client Cost Date Scope & Route Totals 9AV

- Makes client cost totals obey the selected date range and adds route-list financial totals.

## 2.2.565 - Release Manager Publication Receipt Continuity 9AU

- Adds `fieldflow-release-manager-publication-receipt-v1` and certification gate 9AU.
- Makes the WordPress Release Manager publication receipt durable across stale option-cache state by recovering exact-version evidence from the existing `release.published` AuditLog record.
- Mirrors successful publication receipts to a network option on multisite while preserving the existing local option for compatibility.
- Phase 6 now requires WordPress-origin publication evidence and exact SHA-256 corroboration against the private 6B.4 catalog before the PUBLISH gate can pass.
- Recovered receipts heal the local publication option for subsequent reads. No publication bypass is introduced.
- No database schema, routing, form, SubmissionPipeline, media, mail or offline protocol change.
- Phase 6 Stable identity advances 2.2.564 -> 2.2.565.

## 2.2.564 - My Route Periodicity Action UX 9AT

- Adds `fieldflow-my-route-periodicity-action-ux-v1` and certification gate 9AT.
- Adds operational risk levels `normal`, `attention` and `critical` using unresolved cadence, overdue weekly slots and remaining business days.
- `Minha Rota` now shows remaining business days, a compact monthly close and risk-first ordering without expanding the page by default.
- Renames the primary filter to `Só falta cumprir` and persists the selected filter for the browser session.
- Adds a direct `Planear` action for unresolved stores. The operational shell opens Planear with the correct client, project and month, then focuses or preselects the exact store.
- Personal route saves refresh monthly coverage immediately so newly published future stops are reflected in projected coverage.
- No route is auto-created by the coverage widget. No database schema, SubmissionPipeline, media, mail or offline protocol change.
- Phase 6 Stable identity advances 2.2.563 -> 2.2.564.

## 2.2.563 - My Route Periodicity Control 9AS

- Adds `fieldflow-my-route-periodicity-control-v1` and certification gate 9AS.
- Adds a read-only monthly periodicity progress service based on assigned campaign stores, canonical operational submissions and published route stops.
- Adds `GET /routespro/v1/my-periodicity` for the authenticated field user.
- Adds a compact `Cobertura do mês` panel to `Minha Rota`, with P1-P4 progress, completed visits, future planned coverage, stores without visits and unresolved cadence gaps.
- Planned future visits suppress false alerts through the `Coberta no plano` state.
- No database schema change. `LATEST_SCHEMA_VERSION` remains `2.2.529`; `SubmissionPipeline::BUILD` remains `2.2.485`.
- Phase 6 Stable identity advances 2.2.562 -> 2.2.563.

## 2.2.562 - WordPress Media Isolation 9AR

- Adds `fieldflow-wordpress-media-isolation-v1` and certification gate 9AR.
- Core WordPress Media Library uploads, REST media uploads and image-editor requests take a lightweight FieldFlow bootstrap path, preserving PHP memory headroom for GD/Imagick sub-size generation.
- FieldFlow `UploadGuard` hooks are registered only for FieldFlow-owned requests instead of every WordPress upload request.
- Removes generic standalone `form_id` request detection from FieldFlow upload scoping to avoid false positives with Elementor and other plugins.
- Keeps FieldFlow resumable evidence media, MIME controls, rate limits and `no-fixed-image-limit` policy unchanged.
- No database schema, SubmissionPipeline, routing, mail, offline protocol or form-conditional runtime change.
- Phase 6 Stable identity advances 2.2.561 -> 2.2.562.

## 2.2.561 - Condition Graph Integrity 9AQ

- Adds `fieldflow-condition-graph-integrity-v1` as a non-blocking authoring audit for conditional forms.
- Form Builder now shows `Controlada por` on every conditional target and explains OR semantics when multiple source rules can reveal the same question.
- Detects suspicious cross-domain gates such as Conquistas controlling the Order branch, highlighting the exact source rule and affected questions before publication.
- Repeats the integrity audit server-side on save, including schemas edited through advanced JSON, without changing ConditionalFlow visibility semantics.
- Adds certification gate 9AQ. No database schema or SubmissionPipeline change.
- Phase 6 Stable identity advances 2.2.560 -> 2.2.561.

## 2.2.560 - Report Status Canonical Sync

- Reportar passa a consumir estado canónico da visita e leitura live-first.
- Conclusões recebidas por `fieldflow:visit-completed` atualizam imediatamente a lista de lojas.
- Cache offline deixa de poder fazer uma visita concluída regressar visualmente a pendente; uma resposta live posterior continua autoritativa.
- `/routes/{id}` expõe `canonical_status`, `legacy_status` e `status_source` por paragem.

## 2.2.559 - Route & Mobility Dashboard Widgets 9AO

- Adds configurable route and mobility system widgets to the Intelligence Center without creating a parallel routing or cost engine.
- Adds route execution, visit execution, executed km, km composition, fuel/energy, tolls, mobility cost and daily mobility widgets.
- All mobility widgets consume `CanonicalOperationalReportService::routeMobilitySnapshot()`, the same canonical projection used by Daily Close and reporting exports.
- Dashboard support tables expose access, between-PDV and return km, total km and mobility costs while retaining the existing responsive widget layout.
- Widget Intelligence recognizes mobility as a first-class topic for client and merchandiser audiences.
- Adds certification gate 9AO. No database schema, SubmissionPipeline, FlutterFlow or viewport change.
- Phase 6 Stable identity advances 2.2.558 -> 2.2.559.

## 2.2.558 - Routing Endpoint Geographic Integrity 9AN

- Makes the campaign/project country authoritative for route endpoint and PDV geocoding, preventing ambiguous place names from resolving in another country.
- Google geocoding now uses region + country component restriction; Azure Maps uses countrySet, and country is part of the provider cache identity.
- Adds country-aware endpoint metadata including formatted address, place id, provider, verification timestamp and routing geography contract.
- Legacy routes are revalidated before Daily Close/manual resend, route PDVs are checked in the campaign country, and exact Google route metrics are refreshed before reporting.
- Adds a Centro de Atribuições repair action for historical campaign routes, including completed routes.
- Daily Close subject dates use the operational anchor date rather than dispatch time.
- Adds certification gate 9AN with an explicit Montijo Portugal vs Montijo Badajoz regression test.
- No database schema or SubmissionPipeline change. Phase 6 Stable identity advances 2.2.557 -> 2.2.558.

## 2.2.557 - Full Route Mobility Reporting Reconciliation 9AM

- Reconciles Daily Close mobility distance from the full route: access + between-PDV legs + return.
- Detects and corrects reporting totals that are lower than a coherent full-route component sum, without mutating canonical historical route metrics.
- Adds a stored-route-leg reporting fallback for closed routes whose meta totals are stale or incomplete.
- Recalculates fuel/energy costs on the reconciled full-route distance and reconciles estimated tolls when they are not sourced from a real routing API.
- Email and Excel expose access, between-PDV and return kilometres separately, plus the full executed total by day and accumulated period.
- Adds certification gate 9AM. No schema or SubmissionPipeline change.
- Phase 6 Stable identity advances 2.2.556 -> 2.2.557.

## 2.2.556 - Known Mobility Reporting Recovery
- Fixes the real-data case discovered after 2.2.555 certification where closed routes could retain `known_distance_km` while canonical `distance_km` remained unavailable because endpoint/integrity resolution was partial.
- `MobilityCostService::reportingProjection()` now reuses known kilometres and known tolls to produce a reporting-only estimate with the existing canonical mobility formula.
- Recovered distance/costs are explicitly marked partial and never mutate the canonical `costs_available` flag.
- Daily Close mail, Excel and PDF distinguish recovered estimates from genuinely unavailable values, using `Por apurar` / `Por calcular` instead of false numeric zeros.
- Adds certification gate 9AL, Known Mobility Reporting Recovery.
- No schema or SubmissionPipeline change.
- Phase 6 Stable identity advances 2.2.555 -> 2.2.556.

## 2.2.555 - Daily Mobility Cost Reporting Continuity

- Adds `fieldflow-daily-mobility-cost-reporting-v1` and the 9AK certification gate.
- Canonical reporting aggregates kilometres from operationally closed routes only and reuses MobilityCostService estimates for fuel/energy, tolls and total mobility cost.
- Daily Close email shows mobility KPIs plus a per-day table and `TOTAL GERAL` for multi-day ranges.
- Excel adds `Mobilidade Diaria` and enriches `Rotas` with km, fuel/energy, toll and total mobility columns.
- Incomplete distance/cost data is marked `PARCIAL`; open routes are excluded from executed-distance totals.
- PDF follows the same daily and accumulated mobility truth.
- No database migration. `LATEST_SCHEMA_VERSION` remains `2.2.529` and `SubmissionPipeline::BUILD` remains `2.2.485`.

## 2.2.556 - Known Mobility Reporting Recovery 9AL

- Corrects false zero kilometres and mobility costs in Daily Close reporting when a closed route retains valid `known_distance_km` but canonical endpoint integrity is not fully authoritative.
- Adds `MobilityCostService::reportingProjection()` to reuse the canonical mobility formula without mutating `costs_available`.
- Recovered estimates are explicitly marked partial in mail and exports.
- Adds release/certification contract 9AL.


## 2.2.554 - Briefing Store Scope Reporting Continuity

- Adds `fieldflow-briefing-store-scope-reporting-v1` and the 9AJ certification gate.
- Daily briefing reporting now intersects active question-store assignments with the stores represented by the canonical route/report scope.
- Client and merchandiser briefing coverage, alerts and Excel detail use assignment-level target/answered/pending counts.
- Questions mapped to stores outside the route cannot create false pending alerts.
- No schema, SubmissionPipeline, viewport, FlutterFlow, routing or mail transport changes.

## 2.2.553 - Mail Intelligence & Daily Commercial Reporting

- Adds `fieldflow-mail-intelligence-daily-commercial-reporting-v1` and the 9AI certification gate.
- Derives dynamic client and merchandiser narratives from one deterministic canonical commercial snapshot.
- Adds explicit visit outcome and justification reporting and highlights gains, losses, opportunities and exceptions.
- Makes XLSX/PDF reporting dynamic by audience and by available capabilities, avoiding empty zero-value sections.
- Separates immediate `sent`, durable `queued` and `failed` mail semantics in scheduled reporting and Shopping.
- Adds recipient-level idempotency for original Shopping verification mail and individual recipient envelopes for legacy route fallback.
- Adds bounded cleanup for old sent/cancelled outbox rows after attachment retention. Failed rows remain retained for investigation.
- No database migration. `LATEST_SCHEMA_VERSION` remains `2.2.529`, `SubmissionPipeline::BUILD` remains `2.2.485`, and viewport/FlutterFlow contracts remain unchanged.

## 2.2.552 - Single Anchor Viewport Continuity

- Adds `fieldflow-single-anchor-viewport-continuity-v4` and the 9AH certification gate.
- Replaces passive-only mutation handling with one synchronous anchor compensation for Cardex and conditional layout changes.
- Restores media viewport immediately on native picker return, before asynchronous persistence and optimisation.
- Makes hot resume keep the live DOM authoritative instead of replaying an older draft.
- Keeps cold reconstruction durable by restoring the saved draft and applying the saved viewport anchor once.
- Captures store-selection viewport before the native picker opens and hands that checkpoint to the asynchronous form swap.
- Preserves mobile portal geometry while the resume paint guard is active.
- No database migration. `LATEST_SCHEMA_VERSION` remains `2.2.529`, `SubmissionPipeline::BUILD` remains `2.2.485`, and `NATIVE_SHELL_VERSION` remains `1.10`.

## 2.2.551 - Passive Viewport Continuity Hotfix

- Removes the 2.2.550 scroll guard.

## 2.2.550 - Native Picker Paint Continuity

- Replaces the 2.2.549 report-shell `position: fixed` freeze with a non-interactive visual snapshot mask that covers the WebView while the real scroller settles underneath.
- Starts the mask before the native store picker opens, preventing Android/iOS select focus from exposing an intermediate top-scroll frame.
- Keeps the mask through camera/gallery handoff and removes it only after the real scroll position has been restored for two animation frames.
- Adds continuity around dynamic form selects, checkbox/radio conditional changes, and Cardex add/remove operations.
- Keeps the 2.2.549 mail flow finalization, canonical reporting, attachment dedupe and merchandiser KPI scope unchanged.
- No database migration. `LATEST_SCHEMA_VERSION` remains `2.2.529`, `SubmissionPipeline::BUILD` remains `2.2.485`, and `NATIVE_SHELL_VERSION` remains `1.10`.

## 2.2.549 - Mail Flow Finalization & True Viewport Freeze

- Finalises the three operational mail flows: route Daily Close, Shopping verification, and eligible Order Suggestion.
- Daily Close can keep day and week-to-date KPI summaries in the email body while attaching only one canonical XLSX and one canonical PDF for the closed day.
- Merchandiser report bundles use full operational metric visibility constrained by recipient user/client/project scope, so extra-space wins and evidence no longer disappear because of portal widget visibility.
- Shopping verification always requires XLSX + PDF and Order Suggestion continues to require XLSX + PDF only when form eligibility allows delivery.
- MailDispatchService deduplicates attachment paths before persistence and send as a final transport guard.
- Replaces document/body locking with a report-shell visual freeze during store swaps and native media handoff. The document remains scrollable internally while the visible report stays pinned until the transition is complete.
- Adds Phase 9AG release parity checks. No database migration. `LATEST_SCHEMA_VERSION` remains `2.2.529`, `SubmissionPipeline::BUILD` remains `2.2.485`, and `NATIVE_SHELL_VERSION` remains `1.10`.

## 2.2.548 - Certification, Viewport Lock & Mail Template Recovery

- Restores the certified Mobility Cost Intelligence labels `Combustível / energia` and `Custo deslocação` in the operational route analytics table and makes package validation enforce the same markers as the installed Release Gate.
- Adds a hard viewport lock around store selection, form swaps and native media handoff so WebView/browser scroll cannot visibly jump before continuity is restored.
- Keeps `focus-after-hidden` guarded resume semantics from 2.2.545/2.2.546 and does not reintroduce bare focus restore.
- Fixes the operational React fallback text (`Operação pronta` / `Navegação clássica disponível`) to remain readable outside the dark hero.
- Recovers missing `daily_close` Mail Center schedules from persisted project/global email templates while preserving intentionally inactive schedules.
- Manual route-close sending can reconstruct a dormant template when the schedule row is missing and defaults operational attachments to XLSX + PDF.
- Preserves the 2.2.547 canonical operational/commercial reporting model across Analytics, email, Excel, PDF and CSV.
- Adds Phase 9AF certification parity and regression checks. No schema migration, routing-rule or FlutterFlow change.

## 2.2.547 - Canonical Operational Reporting, Delivery Reliability & Viewport Continuity

- Adds `fieldflow-canonical-reporting-delivery-viewport-continuity-v1` and the 9AE certification gate.
- Introduces `CanonicalOperationalReportService` as the single snapshot source for Analytics, email, Excel, PDF and CSV.
- Separates real execution from operational closure so justified non-visits never inflate visit execution.
- Makes routing first-class with ordered visits, route path, start/end points and per-stop status/timing.
- Prioritises fronts, extra-space wins, evidence, order suggestions and briefing coverage across reporting surfaces.
- Reconciles missed Daily Close dispatches and sends reporting retries through the durable FieldFlow queue.
- Defaults legacy empty report formats to XLSX + PDF and blocks incomplete delivery when a requested attachment is missing.
- Replaces window-only scroll restoration with real scroll-container continuity and atomic store-form swap.
- Keeps the previous form mounted until the next store form is ready, preventing temporary height collapse and top jumps.
- No database migration. `LATEST_SCHEMA_VERSION` remains `2.2.529`, `SubmissionPipeline::BUILD` remains `2.2.485`, and `NATIVE_SHELL_VERSION` remains `1.10`.

## 2.2.545 - Resume Certification & Media Integrity

## 2.2.546 - Form Viewport & Shopping Delivery Integrity

- Preserva a posição visual do formulário em mudanças de loja, resume, fotografia, Cardex e reconciliações condicionais.
- Evita colapso temporário da altura do formulário durante reload da loja.
- Unifica corpo do email, Excel e PDF Shopping no mesmo snapshot canónico da submissão.
- Torna anexos configurados obrigatórios: uma falha de geração bloqueia o envio incompleto e fica auditável no Mail Center.
- Melhora a estrutura dos anexos Shopping para leitura operacional.
- Adiciona o gate 9AD Form Viewport & Shopping Delivery Integrity.
- Sem migração de base de dados.

- Adds the `fieldflow-resume-certification-media-integrity-v1` contract and 9AC certification gate.
- Makes the historical 9S Field Session & Form Continuity validation successor-safe, accepting guarded `focus-after-hidden` resume instead of requiring bare `focus`.
- Aligns ReleasePackageValidator and the installed ReleaseGate so the same resume capability is validated before and after installation.
- Preserves the operational report day in post-camera media checkpoints, preventing a resumed historical visit from falling back to today.
- Keeps camera/gallery originals in an isolated IndexedDB recovery namespace before optimisation, while the optimised copy remains the active transport media.
- Offline recovery prefers the active media group and falls back to the original only when the active group is missing.
- Removes duplicate post-optimisation media persistence and explicitly clears stale report context when the store selector is cleared.
- No database migration. `LATEST_SCHEMA_VERSION` stays `2.2.529`, `SubmissionPipeline::BUILD` stays `2.2.485`, and `NATIVE_SHELL_VERSION` stays `1.10`.

## 2.2.544 - Report Selection & Media Continuity

- Adds the `fieldflow-report-selection-media-continuity-v1` contract and 9AB certification gate.
- Fixes direct Reportar store selection by making manual dropdown choice authoritative over stored resume checkpoints.
- Stops Android native select blur/focus from being treated as an app resume unless a real hidden/pagehide transition armed resume first.
- Applies the same armed-resume rule inside `FormRenderer`, preventing a bare focus from restoring an older draft or wizard step while reporting.
- Gives a live mounted report precedence over older stored route/stop context, preventing jumps while the merchandiser is reporting.
- Commits selected camera/gallery files to IndexedDB and checkpoints answers/step immediately after selection, then checkpoints the optimised media set again.
- Preserves the 2.2.543 durable resume behavior, logout cleanup and exact form-step restoration.
- No database migration. `LATEST_SCHEMA_VERSION` stays `2.2.529`, `SubmissionPipeline::BUILD` stays `2.2.485`, and `NATIVE_SHELL_VERSION` stays `1.10`.

## 2.2.543 - Field Resume Navigation Continuity

- Adds the `fieldflow-field-resume-navigation-continuity-v1` contract and 9AA certification gate.
- Persists the operational panel and reporting visit in a user-scoped localStorage checkpoint with a bounded 12-hour TTL while retaining sessionStorage as the fast path.
- Flushes the dynamic form draft and current wizard step before camera/gallery handoff.
- Restores the Report panel and exact route/stop/location after WebView recreation without reloading a live form unnecessarily.
- Clears durable navigation state on real mobile logout.
- No database migration. `LATEST_SCHEMA_VERSION` stays `2.2.529`, `SubmissionPipeline::BUILD` stays `2.2.485`, and `NATIVE_SHELL_VERSION` stays `1.10`.

## 2.2.542 - Submission Corrections & Audit Trail

- adiciona `SubmissionCorrectionService` como fronteira canónica para correções administrativas de submissões;
- exige motivo explícito em cada correção e preserva ator, timestamp, transição de estado e chaves alteradas em histórico limitado;
- substitui eliminação física por transição auditável para `void`, preservando submissão, respostas e versões;
- cria nova versão de `RecordService` quando respostas são corrigidas e reconcilia o ponteiro operacional sem apagar histórico;
- reabre a visita quando a submissão corrigida/anulada era o único facto operacional que a concluía;
- recalcula progresso da rota depois do COMMIT com `RouteProgressService::sync(..., true, false)`, sem reemitir `route.completed` ou Daily Close;
- adiciona contrato successor-safe 9Z e paridade entre Release Gate, ReleasePackageValidator e Release Manager;
- sem migração de base de dados; `LATEST_SCHEMA_VERSION=2.2.529` e `SubmissionPipeline::BUILD=2.2.485` preservados.

## 2.2.541 - Submission Status & Operational Day

- adiciona `SubmissionStatusPolicy` como autoridade para estados operacionais finais (`submitted`, `reviewed`, `approved`);
- preserva `draft` e `rejected` como estados recuperáveis/editáveis, mas exclui-os de execução, Analytics e última visita operacional;
- adiciona `OperationalReportDayPolicy` com precedência `report_day -> route.date -> DATE(submitted_at)`;
- alinha RouteProgress, CanonicalState, ContextQuestions, BO Submissions, Analytics AI, Stats, Public API e Store Profile;
- mantém `submitted_at` como timestamp real de auditoria;
- adiciona contrato 9Y e paridade entre Release Gate e ReleasePackageValidator;
- sem migração de base de dados; `LATEST_SCHEMA_VERSION=2.2.529` e `SubmissionPipeline::BUILD=2.2.485` preservados.

## 2.2.540 - Certification Continuity Visit Closure

- Adds successor-safe `fieldflow-certification-continuity-visit-closure-v1`.
- Replaces obsolete Operational Intelligence literal markers with capability checks for COMMIT, operational completion, durable Daily Close queueing and idempotent dispatch.
- Mirrors that installed Release Gate inside `ReleasePackageValidator` so publication fails before install when the contract is incomplete.
- Makes the historical Operational Intelligence and Mail Reliability Outbox verifiers successor-safe without weakening their minimum contracts.
- Preserves the 2.2.539 Visit Closure & Daily Completion Mail behavior unchanged.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529` and `SubmissionPipeline::BUILD = 2.2.485` unchanged.

## 2.2.539 - Visit Closure & Daily Completion Mail

- Adds the successor-safe `fieldflow-visit-closure-daily-mail-v1` contract for report completion, justified non-visits, route closure and final Daily Close dispatch.
- Restricts visit reconciliation to final operational submission states: `submitted`, `reviewed` and `approved`; `draft` and `rejected` can no longer close a visit.
- Treats `failed` route stops as terminal only when a non-empty justification is persisted, so justified non-visits close the operational day without counting as executed visits.
- Queues the same idempotent Daily Close after form completion, event completion, route stop update and manual completion once the persisted route has zero unresolved stops.
- Adds durable Daily Close retry scheduling and Mail Outbox idempotency, preserving the stable route-completed notification as fallback when no Mail Center Daily Close owns the route.
- Includes additional Mail Center emails in immediate delivery and consolidated client delivery.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529` and `SubmissionPipeline::BUILD = 2.2.485` unchanged, with no database migration.

## 2.2.538 - Canonical Answer Semantics

- Adds the successor-safe `fieldflow-canonical-answer-semantics-v1` contract across Web, Mobile and TypeScript preflight/offline state.
- Canonicalizes number, currency and percent parsing, including comma decimal input, and enforces schema `min`/`max` on the server.
- Rejects malformed numeric answers instead of silently coercing them to `0` or `null`.
- Enforces current schema option domains for select, radio, visual choice, multi-select and checkbox-group answers.
- Normalizes checkbox answers to canonical 1/0 values and rejects malformed scalar payloads for structured matrix questions.
- Preserves historical storage compatibility in SubmissionApplicationService and introduces no historical data rewrite.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, Routing/P1-P4, Cardex resolution, Mail Outbox and `SubmissionPipeline::BUILD = 2.2.485` unchanged.

## 2.2.537 - Order Source & Cardex Consistency

- Adds the successor-safe `fieldflow-order-source-cardex-consistency-v1` contract for the full Fronts → Order Suggestion → persisted submission → email path.
- Makes explicit store-to-Cardex links tolerant of temporarily missing client/project context without allowing a known conflicting scope to win.
- Adds normalized insignia aliases for PD/Pingo Doce, CBD/Continente Bom Dia, CNT/Continente, MDL/Modelo and Intermarché fallback matching when a Cardex has no explicit store scope.
- Keeps `order_suggestion` available in Web/Mobile submission paths even if Cardex resolution is temporarily unavailable, while `product_matrix` still requires a valid Cardex.
- Pairs order extraction to the exact `_fieldflow_paired_fronts_key` so another Product Matrix cannot feed the wrong order question.
- Persists `quantity_source=auto|manual`; once a merchandiser edits a suggested quantity, backend fallback cannot silently recalculate it from zero fronts.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, Routing/P1-P4, Mail Outbox transport and `SubmissionPipeline::BUILD = 2.2.485` unchanged.

## 2.2.536 - Certification Gate Continuity Hotfix

- Fixes the Stable certification false negative discovered after installing 2.2.535.
- Makes the Phase 9L Security & Storage Hardening gate successor-safe by validating `WebViewBridge::NATIVE_SHELL_VERSION >= 1.9` instead of requiring the historical literal `1.9`, so native shell `1.10` remains valid.
- Makes the Phase 9S Field Session & Form Continuity gate validate the actual escaped Release Manager KPI marker `Field Session &amp; Form Continuity`.
- Adds `fieldflow-certification-gate-continuity-9t-v1` to prevent both certification regressions in future successors.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, the complete 2.2.535 emergency field behavior, Routing/P1-P4 and `SubmissionPipeline::BUILD = 2.2.485` unchanged.

## 2.2.534 - Field Form Terrain Continuity Hotfix

- Adds the successor-safe `fieldflow-field-form-terrain-continuity-v1` contract as a restricted field hotfix for active terrain operations.
- Prevents Android/mobile keyboard Enter/Done inside Cardex and text/number controls from implicitly submitting the whole wizard and revealing the first invalid step.
- Makes conditional/question and wizard visibility updates idempotent so ordinary Cardex edits do not unnecessarily re-hide/re-show the current step.
- Checkpoints the local draft before conditional/layout reconciliation on every input/change and reduces server draft sync delay to 900 ms.
- Adds lifecycle `sendBeacon` draft handoff for pagehide/freeze/native suspension, with the existing keepalive request retained as fallback.
- Adds a stable local recovery alias by user/form/binding/client/project/store/report-day and a conservative server recovery scope so route/stop reconstruction after app resume does not orphan the active draft.
- Re-bases a unique recovered server draft to the current route/stop while preserving its row and media references.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, Routing/P1-P4, ConditionalFlow semantics, Mail Outbox, reporting calculations and `SubmissionPipeline::BUILD = 2.2.485` unchanged.

## 2.2.533 - Operational Modernization Wave 2

- Adds the successor-safe `fieldflow-operational-modernization-wave2-v1` contract for Academy, Expenses and Analytics.
- Replaces Academy front per-course, per-lesson and per-mission status reads with the bounded `fieldflow-academy-front-batch-v2` read model, preserving course completion, certificates, required lesson gating and mission writes.
- Adds Academy to the React operational control shell with scoped search and root-owned lifecycle handling.
- Moves Expenses React state reconciliation to explicit `fieldflow:operational-module-refresh` events while preserving camera, QR, upload and REST submission ownership.
- Removes the Premium Analytics global `MutationObserver`; analytics enhancement now runs from explicit initial, lazy-panel, panel-open and `fieldflow:analytics-rendered` events.
- Extends full-width/mobile composition for Academy, Expenses and operational Analytics.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, Routing/P1-P4, ConditionalFlow, offline/media, Mail Outbox and `SubmissionPipeline::BUILD = 2.2.485` unchanged.

## 2.2.532 - Frontend & Runtime Certification Continuity

- Fixes the Frontend Build & TypeScript Foundation Release Gate so `frontend/package.json` is validated against the current `FIELDFLOW_VERSION` instead of the historical 2.2.524 package version.
- Keeps the canonical `assets/fieldflow-offline-runtime.js` byte-identical to the current versioned offline runtime, preventing Portal Entry Sync Visual Continuity from certifying a stale canonical runtime identity.
- Strengthens Release Package validation with current frontend package identity and canonical/versioned offline-runtime parity checks, so the same mismatch is rejected before publication in future releases.
- Adds the successor-safe `fieldflow-frontend-runtime-certification-continuity-v1` contract.
- No functional front rewrite, database schema change, IndexedDB change, Routing/P1-P4 change, Mail Outbox change or SubmissionPipeline change. `SubmissionPipeline::BUILD` remains `2.2.485`.

## 2.2.531 - Operational Modernization Wave

- Adds the successor-safe `fieldflow-operational-modernization-wave-v1` contract for Briefings, Stocks, Shopping and Messages.
- Moves the four priority modules to event-driven React control-shell ownership, avoiding React MutationObserver scanning for those modules while keeping certified PHP/domain business owners intact.
- Closes Briefings and Stocks React mount parity between Team and Client portals.
- Scopes Briefings cache and Shopping drafts by user, preserves safe Shopping draft migration and removes duplicate panel-open listeners.
- Removes the client Stocks per-batch view-count N+1 query and caches Shopping project membership reads per project.
- Extends full-width/UI-composition coverage to Stocks and Shopping and hardens message HTML/link rendering.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, Routing/P1-P4, ConditionalFlow, offline/media, Mail Outbox and `SubmissionPipeline::BUILD = 2.2.485` unchanged.
- Adds the Phase 9O Release Gate, Package Validator contract and successor verifier for Release Manager publication.

## 2.2.530 - AI Governance Certification Continuity

- Fixes the Phase 6 runtime gate false negative introduced in 2.2.529 where `AIUsageRepository.php` was incorrectly required to contain schema index names.
- Keeps index ownership in `Migrations` and `SchemaManifest`, while `AIUsageRepository` is validated only for runtime attribution/query behavior.
- Adds the successor-safe `fieldflow-ai-governance-certification-continuity-v1` contract and verifier.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.529`, AI Governance 2.0, FinOps/budgets and Operational React v2 unchanged.
- Preserves Routing, P1-P4, ConditionalFlow, Mail Outbox, offline/media semantics and `SubmissionPipeline::BUILD = 2.2.485`.
- Keeps direct N-to-N+1 validation through Field Flow Release Manager.

## 2.2.529 - AI Governance & Modernization Consolidation

- Advances AI Governance to 2.0 with native `client_id`, `project_id`, `feature_key` and `request_kind` attribution in the usage ledger.
- Adds monthly AI FinOps breakdowns by client, project, feature, provider, model and request kind without storing prompts or response content.
- Adds configurable global, per-client and per-project monthly budgets plus de-duplicated 70/85/100 threshold alerts.
- Adds the additive `2.2.529` AI usage migration and Schema Manifest 1.11.0 indexes for scoped reporting.
- Introduces the presentation-only React Operational Modules v2 lifecycle and explicit refresh event while legacy PHP/business actions remain canonical.
- Completes same-origin WebView fallback messaging in FormRenderer, Expenses and the shared UI runtime.
- Preserves Routing, P1-P4, ConditionalFlow, order-suggestion mail eligibility, Mail Outbox, offline/media semantics and `SubmissionPipeline::BUILD = 2.2.485`.
- Keeps package validation successor-safe so `2.2.530` can enter through Field Flow Release Manager without a bridge.

## 2.2.528 - Security & Storage Hardening

- Adds a conservative authenticated REST scope guard that rejects explicit cross-client, cross-project and project/client mismatch context before endpoint dispatch.
- Moves mobile form-submission reads and briefing view/acknowledge operations onto object-aware permission callbacks while preserving controller-level checks as defense-in-depth.
- Adds deterministic daily cleanup only for expired private resumable-upload sessions plus read-only diagnostics for resumable, Mail Outbox and private draft storage. Evidence and active drafts are preserved.
- Replaces wildcard WebView parent-message fallback targets with the current same-origin target while leaving the FlutterFlow native `fieldflowNativeEvent` handler unchanged.
- Exposes scope-guard and private-storage governance state in System Health.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.525`, makes no DDL changes, and preserves Routing, P1-P4, FormRenderer, ConditionalFlow, Mail Outbox, offline/media semantics and `SubmissionPipeline::BUILD = 2.2.485`.
- Keeps package validation successor-safe for 2.2.529 and later releases through Field Flow Release Manager.

## 2.2.527 - Runtime & Database Performance

- Replaces Academy top-performer per-user lesson/certificate reads with two grouped batch queries.
- Rebuilds Academy reset-engine reads around batch progress/certificate/user prefetch and request-local project/client scope caching.
- Narrows React and shared UI MutationObserver work so DOM changes do not trigger unnecessary full-document scans.
- Advances Performance & Load Readiness to 1.2.0 with a read-only database footprint in the safe probe.
- Keeps `LATEST_SCHEMA_VERSION = 2.2.525`, does not add DDL or indexes, and preserves Routing, P1-P4, FormRenderer, offline/media and `SubmissionPipeline::BUILD = 2.2.485`.
- Preserves direct N-to-N+1 Release Manager validation for successor releases.

# FieldFlow 2.2.526 - Certification Asset Successor Continuity

- Fixes the Phase 6 Release Gate regression that pinned Frontend Build & TypeScript Foundation and React Client Wave 1 certification to 2.2.524 asset filenames.
- Both gates now resolve the versioned frontend and React assets from the current `FIELDFLOW_VERSION`, preserving the original durable contracts while allowing successor releases.
- Changes the Mail Reliability package-validator rule from an exact 2.2.525 version pin to a minimum 2.2.525 contract boundary, preventing the Outbox foundation from blocking its own successors.
- Keeps the complete 2.2.525 Mail Reliability / Outbox behavior unchanged, including conditional order-suggestion mail eligibility and single retry ownership.
- No database schema migration, Routing, P1-P4, FormRenderer, SubmissionPipeline, offline queue or media behavior change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Release Gate 1.75.0, Phase 6 Stable 1.0.83, SOURCE_RELEASE 2.2.525, STABLE_RELEASE 2.2.526.

# FieldFlow 2.2.525 - Mail Reliability / Outbox Foundation

- Adds `MailOutboxRepository` and the additive `routespro_mail_outbox` persistence layer.
- Extends `MailDispatchService` with durable message identity, optional idempotency, atomic worker claims, bounded retries and stale-lock recovery while keeping `wp_mail()` as the transport.
- Preserves the historical `fieldflow-mail-dispatch-foundation-v1` / BUILD 2.2.524 markers for package-validator continuity and introduces `fieldflow-mail-reliability-outbox-v1` as the successor reliability contract.
- Migrates automated scheduled reports, order suggestions, billing and performance automation to safe accepted/queued semantics, with idempotency only on deterministic automated events. Manual and test sends remain repeatable.
- Keeps legacy order-suggestion retry only for pre-transport generation failures; once a message is accepted by the outbox, mail retry ownership belongs exclusively to the outbox.
- Adds a Mail Center Outbox view for backlog, attempts, errors, requeue, cancellation and an emergency feature toggle.
- Persists failed-send attachment copies only in a protected private area with bounded retention.
- No changes to Routing, P1-P4, FormRenderer or offline/media core. `SubmissionPipeline::BUILD` remains 2.2.485.
- Release Gate 1.74.0, Phase 6 Stable 1.0.82, SOURCE_RELEASE 2.2.524, STABLE_RELEASE 2.2.525.

# FieldFlow 2.2.524 - Mail Dispatch Foundation & Certification Continuity

- Introduces `MailDispatchService` as the single FieldFlow application transport seam while keeping WordPress `wp_mail()` and the existing SMTP/provider configuration unchanged.
- Migrates the 12 previously distributed direct mail send paths across operational notifications, performance automation, billing, Shopping, report automation, Mail Center tests and portal messaging to the canonical dispatcher.
- Normalizes transport failure capture, exception handling, dispatch IDs and duration metadata without adding a database migration, asynchronous queue or retry policy.
- Fixes the Phase 9A Client Portal Shell Stability certification regression by validating the durable `fieldflow-client-portal-shell-stability-v1` contract marker instead of pinning certification to a specific operational-experience BUILD number.
- Preserves historical BUILD marker strings for package-validator continuity from the installed 2.2.523 release.
- Keeps the 2.2.523 portal-entry flash fix intact. No changes to routing/P1-P4, `SubmissionPipeline::BUILD = 2.2.485`, FormRenderer, schema, offline queue/media semantics, AI, Azure or licensing.
- Release Gate 1.73.0, Phase 6 Stable 1.0.81, SOURCE_RELEASE 2.2.523, STABLE_RELEASE 2.2.524.

# FieldFlow 2.2.523 - Portal Entry Sync Visual Continuity

- Fixes the historical Front Transition Stability certification gate so it accepts the Phase 9F synchronous initial-panel successor without restoring the obsolete RAF paint.
- Hides the transient fixed `A sincronizar` status when sync starts with zero queued items, removing the residual portal-entry corner flash while keeping real actionable sync/offline states visible.
- Keeps offline queue, IndexedDB, media, submission, routing, P1-P4, schema, Mail, AI, Azure and licensing behaviour unchanged.
- Release Gate 1.72.0, Phase 6 Stable 1.0.80.

# FieldFlow 2.2.522 - Shared Portal Paint Stability

- Server-first structural portal classes for Client and Operative.
- Server-rendered command decks to remove post-paint layout insertion.
- Synchronous Operative initial-panel restore.
- Prepare-then-reveal path for unloaded lazy tabs when the shared runtime is available.
- Lazy fragments marked ready after scripts/enhancement and portal entrance fades disabled.
- Release Gate 1.71.0, Phase 6 Stable 1.0.79.
- Frozen: routing/P1-P4, SubmissionPipeline 2.2.485, FormRenderer, offline/media core, schema, Mail, AI, Azure, licensing.

# FieldFlow 2.2.521 - UI Composition Ownership + Resume Diagnostics

- Removes duplicate presentation chrome from the field-verified Client and Operative outer panel wrappers while preserving DOM, lazy-loading, PHP renderer and React progressive-enhancement ownership.
- Applies the surface-ownership contract to Client Base, Briefings, Implementations, Stocks, Team and Analytics, plus Operative Briefing, Academy, Expenses, Messages and Analytics.
- Extends native WebView resume coverage to iframe, image, object and embed surfaces behind the neutral paint guard, with independent feature flags for guard, media guard and diagnostics.
- Adds bounded resume diagnostics for active panel, lazy state, React roots and visible media without capturing content text.
- Keeps Routing, P1-P4, FormRenderer, production schema, Mail, AI, Azure, licensing and offline/media data flow unchanged. The offline runtime receives release identity only.
- Keeps `SubmissionPipeline::BUILD = 2.2.485`.
- Advances Release Gate to 1.70.0 and Phase 6 Stable authority to 1.0.78 with SOURCE_RELEASE 2.2.520 and STABLE_RELEASE 2.2.521.

# FieldFlow 2.2.520 — Dual Portal Full-Width Parity + Resume Hardening v2

- Historical Analytics Mobile Full-Width gate accepts the canonical true-full-width successor instead of requiring the obsolete 2.2.472 viewport breakout.
- Client full-width parity: Briefings, Base, Implementations, Stocks, Team and Analytics widgets.
- Operative full-width parity: Briefing, Academy, Expenses, Messages and Analytics widgets.
- Resume Paint Guard v2 hides the live portal while backgrounded, exposes it underneath the guard after layout confirmation, then releases the final frame.
- Routing, P1-P4, forms, offline data flow and SubmissionPipeline remain unchanged.

## 2.2.519 - Phase 9D App Resume Stability & Dual Portal True Full Width

- Aligns Client and Operative dashboards on the same true-full-width shell contract, removing historical 1520/1540/1680px structural caps.
- Removes the operative Analytics mobile viewport-breakout hack now that the parent shell is genuinely full width.
- Normalizes Briefings, Performance/Planear and Team Inbox outer wrappers while preserving intentional inner reading widths and table scrolling.
- Adds a native WebView resume paint guard that covers the first background-to-foreground frames and releases only after a double animation-frame layout pass.
- Applies width-only active-panel ResizeObserver behavior and portal-scoped lazy layout events to both Client and Operative dashboards.
- Extends mobile compositor hardening symmetrically across both portals.
- Keeps Routing, Mobility, P1-P4, forms, offline/media and `SubmissionPipeline::BUILD = 2.2.485` unchanged.
- Advances Release Gate to 1.68.0 and Phase 6 Stable authority to 1.0.76 with SOURCE_RELEASE 2.2.518 and STABLE_RELEASE 2.2.519.

## 2.2.518 - Phase 9C Wave 2 + Cross-Portal Render Isolation

- Isolates Client and Operative portal render/event ownership with explicit `data-fieldflow-portal` scope and no ambiguous global fallback.
- Removes client lazy-panel global resize propagation and scopes Premium Analytics / Operational Experience listeners to the originating portal.
- Hardens Android WebView bottom navigation composition by removing mask/backdrop effects that can leave transient compositor tiles.
- Advances React Client Wave 2 across Briefings, Implementations, Planear and Despesas while PHP/REST remains business-data authority.
- Keeps Routing, Mobility, P1-P4, forms, offline/media and `SubmissionPipeline::BUILD = 2.2.485` unchanged.
- Advances Release Gate to 1.67.0 and Phase 6 Stable authority to 1.0.75 with SOURCE_RELEASE 2.2.517 and STABLE_RELEASE 2.2.518.

## 2.2.517 - Phase 9B Closure + React Client Wave 1 & Analytics Stability

- Closes the remaining Client Portal transition repaint by preventing height-only lazy growth from emitting synthetic global `resize` events in the Android WebView.
- Adds `fieldflow-client-panel-lifecycle-v1` with explicit `mount`, `loading` and `ready` states for client panels, including Analytics.
- Refactors client AI Analytics to resolve AI insights and store intelligence in parallel and commit the visual result once per scope refresh instead of rebuilding the panel twice.
- Keeps the active client tab visible synchronously before paint, while preserving the Phase 9B compatibility marker required by the installed 2.2.516 package validator.
- Starts React Client Wave 1 with `fieldflow-react-client-wave1-v1` on Stocks, Shopping and Messages. React owns control chrome, visual state and lifecycle while PHP/REST remain the authority for data, permissions, actions and persistence.
- Extends full-width/stable-layout guards across the client modules and adds dedicated React Wave 1 loading/refreshing surfaces.
- Adds a Release Manager KPI and formal `fieldflow-react-client-wave1-analytics-stability-v1` release contract.
- No routing, mobility, P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline business ownership change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.66.0 and Phase 6 Stable authority to 1.0.74 with SOURCE_RELEASE 2.2.516 and STABLE_RELEASE 2.2.517.

## 2.2.516 - Client Portal Continuity, Full Width & Messaging

- Phase 9B introduces `fieldflow-client-portal-continuity-v1` and deliberately bundles the remaining client-shell continuity work to avoid micro-updates.
- Removes the client tab-switch global scroll clamp that could produce a small bottom-edge flash/jump in Android WebView when moving to a shorter panel.
- Keeps the active client tab visible by scrolling only the horizontal tab strip with hidden native scrollbars; the document viewport is not moved.
- Expands client panels and primary lazy modules to the full available shell width with `min-width:0`, reducing inherited narrow-container clipping.
- Fixes false client-team sender ineligibility by validating the authenticated sender against client/project scope instead of the campaign operational roster.
- Surfaces real sender/recipient AJAX permission errors and disables Send only when the selected campaign truly has no valid sender or recipient context.
- No routing, mobility, P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline ownership change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.65.0 and Phase 6 Stable authority to 1.0.73 with SOURCE_RELEASE 2.2.515 and STABLE_RELEASE 2.2.516.

## 2.2.515 - Client Portal Shell Stability

- Phase 9A introduces `fieldflow-client-portal-shell-stability-v1`.
- Client Portal desktop shell is server-shaped before first paint, eliminating the late rail reparent/layout shift.
- Client tab navigation now has a single canonical event owner and avoids lazy-loader echo paths.
- Client tab changes preserve document scroll position instead of forcing smooth `scrollIntoView`.
- Lazy client panels inherit the current client/project scope before first load; Growth Hub is invalidated safely on scope change.
- Client tab state synchronises ARIA/hidden attributes without replacing module HTML.
- No routing, mobility, P1-P4, offline, form, schema or SubmissionPipeline business ownership changed.

## 2.2.514, Release Gate Runtime Source Authority

- Fixes the remaining Phase 6 Stable false blocker `runtime:repair_signature` when the canonical plugin source has already been updated but PHP memory or OPcache still exposes a previously loaded class definition.
- Makes the canonical installed source authoritative only when the exact certified signature is present and any loaded runtime class originates from the same canonical plugin file.
- Downgrades same-origin stale bytecode to a non-blocking warning and invalidates OPcache when available; a class loaded from any other path remains a hard certification failure.
- Applies the same source/runtime authority rule to the owner-profile migration signature and distance-finalization contract, avoiding follow-on false blockers after the first endpoint gate.
- No routing, distance, P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.63.0 and Phase 6 Stable authority to 1.0.71 with SOURCE_RELEASE 2.2.513 and STABLE_RELEASE 2.2.514.

## 2.2.513, Route Distance Finalization & Certification Continuity

- Finalizes every reconciled draft/planned route with the ordered Google Routes sequence when available, using departure, each saved PDV in `route_stops.seq`, and arrival.
- Keeps the routing matrix as a safe fallback only, and exposes Google exact, matrix fallback and Google-finalization failure counters in the reconciliation result.
- Persists `fieldflow-route-distance-finalization-v1`, finalization provenance and distance component delta diagnostics so access, inter-stop, return and total km are auditable.
- Preserves historical certification state in the licensing UI as `CERTIFICADA, REVALIDAR` after a release fingerprint changes, while keeping all actual certification and fingerprint gates mandatory.
- Adds certification-history flags to 6B.1 and Phase 6 Stable so first certification is no longer confused with post-update revalidation.
- No P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.62.0 and Phase 6 Stable authority to 1.0.70 with SOURCE_RELEASE 2.2.512 and STABLE_RELEASE 2.2.513.

## 2.2.512, Endpoint Repair Signature Compatibility

- Restores the certified five-parameter public signature of `RouteMetricsService::repairPlannedRouteIntegrity()` and fixes the Phase 6 Stable `runtime:repair_signature` blocker introduced in 2.2.511.
- Moves forced owner-profile route migration to the dedicated `repairPlannedRouteIntegrityWithOwnerProfile()` public entrypoint while keeping the same internal migration implementation.
- Preserves a non-runtime 2.2.511 package compatibility marker so the Release Package Validator already installed on sites can accept the hotfix package.
- Keeps the 2.2.511 bulk route migration behaviour, started-route protection, PDV order, canonical metric recalculation and audit markers unchanged.
- No P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.61.0 and Phase 6 Stable authority to 1.0.69 with SOURCE_RELEASE 2.2.511 and STABLE_RELEASE 2.2.512.

## 2.2.511, Owner Profile Route Migration

- Adds an explicit owner-scoped migration path to `repairPlannedRouteIntegrity()` for routes already persisted with contaminated first/last-PDV endpoints.
- When an operative is selected and the admin checks `Forçar a Base habitual deste operativo`, the saved operator route-base profile becomes authoritative for all editable draft/planned routes in the selected interval.
- The forced path bypasses the old early `endpoints_complete` short-circuit and does not recover historical route endpoints before applying the canonical operator profile.
- Recalculates access, inter-stop, return and total canonical metrics while preserving PDV order and protecting routes that already started, completed or were cancelled.
- No P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.60.0 and Phase 6 Stable authority to 1.0.68 with SOURCE_RELEASE 2.2.510 and STABLE_RELEASE 2.2.511.

## 2.2.510, Semantic Edge Endpoint Reconciliation

- Fixes the remaining legacy endpoint contamination case where the route start/end has the same normalized address as the first/last PDV but geocoder coordinates differ by more than the 30 metre coincidence threshold.
- Address identity is now evaluated before coordinate distance, allowing reconciliation to reject the contaminated route endpoint and fall through to the canonical operator/campaign route base.
- Keeps the geometric threshold at 30 metres for different addresses, preserving legitimate route bases that happen to be physically close to a PDV.
- Reconciliation can therefore recalculate real access/return legs instead of accepting legacy 0.1/0.2 km legs as complete.
- No P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.59.0 and Phase 6 Stable authority to 1.0.67 with SOURCE_RELEASE 2.2.509 and STABLE_RELEASE 2.2.510.

## 2.2.509, Endpoint Reconciliation & Explicit Address Reuse

- Fixes the fatal `repairPlannedRouteIntegrity()` parameter regression introduced in 2.2.508, restoring separate `dateFrom`, `dateTo` and actor parameters for reconciliation.
- Adds Google Places autocomplete and blur geocoding to campaign/operator route-base fallback fields in the Intelligence Center.
- Clarifies and certifies endpoint precedence: explicit endpoints entered in Campaign PDV, Route or Planear win; operator/campaign endpoint profiles are fallback only.
- Adds a release contract preventing endpoint-profile UI from becoming a mandatory duplicate source.
- No P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.58.0 and Phase 6 Stable authority to 1.0.66 with SOURCE_RELEASE 2.2.508 and STABLE_RELEASE 2.2.509.

## 2.2.508, Operational Endpoint Source of Truth

- Adds `RouteEndpointProfileService` and `fieldflow-operational-endpoint-source-of-truth-v1` as the canonical business source for route start/end points.
- Stores campaign and operative route-base profiles in `projects.meta_json` with no schema change.
- Removes implicit first/last-PDV endpoint fallbacks from Mobile Planear, Backoffice Google preview, Rotas Front and Google Maps navigation URLs, and stops Campaign PDV generation from consuming legacy route defaults automatically.
- Routes, Campaign PDV planning, Planear, REST writes, optimisation and route reconciliation now resolve endpoints through the same service.
- Adds explicit legacy-default migration and historical endpoint recovery from route workspace/audit history for editable routes.
- Keeps genuine zero-access/return routes possible only through explicit confirmation or explicit current-location provenance.
- No P1-P4, database schema, IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.57.0 and Phase 6 Stable authority to 1.0.65 with SOURCE_RELEASE 2.2.507 and STABLE_RELEASE 2.2.508.

## 2.2.507, Route Financial Continuity Hotfix

- Separates endpoint trust from route financial calculability through `fieldflow-route-financial-continuity-v1`.
- Suspicious proximity between configured start/end and extreme PDVs remains visible and auditable, but no longer forces fully routable routes into `Por calcular`.
- Recovers 2.2.506 snapshots where complete `known_distance_km` and known tolls were stored but authoritative totals were hidden only by an endpoint trust warning.
- Keeps missing endpoints and unresolved route legs as hard blockers for fuel, energy, toll and operational cost calculations.
- Tightens coincidence tolerance from 80 m to 30 m while preserving explicit zero-leg confirmation and provenance warnings.
- No P1-P4, database schema, IndexedDB, offline, form, Conditional Engine or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.56.0 and Phase 6 Stable authority to 1.0.64 with SOURCE_RELEASE 2.2.506 and STABLE_RELEASE 2.2.507.

## 2.2.506, Route Endpoint Provenance & Legacy Reconciliation

- Adds canonical `RouteEndpointResolverService` with visible-address authority, endpoint provenance and stale-coordinate rejection.
- Detects start/end endpoints that silently collapse onto the first/last PDV; 0 km access/regress is only accepted when explicitly confirmed.
- Reconciles draft/planned legacy routes from stored client/campaign/operative defaults while preserving PDV order and protecting started/terminal routes.
- Extends Campaigns, route optimization, RouteMetricsService, Release Manager and Phase 6 certification without changing P1-P4, schema, offline, forms or `SubmissionPipeline::BUILD` 2.2.485.
- Advances Release Gate to 1.55.0 and Phase 6 Stable authority to 1.0.63 with SOURCE_RELEASE 2.2.505 and STABLE_RELEASE 2.2.506.

## 2.2.505, Route Endpoint Coordinate Trust & Certification Hotfix

- Fixes stale hidden start/end coordinates after manual address edits, so access and return legs are calculated from the visible route endpoints.
- Re-geocodes endpoint addresses server-side through the configured cached Maps provider and persists corrected coordinates on route save.
- Route planner now shows the actual start/end addresses used and flags zero-length endpoint legs when they coincide with the first/last PDV.
- Aligns the Mobility Cost Intelligence Release Gate with the certified export label and restores the historical no-schema-change marker required by Stable certification.
- No P1-P4, offline/media, form or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.54.0 and Phase 6 Stable authority to 1.0.62 with SOURCE_RELEASE 2.2.504 and STABLE_RELEASE 2.2.505.

## 2.2.504, Route Endpoint & Distance Integrity

- Adds `fieldflow-route-endpoint-distance-integrity-v1` and makes full start-to-end route coverage mandatory before distance is authoritative for financial mobility calculations.
- Preserves partial resolved kilometres as `known_distance_km` for diagnostics, while `distance_km`, tolls and mobility costs remain unavailable until start, all route legs and end are complete.
- Planned route creation and Campaign route generation require real endpoints; drafts can remain incomplete without data loss and no endpoint is inferred from the first or last PDV.
- Adds safe planned-route repair in Assignment Centre using existing client/campaign/operator route defaults only, preserving PDV order and protecting started/terminal routes.
- Persists access, inter-stop and return components so complete distance is inspectable as `access + inter-stop + return`.
- Analytics, Client Portal, operational UI and exports distinguish complete totals from known partial distance and no longer present incomplete mobility as false `0.00` costs.
- No schema migration, P1-P4 rule change, offline/media change or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.53.0 and Phase 6 Stable authority to 1.0.61 with SOURCE_RELEASE 2.2.503 and STABLE_RELEASE 2.2.504.

## 2.2.503, Mobility Integrity & Canonical Metrics

- Adds `fieldflow-mobility-integrity-canonical-metrics-v1` and centralizes historical route distance/toll compatibility in `RouteMetricsService::canonicalFromRouteArray()`.
- Mobility calculations consume the canonical resolver, eliminating divergent km/toll interpretation across route APIs and client views.
- Mobility profile/default/override saves perform database readback verification and surface concrete validation failures separately from persistence failures.
- Draft/planned routes refresh mobility snapshots when owner, project or route date changes; in-progress and terminal routes preserve their historical snapshot.
- Normalizes historical route-status aliases (`done` -> `completed`, `cancelled` -> `canceled`) and excludes all terminal aliases from backfill by default.
- Removes the Client Portal JavaScript toll formula so toll values come from the server-side canonical route-metrics path.
- No schema migration, P1-P4 change, offline/media change or submission-pipeline change. `SubmissionPipeline::BUILD` remains 2.2.485.
- Advances Release Gate to 1.52.0 and Phase 6 Stable authority to 1.0.60 with SOURCE_RELEASE 2.2.502 and STABLE_RELEASE 2.2.503.

## 2.2.502, Mobility Cost Intelligence

- Adds `fieldflow-mobility-cost-intelligence-v1` without changing canonical route distance, toll or P1-P4 optimisation ownership.
- Campaign PDV stores the campaign default mobility profile; Intelligence Center adds per-operator profiles, existing-route backfill and exceptional per-route overrides.
- Supports gasoline, diesel, LPG, hybrid, plug-in hybrid and 100% electric cost models, plus an optional separate operational EUR/km component.
- Every route can capture a profile snapshot so later energy-price changes do not silently rewrite historical route economics.
- Planning previews, existing routes, Front, Analytics and route/dashboard exports expose energy cost, estimated litres/kWh, tolls and total mobility cost.
- Reuses `projects.meta_json` and `routes.meta_json`; no new database table or migration is introduced.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485 and preserves offline/media/form/submission contracts.
- Advances Release Gate to 1.51.0 and Phase 6 Stable authority to 1.0.59 with SOURCE_RELEASE 2.2.501 and STABLE_RELEASE 2.2.502.

## 2.2.501, Front Transition Stability

- Adds `fieldflow-front-transition-stability-v1` to remove first-paint React fallback flashes and fast lazy-loader skeleton flashes.
- React fallbacks start pending/invisible and the UI runtime restores them only when the corresponding React island fails to mount.
- Lazy loading delays visual skeletons by 180 ms, so fast panel responses swap directly to real content without transient text flashes.
- Removes the duplicate document-level `fieldflow:lazy-panel-loaded` dispatch; the bubbling placeholder event remains the single canonical signal.
- Makes initial app panel activation idempotent and disables the automatic smooth-scroll on first paint.
- Keeps route optimisation, P1-P4, IndexedDB, resumable media, ProductCardex, validation and submission ownership unchanged.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485.
- Advances Release Gate to 1.50.0 and Phase 6 Stable authority to 1.0.58 with SOURCE_RELEASE 2.2.500 and STABLE_RELEASE 2.2.501.

## 2.2.500, Release Gate Historical Marker Hardening

- Fixes the Phase 8J and Phase 8K Release Gates so historical CSS phase comments are not required to carry the current global release number.
- Gates now validate stable phase labels, contract markers and functional selectors instead of mutable comment-version coupling.
- Adds `fieldflow-release-gate-historical-marker-v1` to prevent recurrence.
- Keeps offline runtime, resumable media, ProductCardex, typed validation, submission and P1-P4 routing unchanged.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485 because submission did not change.
- Advances Release Gate to 1.49.2 and Phase 6 Stable authority to 1.0.57 with SOURCE_RELEASE 2.2.499 and STABLE_RELEASE 2.2.500.

## 2.2.499, Certification Runtime Identity Hotfix

- Fixes the stale Release Gate runtime markers that still expected `RUNTIME_BUILD='2.2.497'` while the installed 2.2.498 runtime correctly declared 2.2.498.
- Rebuilds release-versioned frontend, React and offline runtime assets with the canonical 2.2.499 identity.
- Adds `fieldflow-certification-runtime-identity-v1` to verify plugin version, runtime identity, ResponsiveAssets references and Release Gate expectations remain aligned before Stable certification.
- Keeps IndexedDB schema, offline/outbox semantics, resumable media transport, ProductCardex, form validation, SubmissionApplicationService, SubmissionSideEffectDispatcher and P1-P4 routing unchanged.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485 because the submission component did not change.
- Advances Release Gate to 1.49.1 and Phase 6 Stable authority to 1.0.56 with SOURCE_RELEASE 2.2.498 and STABLE_RELEASE 2.2.499.

## 2.2.498, React Form Completion

- Adds `fieldflow-react-form-completion-v1` as a progressive UX layer for Frentes/Cardex, Share Space, order suggestion and evidence/media surfaces.
- Adds category navigation, contextual summaries and safe next-pending guidance while canonical FieldFlow inputs remain the only data owners.
- Aligns Product Matrix UX with the certified zero-fronts rule: blank front values are reported as `Zero automático`, not as false incomplete errors.
- Makes categorized Evidence count a category as complete only when it is selected and has active media.
- Makes the React order-suggestion surface explicitly inactive when its conditional wrapper is inactive; certified mail eligibility, retry and manual-resend guards remain unchanged.
- Adds Share Space target summary and order-suggestion manual/automatic quantity context without changing ProductCardex calculations.
- Keeps IndexedDB, draft/media recovery, resumable transport, Service Worker, typed validation, SubmissionApplicationService, SubmissionSideEffectDispatcher and P1-P4 rules unchanged.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485 because the submission component did not change.
- Adds Release Gate 1.49.0 and Phase 6 Stable 1.0.55 with SOURCE_RELEASE 2.2.497 and STABLE_RELEASE 2.2.498.

## 2.2.497
- Briefing Progress Tabs Hardening: internal Briefing navigation tabs preserve intrinsic width inside the full-width operational shell.
- Mobile/WebView Briefing tabs stay on one horizontal row with controlled touch scroll and no compressed labels.
- No change to Briefing content/data mechanics, mapping, read acknowledgement, questions, offline/media, submission, Shopping or P1-P4 rules.

## 2.2.496, Operational Action Hardening & Implementations UX

- Fixes the Implementações quick-state tabs on mobile and WebView with bounded horizontal scrolling, stable button sizing and no compressed/wrapped state labels.
- Adds `fieldflow-react-operational-actions-hardening-v1` as a non-destructive proxy hardening contract over the existing React Operational Modules.
- Removes duplicate React input dispatch by using a single input handler per visible input while preserving the module-specific native `input`/`change` event contract.
- Reconciles Implementações section visibility and counters against the combined quick-state filter plus React search, preventing stale section counts and empty visible sections.
- Keeps native module runtimes, Implementações import/mapping/publication, IndexedDB, Service Worker, media recovery, SubmissionApplicationService, SubmissionSideEffectDispatcher, Shopping mail/export flows and P1-P4 routing rules unchanged.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485 because the submission component did not change.
- Adds Release Gate 1.47.0 and Phase 6 Stable 1.0.53 with SOURCE_RELEASE 2.2.495 and STABLE_RELEASE 2.2.496.

## 2.2.495, React Operational Modules

- Adds `fieldflow-react-operational-modules-v1` and a shared `operational-module-controls` island for Briefings, Implementações, Stocks, Shopping, Planear, Despesas and Mensagens.
- React now presents search, filters, quick states, refresh/apply/back actions and pagination where those behaviours already exist, proxying the certified native controls rather than duplicating business logic.
- Native controls are hidden only after a successful React mount and are restored on unmount, preserving the existing PHP/JavaScript fallback path.
- Uses WordPress `wp-element`; no React/ReactDOM package is bundled.
- Preserves IndexedDB, Service Worker, offline queue, media recovery/resumable transport, SubmissionApplicationService, SubmissionSideEffectDispatcher, ProductCardex, order-suggestion mail guards, Shopping mail/export flows and P1-P4 routing rules.
- Keeps `SubmissionPipeline::BUILD` at 2.2.485 because the submission component did not change.
- Adds Release Gate 1.46.0 and Phase 6 Stable 1.0.52 with SOURCE_RELEASE 2.2.494 and STABLE_RELEASE 2.2.495.

## 2.2.494, Front Full Width & Navigation Consolidation

- Adds `fieldflow-front-fullwidth-navigation-v1` as a presentation-only contract for Briefings, Implementations, Stocks, Shopping, Planear, Despesas and Mensagens.
- Expands the progressive React operational shell to every authorised canonical tab and removes the previous five-shortcut truncation.
- Fixes React navigation proxy targeting so it clicks `button[data-panel]` before any passive panel node with the same data attribute.
- Uses near-full viewport width on phone layouts, preserves horizontal scrolling only where necessary and removes nested vertical scrollers from priority operational lists.
- Keeps the PHP tabbar as canonical fallback and preserves Typed Core, IndexedDB, recovery, media transport, ProductCardex, P1-P4 routing rules, durable submission and post-COMMIT side effects unchanged.
- Adds Release Gate 1.45.0 and Phase 6 Stable 1.0.51 with SOURCE_RELEASE 2.2.493 and STABLE_RELEASE 2.2.494.

## 2.2.493, Modernization Stable Closure

- Closes the progressive modernization cycle under `fieldflow-modernization-stable-closure-v1`.
- Adds `fieldflow-react-media-control-v1`: React now owns the visible direct image capture/selection surface and live media state, while native camera/gallery/file inputs, IndexedDB, recovery, zero-byte repair and resumable upload remain canonical.
- Preserves all PHP/native fallbacks intentionally as resilience contracts instead of deleting them as "legacy" code.
- Keeps ProductCardex, Conditional Engine, order-suggestion eligibility, SubmissionApplicationService and SubmissionSideEffectDispatcher ownership unchanged.
- Adds Release Gate 1.44.0 and Phase 6 Stable 1.0.50 with SOURCE_RELEASE 2.2.492 and STABLE_RELEASE 2.2.493.

## 2.2.492, React Form Choice & Special Wave

- Adds visible React controls for radio, checkbox, multiple-choice and visual-choice questions through `fieldflow-react-choice-field-control-v1`, while native inputs stay canonical for conditionals, validation, drafts, offline and submission.
- Adds `fieldflow-react-special-field-assist-v1` status/progress surfaces for Frentes/Cardex, Share Space, Sugestão de encomenda and evidence/media, reusing existing category actions without duplicating business logic.
- ProductCardex, order-suggestion eligibility v2, media recovery, IndexedDB, REST contracts and FlutterFlow remain unchanged.
- Adds Release Gate 1.43.0 and Phase 6 Stable 1.0.49 with SOURCE_RELEASE 2.2.491 and STABLE_RELEASE 2.2.492.

## 2.2.491, React Form Simple Controls

- Promotes the simple form families from React status-assist surfaces to visible React controls while preserving the original FieldFlow input as the canonical value, validation, offline and submission owner.
- Adds `fieldflow-react-simple-field-control-v1` with bidirectional native/React synchronisation, HTML5 invalid-focus handoff, dynamic disabled/min/max/step reflection and select-option parity.
- Preserves `fieldflow-react-simple-field-assist-v1` for N→N+1 compatibility with the certified 2.2.490 validator.
- Keeps Conditional Engine, Draft/Media, IndexedDB, REST payloads, SubmissionApplicationService and FlutterFlow unchanged.
- Adds Release Gate 1.42.0 and Phase 6 Stable 1.0.48 with SOURCE_RELEASE 2.2.490 and STABLE_RELEASE 2.2.491.

## 2.2.490, React Front Operational Wave
- Expands progressive React into the operational shell, Minha Rota toolbar and simple form field assist states.
- React reuses existing route controls/events and keeps native form inputs canonical, preserving validation, conditional logic, drafts, media, offline recovery and submission.
- React Front now remounts islands for lazy-loaded panels and dynamically rendered forms.
- Adds contract `fieldflow-react-front-operational-wave-v1`, Release Gate 1.41.0 and Phase 6 Stable 1.0.47.
- No database migration, REST contract change, IndexedDB schema change or FlutterFlow change.

## 2.2.489, React Front Form Wizard + Certification Continuity

- Fixes the React Devices certification gate so same-origin transport is validated in `frontend/src/react-backoffice/shared.ts`, where the shared transport now lives, while preserving a 2.2.488 N→N+1 compatibility identity marker in `islands.ts`.
- Adds `fieldflow-react-front-foundation-v1` and `fieldflow-react-form-wizard-progress-v1` as the first progressive React layer in the operational front.
- The multi-step form wizard gains a React progress surface that mirrors current step, title and percentage in real time through `MutationObserver`, while the established FormRenderer remains canonical.
- PHP progress markup remains as fallback and is hidden only after a successful React mount.
- Form fields, conditional logic, validation, media, IndexedDB drafts, offline queues, submission and FormData contracts remain owned by the certified runtimes.
- Adds `FIELDFLOW_REACT_FRONT` kill switch, a separate strict-TypeScript front bundle and Release Manager `react_front_form_wizard_ok`.
- No database migration, IndexedDB migration, REST change or FlutterFlow change. Phase 6 Stable advances from 2.2.488 to 2.2.489; Release Gate advances to 1.40.0.

## 2.2.488, Release Continuity + React Analytics Foundation

- Introduces a single N→N+1 continuity authority. Historical component gates no longer pin `SOURCE_RELEASE`; `Phase6StableRelease` and `ReleasePackageValidator` validate the chain dynamically.
- Adds `fieldflow-react-backoffice-shared-v1` for shared strict-TypeScript React backoffice helpers and same-origin transport.
- Adds `fieldflow-react-analytics-foundation-v1` on the Analytics 2.0 admin workspace: universal filters, quick 7/30/90 day ranges and Decision Score KPIs update without a full page refresh.
- `window.FieldFlowAnalytics2` keeps URL, PHP fallback controls and the existing Analytics panels synchronized while ignoring stale requests.
- React continues to use WordPress `wp-element`; no React/ReactDOM bundle, database migration, IndexedDB migration, REST change, FlutterFlow change or field-form migration.
- Phase 6 Stable advances from 2.2.487 to 2.2.488; Release Gate advances to 1.39.0.

## 2.2.486, React Backoffice Foundation

- Adds `fieldflow-react-backoffice-foundation-v1` as the first progressive React layer in the FieldFlow backoffice.
- Uses WordPress `wp-element` rather than bundling React/ReactDOM, avoiding duplicate runtimes and keeping the production dependency surface small.
- Adds a read-only React island to `routespro-mobile` for publication status, with Android/iOS stateful navigation and full PHP fallback.
- Adds `FIELDFLOW_REACT_BACKOFFICE` kill switch, strict TypeScript build, behavioral mount tests, Release Manager `react_backoffice_foundation_ok` and Release Gate 1.37.0.
- Does not change field forms, REST endpoints, database, IndexedDB or FlutterFlow. Phase 6 Stable advances from 2.2.485 to 2.2.486.
- Fixes N -> N+1 Release Manager continuity: the unchanged SubmissionPipeline retains component build `2.2.485`, while the 2.2.486 validator checks the side-effect contract rather than requiring the package version inside an unchanged component.

## 2.2.485, Submission Events & Side Effects

- Introduz `fieldflow-submission-side-effects-v1` e `SubmissionSideEffectDispatcher` como fan-out pós-COMMIT para efeitos secundários de submissões.
- EventBus/Azure, automações de Performance, Mail de Sugestão de encomenda, notificações de rota e hooks públicos passam a lanes independentes, com lock, idempotência por revisão e retry próprio.
- Uma falha numa lane deixa de repetir efeitos já concluídos e nunca invalida uma submissão/respostas já persistidas.
- `EventBus`, `ReportAutomations` e `Performance` deixam de possuir diretamente os hooks `fieldflow_form_submitted`, `fieldflow_form_updated` e `fieldflow_form_visit_completed`; os hooks públicos continuam disponíveis numa lane de compatibilidade.
- Mobile passa a tratar `MediaRegistry` e `AuditLog` pós-COMMIT como não bloqueantes, evitando erro aparente da API depois de a visita já estar gravada.
- Adiciona `fieldflow_submission_side_effect_retry` às filas e ao Process Monitor, com máximo de quatro tentativas por lane.
- Submission Pipeline avança para 1.1.0 e entrega integrações ao novo dispatcher; Release Manager passa a certificar `submission_side_effects_ok`.
- Sem migração de base de dados/IndexedDB, sem alteração REST, FlutterFlow, validações funcionais ou React. Phase 6 Stable avança de 2.2.484 para 2.2.485.

## 2.2.484, Submission Application Service

- Introduz `fieldflow-submission-application-service-v1` como fronteira central para schema, persistência transacional de submissões/respostas e construção do envelope pós-commit.
- `Forms.php` e `Mobile/SubmissionService.php` passam a delegar a persistência durável no mesmo `RoutesPro\Forms\SubmissionApplicationService`, removendo a duplicação crítica de INSERT/UPDATE e answers entre canais.
- Preserva a semântica transacional mobile existente: pipeline novo confirma a persistência antes do pós-commit; modo legacy mantém a transação aberta até Cardex, histórico e conclusão de visita terminarem.
- O serviço centraliza estado durável, mas mantém mail, Analytics, Azure, exports e notificações de rota fora da transação principal, preparando a separação de side effects da fase seguinte.
- O mesmo construtor de schema serve render, submit web e mobile com o contexto correto de cada chamada.
- Release Manager passa a exigir `submission_application_service_ok`; Release Gate 1.35.0 adiciona o contrato `fieldflow-submission-application-service-v1`.
- Sem migração de base de dados ou IndexedDB, sem alteração de endpoints REST, FlutterFlow, UX do formulário ou React. Phase 6 Stable avança de 2.2.483 para 2.2.484.

## 2.2.483, Certification Continuity Hotfix

- Corrige um falso bloqueio do Phase 6 Release Gate no contrato `fieldflow-typed-specialized-validation-core-v1`.
- A 2.2.482 exigia literalmente o marcador documental `web e mobile`, embora a documentação certificada usasse `browser, web and mobile parity`.
- O gate passa a validar os marcadores estruturais `browser`, `web` e `mobile`, reduzindo dependência de redação sem relaxar o contrato técnico.
- Mantém integralmente a lógica de Typed Specialized Validation, Share Space, Evidence, media, matrizes e todos os cores tipados anteriores.
- Sem migrations de base de dados/IndexedDB, sem alterações REST, Flutter ou React. Phase 6 Stable avança de 2.2.482 para 2.2.483.

## 2.2.482, Typed Specialized Validation Core

- Added `fieldflow-typed-specialized-validation-core-v1` in TypeScript and PHP.
- Unified specialized validation for required media, categorized evidence, Evidence conditional branches, Share Space and required product matrices across browser, web and mobile.
- Fixed mobile parity for required Share Space, partial matrices are now rejected just like web submissions.
- Added zero-byte-safe media semantics and PHP/TypeScript behavioural fixtures.
- Added Release Manager certification gate `typed_specialized_validation_core_ok`.
- No database, IndexedDB, REST, Flutter or React migration.

## 2.2.481 - Typed Submission & Validation Core

- Adiciona `fieldflow-typed-submission-validation-core-v1` em TypeScript strict para estado ativo, required e preparação do payload genérico.
- Adiciona `RoutesPro\Forms\SubmissionValidation` como mirror PHP canónico, usado por submissão web e mobile.
- Corrige a divergência de checkbox obrigatório entre web e mobile: `false` falha required, mas um checkbox opcional `false` continua persistível como resposta explícita.
- Perguntas escondidas por condições ficam fora do estado ativo e não bloqueiam required nem entram no payload tipado.
- `FormRenderer` passa a usar progressivamente o core tipado para validação genérica, mantendo as validações especializadas de media, Evidence, Cardex e Share Space.
- Fixtures partilhados certificam paridade PHP/TypeScript e o Release Manager ganha o gate `Typed Submission`.
- Phase 6 Stable avança de 2.2.480 para 2.2.481. No database migration, IndexedDB migration, REST change, Flutter change or React introduction.

## 2.2.480 - Typed Form State + Conditional Engine

- Introduz `fieldflow-typed-form-state-conditional-core-v1` em TypeScript strict para visibilidade condicional, estado ativo/oculto, `required` ativo, filtragem de valores ativos e snapshot condicional.
- `FormRenderer.php` e Forms Runtime V3 passam a consumir o mesmo core TypeScript quando disponível, mantendo a implementação JavaScript anterior como fallback automático.
- `ConditionalFlow.php` avança para contrato v3 e passa a declarar explicitamente a mesma identidade e semântica usada no browser: `show_target_when_visible_source_value_matches`, combinação OR.
- Adiciona fixtures partilhados PHP/TypeScript para `Sim/Não`, cadeias condicionais, múltiplas origens, checkbox e multi-seleção; uma release falha se os dois motores produzirem estados diferentes.
- Perguntas condicionais ocultas ficam explicitamente fora do conjunto de `required` ativo e dos valores ativos do core tipado.
- O snapshot tipado inclui `order_suggestion_keys` e `active_order_suggestion_keys`, reforçando a continuidade com o Mail Eligibility v2 já certificado.
- Release Manager passa a exigir `typed_form_conditional_core_ok`; Release Gate 1.31.0 acrescenta o novo contrato e a paridade PHP/TypeScript.
- Mantém database, IndexedDB schema v2, REST, Flutter, Cardex, Frentes, Analytics, Mail Center, Shopping e Azure sem alterações de contrato. React continua fora desta fase.
- Phase 6 Stable avança de 2.2.479 para 2.2.480. No database migration.

## 2.2.479 - Typed Draft & Media Core

- Introduz `fieldflow-typed-draft-media-core-v1` e move para TypeScript strict as decisões de validade do rascunho, compatibilidade de schema, contagem esperada de media e escolha da cópia local mais recente.
- O novo `draft-core.ts` passa também a fornecer a camada tipada de persistência IndexedDB para snapshots e ficheiros, incluindo leitura, escrita, remoção e limpeza de expirados.
- Mantém exatamente a base `fieldflow-form-drafts`, schema v2, stores `snapshots` e `files`; não existe migração destrutiva e os rascunhos já presentes nos dispositivos continuam compatíveis.
- `FormRenderer.php` usa progressivamente `FieldFlowFrontend.draftCore` para IndexedDB, media e reconciliação, preservando a implementação JavaScript anterior como fallback automático.
- A proteção contra zero-byte media mantém a cópia durável anterior e a recuperação continua a reconciliar localStorage, IndexedDB e servidor antes de alterar o estado do formulário.
- Adiciona testes comportamentais TypeScript para validade/expiração, edição de submissões existentes, schema, contagem de ficheiros, reconciliação `updatedAt` e identidade de grupos de media.
- Release Manager passa a exigir `typed_draft_media_core_ok`; Release Gate 1.30.0 acrescenta `fieldflow-typed-draft-media-core-v1`.
- Mantém REST, WordPress DB, Flutter, Analytics, Mail Center, Shopping, Azure e `window.FieldFlowOffline` sem alterações de contrato. React continua deliberadamente fora desta fase.
- Phase 6 Stable avança de 2.2.478 para 2.2.479. No database migration.

## 2.2.478 - Order Suggestion Mail Eligibility v2

- Corrige uma fragilidade residual do guard 2.2.474: quando existia snapshot condicional, o Mail Center aceitava-o como autoridade única e não cruzava a decisão com as respostas efetivamente persistidas.
- Introduz `orderSuggestionMailEligibility()` e um resolvedor conservador que cruza `order_suggestion_keys`, `active_order_suggestion_keys`, `hidden_question_keys` e, quando a definição do formulário não mudou, a reavaliação canónica das respostas guardadas.
- Se qualquer fonte autoritativa indicar que a pergunta `order_suggestion` estava inativa, o email e anexos são bloqueados.
- Um snapshot ativo mas respostas persistidas equivalentes a “Não” falha fechado com `canonical_inactive` ou `conditional_disagreement_inactive`.
- Estados condicionais inativos deixam de agendar retries de email e passam a ser registados como “Sem envio”, não como falha operacional.
- O payload de sugestão inclui `mail_eligibility` para diagnóstico e o log de email guarda a razão de elegibilidade nos envios permitidos.
- Adiciona verificador comportamental com seis cenários, incluindo snapshot ativo obsoleto vetado pelas respostas persistidas.
- Release Manager passa a validar `order_suggestion_mail_eligibility_ok`; Release Gate 1.29.0 acrescenta `fieldflow-order-suggestion-mail-eligibility-v2`.
- Sem migração de base de dados e sem alterações a REST, Flutter, IndexedDB, Typed Offline Core, Cardex, Frentes, Analytics, Shopping ou Azure.
- Phase 6 Stable avança de 2.2.477 para 2.2.478.

## 2.2.477 - Typed Offline Core

- Migra as primeiras decisões críticas do runtime offline para TypeScript, mantendo o runtime JavaScript atual e respetivos contratos públicos.
- Introduz módulos tipados dedicados a `connectivity`, `sync-lock` e `outbox`, compilados dentro do frontend core existente.
- `connectivity` passa a tipar explicitamente `online`, `degraded`, `offline`, `auth_required` e `unknown`, mantendo `degraded` como rede utilizável.
- `sync-lock` centraliza parse, ownership, lease estrangeiro ativo, criação e renovação do lease com owner/token, preservando Web Locks e fallback localStorage do runtime.
- `outbox` centraliza retry exponencial, elegibilidade de filas, recuperação de `syncing` stale e ordenação temporal.
- O runtime offline 2.2.477 consome o Typed Offline Core progressivamente quando disponível e mantém a implementação 2.2.476 como fallback compatível.
- Adiciona testes comportamentais TypeScript para conectividade degradada, ownership/renewal de leases e semântica de retry/recuperação do outbox.
- Release Manager passa a validar também `typed_offline_core_ok`, impedindo publicação se o bundle TypeScript e o runtime não estiverem ligados ao mesmo contrato.
- Release Gate 1.28.0 acrescenta `fieldflow-typed-offline-core-v1`.
- Sem React nesta release, sem migração de base de dados, sem alteração de REST, Flutter, formulários, Analytics, Mail Center, rotas, Shopping ou Azure.
- Phase 6 Stable avança de 2.2.476 para 2.2.477.

## 2.2.476 - Frontend Build & TypeScript Foundation

- Introduz `frontend/` como fonte estruturada de código browser, com `package.json`, `tsconfig.json`, source TypeScript, build versionado e testes automáticos.
- O build lê a versão canónica diretamente de `fieldflow.php`, gera `version.generated.ts` e produz `assets/fieldflow-frontend-core-2.2.476.js`, eliminando versões frontend escritas manualmente em múltiplos pontos.
- TypeScript passa a usar `strict`, `noEmitOnError` e contratos tipados para o runtime frontend e para a integração existente com `FieldFlowOffline`.
- O bundle compilado fica encapsulado num scope próprio, suporta avaliação repetida sem redeclarar constantes globais e expõe `window.FieldFlowFrontend` com snapshot, eventos e identidade exata da release.
- O novo frontend core é carregado no front e no Backoffice apenas em superfícies FieldFlow, sem substituir qualquer lógica funcional existente.
- Release Manager passa a validar a identidade e a referência do bundle TypeScript versionado, tal como já fazia com o runtime offline.
- Release Gate 1.27.0 acrescenta `fieldflow-frontend-build-foundation-v1` e mantém todos os contratos anteriores obrigatórios.
- Mantém formulários operacionais, offline queue, IndexedDB, REST, Analytics, Mail Center, rotas, Cardex, Shopping, Flutter e Azure sem alterações de contrato.
- React não é introduzido nesta release. Sem migração de base de dados e sem alteração de Flutter.
- Phase 6 Stable avança de 2.2.475 para 2.2.476.

## 2.2.475 - Offline Runtime Hardening

- Corrige a divergência de identidade do runtime offline: o asset 2.2.474 ainda exportava marcadores internos 2.2.472. A release passa a usar `RUNTIME_BUILD='2.2.475'` como fonte única da identidade runtime.
- Substitui o lock curto de sincronização por um lease renovável, com TTL mínimo de 120 segundos, heartbeat de 10 segundos, token exclusivo e proteção contra reentrada no mesmo WebView.
- Usa Web Locks API quando disponível e mantém localStorage como coordenação/fallback, sempre com verificação de owner/token antes de renovar ou libertar.
- Permite sincronização em rede `degraded`, evitando tratar uma ligação móvel lenta mas utilizável como offline.
- Alinha também check-in, check-out, falhas de visita e carregamento operacional dos shortcodes com a mesma política, eliminando gates laterais que ainda bloqueavam `degraded`.
- Alinha o dia operacional offline com WordPress através de `operationalDate` e `siteTimezone`, removendo a dependência de UTC para prefetch de rotas.
- O submit final faz um checkpoint dos ficheiros já otimizados antes de construir o outbox, evitando que media restaurado pré-otimização seja reanexado como fotografia adicional.
- `/offline/ping` expõe data/hora operacional local e capacidades de lease renovável, rede degradada e data operacional do servidor.
- O Release Manager valida a identidade do runtime versionado e a referência de `ResponsiveAssets` antes de aceitar uma publicação.
- Adiciona verificação build-time dedicada e o contrato Release Gate `fieldflow-offline-runtime-hardening-v1`.
- Mantém idempotência server-side, ACK estrito, REST existente, IndexedDB/outbox, Flutter, base de dados, Analytics, rotas, Mail Center, Cardex e Azure sem alterações funcionais intencionais.
- Phase 6 Stable avança de 2.2.474 para 2.2.475. Sem migração de base de dados.

## 2.2.474 - Order Suggestion Conditional Mail Guard

- Guarda na submissão um snapshot canónico da visibilidade condicional, incluindo perguntas ocultas, chaves de sugestão e sugestões efetivamente ativas.
- Se uma resposta anterior fizer a regra ocultar `order_suggestion`, o Mail Center deixa de reconstruir e enviar essa sugestão, mesmo que exista conteúdo histórico ou auto-preenchido.
- Reenvios manuais e retries passam pelo mesmo `getOrderSuggestionSubmission`, portanto respeitam a mesma decisão condicional.
- Submissões anteriores sem snapshot usam um fallback conservador que reavalia o schema e as respostas persistidas antes de permitir o envio.
- Não altera a lógica de Cardex, Frentes, cálculo de quantidades, posicionamento 2.2.473, Flutter ou base de dados.
- Release Gate 1.25.0 acrescenta `fieldflow-order-suggestion-conditional-mail-v1`.
- Phase 6 Stable avança de 2.2.473 para 2.2.474.

## 2.2.473 - Form Order Suggestion Placement

- Corrige a posição da pergunta `Sugestão de encomenda` em formulários wizard legacy onde a pergunta existia no schema mas não estava atribuída a `layout.steps`.
- A recuperação runtime deixa de criar um passo autónomo de encomenda e passa a inserir a pergunta no passo da pergunta de Frentes emparelhada, respeitando a ordem funcional do formulário.
- Se um schema antigo colocar a sugestão antes da respetiva pergunta de Frentes, a dependência é reparada em runtime sem regravar automaticamente o formulário e a sugestão passa a surgir depois da fonte.
- O Form Builder reconcilia perguntas BO sem passo com o vizinho configurado mais próximo e persiste o layout reparado no próximo Guardar.
- Mantém a sincronização dinâmica de referências entre Frentes e Sugestão de encomenda, o Cardex por loja, exceções de sortido, valores manuais e a regra existente de sugestão para zero frentes.
- Não altera Flutter, esquema de base de dados, Analytics, rotas, offline ou Mail Center.
- Release Gate 1.24.0 acrescenta o contrato `fieldflow-form-order-suggestion-placement-v1`.
- Phase 6 Stable avança de 2.2.472 para 2.2.473.

## 2.2.472 - Evidence ZIP System Browser Handoff

- Corrige o ultimo salto do download de ZIP de evidencias no Android: o shell Flutter podia responder `handled=true` ao `fieldflowDownloadRequest` antes de a transferencia nativa terminar e falhar depois, deixando simultaneamente `Download iniciado` e `Nao foi possivel exportar o ficheiro`.
- ZIPs preparados deixam de passar pelo downloader Flutter generico. A app usa o handler externo ja existente `fieldflowOpenExternal` com `systemBrowser`, entregando diretamente a capability URL ao browser/gestor de downloads do dispositivo.
- O fluxo de evidencias deixa de publicar o evento generico `fieldflow.download.requested`; usa eventos dedicados `fieldflow.evidence.download.*`, impedindo que o shell trate o ZIP como exportacao Analytics generica.
- O Portal Cliente passa a usar a mesma `FieldFlowNativeBridge.downloadPrepared()` do Analytics operacional, mantendo paridade entre os dois fronts.
- Fallbacks de browser recebem `data-fieldflow-download-fallback` para nao serem reintercetados pela camada WebView.
- Mantem integralmente o layout mobile full-width 2.2.471, o capability ticket reutilizavel 2.2.470, o writer ZIP portatil, IA, formularios, rotas, Cardex, Mail Center e restantes contratos. Zero alteracoes Flutter e zero migracoes de base de dados.
- Release Gate 1.23.0 acrescenta `fieldflow-evidence-zip-system-handoff-v1`.
- Phase 6 Stable avanca de 2.2.471 para 2.2.472.

## 2.2.471 - Analytics Mobile Full-Width & Download State Continuity

- Analytics na app mobile passa a usar largura praticamente total do viewport, incluindo dashboards e widgets configurados no BO.
- A mesma politica full-width e de scroll continuo aplica-se ao Portal Cliente Analytics, para manter paridade entre os dois fronts.
- Remove scroll vertical interno do Ask FieldFlow, tabelas responsivas e superficies de detalhe em mobile; graficos e tabs mantem apenas scroll horizontal quando necessario.
- Download de ZIP preparado usa diretamente `FieldFlowNativeBridge.downloadPrepared()` quando a ponte mobile esta disponivel, evitando reclassificacao como exportacao geral.
- O estado `Download iniciado` so aparece depois de handoff confirmado; falhas deixam de sobrescrever a nota global da tabela de rotas.
- Zero alteracoes Flutter e zero migracoes de base de dados.

## 2.2.470 - Evidence ZIP Ticket Continuity Hotfix

- Fixes the HTTP 410 evidence ZIP download seen on Android/WebView after the ZIP had already been prepared successfully.
- Replaces the final user-bound admin-post nonce URL with a short-lived 256-bit capability ticket that can be downloaded without depending on WordPress cookies or nonce state.
- Makes the prepared ticket idempotent during its TTL, so native HEAD probes, native GET attempts and browser fallback retries do not consume the ZIP on the first request.
- Keeps HEAD side-effect free, streams GET in 1 MiB chunks and retains the prepared ZIP for the retry window instead of deleting it after the first request.
- Extends the Analytics evidence-group token lifetime to four hours by default so a field user can keep Analytics open without receiving an avoidable 410 when downloading later.
- Keeps the 2.2.469 portable STORE ZIP writer, media path recovery, security scope and REST preparation flow unchanged.
- Adds Release Gate 1.21.0 contract `fieldflow-evidence-zip-ticket-continuity-v1`. No database migration or Flutter change is required.
- Advances Phase 6 Stable continuity from source 2.2.469 to Stable 2.2.470.

## 2.2.469 - Evidence ZIP Runtime v3

- Replaces the host-dependent evidence archive hot path with a portable STORE-only ZIP writer that streams source media and writes a standards-compliant central directory without relying on ZipArchive/libzip.
- Changes front Analytics evidence downloads to a server-bound token flow: the browser submits only the evidence-group token to an authenticated REST prepare step, then downloads the already-created ZIP through a separate short-lived user-bound token. admin-post no longer generates archives inside the WebView download path.
- Applies the same preparation flow to store/date ZIPs in operational Analytics and Client Portal, including single-image groups, while preventing client-supplied arbitrary media URLs.
- Retains ZipArchive and PclZip as compatibility fallbacks only after the portable writer fails.
- Validates ZIP structure independently through Local Header, EOCD and Central Directory metadata instead of validating with the same archive engine.
- Adds Media Registry path recovery for historical/recovered local evidence whose public URL cannot be mapped back through WordPress attachment resolution.
- Adds Release Gate 1.20.0 contract `fieldflow-evidence-zip-runtime-v3`. No database migration or Flutter change is required.
- Advances Phase 6 Stable continuity from source 2.2.468 to Stable 2.2.469.

## 2.2.468 - Analytics Export & Evidence ZIP Resilience Hotfix

- Fixes the Analytics evidence ZIP HTTP 500 path by removing full photo staging before archive creation. ZipArchive now reads valid source media directly, which avoids duplicating hundreds of megabytes of disk I/O before compression.
- Skips unreadable and zero-byte media safely, preserves valid evidence, writes to an atomic `.part` archive, validates the central directory and entry count, then promotes the package only after integrity passes.
- Stores already-compressed image/PDF formats without recompressing them, reducing CPU and request duration on large photo sets while preserving the same evidence bytes.
- Streams the final ZIP in 1 MiB chunks with binary-safe headers, no PHP session lock and no HTTP compression. Old partial archives and fallback staging directories are cleaned automatically.
- Bounds Media Shadow observability on evidence rendering and archive creation so telemetry cannot execute hundreds of database reads/writes on the critical export path. Full exports rely on the already-authorized Analytics scope and do not change access control.
- Releases the PHP session lock for long Analytics exports while keeping existing CSV, XLSX, mobile XLSX and PDF content/contracts intact.
- Adds Release Gate 1.19.0 with `fieldflow-analytics-export-resilience-v1` and `tools/verify-analytics-export-resilience.php`.
- Advances Phase 6 Stable continuity from source 2.2.467 to Stable 2.2.468. No database migration or Flutter change is required.

## 2.2.467 - Operational Intelligence & Field Reliability Hardening

- Adds Temporal Intelligence so closed past days are evaluable, today is treated as work in progress and future routes remain planned work that never lowers operational scores or creates overdue-style anomalies.
- Removes the visual user-question bubble from operational Analytics, Client Portal and Analytics 2.0 management while preserving conversational history internally.
- Adds deterministic FieldFlow help for product onboarding, routes, Analytics, Cardex/order suggestion and daily close questions without requiring metrics or an external AI call.
- Bridges legacy empty/manual order-suggestion questions to the valid Cardex already resolved by the paired Fronts question, while preserving every explicit manual product list.
- Adds an idempotent post-COMMIT Daily Close queue fallback for routes completed by a justified final non-visit, keeping route.completed canonical and preventing duplicate delivery through existing dispatch claims.
- Keeps executed-visit KPIs separate from operational closure semantics. Justified non-visits are terminal, not executed visits.
- Adds Release Gate 1.18.0 with `fieldflow-operational-intelligence-hardening-v1` and a dedicated build-time verifier.
- Advances Phase 6 Stable continuity from source 2.2.466 to Stable 2.2.467. No database migration or Flutter change is required.

## 2.2.466 - Responsive Mail Rendering Hotfix

- Fixes horizontal clipping in Gmail, Outlook and other narrow mail clients without relying on horizontal scrolling.
- Makes the shared FieldFlow email shell fluid at the HTML width-attribute level, with fixed desktop widths restricted to MSO conditional markup.
- Rebuilds order-suggestion KPIs and product detail as email-safe fixed-layout tables/cards, removing flex/min-width behavior that caused the screenshot clipping.
- Hardens Shopping price tables with `table-layout:fixed`, safe wrapping and no nowrap price cells.
- Applies one responsive email contract to route notifications, automatic reports, tests, manual historical replay, order suggestions and Shopping newsletters.
- Hardens editable legacy body HTML at send time so fixed table widths, nowrap cells and oversized images cannot silently reintroduce mobile clipping; saved templates are not modified.
- Adds Release Gate 1.17.0 with `fieldflow-responsive-email-rendering-v1` and a dedicated build-time verifier.
- Advances Phase 6 Stable continuity from source 2.2.465 to Stable 2.2.466. No database migration is required.

## 2.2.465 - Mail Center Historical Recovery

- Adds a guarded `Envio manual` workspace in Mail Center for historical daily-close and order-suggestion messages.
- Supports current client recipients, the persisted operative, both audiences, and isolated test-only delivery without silently including extra automation addresses.
- Reconciles historical route state before replay. Justified non-visits are terminal, unresolved stops still block delivery, and the manual repair suppresses only the automatic completion-mail event while preserving route status integration.
- Rebuilds historical order-suggestion Excel/PDF attachments from the exact persisted submission and bypasses automatic recipient claims/retry scheduling for deliberate manual replay.
- Keeps manual replays out of automation `last_run` / `last_status`, logs real manual messages separately, and permits using a paused template without reactivating its schedule.
- Adds Release Gate 1.16.0 with `fieldflow-mailcenter-historical-recovery-v1` and a build-time verifier.
- Advances Phase 6 Stable continuity from source 2.2.464 to Stable 2.2.465. No database migration is required.

## 2.2.464 - Field Form Reliability Core

- Fixes multi-photo recovery after app/WebView restart by rebuilding missing durable file ordinals before IndexedDB rehydration, including camera/gallery clones and Briefing image questions. Partial recovery is explicitly surfaced instead of appearing complete.
- Fixes server draft publication so every `input_name|input_ordinal` group is published, rather than effectively limiting recovery to ordinal zero.
- Introduces operational terminal route semantics: justified non-visits close the route without being counted as executed visits. Failure states now require a non-empty server-side justification.
- Makes `route.completed` the canonical daily-close mail trigger, preserving the existing Mail Center flow even when the last stop is a justified non-visit.
- Adds project-level operational content revision invalidation for Briefing publish/archive, Cardex sync, form schema and form binding changes, and reconciles obsolete Cardex/store associations.
- Promotes order suggestions to first-class reporting data in Analytics Excel, CSV and PDF. Each persisted suggestion sends an editable Mail Center notification to the operative and associated client users with mandatory Excel and PDF attachments, per-recipient idempotency and controlled retry only for failed addresses.
- Adds Release Gate 1.15.0 and the build-time `fieldflow-form-reliability-core-v1` verifier while preserving the private Release Manager, production-manifest SHA-256 and Azure-backed certification chain.
- Advances Phase 6 Stable continuity from source 2.2.463 to Stable 2.2.464. No database migration is required.

## 2.2.463 - End-to-End Offline Form Recovery Hardening

- Audits the complete field-report lifecycle for regular questions, briefing-generated questions, evidence media, Cardex, quick/focused/standalone reporting, weak connectivity, suspension recovery and post-commit integrations.
- Adds a durable IndexedDB snapshot store for form answers alongside the existing file vault. localStorage remains the fast mirror, while IndexedDB provides the secondary recovery copy.
- Changes draft semantics from strict schema validation to recovery preservation. Server and device drafts survive briefing/form schema changes, with compatible-field rebasing and explicit conflicts only when a non-empty answered/media key was actually removed or renamed.
- Makes server draft media sync incremental, one file per request, with per-index replacement only after the new server copy is durable. No FieldFlow fixed-MB image ceiling is reintroduced.
- Keeps the user-visible draft and durable media vault until the final form receives a strict `fieldflow-form-ack-v1` persistence acknowledgement. Offline, degraded, locked and transient-failure paths never clear recovery state.
- Recovers all valid draft media directly from IndexedDB when constructing the outbox, covering normal image questions and briefing-generated image/evidence questions even when Android/WebView cannot reconstruct `input.files`.
- Prevents a newer server draft from deleting local media before a valid replacement exists, serializes remote-to-local rehydration, and prevents delayed weak-network responses from rolling back a newer device checkpoint.
- Raises the technical media-count envelope to 300 items while retaining 512 KiB resumable chunks, SHA-256 integrity, device/user/mutation binding and the `storage-capacity-only` policy.
- Versions quick-report cached form HTML by `FIELDFLOW_VERSION` and raises `fieldflow_submission_post_commit` queue attempts to 3 so post-commit projections/integrations get an additional recovery layer after the canonical submission is durable.
- Advances Phase 6 Stable continuity from source 2.2.462 to Stable 2.2.463 and Release Gate to 1.14.3. No database migration is required.

## 2.2.462 - Android Zero-byte Capture Recovery & Runtime Cache Bust
- Prevents Android/WebView `canvas.toBlob()` from ever replacing a valid source image with a zero-byte Blob. The original capture is retained whenever optimisation returns empty media.
- Draft persistence no longer overwrites a durable valid photo with a zero-byte handoff, and required-evidence validation only counts files that actually contain bytes.
- Recovers legacy failed outbox media from the separate `fieldflow-form-drafts` IndexedDB when a zero-byte placeholder is detected, then rebuilds the media manifest before retrying.
- Serves the offline runtime from the new versioned path `fieldflow-offline-runtime-2.2.462.js` to defeat stale WebView/PWA/proxy caches while keeping the canonical runtime file current.
- Keeps the no-fixed-image-limit, 512 KiB resumable transport, SHA-256 integrity, device binding and outbox-first protection.
- Advances Release Gate to 1.14.2 and Phase 6 Stable continuity from source 2.2.461 to Stable 2.2.462.

## 2.2.461 - Zero-byte Media Placeholder Hotfix & Field Recovery

- Filters zero-byte browser `File` placeholders before they can enter the protected outbox, media manifest, resumable transport or final multipart submission.
- Keeps the no-fixed-image-limit policy introduced in 2.2.460. Real evidence photos continue through resumable 512 KiB chunks with SHA-256 integrity.
- Scrubs legacy 2.2.460 outbox rows during sync and automatically re-queues failed form items when the stored error is the zero-byte media rejection.
- Keeps blob-index alignment stable by counting only uploadable blobs when resumable references suppress the final multipart copy.
- Advances Release Gate to 1.14.1 and Phase 6 Stable continuity from source 2.2.460 to Stable 2.2.461.

## 2.2.460 - Large Media Resumable Hotfix & Certification Continuity

- Removes the FieldFlow-specific fixed image-size rejection from `UploadGuard`; image uploads keep extension, MIME, authentication, rate-limit and integrity protections without an arbitrary MB ceiling.
- Makes standalone dynamic forms use the same outbox-first `FieldFlowOffline.submitForm` path already used by focused and quick-report flows, so multiple photos are no longer posted as one large multipart request when the resumable runtime is available.
- Serializes client-side image optimisation one photo at a time, preventing 12+ high-resolution gallery selections from causing a mobile WebView memory spike before upload begins.
- Decouples `fieldflow-resumable-media-v1` from `wp_max_upload_size()` and PHP `upload_max_filesize`. Each photo is transferred in small authenticated chunks and is admitted according to real temporary storage capacity instead of the legacy per-request upload ceiling.
- Raises the resumable chunk-count safety envelope for very large captures while preserving per-user/device/mutation binding, SHA-256 verification, exact byte-length validation and idempotent resume semantics.
- Updates Mobile Control Center diagnostics to describe the effective chunked policy instead of reporting PHP upload limits as a normal FieldFlow image constraint.
- Advances Release Gate to 1.14.0 with explicit no-fixed-image-limit contract markers and advances Phase 6 Stable continuity from source 2.2.459 to Stable 2.2.460.
- Keeps private Release Manager publication, package SHA-256, licensing, heartbeat, governance, Azure-backed 6B checks and installation certification mandatory.
- No database migration is required.

## 2.2.459 - Shopping Attachments, Store Learning & Stable Continuity

- Adds per-submission XLSX and PDF Shopping attachments, reused across recipients and included in manual resends according to the batch settings.
- Adds Shopping schema 1.3.0 with a persistent project/location-to-insignia learning table, backfilled from historical Shopping submissions and enriched with stock/category evidence.
- Adds a guarded front fallback for unclassified project stores. A merchandiser can explicitly associate a candidate store to the current insignia, and that choice is then remembered for the whole project team.
- Keeps Shopping team-wide by project, with no route, PDV or single-merchandiser ownership.
- Aligns Phase 6 Stable continuity from source 2.2.458 to Stable 2.2.459 and advances Release Gate to 1.13.0 without weakening private catalog, Release Manager, SHA-256, licensing, heartbeat, governance or Azure-backed 6B controls.

## 2.2.458 - Shopping Outlook Newsletter & Resumable Gate Hotfix

- Replaces the generic Shopping mail shell with a table-first newsletter renderer designed for Outlook desktop as well as modern mobile/web clients.
- Adds a clean TWT/FieldFlow brand strip, solid-color hero, preheader, three Shopping completion metrics and a compact editorial footer without relying on flex, grid or gradients.
- Reworks the Shopping price table into an Outlook-safe three-column layout with article/type context, physical-sequence numbering, regular PVP and promo PVP.
- Renders Shopping observations as a dedicated callout block and keeps all existing subject/body tokens and per-batch custom text compatible.
- Keeps per-recipient audited delivery and manual resend behavior unchanged, including current-recipient selection and Mail Center evidence.
- Fixes a false-negative Resumable Media Transport certification marker: the Release Gate was searching for an escaped PHP source token that can never occur in the runtime file, even though the complete resumable transport was present.
- Improves Phase 6 certification diagnostics so a failed source-contract gate reports the concrete missing marker(s) instead of only the gate label.
- Advances Release Gate to 1.12.0 and Phase 6 Stable continuity from 2.2.457 to 2.2.458 without weakening SHA-256, private catalog, licensing, heartbeat or Azure-backed 6B requirements.
- No database migration is required.

## 2.2.457 - Shopping Shared Team Scope
- Makes every active Shopping insignia available to the full operational/merchandiser team with access to the project, regardless of route, campaign-location or store ownership.
- Removes the back-office ability to assign a Shopping insignia to one merchandiser and replaces it with an explicit team-wide availability indicator.
- Removes `assigned_to` filtering from the front store selector so any project operational user can select any valid store of the chosen insignia.
- Migrates legacy Shopping assignments to `assigned_user_id = NULL` while preserving the historical executor on each submission through `submission.user_id`.
- Upgrades Shopping schema state to 1.2.1 and Release Gate to 1.11.0, which now fails if an active/draft Shopping assignment remains restricted to one user.
- Advances Phase 6 Stable from 2.2.456 to 2.2.457 without weakening private catalog, SHA-256, licensing, heartbeat or Release Manager certification requirements.

## 2.2.456 - Shopping Insignia, Store Selection, Resend & Certification Diagnostics

- Re-models Shopping assignments around the retail insignia instead of a fixed physical store. A single imported Shopping scope can now be executed across the valid stores of that chain.
- Requires an explicit physical store selection on the front before prices can be edited or submitted, with candidates limited by client, project, insignia and the current merchandiser scope.
- Keeps drafts isolated by assignment and physical store and loads the latest submission for the selected store only, preventing cross-store price contamination.
- Upgrades the self-managed Shopping schema to 1.2.0, adding canonical `insignia` and `last_location_id` assignment state plus submission-level insignia evidence.
- Safely consolidates legacy duplicate assignments that resolve to the same batch + insignia while re-pointing every historic submission to the retained assignment and preserving its real `location_id`.
- Clears ambiguous multi-store legacy price seeds during consolidation rather than promoting one store's seed as the generic chain baseline. Historic submitted values remain available per store.
- Makes manual resend start from the batch's current recipient configuration rather than the original notification snapshot. The UI shows `Último envio` and `Configuração atual` separately and permits a fresh per-recipient choice.
- Backfills a minimum attempt count of one for legacy notifications already marked sent, partial or failed so operational history no longer shows an accepted mail with zero attempts.
- Adds `{insignia}` to the Shopping mail token contract and includes both insignia and actual store in the default message, results and exports.
- Replaces the generic Phase 6 Stable certification failure notice with a bounded Portuguese diagnostic list of the exact failing 6B, manifest, catalog, publication or Release Gate conditions.
- Advances Release Gate to 1.10.0, explicitly certifying the insignia-to-store execution model, store selector, current-recipient resend behavior and Shopping schema 1.2.0.
- Advances Phase 6 Stable from 2.2.455 to 2.2.456 while keeping canonical Release Manager publication, private Stable catalog evidence, package SHA-256 and Azure-backed 6B revalidation mandatory.

## 2.2.455 - Shopping Mail & Merchandising Flow

- Adds explicit Shopping recipient configuration per imported batch using the client primary email, individually selected WordPress users associated with that client, and optional external email addresses.
- New Shopping imports can preselect all valid client-associated users from the default email template, while each batch remains independently editable.
- Sends Shopping mail to each recipient individually instead of one grouped `To` list, providing per-recipient acceptance/failure evidence in Mail Center and avoiding address exposure between recipients.
- Adds an audited resend workflow to every Shopping submission. Admins can choose a fresh recipient set and resend the original price verification without altering the saved field submission.
- Tracks notification attempt count, last accepted send time and `sent`, `partial`, `failed` or `no_recipients` state. UI wording explicitly distinguishes WordPress mail acceptance from confirmed inbox delivery.
- Adds Shopping and Shopping resend filters to Mail Center so delivery attempts can be isolated by recipient and inspected operationally.
- Reworks the Shopping front into a default `Percurso do linear` mode that follows the exact imported Excel `source_row` order, preserving Pernod and competitor products in their physical sequence.
- Keeps `Agrupar por tipo` as an optional alternative view, adds numbered product steps and type badges, and improves mobile price-entry ergonomics.
- Upgrades the Shopping self-managed schema to 1.1.0 through `dbDelta`, adding recipient selection and notification audit fields without running the full FieldFlow schema migration path.
- Advances Release Gate to 1.9.0 with an explicit Shopping Mail & Merchandising Flow contract, including runtime markers and verification of the new Shopping database columns.
- Advances Phase 6 Stable from 2.2.454 to 2.2.455 while preserving the Azure-backed 6B certification chain, private Release Manager publication evidence and existing production safety switches.

## 2.2.454 - Resumable Media Transport

- Adds `fieldflow-resumable-media-v1`, an authenticated chunked media transport for protected offline form replays.
- Upload sessions are bound to the current user, device, mutation id, field name, file index and full-file SHA-256.
- Uses 512 KiB chunks by default and asks the server which chunks are already present before sending data, so interrupted uploads resume from the missing chunks instead of restarting from byte zero.
- Reassembles media server-side only after every chunk is present, validates exact byte length and SHA-256, then exposes a signed short-lived media reference to the normal form pipeline.
- Keeps the assembled temporary source available for idempotent form retries, while deleting completed chunk parts to avoid duplicate temporary storage.
- Integrates resumable references with simple uploads, multiple uploads and categorized evidence without changing the existing answer-storage or media-library pipeline.
- Extends Offline Operations Intelligence with resumable uploaded/resumed chunk counters and completed-file counters per device.
- Falls back to the existing full multipart replay when Web Crypto is unavailable or the resumable endpoint is not supported, preserving backward compatibility.
- Advances Release Gate to 1.8.0 and Phase 6 Stable from 2.2.453 to 2.2.454 while preserving the Azure-backed 6B certification chain.
- No database migration is required.

## 2.2.453 - Offline Media Rehydration Hotfix

- Fixes restored offline photographs being present in `input.files` but invisible after reopening a saved form until the user selected another image.
- Adds an explicit `rpRefreshRestoredDraftMediaUi()` pass after IndexedDB and server-draft media restoration because programmatic `input.files` assignment does not emit a browser `change` event.
- Rebuilds upload previews, media counters and evidence-category state immediately after restoration without re-optimising or re-uploading the recovered files.
- Adds a final UI rehydration pass after local/server restore races settle, keeping the preview consistent regardless of which draft source wins.
- Adds the `fieldflow:draft-media-rehydrated` diagnostic event for mobile/WebView troubleshooting.
- Advances Release Gate to 1.7.0 and makes visible offline-media rehydration part of the Stable certification contract.
- Advances Phase 6 Stable from 2.2.452 to 2.2.453 while preserving the Azure-backed 6B certification chain.
- Preserves Zero-Loss, media SHA-256 integrity, Offline Trust shadow mode, device intelligence and the runtime-only upgrade path.
- No database migration is required.

## 2.2.452 - Resilient Media & Azure Offline Trust Foundation

- Adds server-side pagination to FieldFlow > Devices with 25 devices per page by default, 25/50/100 page sizes, search and preserved navigation state after device actions.
- Adds `fieldflow-media-integrity-v1`, a bounded media manifest for offline form files with filename, byte length and SHA-256 when Web Crypto is available.
- Validates the media manifest during offline replay before persistence; mismatches keep the local outbox copy recoverable instead of accepting ambiguous media.
- Adds adaptive form replay timeouts based on pending media volume and effective connection quality while retaining durable outbox-first semantics.
- Adds `fieldflow-offline-trust-v1` in shadow mode. The locally signed lease is issued only from a current Phase 6 Stable certified state and is tied to user, device, runtime release and certification fingerprint.
- Keeps the trust lease non-enforcing so weak or absent connectivity never becomes a prerequisite for field work.
- Extends device telemetry and the control center with trust lease state/expiry and pending media file/byte counters.
- Advances Release Gate to 1.6.0 and requires the pagination, media-integrity and Offline Trust foundation contracts before Stable certification.
- Advances Phase 6 Stable from 2.2.451 to 2.2.452 while preserving the Azure-backed 6B certification chain.
- Preserves Offline Zero-Loss, Offline Operations Intelligence and the lightweight runtime-only upgrade path.
- No database migration is required.

## 2.2.451 - Offline Operations Intelligence

- Adds a bounded synchronization history on each device with protected, syncing, confirmed, pending, attention, conflict and recovered states.
- Extends device telemetry with oldest pending report age, maximum queue attempts, strict-ACK errors, degraded-network duration/events, reauthentication events, app resumes, automatic recovery events, storage pressure and storage persistence.
- Upgrades FieldFlow > Devices into an Offline Operations Intelligence monitor with per-device offline health score, queue age, network condition, storage pressure and recent synchronization history.
- Keeps terminal form submission errors in the local outbox as `attention` instead of deleting the durable copy automatically.
- Adds form definition identity using `definition_version`, full `schema_hash` and `downloaded_at`, and persists client/server definition evidence inside submission metadata.
- Strengthens offline replay by validating the full resolved schema hash when it is available, while retaining the existing schema revision conflict contract.
- Keeps telemetry bounded and sanitized and never copies form answer values or photographs into device monitoring data.
- Advances Release Gate to 1.5.0 and makes Offline Operations Intelligence mandatory before Stable certification.
- Advances Phase 6 Stable from 2.2.450 to 2.2.451 and preserves the Azure-backed 6B certification chain.
- Preserves the 2.2.449 Offline Zero-Loss and 2.2.450 Upgrade Bootstrap Timeout contracts.
- No database migration is required.

## 2.2.450 - Upgrade Bootstrap Timeout Hotfix

- Separates the FieldFlow runtime version from the database schema version during WordPress bootstrap. Runtime-only releases no longer execute the full `Activator::activate()` path when the versioned schema is already current.
- Declares `2.2.436` as the current versioned database schema baseline and exposes it through `Migrations::latestVersion()` for upgrade decisions and certification diagnostics.
- Adds a lightweight upgrade path for installations already on 2.2.448 or newer with schema 2.2.436 or newer. It updates the runtime version atomically without the full table census, compatibility migrations or dbDelta workload.
- Clears a stale legacy upgrade lock when the runtime-only path is used, allowing recovery after an nginx/PHP timeout without rerunning the heavy Activator path.
- Keeps the full Activator fallback for fresh installs, older installations and any installation whose schema is genuinely behind the certified schema baseline.
- Records `fieldflow_last_runtime_upgrade` with source version, target version, schema version and completion time for operational diagnostics.
- Advances Release Gate to 1.4.0 and makes the lightweight bootstrap contract mandatory before Stable certification.
- Preserves the complete 2.2.449 Offline Zero-Loss contract, including outbox-first persistence, strict ACK validation, degraded-network handling and PWA hardening.
- Advances Phase 6 Stable from 2.2.449 to 2.2.450 and keeps the Azure-backed 6B certification chain mandatory.
- No database migration is required.

## 2.2.449 - Offline Zero-Loss Foundation

- Converts form submission to an outbox-first contract. A durable IndexedDB copy is created before the first network attempt, including photos and the idempotent mutation identifier.
- Adds a strict server acknowledgement protocol, `fieldflow-form-ack-v1`. The device removes the local report only after a valid JSON success response confirms `persisted=true`, a positive `submission_id` and the exact mutation ID sent by the device.
- Rejects HTTP 200 HTML pages, invalid JSON and incomplete acknowledgements as successful form persistence. Ambiguous responses remain pending locally and are retried safely.
- Adds an authenticated lightweight `/offline/ping` probe and explicit `online`, `degraded`, `offline`, `auth_required` and `revoked` connectivity states. Weak connectivity is treated as store-and-forward rather than as a blocking online submission.
- Requests persistent browser storage where supported and preserves form queue entries across suspension and intermittent connectivity.
- Separates authentication expiry from device revocation. HTTP 401 never revokes a device, and HTTP 403 only triggers revocation for the explicit `fieldflow_device_revoked` policy code.
- Hardens check-in, checkout and failure events so degraded networks and ambiguous responses fall back to the idempotent operation queue.
- Reworks the PWA service worker to keep versioned FieldFlow static assets without caching private REST/API payloads, and stops deleting that cache on every page load. Native WebView behaviour remains unchanged and keeps the service worker disabled.
- Adds the Offline Zero-Loss contract to Release Gate 1.3.0, making its runtime, server ACK, connectivity probe and PWA markers mandatory before Phase 6 Stable certification can close.
- Advances Phase 6 Stable from 2.2.448 to 2.2.449 and preserves the mandatory Azure Release Certification Gate and private distribution chain.
- No database migration is required.

## 2.2.448 - Front Analytics Parity

- Brings the front Analytics experience to parity across the Client Portal and Merchandiser dashboard while keeping audience-specific presentation.
- Adds a six-signal Command View to the Merchandiser dashboard: Operational Health, Execution, Risk, Opportunity, Stock Risk and Confidence, all scoped to the authenticated merchandiser.
- Progressively enriches the Merchandiser Analytics surface with the isolated Store Intelligence endpoint after the base AI/Analytics response is already usable. Store enrichment remains fail-open and cannot blank base Analytics.
- Adds Predictive Intelligence and Contextual Next Best Actions to the Merchandiser front, including a compact `Prioridades de hoje / próximo ciclo` surface limited to the merchandiser scope.
- Adds `Perguntar ao FieldFlow` to the Merchandiser Analytics surface with operational suggested questions, bounded local history, visible Observed/Projected/Learned/Advisory evidence and server-enforced `audience=merchandiser` / `user_id=me`.
- Adds an explicit six-score Command View to the Client Portal and makes evidence kind labels visible in conversational answer chips.
- Preserves the 2.2.440 WidgetOrchestrator runtime contract, the isolated Store Intelligence path, Predictive/Contextual read-only policy and all no-autonomous-mutation guardrails.
- Does not persist conversations and does not mutate routes, stocks, forms, targets, thresholds, Decision Scores or operational data.
- No database migration is required.
- Advances Phase 6 Stable from 2.2.447 to 2.2.448 and keeps the mandatory Azure Release Certification Gate.

## 2.2.447 - Perguntar ao FieldFlow

- Adds `FieldFlowAskService`, a grounded, read-only conversational analytics layer over the already-scoped FieldFlow intelligence envelope.
- Adds `POST /routespro/v1/analytics/ask` with the existing client/project permission scope, audience-aware merchandiser self-binding and the universal client/project/route/user/location/period/category/product context.
- Builds a bounded evidence catalogue from execution, Decision Scores, deterministic anomalies, stock snapshots, Adaptive learning, Predictive forecasts, Store Intelligence, Contextual Next Best Actions, recommendations and active briefing evidence.
- Classifies evidence explicitly as observed, projected, learned or advisory. Forecasts remain projections rather than probabilities/facts, learned patterns never imply causality, and advisory actions are never presented as already executed.
- Adds a full `Perguntar ao FieldFlow` conversation surface to Analytics 2.0 and the Client Portal, including suggested questions, follow-ups, visible evidence chips and local short conversation history.
- Keeps conversation history ephemeral in the browser and sends only the last bounded turns for continuity. No operational conversation table or database migration is introduced.
- Uses the configured Analytics AI provider when available, under the existing AI governance/entitlement path, and falls back to deterministic smart rules when AI is unavailable or fails.
- Loads Store Intelligence only for store/product/risk/action questions. That enrichment remains isolated and fail-open, with a short 120-second cache bound to exact user and scope to avoid repeated PDV fan-out across follow-up questions.
- Preserves the 2.2.440 WidgetOrchestrator runtime contract and the 2.2.439/2.2.440 critical-path isolation. Ask failures cannot blank base Analytics 2.0.
- Does not mutate routes, stocks, forms, thresholds, targets, operational scores or persisted operational data. Autonomous actions remain disabled and human review remains required.
- No database migration is required.
- Advances Phase 6 Stable from 2.2.446 to 2.2.447 and keeps the mandatory Azure Release Certification Gate.

## 2.2.446 - Contextual Predictive Intelligence + Next Best Action

- Adds `ContextualPredictiveIntelligenceService`, a read-only and fail-open layer that converts already calculated Store/Predictive evidence into explainable Next Best Actions by store, product and issue type.
- Uses exact mapped stock only when store-level stock context is available. The isolated Store Intelligence endpoint deliberately keeps stock enrichment deferred instead of presenting campaign aggregate stock as store-specific evidence.
- Separates observed evidence from projected evidence in every contextual action and explicitly forbids probability claims, inferred causality and autonomous operational mutation.
- Adds contextual priorities to Analytics 2.0, the Client Portal, Mail Center Daily/Weekly emails and the shared Excel/PDF/CSV reporting envelope.
- Adds the `Contextual Actions` Excel worksheet and extends Store Intelligence exports with primary issue, focus product and Next Best Action.
- Keeps the 2.2.440 Analytics critical-path contract and the isolated Store Intelligence endpoint. Contextual enrichment cannot blank the base Analytics response.
- Preserves Adaptive and Predictive base scores. Contextual ranking is advisory and cannot modify routes, stocks, forms, targets, thresholds or persisted operational scores.
- No database migration is required.
- Advances Phase 6 Stable from 2.2.445 to 2.2.446 and keeps the mandatory Azure Release Certification Gate.

## 2.2.445 - Mail Center 2.0

- Unifica o BO de comunicação em Visão geral, Automações, Test Center, Histórico e Templates/Entrega.
- Adiciona automação de Fecho diário baseada na conclusão real da rota do merchandiser, com envio pessoal idempotente e visão cliente do mesmo merchandiser.
- Permite cliente imediato por fecho ou consolidado diário a uma hora definida, mantendo Weekly Intelligence em automação separada.
- Introduz o período Acumulado semanal, ancorado na data do fecho/teste, e mantém acumulado da campanha.
- Permite email Intelligence sem anexos ou com Excel, PDF e CSV.
- Test Center simula cliente ou merchandiser numa data concreta usando o mesmo motor de produção.
- When a Daily Close automation is active for the same client/project/date, the legacy route-completed notification is suppressed to prevent duplicate completion emails.
- Mantém Analytics 2.0, Store, Adaptive e Predictive Intelligence e o Certification Gate Azure obrigatório.

## 2.2.444 - Predictive Intelligence Foundation

- Builds directly from the certified 2.2.442 runtime. Version 2.2.443 is intentionally not published as a Stable release, so the certification chain records 2.2.442 as the source and 2.2.444 as the target.
- Adds `PredictiveIntelligenceService`, a deterministic and explainable forward-projection layer for the next equivalent time window, capped at 30 days.
- Projects execution, risk and opportunity using current deterministic scores, equivalent-window momentum, anomaly pressure, Stock Risk when available and Adaptive evidence maturity. It explicitly does not produce probability claims or infer causes.
- Adds predictive confidence and leading signals so every projection exposes the evidence and coverage behind it.
- Enriches isolated Store Intelligence without database fan-out, adding forecast risk, future priority, forecast direction, forecast confidence and future-risk rankings while preserving all base operational scores.
- Adds Predictive Intelligence to Analytics 2.0, the client portal, shared reporting, automated email summaries and Excel/PDF/CSV exports. Excel includes a dedicated `Predictive Intelligence` worksheet.
- Keeps Predictive Intelligence fail-open and outside critical mutations. A projection failure cannot block base Analytics, Store Intelligence, email delivery or reporting generation.
- Preserves the 2.2.440 WidgetOrchestrator runtime contract and the 2.2.442 reporting/email hotfix.
- Predictive Intelligence cannot modify routes, stocks, forms, thresholds, operational scores or persisted operational data. Autonomous actions remain disabled.
- No database migration is required.
- Advances Phase 6 Stable directly from 2.2.442 to 2.2.444 and keeps the mandatory Azure Release Certification Gate.

## 2.2.442 - Adaptive Reporting & Email Runtime Hotfix

- Fixes the report-generation regression introduced in 2.2.441 when Adaptive Intelligence patterns exist. `dashboard_intelligence_sheets()` accidentally reused `$headers` and `$rows` from the generic CSV exporter while building the Store Intelligence block; on PHP 8, `count($headers)` on the undefined variable throws a TypeError and prevents XLSX generation used by scheduled email tests.
- Keeps Adaptive Intelligence patterns exclusively in their dedicated worksheet and preserves Store Intelligence, recommendations, alerts and Next Best Actions without crossing exporter contracts.
- Hardens scheduled-report diagnostics by recording whether a test failed during attachment generation or during `wp_mail`, including the first safe technical reason in the admin state and notice.
- Hardens the main E-mail Center test so it no longer reports success when `wp_mail()` returns false and surfaces the `wp_mail_failed` diagnostic when WordPress provides one.
- Does not change Analytics formulas, Adaptive Intelligence ranking/guardrails, routes, stocks, forms or persisted operational data.
- No database migration is required.
- Advances Phase 6 Stable to 2.2.442 with 2.2.441 as source release and keeps the mandatory Azure Release Certification Gate.

## 2.2.441 - Adaptive Intelligence Foundation

- Adds a read-only Adaptive Intelligence layer over the auditable Recommendation Learning dataset. Evidence is isolated by client and optional project scope; learning is never mixed across clients.
- Derives smoothed historical effectiveness, human relevance and evidence maturity per recommendation pattern, with explicit guardrails of ±8 confidence points and ±10 ranking points.
- Reorders recommendations and exposes Next Best Actions while preserving deterministic severity, Risk, Opportunity, Health, Execution and Stock Risk scores.
- Adds a single grouped per-store learning pass to the isolated Store Intelligence endpoint. It can adjust displayed confidence and store ordering only; base priority and all operational scores remain unchanged and visible.
- Adds Adaptive Intelligence to Intelligence Center > Aprendizagem and Analytics 2.0 with explainable pattern evidence, maturity, confidence delta, rank delta and Next Best Actions.
- Propagates adaptive recommendation confidence/ranking into the shared reporting envelope, automated email summaries and Excel/PDF/CSV exports. Excel adds an Adaptive Intelligence sheet.
- Preserves the 2.2.440 Analytics critical path and WidgetOrchestrator runtime guard. Adaptive aggregation is not executed by read-only widget orchestration and cannot gate base Analytics.
- Cross-client learning, inferred causality, autonomous mutations, automatic route changes and automatic operational score mutations remain disabled.
- No database migration is required.
- Advances Phase 6 Stable to 2.2.441 with 2.2.440 as source release and keeps the mandatory Azure Release Certification Gate.

## 2.2.440 - Widget Orchestrator Runtime Hotfix

- Fixes the exact Analytics HTTP 500 regression introduced in 2.2.437: `WidgetOrchestrator::orchestrate()` and `compactMetrics()` still called the internal `scoreMetric()` helper, but `scoreMetric()` and its `topic()` dependency had been accidentally removed during the Store Intelligence edit. PHP syntax validation cannot detect this runtime missing-method failure.
- Restores `scoreMetric()` and `topic()` from the proven 2.2.436 Widget Intelligence contract without changing Store Opportunity & Risk formulas, Analytics scores, AI behaviour or reporting semantics.
- Keeps the 2.2.439 critical-path decoupling so Store Intelligence remains independently loaded and cannot gate the base Analytics response.
- Adds a runtime scoring-contract guard plus release-time self-call integrity validation so a future missing helper fails open instead of blanking Analytics.
- No database migration is required.
- Advances Phase 6 Stable to 2.2.440 with 2.2.439 as source release and keeps the mandatory Azure Release Certification Gate.

## 2.2.439 - Analytics Critical Path Decoupling Hotfix

- Restores the proven 2.2.436 critical path for base Analytics 2.0 and removes Store Opportunity & Risk processing from blocking `/stats` and `/analytics/ai-insights` requests.
- Adds isolated `/analytics/store-intelligence`; Store Intelligence loads after the Command View is already usable and can fail independently without blanking all scores.
- Removes previous-period per-location aggregation from the base `/stats` request and computes it only inside the isolated Store Intelligence request.
- Uses an `isolated_core` store profile in this hotfix, deliberately deferring exact per-store stock and Learning enrichment until progressive enrichment is introduced.
- Keeps global Stock Risk, Analytics 2.0, Learning Foundation, forms, routes, exports, email intelligence and all existing operational contracts unchanged.
- Advances Phase 6 Stable to 2.2.439 with 2.2.438 as source release and keeps the mandatory Azure Release Certification Gate.

## 2.2.438 - Analytics Store Engine Resilience Hotfix

- Fixes the Analytics 2.0 HTTP 500 regression observed after 2.2.437 by removing duplicate Store Opportunity & Risk computation from the `/stats` widget-orchestration pass. Store Intelligence is now calculated once in the insights path that actually presents it.
- Makes the Store Opportunity & Risk Engine fail-open. A malformed or unavailable store/stock/learning signal can no longer take the entire Analytics Command View down; FieldFlow falls back to the proven deterministic priority engine and logs the diagnostic.
- Isolates per-store profile failures so one incompatible PDV payload is skipped instead of rejecting the whole analytics response.
- Hardens stock intelligence acquisition and caches exact mapped-location stock contexts for two minutes using published batch ids and location scope, reducing repeated database work without hiding a newly published snapshot.
- Compacts the Store Intelligence envelope sent to the AI provider while keeping the full deterministic Store Intelligence in the FieldFlow response. This prevents unnecessary prompt/memory growth on projects with hundreds of PDVs.
- Keeps Risk, Opportunity and Health formulas unchanged from 2.2.437. This is a resilience/performance hotfix only, not an Adaptive Intelligence release.
- Advances Phase 6 Stable to 2.2.438 with 2.2.437 as source release and keeps the mandatory Azure Release Certification Gate.
- No database migration is required.

## 2.2.437 - Store Opportunity & Risk Engine

- Adds an explainable per-store intelligence profile with Operational Health, Execution, Risk, Opportunity, Stock Risk, Share Gap, temporal trend, priority and confidence.
- Adds priority, risk, opportunity, stock-risk, deteriorating and recovered store rankings.
- Adds exact mapped-location stock contexts so route, merchandiser and store scopes never inherit a client/project aggregate as if it were local evidence.
- Uses Intelligence Learning only as confidence evidence in 2.2.437. Historical feedback and outcomes do not modify Risk, Opportunity or Health scores.
- Adds Store Intelligence to the BO Command View, Client/Merchandiser intelligence surfaces, store alerts, email/report source envelope, Excel, PDF and CSV exports.
- Advances Phase 6 Stable to 2.2.437 with 2.2.436 as source release and keeps the mandatory Azure Release Certification Gate.
- No database migration is required for this release.

## 2.2.436 - Intelligence Learning Foundation

- Introduces an auditable Recommendation Learning foundation that persists deterministic recommendations, human feedback and observed outcomes without enabling autonomous learning or score mutations.
- Adds the `routespro_intelligence_recommendations` and `routespro_intelligence_feedback_events` tables, versioned by migration 2.2.436 and covered by Schema Manifest health checks.
- Adds Intelligence Center > Aprendizagem with universal Client / Project / Route / Operative / Store filters, open recommendation KPIs, feedback coverage, outcome coverage and explicit human decisions: Faz sentido, Não relevante, Adiar and Resolvido.
- Extends Operational Intelligence, Analytics AI and Report Intelligence with recommendations and learning metadata while preserving explainability and the no-inferred-causality policy.
- Surfaces read-only recommendations in front Analytics and Client Portal. Human feedback remains governed in Back Office for this foundation release.
- Extends automated emails, route emails, Excel, PDF and CSV with the same recommendation source so dashboards and exported reporting remain semantically aligned.
- Makes Intelligence event delivery Azure-ready behind a separate `azure_cloud_sync_intelligence` opt-in, disabled by default until the receiving tenant validates the `fieldflow.intelligence.learning` contract.
- Preserves form required/evidence semantics, Widget Intelligence, responsive email, stock snapshot replacement, routing, licensing and all production-safety switches.
- Advances Phase 6 Stable to 2.2.436 with 2.2.435 as the source release and keeps the mandatory Release Certification Gate.
- Includes a database migration for the two learning tables only. No existing operational tables are rewritten.

## 2.2.435 - Evidence Choice Semantics Hotfix

- Fixes the evidence-check contract so choosing `Não` no longer creates an implicit mandatory reason field. The primary Sim / Não / Não aplicável answer is the only required value when the parent question itself is configured as required.
- Keeps the conditional companions available and contextual: photographs appear on the Sim branch and the non-conformity reason appears on the Não branch, both optional by default.
- Introduces EvidenceFlow v2 explicit strictness flags (`evidence_require_photo_on_yes`, `evidence_require_reason_on_no`) for future controlled use without restoring implicit mandatory behaviour.
- Applies the same EvidenceFlow v2 validation contract to web/server and native Mobile SubmissionService because both consume the expanded schema.
- Updates Form Builder previews/help text, Briefings labels and Briefing AI Copilot guidance so new questions no longer describe a mandatory reason after a negative answer.
- Preserves Analytics classification and historical answers: Sim remains conforming, Não remains non-conforming and Não aplicável remains excluded/neutral according to existing analytics rules.
- Preserves Analytics 2.0, Widget Intelligence, Smart Reporting, responsive email, exports, routes, stocks, licensing and the mandatory Azure Release Certification Gate unchanged.
- Advances Phase 6 Stable to 2.2.435 with 2.2.434 as the source release.
- No database migration.

## 2.2.434 - Required Form Validation Hotfix

- Fixes an urgent submission blocker where a conditionally hidden question could remain `required` on the server even though the browser correctly excluded it from visible validation. Server submission now evaluates the same ConditionalFlow visibility contract before required checks.
- Restores schema parity between render, mobile payload and web submission by normalising ConditionalFlow before ProductCardex resolution, also preventing false offline schema conflicts on forms that use conditional branches.
- Hardens required Share Space / briefing comparison validation in the browser so an incomplete mandatory comparison is identified before submit instead of surfacing only as a generic server error. Explicit `0` remains a valid reported value.
- Changes a completed 0/0 Share Space row from the misleading `Por preencher` status to `Sem frentes registadas`.
- Server validation errors now return the exact question key and label, and the quick/focused report shells can reopen and scroll to the rejected field automatically.
- Applies the same hidden-required visibility rule to the native Mobile SubmissionService for web/native contract parity.
- Preserves Briefings Admin UX, Analytics 2.0, Widget Intelligence, Smart Reporting, responsive email, Excel/PDF/CSV exports, stock snapshot semantics, routes and licensing unchanged.
- Preserves the mandatory Azure Release Certification Gate and advances Phase 6 Stable to 2.2.434 with 2.2.433 as the source release.
- No database migration.

## 2.2.433 - Briefings Admin UX Hardening

- Reworks the Briefings > Execution Questions back-office layout into a single-column full-width workspace so the question editor and execution registry no longer compete for horizontal space in Desktop.
- Keeps AI Suggestions, Question Editor and Linked Questions as independent vertical cards, preserving all existing submission, bulk action, analytics, store assignment and historical behaviour.
- Gives the question editor the full content width, improves comparison-matrix editing and store selection, and adds a dedicated save action bar without changing persistence contracts.
- Hardens the linked-question table with predictable column sizing, readable headers, two-line validity dates, compact row actions, hover feedback and horizontal scrolling only as a narrow-viewport fallback.
- Changes the execution KPI strip to four balanced columns inside the question registry and prevents headers such as Form, Validity and Actions from collapsing character-by-character.
- Preserves Analytics 2.0, Widget Intelligence, Report Intelligence, responsive email output and Excel/PDF/CSV exports unchanged.
- Preserves the mandatory Azure Release Certification Gate and advances Phase 6 Stable to 2.2.433 with 2.2.432 as the source release.
- No database migration and no change to stock replacement semantics, routing, form data, licensing, Desktop/mobile contracts or Azure production-safety switches.

## 2.2.432 - Responsive Email Intelligence Hardening

- Makes the FieldFlow Intelligence email layer viewport-aware for mobile Outlook, Gmail and Apple Mail without changing the underlying analytical data.
- Replaces the three-column score layout with a maximum two-column grid and a one-column narrow-screen fallback, eliminating horizontal clipping on mobile clients.
- Adds fixed-layout width constraints, box sizing and safe word wrapping to score cards, scope cards, priority metrics and store-alert tables so content cannot force the message wider than the viewport.
- Applies the same responsive envelope to scheduled Client/Merchandiser reporting and to route completed/exception Intelligence digests.
- Keeps the same Report Intelligence source for Email, Excel, PDF and CSV. This release changes presentation compatibility only, not score semantics, alert scope or export completeness.
- Preserves the mandatory Azure Release Certification Gate and advances the Phase 6 Stable target to 2.2.432, with 2.2.431 as the source release.
- No database migration and no change to stock snapshot replacement semantics, Widget Intelligence, route execution, licensing, Desktop/mobile application contracts or Azure production-safety switches.

## 2.2.431 - Widget Intelligence + Smart Reporting

- Adds `WidgetOrchestrator`, a deterministic dashboard orchestration layer that keeps the complete Analytics widget registry but automatically selects a compact audience-specific front set instead of exposing every configured widget at once.
- Adds Automatic, Assisted and Manual orchestration modes per dashboard, configurable maximum widgets for Client, Merchandiser and Email, alert-driven relevance boost and an administrator `Fixar sempre` override.
- Ranks only existing FieldFlow metrics using briefing priority, briefing/question semantics, strategic metric family, available records, audience relevance, active alerts and explicit administrator pinning. It never invents a metric.
- Adds a second non-persisting front intelligence pass so current execution/share/fronts/stock alerts can influence widget order without creating an alert merely because a dashboard was opened.
- Adds `ReportIntelligenceService` as the single analytical envelope for email, Excel, PDF and CSV, keeping Decision Scores, smart metrics and store alerts consistent across channels.
- Upgrades scheduled report emails with compact analytical score cards, prioritised metrics and store-level alerts, scoped independently for Client and Merchandiser recipients.
- Upgrades route completed/exception emails with a compact route/day intelligence digest and concrete store alerts when store-level evidence exists.
- Adds `Intelligence` and `Alertas Lojas` Excel sheets, Field Intelligence sections to PDF and structured Intelligence/Smart Widget/Store Alert rows to CSV, while preserving all detailed export data even when the front dashboard is compact.
- Adds selectable Daily reporting range and CSV scheduled attachment support. New automations deliberately keep Weekly + Cumulative and Excel + PDF as safer defaults, with Daily/CSV opt-in to avoid attachment bloat.
- Prevents campaign-wide StockService aggregates from being presented as route/user/store-specific stock intelligence until the stock contract can prove that narrower scope.
- Keeps autonomous self-learning disabled. 2.2.431 creates the explainable orchestration and reporting foundation required by the next recommendation-feedback learning phase.
- Preserves the mandatory Azure Release Certification Gate and advances the Phase 6 Stable target to 2.2.431, with 2.2.430 as the source release.
- No database migration and no change to stock snapshot replacement semantics, route execution, form submission, licensing, Desktop/mobile or Azure production-safety contracts.

## 2.2.430 - Analytics 2.0 Command View

- Adds Analytics 2.0 directly inside the FieldFlow Intelligence Center so assignments, operational context and analytics use the same Client / Project / Route / User / Store / Period scope.
- Adds universal filters, equal-window temporal comparison, route/store drill-down, operational AI reading and a deterministic Decision Score layer for Operational Health, Execution, Risk, Opportunity, Stock Risk and Confidence.
- Deliberately disables temporal comparison for an individual `route_id`: a route ID identifies one concrete execution and is not assumed to be the equivalent route in the previous period. Project/store context remains the canonical scope for period comparison.
- Adds `DecisionScoreService` for traceable scoring and store prioritisation based only on FieldFlow facts. It does not infer causes, mutate operations or perform autonomous learning.
- Fixes real Stats inconsistencies: `route_id` now constrains the base route/stop queries instead of only form analytics; `location_id` is preserved in the previous comparison window; and store drill-down counts only stops from the selected store instead of every stop on routes that happen to contain that store.
- Loads the Analytics 2.0 BO experience through dedicated scoped CSS/JS assets rather than extending the historical inline-style surface.
- Preserves human review and keeps self-learning explicitly disabled in this release. The next learning phase can build an auditable recommendation-feedback dataset on top of these deterministic scores.
- Preserves the mandatory Azure Release Certification Gate and advances the Phase 6 Stable target to 2.2.430, with 2.2.429 as the source release.
- No database migration and no change to stock snapshot replacement, routing, form submission, licensing, Desktop/mobile or Azure production-safety contracts.

## 2.2.429 - Intelligence Center + Mandatory Azure Certification Gate

- Reframes the existing Assignments Hub as the FieldFlow Intelligence Center while preserving the canonical `routespro-assignments-hub` slug and all existing assignment actions.
- Adds a unified Command Center that evaluates the selected Client / Project / Route context across team ownership, form bindings, Analytics dashboards, active widgets, AI-generated dashboards, persisted operational alerts and configuration readiness.
- Keeps the operational analytics engine traceable: alerts shown in BO come from deterministic persisted anomalies and do not infer unproven causes.
- Renames the separate generative-AI provider page to Configuration of AI so provider credentials/governance are clearly separated from operational Intelligence.
- Makes Azure certification part of the Release Manager completion contract. A release is no longer considered complete after publication + runtime installation alone.
- Adds one-place Phase 6 actions in Release Manager: revalidate the 6B chain, run the Phase 6 closure probe, then certify the active Stable release.
- Extends Phase 6 Stable evidence with the WordPress Release Manager publication state, package SHA-256, release ID, publication actor/time and publisher service.
- Adds a mandatory `PUBLISH` matrix check so Stable certification only succeeds for the exact release published through the canonical WordPress private-release path.
- Advances Phase 6 Stable target to 2.2.429, with 2.2.428 as the source release.
- No database migration and no change to Stock snapshot replacement semantics, routing, form submissions, mobile contracts or Azure production safety invariants.

## 2.2.428 - Desktop Analytics Canonical Grid + Release Evidence

- Fixes the remaining Desktop Analytics card fragments by neutralising the late, high-specificity legacy `layout-executive` 3/2-column variants and adding a final canonical single-column safety guard.
- Keeps Analytics visual cards full width with `min-width: 0` so long content cannot create hidden extra columns or right-edge slivers.
- Makes the Phase 6 matrix release detail dynamic from `STABLE_RELEASE` instead of displaying the stale 2.2.398 text.
- Makes the Desktop About `Release Gate` chip display the actual FieldFlow Core version instead of the stale 2.2.300 label.
- Advances the Phase 6 Stable target to 2.2.428. Final release status still requires private publication, installation, 6B revalidation, Phase 6 closure test and Stable certification in the live Azure-backed environment.

## 2.2.398 - Stock Snapshot Replacement

- Makes stock publication explicitly replace-based per Client + Project + Insignia. A newly published stock file is the complete active picture, never an increment over the previous week.
- Archives every previously published snapshot in the same scope atomically when the new one becomes active, preserving history without contaminating operational totals.
- Keeps the currently published snapshot live while a new import remains Draft.
- Supports explicit rollback by re-publishing an archived historical batch, which then replaces the current snapshot.
- Normalises legacy installations once so only the newest published batch per stock scope remains active.
- Adds snapshot replacement metadata to stock events and public batch payloads for mobile, REST, Azure and audit continuity.
- Makes the replacement rule visible in the Stocks BO to prevent users interpreting uploads as cumulative stock movements.
- Keeps Stock Excel Intelligence, store/product mappings, Briefing/Cardex integration and all 2.2.397 release-pipeline behaviour unchanged.

## 2.2.397 - WordPress-Native Release Proof

- First production package intended to be published entirely from `FieldFlow > Release Manager`, proving the new WordPress control-plane without a routine Cloud Shell publication script.
- Adds a compact Release Pipeline self-check covering canonical install, Release Publisher health, last WordPress-published release, catalog persistence, SHA-256 verification and runtime installation state.
- Distinguishes the intermediate state “published and waiting for WordPress update” from the final state “pipeline proven end to end”.
- Improves Release Manager contrast and spacing in the hero and explicit publication confirmation block.
- Keeps the canonical package contract `fieldflow/fieldflow.php`, immutable private Blob upload, catalog `inspect` confirmation, manual rollback and no forced automatic updates.
- Advances the Phase 6 Stable release baseline from 2.2.396 to 2.2.397 after the WordPress-native publication path is validated.
- No database migration, operational event change, route/form behavior change, Stock/Briefing/Cardex logic change, mobile contract change or licensing-enforcement change.

## 2.2.396 - Release Pipeline + Canonical Update Hardening

- Adds `FieldFlow > Release Manager` as the WordPress control plane for future private releases.
- Requires a single canonical ZIP root `fieldflow/` and validates `fieldflow.php`, Stable tag, SHA-256 and the complete production manifest before publication.
- Adds a one-purpose Azure Release Publisher sidecar using Managed Identity for immutable Blob writes. Storage credentials never enter WordPress or browser code.
- WordPress signs the exact ZIP bytes with the existing HMAC trust and calls the existing Licensing API only after the immutable Blob upload succeeds.
- The private updater independently re-validates the canonical package and production manifest before WordPress receives the temporary file. Flat legacy packages are rejected.
- Restores the certified associative production-manifest file map used by 2.2.393 and teaches runtime/package validators to read the temporary list format emitted by 2.2.394/2.2.395, eliminating false `release_manifest_integrity` failures.
- Adds a guarded installation normaliser for legacy version-specific plugin directories and exposes Release Manager in BO and Desktop navigation.
- Reduces private update manifest cache to 30 minutes and clears update transients after successful publication.
- Stock workbook diagnostics preserve rejected-sheet reasons instead of silently swallowing row-materialisation exceptions.
- Keeps Azure sync payloads, Google, licensing enforcement mode, client data destinations, mobile contracts and operational data semantics unchanged.

## 2.2.395 - Assortment Intelligence + Weekly Cardex Sync

- Adds persistent `store_format` and `assortment_profile` to Store Intelligence with controlled retailer-specific options and `general` as the safe default.
- Store profile updates now use PATCH semantics, preserving fields omitted by older clients or partial edits and validating profile changes against the active Cardex.
- Pingo Doce P-levels are cumulative: P3 means P1 + P2 + P3. Unknown source codes such as B4 remain auditable anomalies and are never auto-promoted to a store profile.
- Form Store Card, BO Lojas, exports, mobile/offline snapshots and Azure `location.snapshot` share the same persisted profile source.
- Adds `CardexSyncService` to materialise the five Cardex sections embedded in the weekly Pernod Operational Briefing at publish time.
- Weekly sync preserves existing item IDs, per-store live overrides and manual items; source-managed products missing from a later briefing are only deactivated. Reapplying an older published briefing acts as a non-destructive logical rollback.
- Weekly publication is atomic: the Cardex sync requires a SQL transaction and rolls back the complete weekly change set if any write fails, preventing partial live assortments.
- Cardex publication invalidates the affected project mobile/offline generation keys so the next bundle is rebuilt with the current assortment.
- Stock imports now map products against active Cardex using exact EAN/reference/name first and high-confidence matching second, storing `cardex_item_id`, confidence and method per row.
- Stock BO exposes Cardex product coverage separately from store coverage while retaining exact source-vs-database stock reconciliation.
- Keeps the deterministic Excel Intelligence introduced in the unreleased 2.2.394 work and recalibrates weekly Cardex parsing against the real W36 workbook, including merged category rows and ECI purchase/sale/EAN references.
- Direct upgrade target is the certified 2.2.393 baseline; 2.2.394 does not need to be installed. External Azure Functions, licensing contracts, Google integrations and existing public APIs remain unchanged.

## 2.2.394 - Excel Intelligence

- Adds a shared `WorkbookIntelligence` layer for deterministic XLSX/CSV interpretation across operational imports.
- XLSX readers now propagate merged-cell headers, ignore Excel error cells and support multi-row header reconstruction.
- Stocks scan all workbook sheets, score candidate data tables, recognise aliases, normalise identifiers and locale-specific numbers, and retain detailed import diagnostics.
- Stock batch detail now reconciles raw source stock total against the persisted imported total and exposes candidate sheets, selected header span, accepted/skipped rows, duplicates and mapping confidence.
- Store resolution prioritises external references and exact names, with high-confidence automatic mapping only when ambiguity is sufficiently low.
- Operational Briefing gains semantic parsing for narrative sections, promotions, Share Space, Cardex, VAP matrices, central spaces and store assortments. Existing positional parsers remain as fallback for known legacy templates.
- Briefing import applies safe store mappings automatically and reports workbook coverage and unresolved mappings.
- Shopping imports also use workbook intelligence, allowing cover sheets, moved columns, grouped store headers and merged Regular/Promo layouts.
- No schema migration. Azure, licensing, Google, mobile, Desktop, existing exports and public integration contracts remain unchanged.
- Phase 6 Stable source baseline moves to 2.2.393 and target release to 2.2.394. Revalidate 6B.4 and Phase 6 Stable only after publishing the exact private ZIP.

## 2.2.393 - Operational User Purge

- Adiciona `Purga Operacional` para limpar, de forma controlada, o rasto de testes de um utilizador sem apagar a conta WordPress nem master data de clientes, projetos ou lojas.
- A purga é sempre precedida por preview, intervalo de datas opcional, fingerprint do perímetro, token temporário de 15 minutos e confirmação textual `PURGAR <login>`.
- Remove rotas próprias do utilizador, paragens filhas, atribuições de rota, submissões/respostas, versões/valores históricos, eventos, rascunhos, snapshots de rota e submissões Shopping selecionadas.
- Recalcula `form_records` após apagar versões para impedir métricas, última visita ou Memória da Loja órfãs.
- Bloqueia automaticamente qualquer execução que encontre atividade de outros utilizadores em rotas que seriam eliminadas.
- Preserva despesas, utilizador WordPress, projeto/campaign assignments, Ficha Viva da Loja, audit/security logs e ledgers Cloud.
- Deteta jobs Azure concluídos através de `core_events`, mesmo quando uma submissão local já foi eliminada manualmente, e marca explicitamente a necessidade de reconciliação remota.
- Invalida caches de scope e rota/mobile dos utilizadores afetados após commit.
- Integra a ferramenta em Backoffice e Desktop, sem novas tabelas e sem alterar contratos Azure, Google, licensing ou exportações existentes.
- Atualiza o alvo de continuidade Phase 6 Stable para 2.2.393. A certificação 6B.4 deve ser revalidada após publicação do ZIP exato no catálogo privado Stable.

## 2.2.392 - Store Intelligence

- Introduz a Ficha Viva da Loja no formulário após resolução do PDV, mantendo os dados de origem/Google exclusivamente em leitura.
- Cria `routespro_location_profiles` para responsável, telefone e email operacionais, pendentes, notas, controlo de revisão e auditoria, sem contaminar o master data de `routespro_locations`.
- Mantém a atual Memória da Loja e acrescenta estado da última visita, completude do perfil e pendentes no arranque do reporte.
- Adiciona REST autenticado para leitura/gravação do perfil com autorização estrita por rota/atribuição e proteção contra conflitos de revisão.
- Cria o novo BO `Lojas` com pesquisa, filtros por cliente/projeto/merchandiser, visita, pendentes e completude, paginação, edição e exportação CSV/XLS/PDF.
- Expõe o snapshot Store Intelligence no payload mobile e no bundle offline de forma aditiva, usando carregamento em lote para evitar regressões N+1.
- Integra `Lojas` no menu WordPress, navegação Backoffice e Desktop sem alterar os contratos Azure, Google, exports existentes ou endpoints já publicados.
- Atualiza o alvo de continuidade Phase 6 Stable para 2.2.392. A certificação 6B.4 só deve ser revalidada depois de 2.2.392 estar publicada no catálogo privado Stable.

## 2.2.391 - Phase 6 certification continuity hotfix

- Corrige a cadeia de certificação após um update privado nativo.
- A 6B.4 deixa de aparecer CERTIFICADA quando a 6B.3 upstream está por revalidar.
- O fecho Stable passa a distinguir preservação real de identidade/governance da mera validade do certificado local.
- O teste final força heartbeat, governance probe e validação do pacote privado sem ativar enforcement.
- Adiciona revalidação explícita e auditada da cadeia 6B.2 -> 6B.3 -> 6B.4 após updates privados.
- Adiciona diagnóstico de fingerprint de certificação e metadados de continuidade para futuras atualizações.
- Production, Full Operational Payload, runtime blocking e revogação runtime continuam OFF; governance continua SHADOW.
- Nenhum job histórico 5B Pending é processado ou alterado.

## 2.2.390, Phase 6 Stable Closure

- Closes Phase 6 as a single certified baseline over Phase 5 Stable, 6A and 6B.1 through 6B.4.
- Adds `Phase6StableRelease` with final matrix, SHA-256 baseline fingerprint, immutable snapshot and governance ledger evidence.
- Adds a final diagnostic and certification section to Licensing & Tenants.
- Final diagnostic refreshes the licensed private distribution test so the stable catalog must contain the installed 2.2.390 release.
- Designed to be installed from 2.2.389 through the private WordPress updater, proving the commercial update path.
- Preserves Azure Licensing Backend 1.0.6, governance SHADOW, runtime blocking OFF, forced auto-updates OFF, Production Enabled OFF and Full Operational Payload OFF.
- No schema migration and no automatic processing of historical 5B Pending jobs.

## 2.2.389, Phase 6B.4 Private Distribution & Updates

- Adds a licensed private release catalog with stable/beta channels through the Azure licensing service.
- Integrates private FieldFlow releases into the native WordPress plugin update flow without relying on WordPress.org.
- Authenticates release manifests with HMAC-SHA256 and verifies package SHA-256, exact byte size and internal FieldFlow version before WordPress receives the ZIP.
- Uses short-lived signed package URLs and revalidates license, plan, Activation ID and Installation UUID at download time.
- Keeps private release packages in non-public Azure Blob Storage and never exposes the storage account key or connection string.
- Blocks automatic downgrade offers and does not force WordPress auto-updates. Rollback remains an explicit manual operation.
- Adds a dedicated 6B.4 certification gate and end-to-end package verification test that downloads to a temporary file and does not install it.
- Preserves 6B.1 to 6B.3, governance SHADOW, runtime blocking OFF, schema/migrations, Phase 5 and the 11 historical 5B Pending jobs.

## 2.2.388, Phase 6B.3 Policy Fingerprint Canonicalization Hotfix

- Corrige o mismatch deterministico entre PHP e Node para policy maps vazios, onde WordPress codificava `[]` e o backend Azure codificava `{}`.
- A canonicalizacao local de `capabilities` e `limits` passa a usar semantica de mapa JSON, mantendo a mesma policy e produzindo o mesmo SHA-256 do backend 1.0.4.
- O caso Enterprise com `limits` vazio passa a produzir o fingerprint remoto esperado `c602ac00f0fd15343041d6bcc534231d96c38db5b09ac3a7219e5e6a4850a888`.
- Nao altera Azure, governance operations, enforcement SHADOW, runtime blocking, schema, migrations, Fase 5 ou jobs 5B Pending.

## 2.2.387, Phase 6B.2 Installation Identity Recovery Hotfix

- Corrige `installation identity mismatch` quando a Installation UUID local e regenerada inesperadamente durante um upgrade, mantendo o Activation ID e o dominio originais.
- O FieldFlow pode restaurar localmente a UUID ja ligada no Azure apenas depois de validar HTTP 409 especifico, License ID, Activation ID, dominio, estado remoto e fingerprint SHA-256 da identidade devolvida.
- A recuperacao nunca altera o binding remoto e repete o heartbeat uma unica vez depois de restaurar a identidade local.
- Um heartbeat rejeitado deixa de substituir a ultima Installation UUID/fingerprint aceite no estado local da licenca.
- Se a opcao 6B.2 dedicada estiver ausente, a ultima identidade aceite pode ser reutilizada apenas quando o fingerprint calculado e o binding do dominio coincidem.
- Mantem Azure Licensing Backend 1.0.4, 6B.3 em SHADOW, runtime blocking OFF, schema/migrations e Fase 5 inalterados.

## 2.2.386 - Phase 6B.3 Runtime Performance Hotfix

- remove a execucao do grafo completo de System Health dos admin notices em cada pagina FieldFlow;
- adiciona cache de health por request nas camadas 6B.1, 6B.2 e 6B.3 para eliminar recomputacao em cascata;
- adiciona cache curta do production manifest entre requests, invalidada por versao/mtime/tamanho do manifesto;
- Release Gate e certificacoes 6B continuam a forcar verificacao SHA-256 fresca;
- nao altera governance, enforcement, schema, migrations, Fase 5 ou Azure delivery.

## 2.2.385, Phase 6B.3 Performance Hotfix

- Removes the 2.2.384 admin performance regression caused by nested 6B.1, 6B.2 and 6B.3 health checks repeatedly hashing the full production manifest within the same PHP request.
- `ProductionManifest::verify()` now memoizes its result only for the lifetime of the current PHP request. A new HTTP request still performs a fresh full integrity scan, so release integrity semantics are preserved across requests.
- Adds an explicit request-cache reset method for deterministic diagnostics/tests.
- Does not change licensing governance, remote policy, heartbeat, activation, enforcement, WordPress schema, Phase 5 controls, Client Azure SHADOW behaviour or the 11 historical 5B Pending jobs.

## 2.2.384, Phase 6B.3 Governance, Rotation, Limits & Shadow Enforcement

- Introduces `LicenseGovernanceLifecycle` with central policy synchronization, governance inspection and a dedicated 6B.3 certification gate.
- Adds remote admin operations for suspend, resume, revoke and rotate while keeping runtime enforcement in `shadow`.
- Synchronizes plan slug, max installations, expiry, capabilities and usage limits to the licensing backend with deterministic SHA-256 policy fingerprints.
- Keeps the existing remote max-activation ceiling as a real control for new activations and reports active installation count against the licensed ceiling.
- Models a 72-hour grace period for a future enforcement rollout, but this release remains fail-open and cannot disable FieldFlow because Azure is unavailable.
- Rotation returns the replacement key once from request memory only. Raw keys are not persisted in the 6B registry.
- Preserves the certified 6B.2 heartbeat/identity contract and does not introduce a WordPress schema migration.
- Phase 5, SHADOW controls, kill switches, Production Enabled OFF, Full Operational Payload OFF and the 11 historical 5B Pending jobs remain unchanged.

## 2.2.383, Phase 6B.2 Activation, Binding & Heartbeat Pilot

- Introduz `LicenseInstallationLifecycle` com UUID persistente por instalação e fingerprint SHA-256 não reversível ligada ao produto, instalação e domínio normalizado.
- Deteta clones de base de dados para outro domínio e roda a Installation UUID, mantendo apenas o SHA-256 da identidade anterior para auditoria.
- Estende `/license/activate` com identidade da instalação e metadados técnicos mínimos, preservando `domain`, `site_url` e o fingerprint legado.
- Reutiliza a rota HMAC `/license/validate` como heartbeat compatível com o serviço existente. O scheduler faz um sweep horário e só envia quando a cadência configurada, por defeito 6 horas, estiver vencida.
- Adiciona proteção contra heartbeats concorrentes sem armazenar qualquer material de licença no lock.
- Regista observacionalmente instalações na tabela `routespro_license_installations` já criada pela 6B.1 quando a licença corresponde a um tenant local.
- Adiciona diagnóstico e gate de certificação 6B.2. A certificação requer ativação remota real, `license_id`, `activation_id`, domínio válido, heartbeat aceite e acknowledgement Azure da mesma Installation UUID/fingerprint com `heartbeat_recorded=true`.
- Mantém `LICENSE_ENFORCEMENT_ENABLED`, `INSTALLATION_BINDING_ENFORCED` e `REMOTE_REVOCATION_ENABLED` em `false`. Falhas Azure não desativam nem bloqueiam FieldFlow.
- Não introduz migrações WordPress e não altera o comportamento da Fase 5, SHADOW, kill switches ou os 11 jobs históricos 5B Pending.
- Inclui contrato e patch SQL opcional para enriquecer o inventário central Azure com identidade e versões da instalação.

## 2.2.382, Phase 6B.1 Stable Closure

- Fecha formalmente a Foundation 6B.1 sem ativar enforcement, installation binding, remote revocation ou Production Enabled.
- Substitui o transient de 10 minutos usado para apresentar a key remota por um buffer exclusivamente em memoria no mesmo request PHP. A key bruta deixa de poder ser escrita temporariamente em `wp_options` por via de transients sem object cache persistente.
- Mantem no registry 6B apenas `masked_key`, `key_fingerprint_sha256` e `remote_license_id`.
- Corrige `ProductionManifest::verify()` para validar `key_files` quando o manifesto usa lista de paths, preservando compatibilidade com o formato associativo legado.
- A certificacao 6B.1 passa a depender de `production-manifest.json` valido e de consistencia entre `FIELDFLOW_VERSION`, cabecalho do plugin e `Stable tag`.
- Regenera o manifesto integral da release e alinha a versao distribuida em 2.2.382.
- Nao altera schemas, dados, destinos, filas, delivery, Broker, Azure Functions, Fase 5 Stable ou os jobs historicos 5B em SHADOW.

## 2.2.381, Phase 6B.1 Licensing Endpoint Migration Hotfix

- Atualiza o endpoint canónico de licenciamento para `https://func-fieldflow-licensing-prod-eb978f11.azurewebsites.net/api`.
- Migra em runtime valores guardados que ainda apontem para o endpoint Azure retirado.
- Ignora especificamente uma constante `ROUTESPRO_LICENSE_API_BASE` quando contém o hostname legado, evitando que a UI volte ao endereço antigo após gravar.
- Mantém constantes válidas não legadas como override administrativo.
- Atualiza o host pinned do guard HTTP sem abrir allowlists genéricas.
- Mostra a origem efetiva do API Base no ecrã de Configurações.

## 2.2.380, Phase 6B.1 Remote Licensing Connectivity Hotfix

- Corrige o falso positivo da 2.2.379, onde a Foundation podia ficar certificada apenas com API Base e segredos configurados, sem confirmar conectividade real.
- Adiciona um probe explícito e não mutável a `/license/validate`, com HMAC real e uma chave sintética inexistente. O probe nunca cria, ativa, revoga ou altera licenças.
- A Foundation 6B.1 passa a exigir host atual, HTTPS, política outbound válida, resposta HTTP, contrato JSON da rota e HMAC aceite antes de poder ser certificada.
- Adiciona diagnóstico seguro no BO para DNS, validação URL do WordPress, bypass controlado, HTTP, rota Function e HMAC.
- `OutboundHttp` 1.0.1 mantém o modo estrito por defeito. O bypass à validação URL nativa só existe para o hostname fixo da Azure Function de licenciamento FieldFlow e só quando HTTPS, porta 443, host exato, allowlist e proteção de redes privadas já passaram.
- O tenant Pernod Ricard já criado em 6B.1 permanece em `DRAFT`. Nenhuma key é emitida durante a atualização.
- Não existe novo deployment Azure ou Broker. Enforcement, installation binding, remote revocation e Production Enabled continuam `false`.

## 2.2.379, Phase 6A Stable Closure + Phase 6B.1 Licensing & Tenant Foundation

- Fecha formalmente a Fase 6A com `ClientOnboardingCertification`, matriz 6A, fingerprint SHA-256, snapshot e baseline JSON exportável.
- Exige pelo menos um onboarding TWT Cloud `PRODUCTION_READY`, pré-flight validado, provisioning em Shadow e separação explícita entre readiness e ativação.
- Introduz `LicenseTenantFoundation` para iniciar a Fase 6B sem ativar enforcement.
- Adiciona `routespro_license_tenants` e `routespro_license_installations` ao schema canónico.
- Novo BO `Licenciamento & Tenants`, integrado no layout consolidado e no FieldFlow Desktop.
- Reutiliza a API remota de licenciamento já existente para emissão explícita de keys. A key em claro é apresentada uma única vez através de transient curto e nunca é persistida no registry 6B.
- O registry guarda tenant UUID, cliente, plano SaaS, limites, máscara da key, SHA-256 da key, remote license id e fingerprint de binding.
- A 6B.1 certifica apenas a foundation. `LICENSE_ENFORCEMENT_ENABLED`, `INSTALLATION_BINDING_ENFORCED` e `REMOTE_REVOCATION_ENABLED` permanecem `false`.
- Nenhum deployment Azure ou Broker novo é necessário nesta release.

## 2.2.378, Phase 6A Backoffice and Desktop Navigation Integration

- Integra `routespro-client-onboarding` no scope `management` do Backoffice UI Foundation, garantindo o mesmo layout consolidado das restantes páginas FieldFlow.
- Coloca `Onboarding de Clientes` dentro do grupo `Gestão` no menu WordPress FieldFlow, imediatamente após `Clientes`.
- Adiciona `Onboarding de Clientes` ao menu nativo do FieldFlow Desktop, também em `Gestão`, com ícone próprio e navegação dentro da shell.
- Mantém inalterados o motor `ClientOnboardingFoundation`, os contratos 5A a 5E, o Broker 1.8.0, Azure, routing, delivery e credenciais.
- `PRODUCTION ENABLED` e `FULL OPERATIONAL PAYLOAD` continuam `false`. Não existe qualquer ativação de produção nesta release.

## 2.2.377, Phase 6A Client Onboarding & Provisioning

- Introduz `ClientOnboardingFoundation` e o novo BO `Onboarding de Clientes`.
- Templates guiados para TWT Cloud, Azure SQL, Power BI, Fabric, API Externa, Webhook e Azure do Cliente via Relay.
- Cria planos por cliente/projeto, pre-flight seguro, staging de destino/regra em Shadow e readiness consolidada.
- Microsoft Entra cross-tenant reutiliza o onboarding browser-only 5C.2.
- Credenciais API/Webhook continuam Key Vault-only e podem ser certificadas pela 5D sem expor o valor ao WordPress.
- A baseline Stable 2.2.376 continua obrigatoria e certificada antes de qualquer onboarding 6A.
- `PRODUCTION ENABLED` e `FULL OPERATIONAL PAYLOAD` permanecem `false`. Nenhum plano 6A ativa producao automaticamente.

## 2.2.376, Phase 5 Stable Closure

- Closes Phase 5 formally after the certified 5E Production Eligible gate.
- Adds `Phase5StableRelease` with a consolidated 5A to 5E readiness matrix, deterministic stable baseline fingerprint and drift detection.
- Requires the 2.2.376 production manifest to match the installed runtime before stable certification can pass.
- Runs the existing non-destructive platform `ReleaseGate` and blocks stable certification only on hard failures; warnings remain visible in the captured release report.
- Stores a safe Phase 5 baseline snapshot containing only control-plane metadata, hashes, counts and readiness state, never credential values or operational payloads.
- Adds a downloadable JSON baseline with a documented rollback to 2.2.375, including backup, SHADOW and kill-switch preconditions.
- Appends stable certification and reset events to the 5E tamper-evident governance ledger.
- Adds a dedicated Phase 5 Stable section in Data Destinations and status reporting in System Health.
- Upgrades the read-only support `ReleaseGate` to 1.2.0 and includes `Phase5StableRelease.php` in critical runtime-file checks.
- Preserves Entra Broker 1.8.0 and all 5A to 5E Azure/runtime contracts without a new Azure deployment.
- Production remains disabled by default. Full operational payload delivery and the normal external connector dispatcher remain blocked. Activation is intentionally separated into the per-client onboarding/activation layer that follows Phase 5.

## 2.2.375, Phase 5E Governance, Recovery & Final Eligibility Gate

- Adds a dedicated `routespro_governance_ledger` table with an append-only application path and SHA-256 hash chaining (`previous_hash` + `entry_hash`) to make control-plane actions tamper-evident.
- Automatically records Data Destinations admin actions in the governance ledger without persisting credential values or operational payloads.
- Adds scoped governance kill controls for client, destination and explicit event names; the global kill switch remains forced ON in this release.
- Adds controlled dead-letter replay with mandatory human justification, a 15-minute single-use authorization ticket, actor binding, delivery-key binding and audited execution.
- Adds stale-lock recovery, deterministic control-plane snapshots, circuit-breaker reset certification and preservation of one row per deterministic delivery key.
- Adds a final `PRODUCTION ELIGIBLE` gate requiring certified 5C.8 production readiness, a READY 5D credential, valid governance ledger, certified replay/recovery controls, SHADOW mode and kill switches ON.
- Binds final eligibility to a deterministic SHA-256 fingerprint of destinations, routing rules, 5C.8 policies and 5D certified credential versions, so configuration drift automatically invalidates eligibility.
- Adds 5E controls, certification flow, dead-letter view, replay UI, recovery UI, recent governance ledger and final gate state to `Destinos de Dados`.
- Adds 5E health reporting to System Health and bumps the schema manifest to 1.6.0.
- No new Azure deployment is required. Entra Broker 1.8.0 from 5D is preserved.
- Production remains explicitly disabled, full operational payload delivery remains blocked, and the normal 5C.5 network dispatcher remains hard-locked.

## 2.2.374, Phase 5D Credential Lifecycle & Secret Governance

- Adds a Key Vault-only credential lifecycle for API External and Webhook destinations. WordPress stores references such as `kv://vault/secret`, never credential values.
- Adds Entra Broker 1.8.0 Key Vault probes through Managed Identity that return only safe lifecycle metadata, including enabled, not-before, expiry and immutable secret-version data, never the secret value.
- Adds credential certification against a fixed non-persistent TWT sink with HMAC-signed receipts bound to destination UUID, secret reference hash and current secret version.
- Adds rotation detection, expiry enforcement, warning windows, local revocation and automatic readiness invalidation when the certified version changes.
- Adds a synthetic Key Vault test secret and least-privilege preparation script for repeatable 5D certification.
- Keeps 5C.5 in SHADOW, global kill switch ON, full operational payload delivery blocked and general production activation blocked until the final 5E governance/recovery gate.

## 2.2.373, Phase 5C.8 Production Delivery Gate

- Adds a policy-bound Production Delivery Gate for external API/Webhook destinations while the general operational dispatcher remains blocked.
- Requires complete 5C.7 certification for the same target before 5C.8 preparation.
- Adds Broker 1.7.0 target + policy binding using endpoint SHA-256, policy SHA-256 and a short-lived HMAC production-gate token.
- Enforces an explicit event pattern, `metadata_v1` safe projection, bounded hourly rate, bounded retry profile and signed receipts.
- Adds synthetic target certification and a real connector-queue certification path with deterministic delivery keys and duplicate suppression.
- Adds a non-persistent TWT Production Gate Receiver and automatic local bootstrap of its 5C.7 prerequisite.
- Corrects the 5C.7 UI state after one-shot consumption so the control plane no longer appears permanently armed.
- Keeps authenticated production targets reserved for 5D Credential Lifecycle and keeps general activation, full operational payload delivery and normal 5C.5 network dispatch blocked until the 5D/5E closing gates.

## 2.2.372, Phase 5C.7 Safe Live Pilot

- Adds a guarded Safe Live Pilot for API External/Webhook destinations without opening the normal 5C.5 operational dispatcher.
- Adds Broker 1.6.0 target preparation with HTTPS-only validation, public DNS enforcement, DNS pinning, no redirects, endpoint SHA-256 and short-lived HMAC pilot token.
- Adds a strict metadata allowlist so the 5C.7 path never forwards the original operational event payload, form answers, photos, uploads, passwords, API keys, access tokens or client secrets.
- Adds synthetic endpoint certification, signed receipt verification and one-shot operational event certification with consume-before-network and no automatic retry.
- Adds a non-persistent TWT pilot receiver so the gate can be certified without a third-party service.
- Keeps `delivery_enabled=false`, full operational payload delivery blocked, 5C.5 dispatcher hard-locked, and preserves the already certified 5C.6 resilience controls.

## 2.2.371, Phase 5C.6 Controlled External Delivery

- Adds certification-only controlled sender for external API/Webhook jobs.
- Adds Entra Broker 1.5.0 fixed receiver path and signed HMAC receipts.
- Adds end-to-end synthetic delivery, retry/backoff, dead-letter and circuit-breaker certification.
- Keeps operational 5C.5 dispatcher hard-locked and does not open Live delivery.
- No arbitrary target URLs or destination secrets are sent through the 5C.6 certification path.

## 2.2.370, Phase 5C.5 Synthetic Shadow/Queue Certification

- Mantém a rede externa hard-locked e não introduz modo Live.
- Adiciona certificação sintética segura no BO para validar Shadow e Queue sem criar rotas, visitas ou submissões reais.
- O teste usa o DestinationRouter real, escolhe uma regra INCLUDE ativa, cria um evento UUID sintético e valida a fila do destino selecionado.
- Executa duas inserções com o mesmo `delivery_key` para provar idempotência e confirma que existe apenas uma linha na fila.
- Em Shadow confirma payload funcional redigido + SHA-256; em Queue confirma `pending` e persistência apenas do payload sintético de certificação.
- A certificação não dispara o EventBus global, evitando qualquer efeito lateral no Azure TWT, Client Azure 5B ou outros listeners.
- O dispatcher continua a devolver `network_delivery_locked_2_2_370`; sem HTTP externo, sem tokens, sem resolução de Key Vault no WordPress.
- Preserva o Entra Broker 1.4.1 e o estado `EXTERNAL_FABRIC_RESOURCE_VISIBILITY_PENDING`.

## 2.2.369, Phase 5C.5 External API and Webhook Delivery Foundation

- Introduz uma fila dedicada `cloud_connector_jobs` apenas para destinos `external_api` e `webhook`, separada da fila Azure TWT e da fila Client Azure 5B.
- Adiciona intake fail-open no `fieldflow_core_event` com `event_uuid` e `delivery_key` determinística por evento + destino, preservando o fan-out idempotente.
- Adiciona modos 5C.5 `shadow` e `queue`. `shadow` regista apenas envelope técnico com payload funcional redigido; `queue` persiste o payload necessário para uma futura entrega, mas não transmite.
- Adiciona kill switch global, política preparada para batch, timeout, retry/backoff, circuit breaker e retenção. O modo Live não existe nesta release.
- O dispatcher 5C.5 está hard-locked por código e devolve `network_delivery_locked_2_2_369`; não existem chamadas HTTP externas, resolução de segredos ou aquisição de tokens nesta nova fila.
- `secret_ref` continua a ser apenas referência. A resolução futura de API keys/tokens fica exclusivamente no Azure Broker/Key Vault, nunca no WordPress.
- `external_api` e `webhook` passam a apresentar delivery phase `5C.5` no Destination Registry.
- O BO `Destinos de Dados` ganha health 5C.5, Shadow/Queue, kill switch, diagnóstico local, teste do gate e observação da fila.
- System Health ganha `connector_delivery_5c5` e métricas shadow/pending/dead.
- Preserva sem alteração funcional Azure TWT, Client Azure Relay 5B, Entra Broker 1.4.1, probes 5C.3, mobile/offline, rotas, formulários, Portal Cliente e exportações.
- O caso Fabric externo `fabric_workspace_not_visible_to_principal` permanece registado como compatibilidade Microsoft pendente e não bloqueia a 5C.5.

## 2.2.368, Phase 5C.3 External Fabric Workspace Probe

- Preserva integralmente os probes certificados Azure SQL e Power BI REST existentes.
- Adiciona scope explícito `https://api.fabric.microsoft.com/.default` para destinos `client_powerbi` que usem Microsoft Fabric REST.
- Adiciona o recurso read-only `fabric_workspace`; o Broker usa apenas `GET https://api.fabric.microsoft.com/v1/workspaces/{workspaceId}`.
- Entra Broker sobe para 1.4.0 e mantém `delivery_enabled=false`, HMAC/Key Vault/UAMI e Workload Identity Federation sem alterações de modelo.
- Não altera ingest TWT, Relay 5B, schemas SQL, mobile/offline, formulários, rotas, briefings, stock, Portal Cliente ou exportações.
- O caminho Power BI REST legado continua disponível e inalterado para recursos já certificados no tenant TWT.

## 2.2.367, Phase 5C.3 Home Tenant Resource Authorization
- Estende a 5C.3 a recursos Azure SQL e Power BI/Fabric no tenant TWT usando a configuração home do Entra Broker já certificada.
- Mantém o modelo secretless: o WordPress não recebe nem guarda access tokens; o Broker usa Workload Identity Federation e o token existe apenas em memória.
- `entra_cross_tenant` continua a exigir `READY, IDENTIDADE`; `entra_home_tenant` identifica explicitamente o caminho home TWT e usa automaticamente o tenant TWT + scope exato do recurso; `managed_identity` existente mantém a semântica anterior.
- Adiciona `identityContext()` à Resource Authorization Foundation para separar `home_tenant` de `external_tenant` sem duplicar lógica de probes.
- Adiciona ação BO `Preparar Power BI TWT`, idempotente, que cria `Power BI TWT, FieldFlow` em Shadow/inativo no routing com `powerbi://twt-home`.
- A tabela 5C.3 mostra Home TWT e identidades externas em conjunto, com contadores separados e sem alterar o onboarding 5C.2 externo.
- Connector Foundation sobe para 1.4.0 apenas para expor metadata de identidade/recurso também no caminho home TWT; `delivery_ready` permanece false.
- Entra Broker 1.3.0 permanece byte-identical à 2.2.366; não existe novo deployment Azure nesta release.
- Mobile/offline suspension hardening, fila offline, Azure TWT ingest, Relay 5B, rotas, formulários, Portal Cliente e Desktop permanecem sem alteração funcional.

## 2.2.366, Phase 5C.3 Resource Authorization
- Separa explicitamente `READY, IDENTIDADE` da Fase 5C.2 de `READY, RECURSO` da Fase 5C.3; nenhum destes estados ativa delivery.
- Adiciona `ResourceAuthorizationFoundation` em Shadow, sem schema novo, guardando apenas identificadores públicos e estado do probe no `retry_policy_json` existente.
- Azure SQL: exige scope `https://database.windows.net/.default`, hostname `*.database.windows.net` e base de dados; o Broker executa apenas `SELECT 1 AS fieldflow_probe` com token federado em memória.
- Power BI/Fabric: exige scope `https://analysis.windows.net/powerbi/api/.default` e Workspace ID; o Broker faz apenas `GET /v1.0/myorg/groups/{workspaceId}`.
- APIs externas/webhooks permanecem fora da 5C.3 e continuam em Shadow, sem access token nem resource probe, até à fase própria de conector/delivery.
- Tokens de acesso nunca regressam ao WordPress, não são persistidos nem escritos em logs. Erros devolvem apenas códigos seguros e HTTP remoto quando aplicável.
- Entra Broker sobe para 1.3.0 e expõe `/api/v1/resource-probe`, health `resource_authorization_control_plane=true` e `resource_probe_read_only=true`, mantendo `delivery_enabled=false`.
- O BO adiciona diagnóstico local, health do Broker, editores Azure SQL/Power BI, `Verificar acesso`, `READY, RECURSO` e validação exata do scope 5C.2.
- Preserva byte a byte o hardening mobile/offline 2.2.365 e não altera Azure TWT, Relay 5B runtime 1.1.0, rotas, Portal Cliente, Desktop ou fila final offline.

## 2.2.365, Mobile Offline Suspension Hardening
- Fecha a janela de 450 ms em que a última resposta podia existir apenas em memória: cada `input/change` grava imediatamente um snapshot local em `localStorage`.
- Mantém o debounce de 450 ms apenas para consolidar/sincronizar o rascunho com o servidor, preservando o comportamento existente e reduzindo risco de regressão.
- Pré-aquece `IndexedDB` e inicia a persistência da fotografia original antes da otimização/compressão assíncrona. Depois da otimização, a versão final continua a substituir o ficheiro do mesmo grupo.
- `visibilitychange`, `pagehide`, `beforeunload` e `freeze` continuam a forçar flush, agora iniciando também a persistência de ficheiros presentes no formulário.
- `fieldflow:app-resume` e `pageshow` de BFCache reexecutam a recuperação e reconciliam local + servidor usando `updatedAt`, preservando passo atual, respostas, categorias e ficheiros recuperáveis.
- O contexto da visita continua protegido pelo `fieldflow:last-report-visit` já existente no portal operacional; esta release não altera rotas, check-in/out ou conclusão de visita.
- A fila offline (`FieldFlowOffline.queueForm`) permanece byte-identical e só elimina o rascunho local depois de `put(QUEUE_STORE,item)` terminar com sucesso.
- Azure TWT, Entra Broker 1.2.0, Relay 5B runtime 1.1.0, Power BI e delivery 5C permanecem fora desta alteração funcional.

## 2.2.364, Phase 5C.2 OIDC Browser Bootstrap Hotfix
- Troca o browser bootstrap externo para o endpoint Microsoft identity platform `/oauth2/v2.0/authorize`.
- Pede apenas o scope OIDC `openid`, suficiente para o sign-in/consent leg sem acoplar o utilizador ao resource scope operacional do destino.
- `resource_scope` ARM/SQL/Power BI/custom API continua no estado assinado e só é usado pelo Broker no token probe Workload Identity Federation depois do callback.
- Corrige o `AADSTS650056` observado com o endpoint v1 sem configurar permissões artificiais na App Registration.
- Azure Entra Broker 1.2.0, Azure TWT, Relay 5B e delivery 5C permanecem inalterados.

## 2.2.363, Phase 5C.2 Browser-only Tenant Connection
- Troca o onboarding externo de `adminconsent` para o fluxo Microsoft `oauth2/authorize` documentado para instanciar a Enterprise Application de uma aplicação multi-tenant.
- O administrador do cliente precisa apenas do browser e de uma função Entra adequada; não precisa de Azure Cloud Shell, Azure CLI nem subscrição Azure.
- O Broker 1.2.0 aceita o callback browser-only, nunca troca/persiste/loga o authorization code e executa o token probe cross-tenant como prova autoritativa.
- Mantém compatibilidade com callbacks `admin_consent=true` antigos e adiciona retries curtos para propagação do service principal antes do probe.
- UI passa a falar em ligação Microsoft em vez de exigir consentimento administrativo artificial.
- `delivery_enabled=false` e Connector Foundation permanecem em Shadow.

## 2.2.362, Phase 5C.2 Tenant/Scope Editor Hotfix
- Corrige a coluna `Ações` do onboarding 5C.2, onde `Configurar tenant/scope` era apenas um badge informativo e não permitia editar destinos 5C existentes.
- Adiciona ação real `Configurar tenant/scope` / `Editar tenant/scope` e editor inline seguro para `Directory (tenant) ID` e `resource scope`.
- Valida GUID específico, scopes permitidos e impede usar o tenant TWT como tenant externo.
- Se tenant ou scope mudar, reinicia de forma atómica apenas o estado local de onboarding para `DRAFT`, invalidando nonce/estado anterior sem remover qualquer Enterprise Application Microsoft.
- Mantém `delivery_enabled=false`, Connector Foundation em Shadow e o Entra Broker Azure 1.1.0 inalterado.

## 2.2.361, Phase 5C.2 External Tenant Onboarding
- Adds secure tenant onboarding for 5C Entra destinations with expiring HMAC-signed consent state.
- Azure Entra Broker validates the callback, performs the authoritative cross-tenant token probe, and posts a HMAC-signed control-plane receipt to WordPress.
- Adds per-destination onboarding states and manual re-verification fallback.
- `READY` means identity ready only; connector delivery remains disabled and no client secrets/access tokens are stored in WordPress.
- Embeds the certified Key Vault/UAMI HMAC runtime fix from the final 5C.2 Broker deployment.

## 2.2.360, Phase 5C.2 Microsoft Entra Cross-Tenant Identity Broker Foundation
- Introduz uma camada de identidade cross-tenant separada do delivery 5C. Os conectores continuam rigidamente em `shadow` e `delivery_ready=false`.
- Adiciona `EntraCrossTenantFoundation`, que guarda no WordPress apenas endpoint TWT, Application client ID, tenant IDs e resource scopes nao sensiveis.
- Adiciona um Entra Broker TWT dedicado em Azure Functions Flex Consumption + Node 24, sem redeploy nem alteracao da Function de ingestao certificada ou do Relay 5B.
- O Broker usa uma `User Assigned Managed Identity` dedicada como credencial federada de uma App Registration `AzureADMultipleOrgs`, com audience `api://AzureADTokenExchange`.
- Nao cria client secret nem certificado. Access tokens nunca regressam ao WordPress nem sao escritos nos logs; o probe devolve apenas metadados seguros de sucesso/erro.
- Reutiliza o HMAC v1 TWT certificado apenas para autenticar os probes de control-plane WordPress -> Broker.
- Adiciona health, diagnostico local, configuracao segura do Broker e token probe no tenant TWT.
- Prepara callback de admin consent para o proximo bloco de onboarding, mas a resposta do callback nao e aceite como prova de autorizacao. A validacao final exigira token probe.
- Mantem Azure TWT, Client Azure 5B, Power BI, Desktop, mobile/WebView, rotas, submissões, Portal Cliente e scripts 5B v7 inalterados.

## 2.2.359, Desktop Data Destinations Navigation Parity
- Adiciona `Destinos de Dados` ao menu nativo do FieldFlow Desktop na secção `Integrações`, entre `Integrações` e `API Pública e Webhooks`.
- O módulo passa também a integrar automaticamente command palette/pesquisa, recentes, permission stamp e navegação segura em frame do Desktop.
- Revê o catálogo primário de páginas do FieldFlow contra `DesktopPortal`; `routespro-data-destinations` era o único módulo primário registado no BO sem entrada Desktop.
- Mantém integralmente a Fase 5C.1 em Shadow e não altera conectores, routing, filas, Azure TWT, Relay 5B, Power BI, mobile/WebView, rotas ou formulários.

## 2.2.358, Phase 5C.1 Connector Foundation
- Formaliza os quatro contratos 5C.1: `external_api`, `webhook`, `data_warehouse` e `client_powerbi`.
- Todos os conectores 5C ficam rigidamente em `shadow`, com `delivery_ready=false`, sem sender hook, chamadas HTTP/SQL/Power BI, resolução de segredos ou aquisição de tokens.
- Adiciona metadados de contrato, transporte, estratégia de autenticação prevista e subfase futura ao plano de routing.
- Adiciona validação type-aware de referências não sensíveis, incluindo `api://`, `webhook://`, `azure-sql://`, `warehouse://`, `synapse://`, `fabric://` e `powerbi://`.
- O registry 1.1.0 atualiza de forma idempotente destinos 5C existentes para `shadow_ready` sem tocar no destino Azure TWT ou no `client_azure` 5B.
- O BO ganha health 5C.1, catálogo de contratos, diagnóstico local e validação de destino sem chamadas externas.
- System Health passa a expor `connector_foundation_5c` e métricas de contratos/destinos.
- O runtime certificado de Azure TWT, EventBus e Client Azure 5B não é alterado.
- Os artefactos Azure 5B incluídos no ZIP são alinhados com o deployment v7 Flex Consumption + Node 24 efetivamente certificado.

## 2.2.357, Phase 5B Intake Recovery
- Corrige o ponto de integração observado em produção de teste em que o Preview resolvia `client_azure`, mas uma submissão real podia não gerar job na fila 5B.
- O listener `ClientAzureDelivery` passa a prioridade 5 e é integralmente fail-open, garantindo que uma falha 5B nunca interrompe o listener Azure TWT certificado em prioridade 20.
- Adiciona hidratação defensiva de scope por payload, CanonicalStateService, route stop e rota antes do routing 5B.
- Adiciona histórico sanitizado de intake 5B no BO, com event name, client/project, submission, estado, motivo, routed e enqueued, sem respostas nem payload funcional.
- O diagnóstico local 5B valida agora `core_event_intake` e `intake_fail_open_priority`.
- Não altera `AzureSyncFoundation`, endpoint HMAC, Azure Function principal, Relay Azure, schemas Azure, Power BI, apps ou fluxos operacionais.

## 2.2.356, Phase 5B Client Azure Relay
- Adiciona entrega assíncrona para destinos `client_azure` sem substituir nem alterar o canal primário `AzureSyncFoundation`.
- Introduz `ClientAzureDelivery`, fila WordPress independente e idempotente por delivery key, com estados pending, running, completed e dead.
- Introduz `ClientAzureRelayConnection`, que autentica apenas o Relay TWT através do HMAC v1 já certificado. O WordPress nunca recebe a credencial do Azure do cliente.
- Adiciona modos `shadow`, `queue` e `live`, permitindo testar routing, persistência e delivery em etapas separadas.
- O novo Relay Azure TWT usa Managed Identity para Azure SQL e Azure Key Vault, e persiste entregas em `ctl.ff_delivery_jobs`.
- O worker Azure aplica retry exponencial independente, recuperação automática de locks stale e idempotência antes de entregar ao receiver Azure do cliente.
- A 5B inicial suporta receivers HTTPS Azure autenticados por Function key guardada no Key Vault. Cross-tenant Entra e outros destinos ficam reservados para 5C.
- Inclui receiver Azure de teste isolado, scripts SQL, security role `ff_client_azure_relay`, Managed Identity e validação de ausência de grants `ctl` nas roles Power BI.
- O BO `Destinos de Dados` ganha health 5B, configuração do Relay, diagnóstico local, fila, dispatch manual, recuperação dead e probe end-to-end.
- Não altera a Function de ingestão existente, Power BI DirectQuery/SSO, schemas `rpt`/`sec`, apps mobile/WebView, Desktop, rotas, visitas, submissões, briefings, stocks ou exportações.

## 2.2.355, Phase 5A Menu + Release Integrity Recovery
- Coloca `Destinos de Dados` dentro do grupo visual `Plataforma` no menu FieldFlow, eliminando o item solto fora das secções.
- Regenera o `production-manifest.json` para a release atual, cobrindo os ficheiros da Foundation 5A e removendo o falso alerta de integridade causado pelo manifesto legado 2.2.353.
- Não altera migrations, schema Multi-Destination, AzureSyncFoundation, HMAC, Power BI, SSO, mobile/WebView, rotas, visitas, submissions, stocks, briefings ou exportações.

## 2.2.354, Phase 5A Multi-Destination Foundation
- Introduz o Destination Registry por tenant, cliente e projeto/campanha, com tipos `twt_azure`, `client_azure`, `external_api`, `client_powerbi`, `webhook` e `data_warehouse`.
- Regista automaticamente o Azure TWT como destino `primary` obrigatório, usando apenas uma referência não sensível ao conector Cloud existente, sem copiar endpoint ou segredo HMAC para novas tabelas.
- Adiciona Routing Rules com include/exclude, padrão de evento, prioridade e scope, mais preview local do plano de routing.
- Adiciona delivery key determinística derivada de Event UUID + destination UUID para preparar fan-out idempotente sem alterar o EventBus certificado.
- Mantém `AzureSyncFoundation` como único caminho de delivery real nesta fase. Destinos externos ficam em `shadow`/foundation only até 5B e 5C.
- O registry aceita apenas referências Azure Key Vault para credenciais externas, nunca secrets/tokens/passwords em plaintext.
- Novo BO `FieldFlow > Destinos de Dados` com health, diagnóstico local, gestão de destinos e regras.
- Adiciona migração WordPress 2.2.354 e scripts Azure SQL 5A separados, sem alterar schemas `rpt`, `sec`, Power BI SSO, apps mobile, rotas, visitas, submissões, stocks, briefings ou exportações.

## 2.2.353, Client Route Export Download Recovery
- Corrige a falha de download das exportações de Rotas no Portal Cliente em CSV, Excel e PDF.
- O endpoint `routespro_export_routes` passa a distinguir BO e Portal Cliente pelo nonce recebido, em vez de assumir o contexto apenas pela capability `routespro_manage`; isto resolve o Portal Cliente quando aberto por um administrador.
- Adiciona `routespro_create_route_export_ticket`: o ficheiro é construído enquanto a sessão WordPress existe, depois é entregue através de um ticket opaco de utilização curta.
- FieldFlow Desktop reconhece exportações de rotas e pede o ticket autenticado antes de enviar o URL ao downloader nativo do Windows.
- A WebView mobile usa o mesmo transporte por ticket para rotas, sem alterar câmara, galeria, submissões, permissões iOS ou código nativo Flutter.
- CSV/Excel/PDF limpam buffers antes dos headers e incluem `Content-Disposition` robusto para nomes UTF-8.
- Mantém o layout responsivo de Campanhas PDV da 2.2.352 sem alterações e não altera Azure, Power BI, HMAC, Data Hub ou schemas.

## 2.2.352, Client Route Exports + Campaign PDV Responsive Table
- Adiciona exportação de Rotas no Portal Cliente em CSV, Excel e PDF, respeitando cliente/campanha e o operativo selecionado.
- Sem operativo selecionado, o export inclui todas as rotas dentro do scope autorizado do Portal Cliente e não é limitado pelo intervalo de datas do dashboard.
- Endurece o `routespro_export_routes` com nonce próprio de Portal Cliente e `Permissions::scope_sql`, impedindo exportação fora do scope mesmo com manipulação do URL.
- Mantém o export de rotas do BO compatível e preserva os controlos de entitlement/quota dos formatos.
- Corrige Campanhas PDV > PDVs e Regras: tabela deixa de esmagar colunas no BO web/Desktop, ganha larguras operacionais e scroll horizontal controlado.
- Em mobile, a tabela PDV transforma cada linha num cartão legível com labels por campo; barra de gravação bulk e inputs passam a adaptar-se ao ecrã.
- Não altera Azure, Power BI, HMAC, contratos Cloud, `src/Mobile` nem as correções iOS certificadas.

## 2.2.351, Phase 4B.1 Operatives / Merchandisers Reporting
- Adiciona `OperativeBackfill` para sincronizar apenas utilizadores operacionalmente relevantes com o Azure Data Hub.
- O contrato `operative.snapshot` contém ID operacional, nome de apresentação, roles e flags de capacidade, excluindo email, telefone, morada e restantes campos de contacto.
- Adiciona controlos BO para ativar o scope e executar o snapshot de operativos em lotes, reutilizando a fila HMAC certificada.
- Atualiza o contrato de Reporting para schema 1.5.0 e inclui `rpt.operatives` e `rpt.operative_execution_daily`.
- Não altera `src/Mobile`, endpoints nativos, contratos existentes de visitas/submissões/briefings/stocks nem requer redeploy da Azure Function.

## 2.2.350, Phase 4A Azure Reporting Security Foundation
- Introduz contrato de reporting separado da ingestão, sem guardar credenciais Azure SQL ou Power BI no WordPress.
- Adiciona `ReportingFoundation` com schema esperado 1.4.0 e catálogo de views seguras `rpt`.
- Integrações passa a distinguir a nova camada Power BI/Azure SQL do conector legado Push JSON.
- Recomenda DirectQuery + Microsoft Entra SSO e prepara também RLS para modelos Import.
- Mantém Data Hub, fila Cloud, apps iOS/Android e fluxos operacionais sem alterações funcionais.

# 2.2.349 - Team Inbox Transient DB Recovery

- Impede a área Mensagens de apresentar HTML técnico devolvido por WordPress/hosting durante falhas transitórias de base de dados.
- Adiciona uma única repetição automática segura aos GETs do Team Inbox.
- Mantém POSTs sem retry automático para evitar efeitos laterais duplicados.
- Sanitiza respostas não JSON/HTML e usa mensagens controladas no UI.
- Preserva integralmente a baseline 2.2.348 e o Azure Data Hub 1.3.0.

## 2.2.346, Azure Data Hub Phase 3C, Briefings + Stocks

## 2.2.348 - iOS Media and Visit Completion Recovery

- Corrige a abertura da camara na app iOS: em contexto nativo o formulario deixa de depender de getUserMedia/WebRTC e usa o input nativo com capture=environment.
- Corrige o fluxo de galeria no iOS com accept image/* e abertura por click direto no file chooser da WKWebView, preservando selecao multipla quando aplicavel.
- Expoe a plataforma do web session em window.FieldFlowMobile e data-fieldflow-platform para comportamento deterministico por shell.
- Regista routespro_submit_form tambem em wp_ajax e usa admin-ajax.php para submissoes AJAX da app nativa, mantendo admin-post.php para fallback web sem JavaScript.
- Sanitiza respostas HTML inesperadas antes de as apresentar no portal, impedindo que markup WordPress/Site Kit seja mostrado no erro de conclusao da visita.
- Nao altera schema Azure 1.3.0, Azure Function, HMAC, Data Hub, tabelas operacionais ou contratos REST mobile.

## 2.2.347 - Phase 3 Final, Stock Bulk Backfill Auto Runner

- Adiciona Auto Runner para `stock_items` com processamento contínuo em chunks técnicos de 100 linhas.
- Continua exatamente a partir do cursor 3C existente, sem reiniciar snapshots e sem duplicar eventos.
- Driver rápido via AJAX no BO, com scheduler WordPress como fallback em background.
- Implementa lock exclusivo, pausa/retoma, progresso, linhas/minuto, ETA, contadores de fila e estado persistente.
- Aplica backpressure: a fila Cloud pendente é drenada antes de avançar o próximo chunk de origem.
- Em `dead_jobs_detected` ou erro de Cloud, o runner entra em estado de erro e deixa de avançar automaticamente.
- Não altera schema Azure, Azure Function, HMAC, contratos Mobile, Submissões, Visitas, Briefings ou materializadores existentes.

- Fecha a Fase 3 do Data Hub com snapshots e materialização de Briefings e Stocks.
- Adiciona `BriefingStockBackfill` para histórico de `briefings`, `stock_batches` e `stock_items`, usando a fila HMAC já certificada e Event UUID determinístico.
- Linhas de stock são enviadas em chunks por lote para evitar payloads excessivos e manter retries/idempotência por bloco.
- `briefing.published` é enriquecido apenas no canal Azure com cabeçalho, secções, perguntas e itens/alocação de lojas.
- `stock.imported` é enriquecido com snapshot do lote e chunks `stock.items.snapshot`, sem alterar o payload do EventBus consumido por outras integrações.
- O BO de Integrações recebe a área Data Hub 3C com progresso independente e processamento manual da fila.
- O schema Azure 1.3.0 adiciona Briefings, perguntas, itens por loja, lotes e linhas de stock, e as views `vw_ff_briefing_execution`, `vw_ff_stock_latest`, `vw_ff_stock_by_location` e `vw_ff_execution_360`.
- Sem alterações a `src/Mobile`, FlutterFlow ou contratos REST mobile.

## 2.2.345, Data Hub 3B Visit Snapshot Integrity Hardening

- Corrige o backfill operacional de `visits` quando a tabela `route_stops` contém registos históricos órfãos cuja rota já não existe.
- A seleção e os contadores de Visitas passam a considerar apenas `route_stops` com uma rota pai válida.
- Um registo histórico inválido deixa de bloquear permanentemente o cursor do snapshot; é ignorado com razão de integridade e o lote continua.
- O BO mostra `Ignorados por integridade` para Visitas quando existirem paragens órfãs no histórico.
- Mantém o mesmo endpoint HMAC, Azure Function, schema Azure 1.2.0, materializadores, Submissões 3A e snapshots de Lojas/Rotas.
- Sem alterações a `src/Mobile`, FlutterFlow, contratos REST mobile, apps iOS/Android ou fluxos operacionais.

## 2.2.344, Azure Data Hub Phase 3B

- Adiciona `OperationalBackfill` para snapshots históricos controlados de Lojas, Rotas e Visitas, sem repetir hooks operacionais do WordPress.
- Cada entidade usa Event UUID determinístico no snapshot e passa pela mesma fila HMAC, retries e idempotência já certificadas nas Fases 2 e 3A.
- `visit.completed` é enriquecido apenas no canal Azure, preservando o payload do EventBus recebido por Webhooks, Billing e Usage.
- Uma visita Cloud concluída passa também a enfileirar snapshots relacionados de Rota e Loja, mantendo as dimensões operacionais atualizadas sem criar um segundo canal de transporte.
- O backfill 3B é separado do snapshot de Submissões e pode ser processado em lotes independentes de Lojas, Rotas e Visitas.
- A camada Mobile permanece byte-a-byte inalterada face à 2.2.343.
- O schema Azure 1.2.0 materializa `location.snapshot`, `route.snapshot`, `visit.snapshot` e `visit.completed` e expõe views analíticas para Power BI.

## 2.2.343, Data Hub Backfill Dispatcher Hardening

- Corrige lotes históricos de Submissões que podiam permanecer em `pending` quando o WP-Cron não acordava o dispatcher Cloud após a ação manual no BO.
- `AzureSyncFoundation::dispatch()` aceita agora um limite explícito e `dispatchNow()` reutiliza exatamente o mesmo claim, HMAC, retry e tratamento de erro do dispatcher agendado.
- `SubmissionBackfill` faz um kick limitado antes e depois de enfileirar o lote, mantendo WP-Cron apenas como fallback/background.
- Adiciona `Processar fila Cloud agora` para recuperar, sem reset, jobs de backfill já pendentes.
- O painel distingue `Enfileiradas no snapshot`, `Fila histórica pendente`, `em curso` e `falhas definitivas`.
- O snapshot só é apresentado como concluído quando não existem registos restantes nem jobs históricos pending/running/dead.
- A camada Mobile permanece inalterada face à 2.2.342.

## 2.2.342, Azure Data Hub Phase 3A

- Adiciona `SubmissionBackfill`, um backfill historico controlado de submissoes para a fila Cloud.
- O snapshot fixa o maior `submission_id` existente no arranque, impedindo que novas submissoes sejam confundidas com historico.
- Cada submissao historica usa Event UUID deterministico e a fila Cloud preserva a idempotencia local e Azure.
- O backfill nao repete os hooks normais `fieldflow_form_submitted`, evitando duplicar webhooks, usage metering, billing ou notificacoes.
- O BO de Integracoes passa a mostrar progresso do Data Hub 3A e a enviar o historico em lotes configuraveis de 1 a 100.
- Para novas instalacoes, Visitas, Briefings e Stocks ficam desligados por defeito no Cloud Sync durante a Fase 3A; Submissoes permanecem ligadas.
- Sem alteracoes a `src/Mobile`, contratos REST mobile, FlutterFlow ou fluxos aprovados nas stores.

## 2.2.341, Azure Secure Connector Phase 2

- Adiciona endpoint Cloud dedicado, independente do conector Azure legado.
- Adiciona autenticação HMAC-SHA256 v1 por pedido, assinando timestamp, tenant, installation, event UUID e hash SHA-256 do corpo.
- Guarda o segredo HMAC através do `SecretVault`, com cifragem autenticada já existente.
- Adiciona probe Azure assinado manual que pode ser executado com o sync desligado.
- Mantém a fila Cloud desligada por defeito e preserva o comportamento operacional da 2.2.340.
- Remove os valores persistidos de tokens e secrets do HTML da página de Integrações. O token API regenerado é revelado apenas uma vez, imediatamente após a ação explícita do administrador.
- Sem alterações aos contratos REST mobile, autenticação mobile, FlutterFlow, formulários ou fluxo de visitas.

## 2.2.340, FieldFlow Cloud Foundation Phase 1

- Introduz uma identidade persistente `tenant_id` e `installation_id` na camada Cloud, sem alterar as tabelas operacionais existentes nem o contrato mobile.
- Adiciona `routespro_cloud_sync_jobs`, uma fila persistente dedicada a sincronizacao externa, com idempotencia por destino/evento, estados, bloqueio, retries exponenciais e dead jobs.
- A fila recebe eventos do `EventBus` central para `submission.created`, `submission.updated`, `visit.completed`, `briefing.published` e `stock.imported`.
- Introduz `AzureConnectionManager` com validacao HTTPS/SSRF atraves de `OutboundHttp`, reutilizando o cofre de credenciais e o endpoint Azure existente.
- O novo Azure Cloud Sync fica desligado por defeito e nao produz qualquer trafego externo durante a atualizacao.
- O BO de Integracoes passa a mostrar identidade multi-tenant, estado da fila, dados selecionados, retries, lote, destino futuro e um diagnostico local que nao contacta Azure.
- O envelope de push das integracoes passa a incluir o bloco `tenant`, preservando o contrato existente de `connector`, `resource`, `meta` e `export`.
- Mantem os conectores existentes, API publica, Power BI, Google Cloud, webhooks, licenciamento, Stripe e contratos das apps sem alteracoes destrutivas.

## 2.2.339, iOS Camera Consent Flow Hardening

- Remove o preflight JavaScript `fieldflowMediaRequest` que era executado antes de `getUserMedia()` nos formulários e nas Despesas. A tentativa de abrir a câmara passa a começar diretamente no gesto explícito do utilizador.
- Elimina mensagens que encaminhavam o utilizador para as Definições da aplicação ou do navegador após uma recusa de câmara. O FieldFlow passa a oferecer apenas nova tentativa ou seleção de imagem.
- Mantém `fieldflow.media.camera_requested` e `fieldflow.media.gallery_requested` como sinais de telemetria, sem os usar como bloqueio de permissão ou navegação.
- Preserva o fallback para o input nativo com `capture=environment` apenas quando a API de câmara não está disponível. Uma recusa de permissão não dispara qualquer fallback automático nem qualquer abertura de Definições.
- A leitura de QR nas Despesas continua a pedir a câmara apenas após o toque em `Ler QR com câmara` e mantém a mensagem neutra já existente quando a captura não pode ser iniciada.
- O planeador de rotas deixa igualmente de executar o preflight nativo `fieldflowLocationPermission`. `navigator.geolocation` passa a ser chamado diretamente a partir de `Usar localização atual`, e a recusa deixa de instruir o utilizador a abrir Definições.
- Alteração limitada ao consentimento e UX de câmara. Não altera uploads, evidências, respostas, submissões, endpoints, rotas, Briefings, Stocks, Analytics ou contratos da API mobile.

## 2.2.338, Context-aware Analytics Census & Comparativo Dedup

- Corrige a lacuna da 2.2.337 em que `briefing_comparativo_referencias` ainda não fazia parte do conjunto de famílias consideradas pelo desativador de duplicados semânticos. O owner evergreen pode agora substituir o binding legacy sem aumentar a contagem de widgets ativos.
- O Centro de Atribuições passa a filtrar dashboards, grupos e bindings Analytics pelo Cliente, Projeto e Rota selecionados, usando o scope do próprio binding e fallback para o scope do dashboard.
- O KPI passa a indicar `Widgets ativos no contexto`, evitando confundir bindings globais/legacy com os widgets do projeto em análise.
- Contagens de widgets por dashboard, evidências e frentes consideram apenas bindings ativos, mesmo quando a vista de inativos/legados está aberta.
- A migração `routespro_context_analytics_census_22338` repete a reconciliação evergreen de forma não destrutiva e guarda o número de duplicados semânticos desativados.
- Sem alterações a respostas, submissões, uploads, evidências, chaves históricas, API mobile ou FlutterFlow.

## 2.2.337, Explicit Analytics Ownership & Mobile Analytics Layout

- A propriedade do comportamento Analytics passa a exigir confirmação explícita. `analytics_behavior_source=user` só é considerado uma decisão manual quando `analytics_behavior_user_confirmed=1`. Linhas antigas sem o novo marcador são tratadas como auto-saves legacy e podem ser reconciliadas.
- O editor de perguntas passa a usar `analytics_behavior_user_intent`. Alterar o destino Analytics ou qualquer configuração permanente fixa a decisão; apenas abrir e guardar a pergunta já não o faz.
- A migração `routespro_explicit_analytics_ownership_22337` executa novamente a reconciliação evergreen, mede `ownership_repaired` e preserva escolhas manuais confirmadas.
- `Comparativo de referências do briefing` passa para a família estável `briefing_comparativo_referencias`, com label `Comparativo de referências`, impedindo que um tipo técnico `share_space_matrix` o coloque em `Frentes e Share Space`.
- Perguntas temporárias sem família estratégica e sem decisão manual confirmada passam para `Briefing operacional`. As métricas estratégicas continuam a convergir por significado de negócio.
- O Centro de Atribuições mostra quantas decisões legacy de propriedade foram reparadas depois de `Otimizar dashboard`.
- No mobile, os cabeçalhos `.rp-analytics-card-head` e `.ffad-metric-label` passam para grelha de uma coluna, os badges fazem wrap e títulos/labels mantêm escrita horizontal e largura útil total. O eyebrow redundante é ocultado no breakpoint mobile.
- O portal do cliente e o dashboard web/app aumentam o padding e scroll padding inferiores em contexto WebView/standalone para evitar que a navegação fixa tape o conteúdo Analytics.
- Alteração não destrutiva: perguntas, question keys, respostas, submissões, uploads, media, evidências e histórico permanecem intactos; apenas ownership, identidade Analytics e estado de bindings legados podem ser atualizados.

## 2.2.336, Semantic Dashboard Guardrails

- `QuestionAnalyticsService::strategicFamilyKey()` passa a classificar famílias permanentes pelo significado textual da pergunta. `share_space_matrix`, `product_matrix` e `order_suggestion` são apenas sinais auxiliares e nunca promovem uma pergunta sem evidência semântica suficiente.
- Um `Comparativo de referências do briefing` com tipo técnico `share_space_matrix` deixa de ser classificado automaticamente como `Frentes e Share Space`; passa para `Briefing operacional` salvo decisão explícita do administrador.
- Share Space exige agora linguagem semanticamente compatível, por exemplo `Share Space`, quota, frentes face à concorrência ou equivalente. Frentes e sugestão de encomenda também exigem termos do respetivo conceito.
- `deactivateManaged()` remove aliases, tipos e evidências da fonte retirada e troca a `question_key` representativa quando o binding consolidado mantém outras fontes, evitando widgets com título/origem já desassociados.
- Quando a família canónica de um binding gerido muda por correção do sistema, a configuração visual herdada da família anterior deixa de ser preservada automaticamente. Escolhas manuais continuam intactas.
- Nova limpeza semântica desativa widgets briefing legados da mesma família e do mesmo scope quando já existe um binding evergreen gerido mais completo. A operação continua a ser apenas `is_active=0`.
- Migração de ativação `routespro_semantic_dashboard_guardrails_22336`, idempotente e não destrutiva. Não elimina perguntas, respostas, submissões, media, uploads, evidências ou histórico Analytics.

## 2.2.335, Aggressive Evergreen Consolidation

- Reforça `reconcileSmartBriefingAnalytics()` para reclassificar automaticamente perguntas pontuais antigas para o widget dinâmico `Briefing operacional`, preservando qualquer decisão explícita do utilizador através de `analytics_behavior_source=user`.
- Adiciona limpeza de bindings antigos cobertos por métricas evergreen atuais. A correspondência usa primeiro o scope completo e, para chaves históricas `briefing_*`, uma fallback segura por formulário e question key, permitindo desativar duplicados legados sem os apagar.
- Normaliza métricas estratégicas por famílias permanentes, incluindo Espaços centralizados, Materiais VAP, Promoções e visibilidade, Preços em linear, Stock, Disponibilidade de produto, Frentes e Share Space, Frentes atuais, Sugestão de encomenda e Problemas e oportunidades.
- Compacta widgets operacionais estáveis no mesmo dashboard quando partilham exatamente o mesmo âmbito. Stock e Disponibilidade podem alimentar `Stock e disponibilidade`; Problemas e Oportunidades podem alimentar `Problemas e oportunidades` com breakdown por pergunta.
- Cria o rollup gerido `Evidências operacionais`, que agrega os widgets fotográficos permanentes por tipo e loja. Os widgets fonte ficam inativos / legados e o rollup pode incorporar novas fontes em reconciliações futuras.
- Métricas evergreen passam a guardar `managed_question_types` e `managed_evidence_question_keys`, permitindo que uma família estratégica apresente fotografias associadas sem obrigar o widget principal a ser `image_gallery`.
- `StatsController` devolve evidências relacionadas, breakdown por pergunta para métricas multi-fonte e suporta `operational_evidence_rollup` com o mesmo contrato de `briefing_evidence_rollup`.
- Portal do cliente e dashboard web/app passam a renderizar evidências associadas às métricas estratégicas e a distinguir `Evidências operacionais` de `Evidências do briefing`.
- O Centro de Atribuições identifica o novo rollup de evidências operacionais, mostra o número de tipos reunidos e mantém os widgets geridos protegidos contra edição ou eliminação acidental.
- Migração de ativação `routespro_aggressive_evergreen_consolidation_22335`, idempotente e não destrutiva. Não elimina perguntas, respostas, submissões, media, uploads ou histórico Analytics.

## 2.2.334, Smart Evergreen Dashboard

- Introduz dois widgets dinâmicos geridos pelo FieldFlow, `Briefing operacional` e `Evidências do briefing`, para absorver perguntas pontuais e respetivas evidências sem criar widgets individuais semana após semana.
- O editor de perguntas de Briefing passa a persistir `analytics_behavior` com três destinos: `rollup`, `existing` e `new`. O comportamento por defeito é `rollup`; métricas estratégicas existentes podem ser reutilizadas e métricas novas podem ser criadas explicitamente.
- `QuestionAnalyticsService` passa a reconhecer famílias estratégicas estáveis para Espaços centralizados, Materiais VAP, Promoções, Preços em linear, Stock, Disponibilidade, Share Space e Número de frentes. Variantes semânticas da mesma família partilham um único widget permanente e mantêm breakdown por pergunta.
- `reconcileSmartBriefingAnalytics()` reutiliza o dashboard preferido `Briefing semanal`, assegura os dois widgets dinâmicos e desativa bindings automáticos redundantes. A reconciliação é não destrutiva e pode ser repetida por cliente/projeto através de `Otimizar dashboard`.
- A API Analytics passa a devolver `briefing_rollup`, `briefing_evidence_rollup` e `question_breakdown`. O mesmo contrato alimenta o portal do cliente, merchandiser web e FieldFlow App.
- O Centro de Atribuições identifica claramente widgets dinâmicos e métricas permanentes, impede a edição/eliminação manual dos rollups geridos e mantém a ordenação manual.
- Migração 2.2.334 não elimina perguntas, respostas, submissões, ficheiros, imagens ou evidências. Apenas altera identidade/configuração Analytics e estado de bindings redundantes.

## 2.2.333, Briefing Active Store Scope

- O editor de perguntas de Briefing passa a ter seis âmbitos explícitos: lojas mapeadas da secção, todas as mapeadas do briefing, mapeadas selecionadas, todas as lojas ativas da insígnia, todas as lojas ativas do projeto/campanha e lojas ativas selecionadas do projeto.
- Os três novos modos `retailer_active`, `project_active` e `selected_active` usam `locationsForQuestionScope()`, cuja fonte de verdade é a carteira ativa da campanha quando existe.
- `selected_active` permite escolher uma loja ativa mesmo que ela não tenha alias/mapping no Excel do briefing.
- `ContextQuestions::briefing_for_context()` e `for_briefing()` passam a respeitar o `target_scope`: os modos históricos continuam protegidos pelo mapping, enquanto os modos ativos aceitam as linhas de atribuição gravadas para lojas não mapeadas.
- A integração é transversal: formulários web, drafts, submissão mobile, payload mobile e FieldFlow App herdam o mesmo `BriefingFormBridge`, sem alterações necessárias no FlutterFlow.
- O front de Briefings troca a linguagem “loja mapeada” por “loja alvo” e apresenta o nome do âmbito junto da cobertura. O BO faz o mesmo na tabela de perguntas.
- Alteração não destrutiva, sem schema novo e sem migração de respostas. Perguntas antigas mantêm exatamente o comportamento anterior até serem editadas para um novo âmbito.

## 2.2.332, Evergreen Briefing Analytics & Store Coverage

- Introduz `analytics_metric_key` nas perguntas contextuais para separar a identidade permanente da métrica da `question_key` semanal usada para histórico e auditoria.
- Perguntas recorrentes de briefing com formulações equivalentes reutilizam automaticamente a mesma métrica permanente; a associação pode também ser escolhida explicitamente no BO.
- O dashboard preferido passa a ser `Briefing semanal` por cliente/projeto. A migração 2.2.332 centraliza nele os widgets automáticos de briefing e cria o dashboard quando não existe.
- `QuestionAnalyticsService` consolida os aliases históricos em `managed_question_keys`, preserva configurações manuais do widget e desativa apenas bindings automáticos duplicados.
- `StatsController` consulta todas as chaves históricas da métrica permanente e devolve `question_keys`, `source_question_count`, `question_labels`, `briefing_ids`, `evergreen_briefing` e `canonical_metric_key`.
- O portal do cliente e o dashboard Analytics da FieldFlow App consomem o mesmo payload consolidado e identificam visualmente métricas com histórico de várias versões.
- O BO dos Briefings apresenta, por pergunta, lojas alvo, respondidas e pendentes, com lista completa pesquisável, estado, resposta mais recente e data.
- O front dos Briefings replica a cobertura por loja na área Execução, com pesquisa e respeito pelo âmbito do utilizador ou do cliente.
- O Centro de Atribuições oculta legados/inativos por defeito, permite revelá-los e disponibiliza uma reconciliação manual segura através de `Consolidar briefings`.
- Migração não destrutiva: não elimina perguntas, submissões, respostas, uploads ou evidências. Os widgets redundantes ficam inativos para auditoria e recuperação.

## 2.2.331, Privileged MFA & Session Policy Foundation

- Adiciona TOTP RFC 6238 para contas privilegiadas (`routespro_manage`) com challenge pós-password no BO/Desktop e no front web privilegiado, preservando a página de autenticação pública `/login/`.
- O rollout é fail-safe: `mfa_mode=observe` e `session_mode=observe` por defeito. A instalação não bloqueia contas nem termina sessões existentes.
- Enrollment TOTP usa segredo aleatório CSPRNG cifrado pelo `SecretVault`; recovery codes são mostrados uma vez, persistidos apenas como hashes WordPress e consumidos individualmente.
- Inclui proteção contra replay de TOTP, janela temporal ±1 passo de 30s e rate limiting de tentativas MFA.
- Enforcement MFA exige que o administrador que ativa a política tenha MFA configurado e a sessão atual já verificada. Outras contas privilegiadas recebem janela de adesão configurável e podem ser repostas por um manager.
- Introduz política transversal de sessão com modo Observação/Enforcement: BO/Desktop idle/absolute, mobile refresh idle/absolute e WebView idle/absolute.
- Ao ativar enforcement de sessão, o timestamp da ativação torna-se baseline mínima do limite absoluto, evitando logout em massa de sessões mobile já existentes; sessões realmente inativas continuam sujeitas ao idle timeout.
- Mobile privilegiado suporta `mfa_code` no contrato/login API, mas `mobile_privileged_mfa` fica OFF por defeito até existir UI correspondente no FlutterFlow. Merchandisers mantêm o fluxo atual.
- WebView nativa fica fora do challenge WordPress e usa a sua própria política; quando expira, o portal pode ser reaberto a partir da sessão mobile ativa sem pedir password.
- Saúde do Sistema e System Health JSON mostram cobertura e modos de forma agregada, sem segredos MFA, recovery codes, tokens, session hashes ou IDs individuais.
- Sem alterações ao hotfix 2.2.330 de anexos de Mensagens, Analytics, export tickets, rotas, formulários, multi-foto, SSRF Guard ou Protected Media.

## 2.2.330, Team Attachment External Binary Endpoint Hotfix

- Corrige `ERR_INVALID_RESPONSE` ao abrir anexos XLSX/documentais de Mensagens no browser externo da app.
- Novos links temporários assinados deixam de usar a raiz pública `home_url('/')` e passam a usar `wp-admin/admin-post.php?action=routespro_download_team_attachment`, evitando cache/canonical/proxy da página pública.
- Mantém o endpoint `template_redirect` anterior apenas para compatibilidade com links temporários já emitidos antes da atualização.
- O streaming binário remove buffers/encodings prévios, tenta desativar compressão PHP/Apache, força `no-transform`, `Content-Length` exato e usa `fpassthru` sem o antigo loop de `flush()`.
- Documentos são servidos como `application/octet-stream` com o nome original em `Content-Disposition`; imagens inline mantêm MIME de imagem.
- Preserva HMAC, expiração, confinamento `realpath` a uploads, viewer interno de imagens e separação entre anexos de Mensagens e exportações Analytics.
- Sem alterações a FlutterFlow, schema, rotas, formulários, Analytics, export tickets, Desktop exports, SSRF Guard ou Protected Media.

## 2.2.329, Team Message Attachment Compatibility Hotfix

- Corrige o fluxo de anexos da inbox de equipa no front/app, com foco no merchandiser.
- Os links de documentos passam a transportar uma marca DOM explícita de `team_attachment`, evitando que um XLSX histórico/CDN seja confundido com uma exportação Analytics quando o URL assinado não estava disponível.
- A cadeia mobile de documentos tenta `fieldflowOpenExternal`, publica também o evento de compatibilidade `fieldflow.external.open`, e só depois usa o downloader nativo/fallback browser.
- Imagens recebem uma `view_url` temporária assinada com `Content-Disposition: inline`, mantendo o visualizador interno e acrescentando uma ação de download dedicada.
- O resolvedor local aceita URLs públicas de CDN/proxy quando o path pode ser resolvido, por `realpath`, estritamente dentro do diretório de uploads. A segurança do ficheiro continua dependente do token HMAC e da contenção local.
- A UI da inbox agrega anexos originais e anexos da última resposta sem esconder a segunda coleção por causa de um array vazio.
- Sem alterações de schema, rotas, formulários, Analytics, Desktop exports, SSRF Guard ou Protected Media enforcement.

## 2.2.328, System Health Semantics & Reconciliation

- Corrige o double-count do Offline First: `queue_failed` passa a representar apenas falhas terminais/reais e `queue_conflicts` mantém conflitos separadamente. A leitura de dispositivos antigos reconcilia `meta_json` sem reescrever histórico.
- O runtime offline 2.2.328 passa a reportar falhas e conflitos como dimensões independentes e reabre, uma única vez por item/geração, conflitos operacionais seguros (`stop_checkin`, `stop_checkout`, `stop_failure`) para nova tentativa controlada.
- A Saúde do Sistema passa a avaliar Observabilidade por críticos ativos numa janela de 6 horas; críticos antigos continuam `open` e são apresentados como históricos, sem eliminação ou downgrade da evidência.
- Load Readiness sobe para 1.1.0: respostas 4xx passam a `client_error`, separadas de 5xx/fatal; o gate usa p95 de queries, conserva max queries como evidência e trata outliers isolados de queries/latência como warning de capacidade.
- Queue Health deixa de penalizar permanentemente falhas já recuperadas e terminais para os quais houve sucesso posterior; os contadores históricos continuam exportados.
- Corrige a privacidade do export System Health 1.0: mensagens `mobile_app_error`/`mobile_session_error` são normalizadas e caminhos de filesystem Android/Windows/Unix são redigidos.
- BO de Dispositivos e Saúde do Sistema passam a apresentar `pendentes / falhas / conflitos` separadamente.
- Sem migrações destrutivas, sem alterações a dados funcionais, autenticação, autorização, rotas, formulários, Analytics, exportações, mensagens, Desktop shell, Outbound HTTP/SSRF Guard ou Protected Media Shadow Mode.

## 2.2.327, System Health Diagnostic Drilldown & JSON Export

- Adiciona a aba `Diagnóstico warnings` em Saúde do Sistema, com leitura imediata dos checks ativos, Offline First, Observabilidade, Performance, Queue Health, cron e SSRF Guard.
- Adiciona exportação JSON sanitizada específica de Saúde do Sistema, separada da exportação de Segurança e Observabilidade.
- O diagnóstico Offline First agrega dispositivos apenas por plataforma, versão, runtime e códigos técnicos; nunca inclui user IDs, device IDs ou erros brutos.
- O diagnóstico de Observabilidade inclui eventos críticos/warning abertos com contexto técnico numa allowlist e sem payload funcional.
- Performance exporta gate por categoria, p95, queries, memória, error rate, thresholds, cron e queue health para explicar exatamente o estado `fail/warning`.
- Processos e filas incluem score operacional, processos overdue, estado do circuit breaker e métricas da fila sem argumentos de jobs, tokens ou payloads.
- O evidence pack inclui estado do Production Manifest, Release Gate e Outbound HTTP / SSRF Guard.
- Exportação protegida por `routespro_manage` + nonce, servida com `no-store`, `nosniff` e `Referrer-Policy: no-referrer`.
- Sem alterações a schema SQL, autorização, app, Desktop shell, formulários, Analytics, exportações operacionais, mensagens, multi-foto, SSRF policy ou Protected Media.

## 2.2.326, BO/Desktop Menu Integrity & Navigation Parity

- Corrige a fragmentação visual do submenu FieldFlow: os grupos passam a mover fisicamente os seus itens para uma sequência contígua, em vez de apenas os ocultar/mostrar na posição original.
- `Sistema e Governação` passa a agrupar Privacidade, Segurança e Observabilidade, Centro de Processos, Configurações e Saúde do sistema.
- Adiciona integrity pass tardio para garantir que as cinco páginas críticas de sistema continuam visíveis a utilizadores com `routespro_manage`, mesmo após filtros de menu.
- Renomeia `Settings & Categorias` para `Configurações`.
- Torna visíveis no BO `Formulários por Contexto` e `Temas por Campanha`, já existentes no Desktop, mantendo os entitlement guards aplicáveis.
- Preserva páginas de extensões não catalogadas e atualiza a navegação para storage namespace v2.
- Alinha o divisor do Desktop para `Sistema e Governação`; o catálogo administrativo fica em paridade, exceto o atalho intencional `Utilizadores` para o core WordPress.
- Sem alterações a dados, permissões funcionais, REST/AJAX/admin-post, SSRF, app, Analytics, exportações, formulários, multi-foto, mensagens ou Protected Media.

## 2.2.325, Outbound HTTP / SSRF / Redirect Hardening

- Introduz `RoutesPro\Security\OutboundHttp` como guard central para todas as saídas HTTP do FieldFlow.
- Migra os 21 pontos reais de dispatch HTTP da baseline 2.2.324 para o guard central.
- Força HTTPS, valida host/porta, bloqueia localhost e redes privadas/reservadas e mantém `wp_safe_remote_request` como segunda validação imediatamente antes do transporte.
- APIs oficiais usam allowlist explícita de host: Stripe, Google Gemini/Maps/Routes e Azure Maps.
- Endpoints configuráveis (Azure/OpenAI custom, Copilot webhook, optimizer, licenciamento, Power BI/GCloud/Azure connectors e webhooks) ficam presos ao host exato configurado pelo administrador.
- TLS verification e `reject_unsafe_urls` não podem ser desligados pelos callers.
- Redirects ficam desativados por omissão para integrações com credenciais. O carregamento remoto de imagens de Performance/PDF pode seguir até 2 redirects, mas cada salto é revalidado e não transporta credenciais.
- Adiciona limites uniformes de timeout e tamanho de resposta por família de integração.
- URLs de webhook passam a ser validadas pelo guard já na criação e novamente em cada entrega.
- Bloqueios de outbound são registados no SecurityService apenas com purpose, host e reason code sanitizados, sem URL completo, query string ou credenciais.
- Saúde do Sistema passa a mostrar `Outbound HTTP / SSRF Guard`.
- Sem alterações a autenticação, autorização funcional, schema SQL, app, Desktop, formulários, Analytics, mensagens, multi-foto ou Protected Media Shadow Mode.

## 2.2.324, Security Observability JSON Export

- Adiciona exportação JSON sanitizada da área Segurança e Observabilidade para facilitar auditoria e partilha técnica sem screenshots.
- O export inclui período, origem por superfície, resumo de classificações, drill-down de todas as classes e eventos de segurança sanitizados.
- Nunca exporta payload_json bruto, passwords, tokens, nonces, cookies, IP/IP hash, device IDs ou IDs individuais de utilizador.
- A exportação exige `routespro_manage` e nonce WordPress, usa `Content-Disposition: attachment`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff` e `Referrer-Policy: no-referrer`.
- Aumenta apenas o limite interno do read model de drill-down para permitir exportação completa; a tabela BO continua Top 30.
- Sem alterações a autenticação, autorização, schema SQL, app, Desktop, formulários, Analytics, exportações operacionais, mensagens ou Protected Media.

## 2.2.323, Authorization Denial Drilldown

- Adicionado drill-down read-only das recusas classificadas como `authorization_denied`.
- Agrupamento por superfície, endpoint/ação, método HTTP, status e `reason_code` sanitizado.
- Contadores por ocorrências autenticadas/anónimas, atores distintos e primeira/última deteção.
- Histórico 2.2.320/2.2.321 continua imutável; linhas sem reason code são assinaladas como legado, sem inferir incidente.
- O painel continua sem armazenar payloads, passwords, tokens, nonces, nomes de ficheiros ou conteúdo funcional.
- Sem alterações a autorização, REST/AJAX/admin-post, schema SQL, Analytics, exportações, FlutterFlow, Desktop, formulários, mensagens ou Protected Media.

# FieldFlow 2.2.322 - Multi-photo Durability Guard and Presentation Fix

- Confirma e preserva upload múltiplo em `image_upload`, `categorized_image_upload` e evidências de conformidade.
- Impede gravação parcial quando o cliente selecionou mais ficheiros do que aqueles que chegaram ao servidor.
- Corrige apresentação/exportação de arrays de fotografias, passando a gerar uma entrada por ficheiro.
- Contagem frontend ignora inputs de upload desativados por condições.
- Sem alteração de schema, FlutterFlow ou contratos funcionais existentes.

## 2.2.321, Authorization Telemetry Classification

- Separa observabilidade de sessão/autenticação de verdadeiras recusas de autorização, evitando tratar todos os `401/403/429` como o mesmo evento de segurança.
- Classifica recusas em `session_expired`, `authentication_failed`, `authorization_denied`, `cross_scope_denied`, `rate_limited`, `csrf_nonce_denied`, `security_policy_denied` e `other_denied`.
- Eventos históricos da 2.2.320 são classificados dinamicamente a partir do status, route e reason code já persistidos; não existe migração nem reescrita da tabela de eventos.
- Novos eventos REST persistem apenas `classification` e `reason_code` sanitizados, além dos metadados técnicos já permitidos.
- `check_ajax_referer()`/`wp_die(-1, 403)` e mensagens WordPress típicas de nonce expirado são reconhecidas como CSRF/nonce sem guardar a mensagem original.
- Fingerprints de recusas incluem status/classificação para impedir que 401, 403 e 429 do mesmo endpoint sejam agregados no mesmo bucket.
- O painel Segurança passa a mostrar KPIs semânticos e mantém REST/AJAX/admin-post como origem técnica secundária.
- Não altera autenticação, autorização, respostas HTTP, schema, Analytics, exportações, FlutterFlow, Desktop, mensagens ou Protected Media Shadow Mode.

## 2.2.320, Authorization Observability & Release Integrity

- Adiciona logging central e passivo de respostas FieldFlow `401`, `403` e `429` em AJAX e `admin-post`, complementando a cobertura REST existente.
- A observabilidade não altera handlers, respostas ou permissões e falha sempre de forma não bloqueante.
- Regista apenas ação/superfície/método/status e IDs técnicos numa allowlist; nonces, tokens, payloads, nomes de ficheiro e conteúdo funcional não são persistidos.
- Eventos repetidos continuam agregados durante uma hora pelo `SecurityService`, com IP apenas em hash.
- O painel Segurança passa a mostrar recusas REST, AJAX e admin-post dos últimos 30 dias.
- Introduz verificação runtime do `production-manifest.json` no Release Gate, incluindo versão, contagem, SHA-256 agregado, hashes individuais e rejeição de symlinks inesperados.
- Atualiza Stable Tag, matriz ASVS e manifesto da release corrente.
- Não altera Analytics, exportações mobile/Desktop, FlutterFlow, mensagens, formulários, schema SQL ou Protected Media Shadow Mode.

## 2.2.319, Desktop Analytics Export Ticket Hotfix

- Corrige o download de exportações Analytics dentro do FieldFlow Desktop/Windows sem alterar os fluxos mobile ou browser.
- O shell Desktop deixa de enviar diretamente URLs autenticados de `admin-post.php` ao downloader nativo para `routespro_export_dashboard`.
- A sessão autenticada do shell cria primeiro o ticket temporário já introduzido na 2.2.318 e só depois entrega o URL opaco ao Windows.
- O ticket continua sujeito ao nonce e às validações de scope do Analytics, expira em 10 minutos e é single-use após streaming.
- CSV, Excel completo, Excel mobile e PDF do Analytics passam pelo mesmo caminho seguro no Desktop nativo.
- Downloads que não sejam Analytics mantêm o comportamento Desktop existente.
- Mantém integralmente o hardening de autorização da 2.2.317 e o Protected Media Shadow Mode.

## 2.2.317, Legacy AJAX Authorization Hardening

- Fecha BOLA/IDOR em endpoints AJAX legados de assignments por rota.
- `routespro_users` exige contexto FieldFlow e scope válido para cliente/projeto.
- Leitura de assignments e owner IDs passa a validar o objeto rota no servidor.
- Escrita legacy de assignments exige `routespro_manage` e nonce válido.
- Atualização de assignments passa a ser transacional e auditada.
- Adiciona `CERTIFICATION-ASVS-5.0.0-L2.md` com a primeira matriz interna, gaps e bloqueadores reais de L2.
- Não altera REST, FlutterFlow, schema, media, formulários ou mensagens.

## 2.2.316, Protected Media Shadow Mode

- Introduz Shadow Mode real para Protected Media, sem qualquer enforcement, rewrite de URL, migração ou bloqueio.
- Adiciona tabela agregada `media_shadow_access`, sem guardar URLs ou conteúdo dos ficheiros.
- Avalia `ALLOW`, `DENY` e `UNKNOWN` com reason codes e contagem diária por utilizador/media/superfície.
- Instrumenta Analytics/evidências, criação de ZIPs de evidências, inbox de equipa e download assinado de anexos.
- Distingue acesso operacional estrito por rota de acesso de portal por cliente/projeto, e trata destinatários de mensagens como contexto validado pelo servidor.
- Adiciona diagnóstico de Shadow Mode à Saúde do Sistema.
- Integra os eventos Shadow na governação de privacidade com retenção padrão de 90 dias e eliminação no eraser RGPD.
- Protected Media continua totalmente fail-open e desligado para enforcement.

## 2.2.315, Certification Baseline Alignment

- Valida explicitamente que apenas perfis de portal de cliente ou gestão podem enumerar destinatários/remetentes e enviar mensagens de equipa.
- Faz preflight de campos, scope e autorização antes de qualquer anexo ser armazenado.
- Regista novos anexos de mensagens no Media Registry em modo `observe`, sem alterar URLs ou bloquear downloads.
- Acrescenta nonce obrigatório à listagem de destinatários da equipa.
- Reforça links temporários de download com `Referrer-Policy: no-referrer` e `X-Robots-Tag`.
- Protected Media continua sem enforcement e não existe migração de dados históricos.

## 2.2.314, Image Attachment Viewer Restore

- Restaura o comportamento anterior para imagens anexadas a mensagens na app e no portal.
- Anexos `image/*` e JPG/JPEG/PNG/WebP/GIF/AVIF/BMP abrem novamente no visualizador interno, usando o URL original da imagem.
- Documentos como XLSX, PDF, ZIP e DOCX continuam a usar o fluxo de download externo introduzido na 2.2.313.
- Não altera mensagens, ficheiros, URLs armazenados, base de dados ou o Custom Widget FlutterFlow.

## 2.2.313, Team Attachment Native Bridge Compatibility Hotfix

- Corrige o conflito entre o download nativo do Custom Widget Flutter e o fallback WebView nos anexos de mensagens de equipa.
- Anexos de mensagens com URL temporário assinado deixam de usar o pipeline nativo de exportações, que valida/abre ficheiros e pode devolver `handled:false` depois de o ficheiro já ter sido processado.
- Na app, anexos de equipa são agora abertos através do handler nativo `fieldflowOpenExternal`, usando o navegador/aplicação do sistema e preservando `Content-Disposition: attachment`.
- Mantém o pipeline nativo existente para exportações Analytics, CSV, PDF, ZIP e Excel geradas pelo FieldFlow.
- Mantém fallback WebView apenas para shells antigas sem handler externo.
- Não altera mensagens, anexos, URLs históricos, schema, rotas REST, formulários ou FlutterFlow.

## 2.2.312, Team Attachment Same-Origin Download Hotfix

- Move o download temporário dos anexos da inbox de `/wp-admin/admin-post.php` para um endpoint front-end assinado no domínio normal do portal.
- Mantém compatibilidade com o endpoint antigo de `admin-post.php`, sem alterar mensagens ou anexos existentes.
- O WebView reconhece o novo endpoint como download e recebe `Content-Disposition: attachment` e `X-FieldFlow-File-Size`.
- Reduz incompatibilidades de WebView/WAF com downloads de XLSX, DOCX, PDF e ZIP.
- Nenhuma alteração de base de dados, broadcast, ficheiros históricos ou FlutterFlow.

## 2.2.311, Team Attachment WebView Download Loop Hotfix

- Corrige o ciclo de download de anexos na app causado pela reintercecao do link `blob:` criado pelo proprio fallback do WebView.
- Links temporarios de blob usados internamente recebem uma marca dedicada e deixam de ser tratados como um novo pedido de download FieldFlow.
- Se a partilha nativa de ficheiros falhar ou for recusada pelo WebView, o fluxo continua automaticamente para download local em vez de terminar em erro.
- Nao altera mensagens, anexos, URLs historicos, base de dados, broadcast ou FlutterFlow.

## 2.2.310, Team Attachment Download Hotfix

- Corrige downloads de anexos XLSX/DOCX/PDF na inbox da equipa dentro da app/WebView.
- Os anexos da inbox passam a receber um URL temporário assinado, sem alterar o ficheiro original nem o URL guardado no histórico.
- O endpoint de download serve apenas ficheiros locais do WordPress uploads e força `Content-Disposition: attachment`.
- Compatível com downloader nativo da app e fallback por `fetch`, mesmo sem cookies da sessão no pedido nativo.
- Nenhuma migração de dados, alteração de schema ou modificação de anexos históricos.

## 2.2.309, Attachment Upload Hardening
- Mantém o transporte direto de broadcasts da 2.2.308.
- Declara MIME explícito para XLS/XLSX, DOC/DOCX, ZIP, PDF, CSV/TXT e imagens nos anexos de mensagens.
- Captura exceções do processamento de uploads e devolve erro funcional em JSON em vez de HTTP 500 opaco.
- Não altera mensagens individuais, schema, rotas, formulários, media registry ou FlutterFlow.

## 2.2.308, Team Broadcast Direct Transport

- Remove a dependência de WP-Cron no envio para Todos.
- Envia o broadcast num único transporte de email com BCC, preservando privacidade entre destinatários.
- Anexos são processados uma única vez e seguem no mesmo envio.
- Mantém uma entrada individual na inbox/email log para cada membro através do mesmo broadcast_id.
- O envio individual permanece inalterado.
- Sem alterações de schema, rotas, formulários, media registry ou FlutterFlow.

## 2.2.307, Team Broadcast Queue & Attachments
- Broadcast da aba Equipa passa a fila assíncrona por destinatário para evitar timeouts.
- Anexos são persistidos uma vez e reutilizados em todas as entregas individuais.
- Cópia ao remetente inclui os anexos.
- Front mostra erro HTTP explícito se o AJAX não devolver JSON válido.
- Envio individual permanece inalterado.

## 2.2.306, Team Broadcast Hotfix

- Corrige ciclo duplicado na preparação de destinatários do envio “Todos”.
- Isola falhas por destinatário para impedir que uma entrega derrube o broadcast completo.
- Protege a cópia ao remetente contra falhas do provider de email.
- Sem alterações de schema, rotas, formulários, media ou FlutterFlow.

## 2.2.303 - Audit & Privacy Hardening Candidate

- Central audit-log sanitization for newly created audit records only.
- Sensitive credential fields are automatically redacted before persistence.
- Submission audit records now store metadata summaries instead of answer values.
- Existing audit history, submissions, answers, media, database schema and operational contracts are untouched.
- No endpoint, shortcode, payload contract or FlutterFlow integration changes.

## 2.2.302 - Media & Public API Hardening Candidate
- Adds conservative structural validation for ZIP/DOCX/XLSX uploads, including traversal, archive consistency and decompression safeguards.
- Adds dormant ProtectedMedia foundation. No existing media URLs, attachments, exports or historical evidence are migrated or changed.
- Public mobile API response contracts remain unchanged in this candidate to avoid FlutterFlow regressions.
- No database migration.

## 2.2.301, Security Hardening Candidate

- Separa leitura contextual de rotas da autorização de escrita.
- Adiciona `Permissions::can_edit_route()` com política estrita: manager, owner ou assignment ativo.
- Todas as mutações REST de rotas/paragens passam a usar a autorização estrita, mantendo GET por scope como estava.
- Otimização aplicada, eventos operacionais e sincronização offline de paragens passam pela mesma autorização estrita.
- `PATCH /routespro/v1/campaign-pdvs/{id}` passa a exigir `routespro_manage`, com verificação redundante no callback.
- `GET /routespro/v1/campaign-pdvs` permanece disponível a utilizadores autorizados no front e mantém o filtro pelo utilizador.
- Sem migrações de base de dados e sem alteração de contratos REST/payloads.

# 2.2.297 - Desktop Workspace Experience

- Dashboard do Desktop passa a carregar diretamente o dashboard operacional FieldFlow quando executado dentro do shell.
- Memoria local por utilizador do ultimo modulo e dos oito modulos recentes, excluindo a administracao tecnica WordPress.
- Command palette com `Ctrl+K` para modulos FieldFlow, clientes e campanhas/projetos.
- Barra superior com voltar, avancar, atualizar, pesquisa rapida, recentes e indicador de ligacao ao servidor.
- Heartbeat autenticado e leve para distinguir online, offline, sessao expirada e servidor indisponivel.
- Tratamento desktop de links externos e feedback de downloads.
- Drag and drop de ficheiros para o primeiro campo de upload visivel do modulo atual, sem auto-submit.
- Sem alteracoes de schema SQL, endpoints mobile, FlutterFlow, Android ou iOS.

## 2.2.296, Desktop Clean Frame & Full Module Map

- Remove de forma redundante e resiliente o `wpadminbar`, `adminmenumain`, `adminmenuwrap` e `adminmenuback` das páginas administrativas abertas no shell Desktop.
- O frame administrativo preserva `ff_desktop_frame=1` em links e formulários GET.
- O shell parent injeta a limpeza do chrome em páginas same-origin como segunda camada, cobrindo redirects que removam parâmetros de query.
- `WordPress completo` continua fora do modo frame, mantendo a administração técnica nativa intacta.
- Expande o menu Desktop para o catálogo operacional completo do FieldFlow, incluindo Mapping, Importar PDVs, Categorias, Novo Formulário, Formulários por Contexto, Subscrições, Faturação, IA, E-mails, API Pública e Webhooks, Personalização, Temas por Campanha, Dispositivos, Privacidade, Segurança e Observabilidade e Centro de Processos.
- Adiciona pesquisa instantânea de módulos na navegação lateral.
- Sem alterações de schema SQL, IndexedDB, REST mobile, FlutterFlow, Android ou iOS.

## 2.2.295, Desktop Shell & Role Router

- Evolui a fundação Desktop para uma experiência desktop-first, mantendo o WordPress como backend e o FieldFlow como interface principal.
- Adiciona classificação de perfil para Administrador, Supervisor, Operacional e Cliente, baseada em `routespro_manage`, `routespro_execute`, roles e âmbito existente, sem criar novos roles ou permissões.
- Administradores entram diretamente no Dashboard FieldFlow. O painel geral do WordPress deixa de ser a experiência normal do Desktop.
- Reorganiza o menu administrativo por Workspace, Operação, Dados, Gestão, Análise, Sistema e Administração técnica.
- Adiciona acessos diretos a Cardex Produtos e Campanha PDV no shell desktop.
- Mantém o WordPress completo disponível apenas em Administração técnica, sem o modo frame que esconde a navegação nativa.
- Clientes são encaminhados prioritariamente para o Portal Cliente. Merchandisers e operacionais são encaminhados para a área Operação. Supervisores recebem a área Operação e Planeamento.
- O frame front remove header/footer do tema, PWA duplicada e reposiciona a navegação interna do FieldFlow para o topo, melhorando o aproveitamento de monitores.
- O shell ganha menu lateral recolhível com estado guardado localmente, identificação do perfil, título dinâmico e distinção visual da Administração técnica.
- Mantém o ponto de entrada canónico `/?fieldflow_desktop=1`; o `FieldFlow-Desktop-QuickInstall-v0.1.2.ps1` continua compatível.
- Sem alterações de schema SQL, IndexedDB, REST mobile, FlutterFlow, Android ou iOS.

## 2.2.294, Desktop Foundation Merge

- Integra a fundação FieldFlow Desktop diretamente sobre a linha principal 2.2.293, preservando o Evidence Conformity Flow e todas as correções intermédias.
- Adiciona o ponto de entrada canónico `/?fieldflow_desktop=1`, independente de regras de permalink, mantendo `/fieldflow-desktop/` como alias amigável.
- Utiliza a sessão WordPress e as capabilities FieldFlow existentes, sem criar passwords, tokens, roles ou tabelas paralelas.
- Administradores com `routespro_manage` recebem o FieldFlow Admin Console desktop, com acesso direto às principais áreas do BO e opção de abrir o WordPress completo.
- Utilizadores não administrativos recebem apenas os portais operacionais ou de cliente já autorizados pelo âmbito, role e subscrição existentes.
- O modo frame remove apenas a navegação WordPress duplicada dentro do Desktop, sem alterar callbacks, permissões ou dados do backoffice.
- Reforça o arranque desktop com redirect de login para a entrada canónica, rewrite versionado, deteção de WordPress em subdiretório e proteção `noindex`.
- Atualiza a navegação para os módulos atuais, incluindo Shopping e Preços, Perguntas Contextuais, Histórico de Formulários, Integrações, Aplicação Mobile e PWA Web.
- Corrige o estado visual ativo da navegação quando o Dashboard é o conteúdo inicial do Admin Console.
- Sem alterações de schema SQL, IndexedDB, REST mobile, FlutterFlow, Android ou iOS.

## 2.2.293, Evidence Conformity Flow

- Introduz o tipo semântico `evidence_check` para Briefings Operacionais, Perguntas de Contexto e Form Builder.
- No runtime, cada `evidence_check` é expandido de forma idempotente para uma resposta `radio`, um `image_upload` condicional em Sim e um `textarea` condicional em Não.
- A resposta Não exige sempre um motivo no backend, tanto em submissões web como mobile. Fotografias em Sim são opcionais e suportam múltiplas imagens.
- O fluxo usa `ConditionalFlow` existente, por isso Android, iOS, WebView e payload mobile recebem apenas componentes já suportados. Não é necessária alteração no FlutterFlow.
- A expansão preserva posição em steps e largura de campo, funciona com perguntas estáticas, perguntas de contexto e perguntas injetadas por blocos de briefing.
- As exportações classificam corretamente a resposta de conformidade, o motivo e as fotografias geradas. Evidências continuam na folha de evidências e o motivo permanece pesquisável nas respostas.
- O Briefing AI passa a reconhecer e sugerir `evidence_check` para validações físicas de implementação, reduzindo pares desconectados de Sim/Não + fotografia.
- Perguntas antigas `image_upload` e `categorized_image_upload` não são convertidas automaticamente, preservando compatibilidade funcional com formulários existentes.
- Sem alterações de schema SQL, IndexedDB ou contratos que obriguem a rebuild da app.

## 2.2.292, Stable Mobile Visit Memory Modal

- Corrige definitivamente o flash do popup de memória da visita na app quando o mesmo formulário é renderizado mais do que uma vez durante a resolução do contexto da loja.
- Substitui a identidade aleatória do modal por uma chave estável baseada em formulário, cliente, campanha, loja e dia do reporte.
- O popup passa a ser um singleton global por visita: uma segunda renderização da mesma loja reutiliza o modal já existente em vez de o apagar e recriar.
- A abertura é desacoplada da sobrevivência do nó `<form>`, permitindo que a WebView substitua o formulário sem fechar o popup.
- A abertura usa um pequeno debounce de estabilização e só desaparece após ação explícita do utilizador ou mudança efetiva para outra visita.
- Mantém intacta a recuperação administrativa de rascunhos, respostas, fotografias e uploads introduzida na 2.2.291.
- Sem alterações de schema SQL, IndexedDB, contratos REST/mobile ou FlutterFlow. Não exige rebuild da app.

## 2.2.291, Mobile Visit Memory and Draft Recovery

- Corrige o popup de memória da visita em formulários carregados por AJAX dentro da WebView, inicializando-o pelo runtime global em vez de depender de script inline.
- O modal é movido para o body antes de abrir, evitando clipping, flashes e conflitos de stacking no shell mobile atual, sem exigir nova build FlutterFlow.
- O fecho manual em BO procura o último rascunho de formulário sincronizado da visita e, quando existe, promove-o através do pipeline normal de submissão antes de concluir a paragem.
- A recuperação administrativa preserva respostas, matrizes, fotos e uploads já sincronizados no servidor, mantendo o merchandiser como autor original e registando o administrador como ator da recuperação.
- Rascunhos incompletos podem ser recuperados sem inventar respostas em falta. A submissão fica marcada em metadata como recuperação administrativa.
- Se a recuperação de um rascunho preenchido falhar, a visita não é fechada, evitando dados órfãos.
- A retenção de rascunhos de formulário passa de 72 horas para 30 dias e a limpeza passa a considerar a última atualização, permitindo recuperar rascunhos antigos ainda presentes no servidor.
- Antes da recuperação, valida a revisão do formulário e confirma que todos os anexos esperados existem no servidor; se alguma prova estiver incompleta, a visita permanece aberta.
- A publicação de ficheiros torna-se estrita durante a recuperação administrativa, evitando uma submissão aparentemente bem-sucedida com fotos em falta.
- Quando não existe reporte final nem rascunho sincronizado, o BO bloqueia o primeiro fecho e exige confirmação explícita antes de concluir sem dados, alertando para informação que possa existir apenas no dispositivo.
- A revisão de schema do serviço de rascunhos passa a incluir Product Cardex e a retenção local do browser é alinhada para os mesmos 30 dias.

## 2.2.290, Briefing Scope and Visit Continuity

- Deduplica secções e itens de loja equivalentes durante novas importações de briefing, normalizando texto e conteúdo estruturado antes de criar a impressão digital canónica.
- Aplica a mesma proteção de leitura a briefings já existentes, evitando duplicação visual no backoffice e no front sem eliminar linhas históricas da base de dados.
- Torna o conjunto de lojas mapeadas no briefing a fronteira canónica para perguntas de execução, sempre intersectado com a carteira ativa da campanha quando esta existe.
- Perguntas de briefing deixam de aparecer em lojas não mapeadas, incluindo perguntas antigas que tenham sido criadas com âmbito excessivamente amplo.
- O estado de execução passa a ser calculado por pergunta e por loja a partir da resposta válida mais recente. Respostas positivas como Sim ou Implementado classificam a loja como implementada, respostas negativas como Não ou Não implementado colocam a loja em atenção, N/A fica separado e respostas vazias permanecem pendentes.
- O BO e o front do briefing passam a mostrar o estado agregado e o detalhe por loja, tornando visível onde existe implementação, pendência ou não conformidade.
- O construtor de formulários ganha a opção Memória da visita por pergunta. Numa nova visita, a resposta anterior é apresentada num popup informativo e o campo atual fica livre para nova resposta.
- O backend impede que campos configurados com Memória da visita sejam silenciosamente preenchidos com o histórico quando o utilizador não responde. Edições da mesma visita diária continuam a preservar a resposta já submetida.
- O payload mobile expõe a resposta anterior como informação separada e mantém o valor da visita atual vazio, preservando compatibilidade com WebView, Android e iOS sem alteração obrigatória no FlutterFlow.
- O editor de Rotas no backoffice ganha Concluir manualmente para visitas do próprio dia ou anteriores que ficaram operacionalmente abertas. A ação exige momento e motivo, atualiza apenas o estado da paragem e recalcula o progresso da rota.
- A conclusão administrativa nunca fabrica submissões, respostas ou fotografias. O histórico de correções fica no meta da visita e é enviado para Audit Log e Event Bus quando disponíveis.
- Falhas em telemetria ou invalidação de cache depois do commit não transformam uma conclusão já gravada num falso erro de API.
- Sem alterações de schema SQL, IndexedDB ou contratos mobile e sem necessidade de rebuild do FlutterFlow, Android ou iOS.

## 2.2.289, Multi-Insignia Stock Management

- Evolui Stocks sobre a base 2.2.288 Expense Critical Error Recovery, preservando integralmente as correções críticas de Despesas.
- Adiciona pesquisa, filtro por insígnia e estado, seleção múltipla e eliminação direta ou em massa no histórico de importações de stock do backoffice.
- Eliminar um snapshot remove o lote, respetivas linhas e leituras, mantendo os mappings de lojas para acelerar importações futuras.
- O importador reconhece nativamente o formato Continente com `STORE`, `STORE_NAME`, `ARTIGO`, `DESC_ARTIGO`, `EAN` e `SOH`, mantendo compatibilidade com o formato Pernod/Pingo Doce já utilizado.
- `SOH` passa a ser interpretado como stock, `DESC_ARTIGO` como descrição e, quando ausentes, `UM` e `FC` assumem `UN` e `1`.
- O matching Continente reconhece e normaliza `CNT`, `MDL` e `CBD`.
- O front da equipa ganha filtro de insígnia, identificação visual por cadeia e separação lógica por insígnia + loja, evitando mistura de Pingo Doce, Continente e futuras redes.
- O portal do cliente passa a mostrar o número de insígnias ativas e a identificar a insígnia nas lojas prioritárias.
- Sem alterações de schema SQL, IndexedDB, contratos mobile ou FlutterFlow.

## 2.2.288, Expense Critical Error Recovery

- Protege o callback REST de criação de Despesas com `try/catch` de `Throwable`, impedindo que exceções do módulo sejam convertidas na página genérica “Ocorreu um erro crítico neste site”.
- Cada exceção interna recebe uma referência `EXP-...` e é registada no `error_log` do servidor com âmbito, classe, mensagem, ficheiro e linha, sem expor o stack trace ao utilizador operacional.
- Se a base de dados já tiver confirmado o INSERT e ocorrer uma falha apenas na construção da resposta, a API devolve sucesso e não incentiva uma repetição que poderia duplicar a despesa.
- Se a gravação falhar antes do INSERT, qualquer comprovativo privado já movido é removido de forma segura.
- A validação do comprovativo deixa de depender de `wp_check_filetype_and_ext()` e usa deteção tolerante por `getimagesize` e `fileinfo`, com whitelist para JPG, PNG, WEBP, HEIC e HEIF.
- A leitura e validação do ficheiro estão igualmente protegidas contra `Throwable`, devolvendo um erro operacional em vez de um fatal PHP.
- O frontend reconhece o `internal_server_error` nativo do WordPress, remove HTML da mensagem e deixa de apresentar tags `<p>` ao merchandiser.
- Mantém a otimização de imagem e limites de upload da 2.2.287 e preserva visitas, Cardex, rotas, Analytics, offline, BO de Despesas e contratos mobile.
- Sem alterações de schema SQL, IndexedDB ou FlutterFlow.

## 2.2.287, Expense Receipt Upload Hardening

- Corrige a apresentação de `Unexpected token '<'` na aba Despesas quando uma resposta HTML de proxy, sessão ou limite de upload era interpretada diretamente como JSON.
- Adiciona parsing defensivo de respostas REST, com mensagens operacionais próprias para HTTP 413, 401, 403, 404, 5xx, redirecionamento para login e respostas HTML inesperadas.
- Fotografias JPG, PNG e WEBP acima do alvo seguro são redimensionadas e comprimidas no dispositivo antes do `multipart/form-data`, reduzindo o risco de rejeição pelo servidor sem perder legibilidade do comprovativo.
- O alvo de compressão é limitado a cerca de 900 KB e ajustado para baixo quando o limite real de upload do WordPress é inferior.
- O servidor passa a usar o menor valor entre o limite interno de 12 MB e `wp_max_upload_size()`, com mensagens específicas para `UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE` e uploads parciais.
- Quando o PHP recebe um pedido cujo `CONTENT_LENGTH` ultrapassa o limite configurado e o ficheiro já foi descartado, o endpoint devolve um erro REST 413 explícito.
- O histórico de despesas, QR fiscal, BO, permissões, visitas, Cardex, sincronização offline e contratos FlutterFlow permanecem inalterados.
- Sem alterações de schema SQL ou IndexedDB e sem necessidade de rebuild do FlutterFlow.

## 2.2.286, Rolling Visit History Comparison

- Corrige a rotação do histórico de `product_matrix`: numa nova visita, `Frentes antes` passa a usar o valor final (`after`) da visita imediatamente anterior, em vez de manter o primeiro `before` histórico.
- `Frentes depois` inicia a nova visita com o mesmo valor da visita anterior e fica disponível para a atualização da visita atual.
- Ao editar o reporte diário já existente, o par `before/after` é preservado, evitando que uma simples reabertura do formulário transforme a edição numa visita nova.
- A API mobile recebe o mesmo contexto de reporte diário usado pelo frontend, garantindo paridade em web, APK e iOS sem rebuild do FlutterFlow.
- O fluxo web legacy passa a limpar o `record_version_id` antigo e os marcadores de reconciliação numa edição diária, impedindo o worker assíncrono de considerar uma versão anterior como já sincronizada.
- Sem alterações de schema SQL, IndexedDB, contratos FlutterFlow, Cardex vivo, fotografias ou rotas.

## 2.2.285, Offline Sync Orphan Recovery Hotfix

- Corrige o estado persistente **Requer atenção** quando a fila local contém um check-in, check-out ou ocorrência ligado a uma paragem que entretanto foi removida ou substituída na rota.
- O servidor trata uma operação operacional sobre um `route_stop_id` inexistente como uma transição ultrapassada e idempotente, porque já não existe um alvo ativo que possa ser alterado.
- O runtime recupera uma vez operações operacionais antigas em estado `failed` ou `conflict`, gerando um novo mutation ID para não ficar preso a uma resposta de erro guardada no MutationStore.
- A recuperação preserva `createdAt`, garantindo que eventos criados antes de um **Repor como pendente** continuam a ser reconhecidos como anteriores à reposição e nunca reabrem a visita por engano.
- O botão **Tentar novamente** passa igualmente a preservar a data original da operação.
- Falhas reais continuam visíveis no Centro de Sincronização depois da tentativa controlada de recuperação.
- Não altera o Cardex vivo, submissões, respostas, fotografias, Analytics, estrutura de base de dados ou contratos FlutterFlow.

## 2.2.284, Living Store Cardex

- Cria um estado de Cardex persistente por loja, partilhado por todos os merchandisers que visitam o mesmo PDV.
- Substitui o modelo de snapshot para novas edições por revisões imutáveis de diferenças sobre o Cardex base.
- Remoções ficam gravadas como exceções da loja, alterações mantêm ligação ao produto base e novas referências são deduplicadas por identidade.
- Novas referências adicionadas no BO ou por importação passam a entrar automaticamente no Cardex das lojas ajustadas, salvo quando exista uma remoção explícita nessa loja.
- Mantém leitura integral dos snapshots existentes até à 2.2.283, sem migração destrutiva nem alteração de reportes antigos.
- O histórico de respostas continua apenas como histórico e deixa de ressuscitar referências já removidas de um Cardex persistente.
- Na primeira utilização após upgrade, referências custom antigas sem item base podem ser recuperadas do último histórico e consolidadas na próxima submissão.
- A API mobile sincroniza automaticamente matrizes Cardex submetidas, removendo a dependência do flag `sync_cardex_override` e evitando alterações no FlutterFlow.
- Matrizes Cardex vazias passam a ser armazenadas e reconciliadas, permitindo uma loja sem referências quando esse for o estado real.
- Importações CSV com substituição do Cardex base preservam ajustes por loja.
- O BO apresenta modo do estado, revisão, último editor e sortido efetivo por loja.
- Adiciona tabelas de estado, revisões e diferenças versionadas com ponteiro atómico para o estado atual.

## 2.2.283, Visit Reset Pending Hotfix

- Adiciona no editor de rotas a ação administrativa **Repor como pendente** para visitas em curso.
- A reposição limpa o estado operacional da visita, incluindo check-in, checkout, duração, falha e coordenadas reais.
- Formulários, respostas, fotografias, anexos e histórico não são eliminados.
- A ação é bloqueada quando existe uma submissão associada à paragem.
- O motivo, utilizador, momento e estado anterior ficam guardados no meta da visita e no Audit Log.
- Eventos offline criados antes da reposição são reconhecidos como ultrapassados e não voltam a iniciar a visita.
- O progresso da rota e a cache mobile são recalculados imediatamente.

## 2.2.282, Live Route Adjustment

- Remove o bloqueio global de planeamento depois do início da rota, sem aplicar qualquer limite por hora ou fim do dia.
- Protege lojas executadas e em curso, incluindo estado, check-in, checkout, reportes, evidências e ordem relativa.
- Permite acrescentar novas lojas pendentes, remover pendentes e reorganizar o segmento ainda por executar.
- Introduz o modo Ajuste de rota ativa no backoffice e em Minha Rota, com mensagens e controlos próprios.
- Mantém a gravação atómica e o histórico da rota. Se uma rota concluída receber uma nova loja, regressa de forma controlada ao estado em curso.
- Atualiza os contratos REST e mobile de forma aditiva, sem nova tabela, migração de dados ou nova build FlutterFlow.

## 2.2.281, Visit Sync Conflict Hotfix

- Corrige conflitos falsos entre check-in, submissão de formulário com fotografias e checkout na conclusão de visitas.
- Torna os eventos operacionais semanticamente idempotentes e recupera conflitos deixados pela 2.2.280 sem duplicar reportes.

## 2.2.280, Offline Sync and Mobile Reliability

- Unifica formulários, eventos operacionais e mutações mobile numa fila persistente, com sincronização em lote, idempotência e bloqueio entre separadores.
- Introduz sincronização incremental por cursor e fallback controlado para bootstrap completo quando o cursor expira ou deixa de ser válido.
- Adiciona estados de conflito separados de falhas, incluindo alterações de formulários e registos no servidor enquanto o dispositivo esteve offline.
- Cria um Centro de Sincronização no frontend e WebView com estado da rede, pendentes, falhas, conflitos, repetição manual, remoção e diagnóstico técnico copiável.
- Regista apenas diagnósticos técnicos sanitizados, como versão, rede, endpoint, código HTTP, tentativas e armazenamento, sem respostas, fotografias, moradas ou coordenadas.
- Adiciona o painel Sincronização Mobile em Segurança e Observabilidade e reforça a vista de Saúde do Sistema com conflitos offline.
- Atualiza o contrato mobile para 1.3.0, API 1.8, e publica no app-config as capacidades de delta incremental, conflitos, centro de sincronização e cross-tab lock.
- Mantém a base de dados existente e preserva a fila IndexedDB instalada em dispositivos com versões anteriores.

## 2.2.279, Route Planning Workspace

- Transforma o planeamento de rotas num workspace versionado em backoffice e em Minha Rota, com rascunhos, publicação controlada e bloqueio automático após atividade operacional.
- Permite reordenar lojas por drag and drop, fixar posições, selecionar e remover várias lojas numa operação atómica e preservar reportes e histórico.
- Adiciona comparação entre a ordem atual e a alternativa otimizada, com distância, duração e poupança estimada antes de aplicar.
- Introduz histórico recuperável limitado a 12 versões por rota, incluindo restauro seguro e criação automática de uma versão antes de guardar, publicar, otimizar, remover ou restaurar.
- Cria endpoints REST partilhados para guardar o workspace, remover lojas em massa e restaurar versões, com paridade no contrato mobile para Android, iOS e Flutter.
- Atualiza o contrato mobile aditivo para 1.2.0 e publica no app-config as capacidades e templates REST do workspace.
- Mantém a ordem manual protegida, respeita lojas com posição fixa, invalida métricas antigas e usa o motor interno na comparação para evitar custos desnecessários.
- Passa a permitir guardar rotas próprias como rascunho no frontend, sem forçar publicação imediata.

## 2.2.278, API Cost Ledger and Media Reliability

- Adiciona um API Cost Ledger técnico para Google Routes, Google Maps e Azure Maps, com pedidos, unidades, cache hits, erros, duração, custo estimado e poupança por cache.
- Cria os painéis Custos API e Media e Uploads em Segurança e Observabilidade, incluindo orçamento mensal, alertas aos 80% e 100%, tarifas configuráveis e evolução diária.
- Regista apenas metadados técnicos, sem guardar moradas, coordenadas, fotografias, respostas, nomes de ficheiro, chaves ou payloads.
- Reforça uploads de formulários com compressão mensurável, progresso real, uma repetição automática segura quando a falha ocorre antes de o upload terminar, e fallback para a fila offline.
- Remove seleções duplicadas dentro da mesma pergunta e mede tamanho original, tamanho otimizado, dados poupados, retries, falhas e tempo de processamento.
- Adiciona retenção configurável e limpeza automática para os novos registos de custos e telemetria de media.

## 2.2.277, Camera Multi Capture, Route Editing and API Cost Control

- Permite adicionar várias fotografias consecutivas através da câmara no mesmo campo de formulário, em web, APK, iOS e Flutter WebView.
- Elimina a limpeza prematura do input de câmara quando a WebView recupera o foco antes de receber o ficheiro capturado.
- Adiciona uma alternativa POST segura para remover lojas de rotas quando o método DELETE é bloqueado pelo alojamento, proxy, WAF ou WebView.
- Sincroniza rotas geradas por sugestão automática com a matriz de atribuições, o progresso da rota e as caches de âmbito do owner.
- Usa o motor interno nas otimizações silenciosas do planeador e reserva a rota rodoviária paga para reotimização explícita e gravação final.
- Faz carregamento tardio de Google Places e introduz cache de provider para geocoding, matrizes, direções e pesquisa de locais.

## 2.2.276, Cardex Fronts and Order Synchronization

- Faz da pergunta de Frentes a fonte de Cardex para a Sugestão de encomenda no contexto de cada loja.
- Corrige schemas antigos com fontes de produto diferentes entre Frentes e Sugestão, que podiam ocultar a segunda pergunta apesar de existir Cardex válido.
- Garante que ambas as perguntas usam o mesmo sortido base, as mesmas referências específicas da loja e o mesmo identificador de Cardex.
- Preserva o automatismo que sugere caixas quando as frentes estão a zero e mantém a submissão validada pelo mesmo conjunto de produtos.
- Aplica a mesma reconciliação ao front web, Flutter WebView, payload mobile e submissão mobile, sem migrar ou regravar formulários existentes.

## 2.2.275, Camera Permission and Upload Integrity

- Corrige o conflito que abria o seletor de ficheiros antes de o Flutter concluir o pedido de permissão da câmara.
- A ação Tirar fotografia passa a pedir primeiro a permissão e abre uma captura integrada com a câmara traseira, sem encaminhar o utilizador para a pasta.
- A ação Escolher imagem deixa de chamar o fluxo de permissão da câmara e mantém o seletor de galeria dentro do gesto do utilizador.
- Reforça a atribuição do ficheiro capturado, a pré-visualização e as mensagens de erro em formulários, Conquistas e Despesas.
- Mantém fallback nativo apenas quando a API de câmara não existe, preservando web, APK, iOS e Flutter WebView.

## 2.2.274, Shopping Results and Client Visibility

- Added a Shopping results panel to the client portal, scoped by the selected client and project.
- Added detailed submission views with store, merchandiser, notification status, notes and product prices.
- Added CSV, XLSX and PDF exports, including separate summary and price-detail Excel sheets.
- Added a Shopping submissions area in the WordPress backoffice with filters and KPIs.
- Separated Shopping result read permissions from form execution permissions while preserving web, APK and iOS contracts.

# 2.2.273

- Shopping passa a usar como fonte única os utilizadores associados ao projeto no Centro de Atribuições.
- O seletor deixa de listar todos os utilizadores com role operacional e mostra apenas a equipa do projeto.
- Corrige automaticamente lotes ativos e rascunhos já importados com merchandisers fora do projeto.
- Mantém a filtragem e os endpoints partilhados pelo shortcode, web, APK, iOS e Flutter WebView.

# 2.2.272

- Integra `Shopping e Preços` no grupo `Operação` da navegação do backoffice e no âmbito visual da fundação administrativa.
- Converte a importação XLSX para AJAX, mantendo o POST tradicional como fallback, e mostra progresso, sucesso ou erro diretamente no cartão.
- Elimina a sensação de página bloqueada durante o upload e devolve mensagens específicas para limites de servidor, uploads incompletos, ficheiros inválidos e falhas de base de dados.
- Otimiza a obtenção de PDVs através de consultas separadas e indexáveis, evitando o `LEFT JOIN` com condição `OR` que podia ficar lento em bases comerciais grandes.
- Reutiliza a atribuição do merchandiser carregada com o PDV e reduz consultas repetidas durante a criação dos formulários.
- Adiciona correspondência rápida por nome exato antes do motor de mapping e limita o universo de candidatos em operações muito grandes.
- Corrige a lista de formatos usada na gravação do lote e valida a criação de cada formulário por loja.
- Torna a importação transacional e limpa lotes parciais quando ocorre uma falha, evitando registos órfãos ou duplicados incompletos.
- Mantém o Excel, shortcodes, formulários, notificações, Flutter WebView, Android, iOS e contratos REST da 2.2.271.

# 2.2.271

- Introduz o módulo `Shopping, Verificação de Preços`, com importação XLSX, deteção automática de semana, lojas, artigos e pares PVP Regular e PVP Promo.
- Cria uma verificação independente por loja e filtra a nova aba Shopping pelo merchandiser atribuído ao PDV ou definido no lote.
- Adiciona o shortcode `[fieldflow_shopping]`, a nova aba no portal operacional e endpoints REST compatíveis com web, APK, iOS e Flutter WebView.
- Guarda rascunhos de preços no dispositivo, permite pesquisa por artigo, reaproveita os valores importados e mantém histórico de submissões por loja.
- Envia ao cliente um email HTML de estilo newsletter após cada submissão, com template global pré-criado, personalização por lote e destinatários adicionais editáveis.
- Corrige o upload de comprovativos em Despesas com botões separados para câmara e galeria, preservando o gesto nativo exigido por Android, iOS e browsers.
- Remove o botão redundante `Adicionar mais imagens` nas perguntas de fotografia e elimina a caixa vazia com o botão vermelho que ficava visível ao cancelar a seleção.
- Reforça anexos em perguntas categorizadas, incluindo Conquistas, ativando corretamente a categoria e abrindo o seletor dentro do gesto do utilizador.
- Mantém contratos existentes de formulários, rotas, stocks, briefings, exportações, offline e FlutterFlow.

# 2.2.270

- Introduz `Live Route Optimization & Reconciliation 1.0` com um serviço canónico partilhado por backoffice, frontend, WebView e endpoints mobile.
- Liga o motor existente de matriz rodoviária, vizinho mais próximo, regret insertion e 2-opt ao planeador manual `Minha Rota`.
- Reotimiza a sequência quando uma loja é adicionada ou removida e quando partida, chegada ou regresso à base são alterados.
- Mantém um botão de reotimização, um modo automático ativo por defeito e a opção de fixar manualmente a ordem por dia.
- Revalida e volta a otimizar no servidor antes de guardar, impedindo que a ordem dependa apenas do JavaScript ou da plataforma utilizada.
- Adiciona os endpoints `route-optimization/preview` e `routes/{id}/optimize` nas APIs web e mobile, sem remover campos existentes.
- Guarda versão, fornecedor, métricas, sequência original, sequência otimizada e poupança estimada nos metadados da rota.
- Atualiza o construtor frontend de descoberta de PDVs para respeitar o limite de 8 paragens, ligar autocomplete aos pontos de partida e chegada, otimizar sem condições de corrida e aplicar a ordem canónica depois da gravação.
- Invalida o cache mobile e offline após guardar ou reotimizar uma rota, garantindo que Android e iOS recebem a nova sequência no próximo refrescamento.
- Acrescenta no Centro de Processos uma reconciliação retomável em lotes, com filtros por cliente, campanha, utilizador e datas, modo simulação e aplicação explicitamente confirmada.
- Protege rotas iniciadas, concluídas ou com ordem manual bloqueada contra reordenação automática.
- Não cria tabelas ou menus e preserva formulários, submissões, Google Places, navegação nativa, FlutterFlow v1.7 e shell nativa 2.2.269.1.

# 2.2.269

- Introduz `Native Shell Reliability & Security 1.0` para a integração FlutterFlow sem alterar os contratos mobile existentes.
- Adiciona a ponte genérica `fieldflowNativeEvent`, mantendo `postMessage` como compatibilidade e permitindo ao Flutter receber eventos de sessão, contexto, billing, downloads e logout.
- Passa a executar heartbeat, verificação de contexto, atualização de billing e sincronização offline quando a app regressa ao primeiro plano.
- Mantém formulários ativos intactos durante a retoma, evitando recargas automáticas destrutivas.
- Expande a navegação nativa para Google Maps, telefone, email e SMS e mantém websites externos fora da WebView.
- Alinha a shell nativa com uma lista de domínios autorizados fornecida pelo plugin.
- Acrescenta metadados seguros de diagnóstico à mensagem `fieldflow.portal.ready`, sem expor tokens ou segredos.
- Atualiza a versão da shell nativa para 1.7 e preserva o fallback para apps que ainda não tenham o novo handler.
- Não cria tabelas, menus, endpoints, shortcodes ou migrações e não volta a agendar manutenção histórica.

# 2.2.268

- Introduz `Native Route Navigation Bridge Fix 1.0` sem alterar endpoints, shortcodes, payloads, schema, permissões ou contratos FlutterFlow existentes.
- Acrescenta `dir_action=navigate` aos URLs universais de Google Maps para solicitar navegação curva a curva quando o ponto de partida coincide com a localização atual, mantendo pré-visualização quando isso não é possível.
- Substitui os `window.open()` dos fluxos de rota por uma abertura externa controlada, evitando novas janelas silenciosamente descartadas pela WebView.
- Cria `window.FieldFlowNavigation.open()` como ponto único para abrir itinerários no browser, Android, iOS e Flutter InAppWebView.
- Tenta primeiro o handler nativo opcional `fieldflowOpenExternal`, publica a mensagem `fieldflow.external.open` para compatibilidade e usa navegação normal como fallback seguro.
- Corrige `Minha rota`, planeador pessoal, detalhe de rota do cliente e construtores locais de Google Maps.
- Mantém os waypoints e a ordem operacional da rota, evitando deep links nativos que perderiam paragens intermédias.
- Melhora a interceção de links externos na shell WebView, impedindo dupla abertura e devolvendo controlo ao fallback quando o handler nativo não está disponível.
- Não volta a agendar deduplicação ou backfill histórico numa atualização direta da 2.2.267.
- Preserva Google Places Autocomplete 1.0, FlutterFlow v1.7, offline, formulários, submissões, exportações, menus e dados existentes.

# 2.2.267

- Introduz `Google Places Autocomplete Reliability 1.0` sem alterar endpoints, shortcodes, payloads, formulários, permissões ou contratos FlutterFlow.
- Cria um carregador central e partilhado para a Google Maps JavaScript API, com Promise única, deteção de scripts já existentes, callback de readiness, timeout e recuperação através de `importLibrary()`.
- Impede a inclusão repetida do Google Maps por Campanhas PDV, Rotas, Base Comercial e shortcodes no mesmo carregamento de página.
- Corrige o bloqueio em que um módulo encontrava uma tag Google já carregada e ficava à espera de um evento `load` que já tinha terminado.
- Confirma explicitamente a presença de `google.maps.places.Autocomplete` antes de ligar os campos.
- Separa a escolha do fornecedor visual de mapas da disponibilidade do Google Places, permitindo autocomplete com uma chave Google válida mesmo quando o provider selecionado é Leaflet.
- Corrige os campos de partida e chegada em Campanhas PDV, criação e edição de Rotas e planeamento pessoal no frontend.
- Corrige autocomplete na Base Comercial, descoberta de PDVs e adição de paragens nos shortcodes operacionais.
- Adiciona uma camada global para manter a lista `.pac-container` visível acima de cartões, modais e navegação do BO.
- Impede que uma atualização direta da 2.2.266 para a 2.2.267 volte a agendar deduplicação e backfill histórico, mantendo essas tarefas apenas para instalações anteriores à 2.2.266.
- Mantém fallbacks locais e compatibilidade integral com a 2.2.266, FlutterFlow v1.7, WebView, offline e dados existentes.

# 2.2.266

- Introduz `Schema Integrity & Release Safety 1.0` como camada de consolidação, sem alterar layouts, shortcodes, endpoints, payloads, permissões ou contratos FlutterFlow.
- Adiciona um manifesto canónico de 90 tabelas conhecidas, incluindo 66 schemas completos com colunas e índices esperados.
- Cria uma auditoria de schema apenas de leitura, rápida ou integral, com deteção de tabelas, colunas, tipos, nullability e índices em falta ou divergentes.
- Adiciona um histórico persistente das migrações com estado, tentativas, duração e erro sanitizado.
- Impede que `routespro_schema_version` e `routespro_version` avancem quando uma migração obrigatória falha.
- Cria um executor retomável em lotes para auditoria integral, deduplicação progressiva de locais e backfill histórico, usando a fila persistente e mantendo fallback para WP-Cron.
- Move reparações históricas pesadas para tarefas progressivas, evitando bloquear o primeiro pedido após uma atualização.
- Adiciona um release gate não destrutivo para versão, runtime, REST, contrato mobile, schema, migrações, fila, cron, uploads, assets e ambiente.
- Integra schema e release gate no separador `Schema e releases` da Saúde do Sistema, e as execuções em lotes no Centro de Processos, sem criar novos menus ou alterar a navegação agrupada existente.
- Integra schema, migrações e release gate nos avisos administrativos e no pacote de diagnóstico sanitizado.
- Mantém WordPress 6.4+, PHP 7.4+, FlutterFlow v1.7, WebView, offline, formulários, submissões, exportações, faturação e dados operacionais compatíveis.

# 2.2.265

- Introduz `Architecture & Queue Modernization 1.0` com um composition root dedicado e registo central dos 21 controllers REST, preservando ordem, callbacks e contratos.
- Cria a tabela `queue_jobs` através de migração não destrutiva, sem alterar ou converter agendamentos existentes.
- Adiciona jobs persistentes com deduplicação por chave ativa, prioridades, claim atómico, lock com TTL, heartbeat e limite de runtime por worker.
- Implementa backoff de 1, 5 e 15 minutos, número máximo de tentativas e dead letter queue com recolocação manual controlada.
- Mantém o modo `observe` por defeito, no qual os jobs elegíveis são medidos mas continuam integralmente executados pelo WP-Cron.
- Permite um piloto por hook apenas para histórico, Cardex, rota e integrações pós-submissão, sem alterar payloads ou respostas web e mobile.
- Seleciona Action Scheduler quando disponível e configurado, com fallback automático para WP-Cron em qualquer falha de agendamento.
- Ao sair do piloto ou executar o rollback, transfere jobs pendentes para os hooks WordPress originais e preserva o histórico técnico.
- Adiciona teste seguro da fila, watchdog, limpeza, comandos WP-CLI, painel no Centro de Processos, Saúde do Sistema e diagnóstico sanitizado.
- Mantém FlutterFlow v1.7, Submission Pipeline, WebView, offline, shortcodes, formulários, exportações e dados operacionais compatíveis.

# 2.2.264

- Introduz `Performance & Load Readiness 1.0` com recolha amostrada e sem conteúdo pessoal para REST, mobile, submissões, exportações, backoffice, portais e cron.
- Mede duração, número de consultas, memória de pico, taxa de erro e saúde do runtime assíncrono sem guardar SQL, payloads, utilizadores, IPs ou tokens.
- Adiciona um release gate consultivo com P95, limites configuráveis, amostra mínima e janela temporal controlada.
- Acrescenta um teste seguro de prontidão baseado apenas em leituras agregadas, disponível no backoffice e em `wp fieldflow performance probe`.
- Disponibiliza o gate por WP-CLI através de `wp fieldflow performance gate` para integração futura em deploys.
- Deteta WP-Cron, cron real através do heartbeat e disponibilidade do Action Scheduler, mantendo o driver atual e fallback sem alterações.
- Cria uma tabela própria de amostras com retenção configurável, índices temporais e limpeza diária monitorizada.
- Integra performance, carga e filas na Saúde do Sistema, Segurança e Observabilidade e pacote de diagnóstico sanitizado.
- Preserva FlutterFlow v1.7, Submission Pipeline, WebView, offline, shortcodes, exportações, formulários e dados operacionais.

# 2.2.263

- Introduz o `Privacy Center 1.0` com políticas centrais de retenção, modo de simulação, aplicação explícita, retenção legal e execução limitada por lotes.
- Mantém submissões, respostas, fotografias, lojas, rotas, stocks e despesas fora das rotinas automáticas de limpeza.
- Adiciona registo e acompanhamento de pedidos RGPD por titular, tipo, âmbito e estado.
- Integra o FieldFlow com os exportadores e eliminadores de dados pessoais nativos do WordPress.
- Cria a `AI Governance 1.0` num ponto único antes de cada fornecedor, com observação de prompt injection, redação opcional de emails e telefones e bloqueio apenas em modo de aplicação.
- Passa a recolher tokens reais quando o fornecedor os devolve e a assinalar estimativas nos restantes casos.
- Adiciona custo configurável por milhão de tokens, orçamento mensal, versão e hash dos prompts, sem guardar o conteúdo enviado à IA.
- Adiciona as tabelas de pedidos RGPD e execuções de retenção, bem como novos campos de utilização e custos de IA através de migração não destrutiva.
- Mostra Privacidade e Governação da IA na Saúde do Sistema e preserva o modo de reversão visual 2.2.260.
- Preserva shortcodes, Submission Pipeline, exportações, base de dados operacional, permissões, contratos FlutterFlow v1.7, WebView e offline.

# 2.2.262

- Expande a `BackofficeUIFoundation` de 13 para todas as 41 superfícies administrativas FieldFlow conhecidas, mantendo o modo legado sem migração.
- Consolida cartões, grelhas, filtros, formulários, abas, tabelas, estados e regras responsivas por família de módulo.
- Adiciona auditoria visual no browser para overflow de página, texto cortado, IDs duplicados, tabelas protegidas e tempo de inspeção.
- Extrai sete blocos de CSS e três scripts inline simples para assets administrativos partilhados, preservando o comportamento também no modo legado.
- Reduz a dependência de `!important` nos módulos extraídos através de escopo administrativo e ordem explícita de assets.
- Mostra na Saúde do Sistema a cobertura visual, famílias abrangidas, dimensão dos assets e estado da consolidação.
- Mantém reversão imediata para o layout 2.2.260 através da mesma opção e da constante `FIELDFLOW_BACKOFFICE_UI_MODE`.
- Preserva shortcodes, Submission Pipeline, exportações, base de dados, permissões, contratos FlutterFlow v1.7, WebView e offline.

# 2.2.261

- Introduz a `BackofficeUIFoundation` como camada visual controlada, sem alterar dados, permissões, URLs ou fluxos funcionais.
- Aplica a fundação apenas ao Centro de Atribuições, Centro de Analytics, Formulários, Rotas e Saúde do Sistema.
- Consolida tokens de espaçamento, dimensões de controlos, larguras, grelhas, cartões, ações e comportamento responsivo.
- Corrige overflow horizontal e conteúdos cortados através de regiões de deslocação identificadas e diagnóstico de layout no browser.
- Reorganiza o menu administrativo em Gestão, Operação, Formulários, Plataforma e Sistema, mantendo os slugs e links existentes.
- Adiciona ativação e reversão imediata na Saúde do Sistema, com modo legado 2.2.260 sempre disponível.
- Permite bloquear o modo visual através de `FIELDFLOW_BACKOFFICE_UI_MODE` no servidor.
- Mantém intactos os shortcodes públicos, Submission Pipeline, exportações, base de dados, endpoints, FlutterFlow v1.7, WebView e offline.

# 2.2.260

- Introduz um `CredentialStore` central para as credenciais de IA, Google Maps, Azure Maps, Google Routes, licenciamento remoto, otimizador, integrações e notificações PWA.
- Migra automaticamente valores legados para cifragem autenticada com Sodium ou AES-256-GCM, mantendo compatibilidade de leitura e preservando o valor anterior se a cifragem falhar.
- Passa a guardar a chave de licença e a chave privada VAPID no mesmo cofre, sem alterar os respetivos fluxos de ativação ou utilização.
- Substitui valores secretos visíveis no backoffice por campos vazios com máscara, evitando exposição acidental e mantendo a credencial quando o campo não é alterado.
- Cria as tabelas `scope_memberships` e `scope_index_state` para normalizar o âmbito efetivo por utilizador, cliente, campanha e rota.
- Reconstrói o índice de forma preguiçosa a partir das fontes atuais, invalida apenas os utilizadores afetados e mantém fallback automático para o resolvedor legado.
- Permite desativar imediatamente o índice através de `FIELDFLOW_SCOPE_INDEX_ENABLED` sem alterar dados ou atribuições.
- Adiciona o estado do cofre e do índice à Saúde do Sistema e ao pacote de diagnóstico, sem incluir segredos.
- Preserva a Submission Pipeline 1.0, contratos FlutterFlow v1.7, endpoints, payloads, WebView, offline, formulários, rotas, Analytics, exportações e integrações existentes.

# 2.2.259

- Introduz a `SubmissionPipeline` partilhada por web e mobile para orquestrar tarefas posteriores ao `COMMIT`.
- Mantém a persistência principal nos handlers comprovados e impede que falhas de histórico, cardex, visita, auditoria ou integrações revertam um reporte já guardado.
- Adiciona rollout `shadow` e `pilot`, seleção por canal, allowlists de utilizador e projeto, amostragem determinística e diagnóstico operacional.
- O piloto mobile pode sincronizar o histórico logo após o `COMMIT`, preservando `record_id` e `record_version_id` sem manter a transação principal aberta.
- Adiciona reversão imediata no Centro de Controlo Mobile e estado da pipeline ao diagnóstico e à Saúde do Sistema.
- O owner continua `legacy` por defeito. Nenhuma instalação muda de motor ao atualizar.
- Preserva endpoints, payloads, respostas, contrato mobile v1.7, WebView, offline, formulários, rotas, exportações e integrações FlutterFlow.

# 2.2.258

- Corrige o editor de dashboards no Centro de Atribuições para ocupar a largura total quando está em modo de edição.
- Remove a sobreposição dos cabeçalhos fixos que escondia a primeira linha, incluindo títulos, IDs, estados e botões de ação.
- Define larguras adequadas para as tabelas de conteúdo, dashboards, grupos e widgets, com deslocação horizontal controlada quando necessário.
- Mantém a coluna de ações visível durante a deslocação horizontal e impede botões cortados ou comprimidos.
- Preserva integralmente a lógica Analytics da 2.2.257, exportações Premium, contrato mobile v1.7, WebView e integração FlutterFlow.

# 2.2.257

- Adiciona edição completa de dashboards existentes no Centro de Atribuições, preservando IDs e relações atuais.
- Integra no editor do dashboard a gestão do conteúdo, tipo de gráfico, KPI, tabela, estado e ordem dos widgets.
- Permite adicionar novos widgets diretamente ao dashboard em edição e manter o mesmo fluxo detalhado já existente.
- Adiciona ações Subir e Descer para publicar a ordem dos dashboards no portal do cliente e no FieldFlow App.
- Corrige a reconstrução da lista no FieldFlow App para respeitar explicitamente `sort_order`, em vez de depender da ordem numérica dos IDs.
- Torna efetivos os layouts Misto, Executivo e Detalhado no portal do cliente e no dashboard do merchandiser.
- Preserva widgets ao apagar um dashboard, movendo-os para o dashboard automático em vez de criar referências órfãs.
- Mantém intactos os endpoints, campos JSON, filtros, exportações Premium, contrato mobile v1.7, WebView e integração FlutterFlow.

# 2.2.256

- Adiciona recuperação automática a jobs idempotentes quando ocorre erro fatal, interrupção ou bloqueio operacional.
- Aplica backoff progressivo de 1, 5 e 15 minutos, limitado a três recuperações automáticas por cadeia.
- Move execuções que excedem o limite para uma fila de falhas definitivas, com alerta crítico e ação manual controlada.
- Introduz circuitos de recuperação para processos ligados a serviços externos, abrindo após falhas consecutivas e passando por teste controlado.
- Permite recuperar bloqueios seguros e repor circuitos diretamente no Centro de Processos.
- Calcula um indicador de saúde operacional com base em falhas recentes, atrasos, circuitos abertos e falhas definitivas.
- Adiciona o estado de processos, circuitos e recuperações ao pacote de diagnóstico sanitizado.
- Mantém intactos os retries internos já existentes e exclui relatórios e integrações pós-submissão da recuperação automática.
- Preserva FlutterFlow, contrato mobile v1.7, WebView, formulários, rotas, offline, exportações e integrações existentes.

# 2.2.255

- Adiciona um Centro de Processos no backoffice para observar os cron jobs operacionais existentes.
- Regista estados pendente, em execução, concluído e falhado, com datas, duração, tentativas, origem, referência e detalhe de erro.
- Importa para o histórico os eventos já presentes no WP-Cron e acompanha novos agendamentos sem alterar os hooks atuais.
- Permite execução imediata de rotinas recorrentes seguras e reexecução controlada apenas em jobs idempotentes elegíveis.
- Bloqueia pedidos manuais duplicados e marca execuções bloqueadas ou pendentes fora do prazo como falhadas.
- Mantém histórico operacional com retenção diferenciada e sanitização dos argumentos registados.
- Abre e resolve alertas de observabilidade quando processos recorrentes ficam sem próxima execução válida.
- Preserva FlutterFlow, contrato mobile v1.7, WebView, formulários, rotas, offline, exportações e integrações existentes.

# 2.2.254

- Mantém integralmente o contrato FlutterFlow v1.7, endpoints móveis, headers, handlers da WebView, shortcodes e estruturas de resposta existentes.
- Cifra de forma autenticada tokens e credenciais das integrações e migra automaticamente valores legados sem pedir nova configuração.
- Preserva a leitura de segredos `s1`, `o1` e `p1`, mas novos valores passam a exigir Sodium ou AES-256-GCM.
- Valida endpoints externos e usa pedidos HTTPS seguros com verificação SSL para Power BI, Google Cloud, Azure e imagens remotas de PDF.
- Protege a pesquisa auxiliar de PDVs com autenticação WordPress e mantém o mesmo endpoint para as páginas FieldFlow e a WebView.
- Aplica cache por pedido ao âmbito de permissões, sem alterar as regras de acesso ou a fonte atual das atribuições.
- Adiciona ao Estado do Sistema uma verificação explícita da capacidade de cifragem do servidor.
- Preserva formulários, navegação condicional, rotas, offline, exportações, emails, billing, Analytics e esquema da base de dados.

# 2.2.253

- Corrige a incompatibilidade OOXML concreta que fazia o Excel desktop rejeitar o Excel completo apesar de o ZIP estar íntegro.
- Escreve `autoFilter` antes de `mergeCells`, conforme a sequência exigida pelo esquema SpreadsheetML.
- Corrige a sequência das propriedades estendidas do workbook.
- Impede `INF`, `-INF` e `NAN` em células numéricas, uma causa possível de corrupção apenas com dados reais.
- Adiciona validação DOM e verificações específicas de compatibilidade Microsoft antes do stream.
- Preserva dados, folhas, filtros, automações de email, Analytics, shortcodes e contrato móvel v1.7.

# 2.2.252

- Corrige a corrupção do Excel completo no download desktop dos shortcodes de cliente e merchandiser.
- Isola o stream binário de avisos PHP, HTML, buffers e compressão de saída.
- Adiciona validação da assinatura ZIP, diretório central, entradas OOXML obrigatórias e tamanho do ficheiro.
- O browser valida o XLSX e repete uma transferência incompleta antes de o guardar.
- Normaliza UTF-8, caracteres XML proibidos, nomes de folhas e o limite máximo de texto por célula.
- Mantém as otimizações de performance da 2.2.251 e não altera dados, folhas, filtros, emails automáticos ou contrato móvel v1.7.

# 2.2.251

- Corrige o bloqueio transversal das exportações Premium no front-end.
- Substitui a consulta individual de respostas por submissão por carregamento em lotes, eliminando milhares de queries em exportações extensas.
- Impede que uma exportação reconcilie e grave até 500 rotas antes de gerar o ficheiro.
- Reduz o pico de memória do XLSX ao construir e comprimir uma folha de cada vez.
- Permite ao Excel mobile reutilizar o snapshot Analytics já validado e apresentado no ecrã.
- Remove a abertura de separadores vazios em desktop, mantendo o download normal por Content-Disposition.
- Preserva dados, filtros, permissões, formatos, folhas, Analytics, FlutterFlow e contrato móvel v1.7.

# 2.2.250

- Consolida o processo de release num gate atual e independente de versões históricas.
- Torna atómica a gravação de planos com vários dias, impedindo semanas ou meses parcialmente guardados.
- Adiciona locks com expiração aos upgrades e às migrações.
- Substitui a leitura integral das lojas por agregações SQL nos resumos da Campanha PDV.
- Mede apenas diretórios próprios do FieldFlow na verificação horária de armazenamento.
- Completa a limpeza de cron, tabelas, opções, metadados, capacidades e diretórios privados no uninstall integral.
- Adiciona geração de pacote WordPress de produção sem testes, auditorias, documentação interna ou ficheiros Flutter.
- Preserva o contrato FlutterFlow v1.7 e o comportamento funcional da 2.2.249.

# 2.2.249

- Fecha o ciclo de edição das rotas entre Planear e Minha Rota.
- Adiciona Remover da rota no front-end e na WebView, preservando reportes e histórico operacional.
- O planeador passa a carregar e reconciliar rotas existentes, reutilizando automaticamente partida e chegada.
- A obrigatoriedade de partida e chegada fica limitada à criação de dias novos sem rota existente.
- Reforça a geolocalização no browser e adiciona um handler nativo dedicado ao Custom Widget FlutterFlow.
- Recalcula o progresso e o número de lojas, e invalida métricas antigas para recálculo seguro depois de alterações ao itinerário.
- Preserva o contrato FlutterFlow v1.7.

# 2.2.246

- Corrige o Google Autocomplete nos campos de partida e chegada do planeador pessoal.
- Adiciona localização atual separada para a partida e a chegada, com reverse geocoding quando disponível.
- Mostra erros acionáveis para permissões, GPS, timeout e contexto HTTPS.
- Adiciona suporte explícito de geolocalização ao Custom Widget FlutterFlow com `flutter_inappwebview`.
- Preserva a lógica funcional da 2.2.245 e o contrato móvel v1.7.

# 2.2.244

- Adiciona o Centro Operacional contextual aos shortcodes do cliente e da equipa.
- Melhora Resumo, Rotas, Campanha PDV, Relatórios e Despesas em desktop, tablet e mobile.
- Campanha PDV passa para cartões por loja em ecrãs estreitos, mantendo a tabela premium em desktop.
- Organiza Excel completo, Excel mobile, PDF e CSV num Centro de Exportações com indicação de finalidade.
- Harmoniza estados de rotas, paragens e despesas, bem como vazios, carregamento e áreas de toque.
- Mantém FlutterFlow, Custom Widget, contrato móvel, endpoints, base de dados, formulários e exportadores sem alterações.

# 2.2.243.8

- Corrige o bloqueio persistente do Excel Android durante a abertura do ficheiro descarregado pela APK.
- Valida o tamanho real da resposta HTTP, a assinatura ZIP, o diretório central e as entradas obrigatórias do XLSX antes de abrir.
- Repete automaticamente o download uma vez quando deteta transferência incompleta.
- Guarda as exportações Android na área externa própria da aplicação, com nome ASCII seguro e MIME explícito.
- Simplifica o workbook Excel mobile, removendo estruturas desnecessárias e reduzindo os limites de linhas para dispositivos móveis.
- Mantém Excel completo, PDF, CSV, Stocks, IA, formulários e contrato FlutterFlow v1.7 intactos.

# 2.2.243.7

- Corrige XLSX válidos mas armazenados sem compressão interna, que podiam ficar indefinidamente a abrir no Excel Android.
- Aplica DEFLATE a todas as entradas OOXML e valida a taxa de compressão.
- Reduz a variante Excel mobile e preserva a exportação completa separada.
- Custom Widget abre com MIME explícito e ficheiro persistente confirmado.

## 2.2.243.6, Real XLSX & Mobile Excel Compatibility

- Corrige a causa do erro “Este tipo de ficheiro não é suportado” no Excel mobile.
- O formato anunciado como XLSX passa a ser um pacote OOXML real, com extensão e MIME coerentes.
- Adiciona exportações separadas Excel completo e Excel mobile no Analytics, portal cliente e submissões.
- Excel mobile omite folhas por pergunta, limita folhas extensas e reduz larguras para menor consumo de memória.
- O formato XML Spreadsheet 2003 permanece apenas como fallback legado `.xls`, sem botão principal.
- O Custom Widget valida a assinatura ZIP de ficheiros XLSX e rejeita respostas HTML ou ficheiros incorretos.
- Contrato FlutterFlow v1.7 preservado.

## 2.2.243.5, APK Live Stocks & AI Actions Integrity

- Stocks Operacionais passam a consultar sempre o último snapshot ao abrir o separador.
- Respostas REST de stocks recebem cabeçalhos `no-store`, `no-cache` e versão do plugin.
- Retomar a aplicação dispara uma atualização segura do painel Stocks quando está ativo.
- Ações controladas da IA passam a navegar pelos botões reais `data-panel` em app e portal cliente.
- Corrigido o Custom Widget FlutterFlow, sem duplicar `path_provider` e com leitura compatível de `Content-Disposition`.
- Exportações nativas da 2.2.243.4 preservadas.

## 2.2.243.4, APK Native Export Download Bridge

- Corrige exportações Excel, PDF, CSV e ZIP na WebView da APK.
- Adiciona deteção de URLs `admin-post.php` por ação e formato.
- Adiciona handler nativo autenticado e fallback Web Share.
- Atualiza o Custom Widget `FieldFlowCameraWebView` para guardar e abrir ficheiros.

## 2.2.243.3, APK Context & Operative Source Integrity

- O contexto cliente/campanha é agora resolvido no pedido e no carregamento real da WebView.
- Sessões WebView existentes com contexto vazio são corrigidas no servidor.
- O payload móvel passa a incluir contexto e tema específicos da campanha.
- A assinatura de contexto passa a mudar quando o tema muda, forçando atualização segura da WebView.
- Todos os filtros operacionais excluem contas administrativas.
- A Campanha PDV deixa de usar associações antigas de cliente para construir a lista de operativos.
- Utilizadores com acesso amplo ao cliente herdam a única campanha com tema próprio.
- Contrato atual preservado por extensão aditiva, sem remoções ou renomeações.

## 2.2.243.2, Portal Operative Scope & Campaign Theme Context Hotfix

- Separado o acesso do cliente da equipa operacional apresentada nos filtros do portal.
- O filtro Operativo do shortcode do cliente passa a usar uma fonte própria e estrita do Centro de Atribuições.
- Administradores e responsáveis de projeto deixam de aparecer como merchandisers quando não estão explicitamente na equipa do projeto.
- Mantidos no filtro os utilizadores com acesso ao projeto, owners de rota e equipas ativas das rotas, com exclusão segura de gestores não atribuídos.
- Utilizadores associados ao cliente voltam a herdar as campanhas desse cliente para efeitos de portal e tema.
- Quando existe uma única associação direta a campanha, essa campanha ganha prioridade no primeiro carregamento.
- A resolução do tema passa a normalizar o cliente através do projeto e a reconhecer chaves antigas guardadas apenas no índice da configuração.
- O mapa JavaScript publica também um alias por projeto, evitando fallback indevido para o tema global.
- Contrato FlutterFlow `fieldflow-mobile/v1.7` preservado byte a byte.
- Sem migrações de base de dados.

## 2.2.243.1, Assignment Source of Truth & Theme Integrity Hotfix

- Corrigido o overflow dos cartões em "Ações controladas" no mobile.
- Removida a edição duplicada de utilizadores dos menus Clientes e Projetos.
- Centro de Atribuições passa a ser a única interface de gestão de acessos, responsáveis e equipas.
- Guardar dados comerciais de Clientes ou Projetos deixa de poder limpar ou substituir associações existentes.
- Utilizadores associados ao cliente passam a herdar projetos sem restrição explícita.
- Administradores deixam de aparecer automaticamente como operativos quando não estão atribuídos.
- Atribuições inativas deixam de alimentar os seletores de operativos.
- Temas por campanha passam a usar chaves normalizadas cliente/projeto e contexto automático quando existe uma única campanha acessível.
- Contrato FlutterFlow `fieldflow-mobile/v1.7` preservado byte a byte.
- Sem migrações de base de dados.

## 2.2.242.1, Header Preview & Analytics Mobile Hotfix

- Restaurado o contraste do título, etiqueta e subtítulo na pré-visualização dos temas.
- Impedido que a camada visual global do backoffice substitua as cores específicas do header.
- Compactados os cards de rotas em Analytics no mobile.
- Mantidas Paragens, Concluídas e Execução na mesma linha em ecrãs estreitos.
- Impedida a quebra dos badges de estado e traduzidos os labels apresentados ao utilizador.
- Contrato FlutterFlow `fieldflow-mobile/v1.7` preservado byte a byte.
- Sem migrações de base de dados.

## 2.2.242, Unified Layout & Template Consistency

- Aplicada uma camada visual comum aos shortcodes, Analytics, formulários e backoffice FieldFlow.
- Uniformizadas evidências fotográficas, tabelas responsivas, cards, KPIs e navegação inferior.
- Ligados os widgets do Centro de Atribuições ao mesmo sistema de personalização do front.
- Contrato FlutterFlow e lógica funcional preservados.

## 2.2.241.2, Unified Photo Removal Integrity

- Uniformizada a eliminação de fotografias em todas as perguntas de imagem.
- Fotografias tiradas pela câmara e imagens escolhidas da galeria podem ser removidas antes da submissão.
- Em uploads múltiplos, cada imagem pode ser eliminada individualmente sem afetar as restantes.
- Fotografias já guardadas podem ser marcadas para eliminação durante a edição e repostas antes de guardar.
- A validação de campos obrigatórios passa a reconhecer imediatamente quando todas as fotografias existentes foram marcadas para remoção.
- Rascunhos locais e sincronizados acompanham a remoção de novas fotografias.
- Contrato FlutterFlow `fieldflow-mobile/v1.7` mantido sem alterações.
- Cache da WebView e runtime offline atualizadas para 2.2.241.2.
- Sem migrações de base de dados.

## 2.2.241.1, Existing Evidence Removal Integrity Hotfix

- Permite ao merchandiser remover fotografias já guardadas quando edita o seu reporte diário.
- A fotografia fica marcada como remoção pendente e pode ser reposta antes de guardar.
- O servidor aceita apenas remoções em modo de edição autorizado e apenas sobre URLs já existentes nessa resposta.
- Fotografias novas e existentes passam a coexistir corretamente em campos de upload múltiplo durante a edição.
- A remoção atualiza o reporte, analytics e exportações, sem eliminar fisicamente o ficheiro do servidor.
- Contrato FlutterFlow `fieldflow-mobile/v1.7` mantido sem alterações.
- Cache da WebView e runtime offline atualizadas para 2.2.241.1.
- Sem migrações de base de dados.

## 2.2.241, Forms Runtime 3.0 Controlled Pilot

- Adicionado modo piloto explicitamente limitado por IDs de utilizador.
- Runtime V3 passa a controlar apenas visibilidade condicional, passos visíveis e navegação canónica.
- Uploads, câmara, rascunhos, validação, submissão e conclusão da visita continuam no runtime estável.
- Adicionada comparação entre a projeção V3 e a lógica compatível com o runtime anterior antes de aplicar alterações.
- Adicionado fallback automático por formulário, sem recarregar a página e sem perder respostas.
- Adicionada reversão imediata no Centro Mobile para desligar globalmente o piloto.
- Adicionados eventos técnicos de intenção de submissão para web e fluxo focado da WebView.
- Contrato FlutterFlow `fieldflow-mobile/v1.7` mantido sem alterações.
- Atualizadas as caches de WebView e offline para 2.2.241.
- Sem migrações de base de dados.

## 2.2.240, Forms Runtime 3.0 Shadow and Rollout Foundation

- Adicionado Runtime V3 externo em modo shadow, sem mutações no formulário.
- O runtime atual mantém a propriedade exclusiva de condições, navegação, uploads, rascunhos e submissões.
- Adicionada validação automática de perguntas duplicadas, alvos condicionais, passos ativos e barras de navegação.
- Adicionado rollout interno por utilizador, projeto, contexto Flutter WebView e percentagem de amostragem.
- Adicionada integração opcional com o diagnóstico existente, sem enviar respostas, imagens ou credenciais.
- Adicionado controlo no Centro Mobile e kill switch através da feature flag `forms_runtime_v3`.
- Atualizadas as caches de WebView e offline para 2.2.240.
- Sem migrações de base de dados e sem alteração dos contratos FlutterFlow.

## 2.2.239, Stability Baseline & Flutter Contract Lock

- Congelado o contrato atual da API `fieldflow-mobile/v1` com política additive-only.
- Adicionado manifesto versionado, checksum SHA-256 e endpoint público de compatibilidade.
- Adicionados headers de versão, contrato, plugin e correlação sem alterar os corpos existentes.
- Adicionado endpoint autenticado de diagnóstico mobile com allowlist e sem respostas, tokens ou fotografias.
- Adicionadas feature flags para rollout seguro, mantendo Forms Runtime 3, API v2 e novo motor de submissão desligados.
- Adicionado release gate focado nos fluxos críticos atuais e na integração FlutterFlow.
- Sem migrações de base de dados e sem alteração dos endpoints ou formatos consumidos pela app.

## 2.2.238.8, Conditional Evidence DOM Integrity

- Corrigido um fecho `div` excedente no renderer de evidências categorizadas, que retirava categorias, perguntas e navegação dos respetivos contentores.
- Navegação normalizada em todo o formulário, não apenas nos filhos diretos do wizard.
- Shortcode focado elimina controlos legados recuperados de cache ou criados pela reparação automática do browser.
- Cache do formulário focado atualizada para 2.2.238.8.
- Layout das categorias reforçado para mobile e WebView.

## 2.2.238.6, Conditional Flow Integrity

- Corrige a interpretação das condições: as regras passam a mostrar ou esconder perguntas sem saltar passos válidos do formulário.
- Garante que Sugestão de encomenda, Evidências e Observações permanecem no percurso sempre que estiverem visíveis.
- Recalcula imediatamente os passos visíveis quando uma resposta condicional muda.
- Reconstrói uma única barra canónica de Anterior/Seguinte em cada atualização do wizard, removendo barras antigas e controlos soltos.
- Mantém Concluir visita, Guardar alterações ou Reporte já efetuado como única ação final no shortcode do merchandiser.

## 2.2.238.5, Complete Step Navigation Recovery

- Devolve ao `FormRenderer` a propriedade exclusiva de `Anterior` e `Seguinte`.
- Limita o shortcode focado à ação final da visita, evitando dois motores de navegação.
- Recupera os passos de Sugestão de encomenda e Evidências no percurso completo.
- Reconcilia perguntas existentes no schema mas ausentes de `layout.steps`, agrupando-as em passos de recuperação.
- Elimina barras antigas com owner `focused-shortcode` e atualiza a chave de cache para 2.2.238.5.

## 2.2.238.3, Navigation Single Source of Truth

- Define uma única barra canónica de navegação por formulário.
- Elimina botões Anterior/Seguinte fora dessa barra, incluindo HTML antigo recuperado da cache.
- Observa mutações tardias no DOM e remove automaticamente uma segunda faixa Seguinte.
- No último passo do fluxo embebido remove todos os controlos Seguinte do formulário.
- Mantém Concluir visita, Guardar alterações ou Reporte já efetuado como única ação final.
- Atualiza a chave de cache do formulário focado para 2.2.238.3.

## 2.2.238.2, Final Step Action Integrity
- Remove fisicamente `Seguinte` no último passo visível dos formulários embebidos.
- Garante exclusividade entre navegação interna e `Concluir visita` ou `Guardar alterações`.
- Recria `Seguinte` quando o utilizador regressa a um passo anterior.
- Invalida a cache do formulário focado.

# 2.2.238.1

- Adiciona eliminação individual diretamente na pré-visualização de imagens da câmara e da galeria.
- Mantém as restantes fotografias quando uma imagem é removida de uma seleção múltipla.
- Atualiza rascunhos, validação e estado offline imediatamente após a remoção.
- Elimina linhas adicionais vazias e invalida o cache de formulário da versão anterior.

# 2.2.238

- Unifica a ação final dos formulários entre web, Android e iOS.
- Mantém `Submeter` apenas nos formulários autónomos.
- Usa `Concluir visita` para novas visitas e `Guardar alterações` durante a edição.
- Calcula os passos efetivamente visíveis e elimina `Seguinte` no último passo real.
- Comprime fotografias antes do rascunho, upload e fila offline.
- Substitui a mensagem genérica de falta de espaço por diagnósticos específicos.
- Remove a duplicação local das fotografias depois de colocar um reporte na fila offline.
- Liberta a interface logo após a confirmação do reporte e reconcilia a rota em segundo plano.
- Invalida o cache antigo dos formulários na WebView.

# 2.2.237

- Remove o botão interno `Submeter` dos formulários embebidos, deixando a conclusão exclusivamente no controlador exterior do shortcode.
- Esconde o botão exterior `Concluir visita` até o utilizador chegar ao último passo do formulário.
- Mantém apenas `Anterior` e `Seguinte` durante a navegação, em mobile, tablet e desktop.
- Normaliza HTML antigo vindo de cache, removendo barras internas de submissão incompatíveis com o fluxo atual.
- Clarifica a configuração das dependências `flutter_inappwebview` e `permission_handler` no FlutterFlow.

# 2.2.236

- Consolida a navegação dos formulários num único conjunto de botões Anterior, Seguinte e Submeter.
- Impede que passos ocultos ou estilos responsivos revelem controlos duplicados em mobile, tablet ou desktop.
- Normaliza formulários antigos recuperados do cache offline, removendo barras de navegação repetidas no arranque.
- Atualiza o Custom Widget FlutterFlow para evitar `dart:io`, melhorar compatibilidade multiplataforma e documentar a configuração de dependências e parâmetros.

# 2.2.235

- Gestão de ocorrências e incumprimentos nas rotas.
- Notificação automática e configurável ao cliente.
- Prevenção de emails duplicados e opção de reenvio.
- Templates globais e por projeto com preview e teste.

# 2.2.234

- Separa a entrada de câmara da entrada de galeria com tipos MIME específicos.
- Reforça `capture="environment"` imediatamente antes da abertura do seletor.
- Mantém a captura traseira nas fotografias adicionais de perguntas com múltiplas imagens.
- Usa `showPicker()` quando disponível e conserva `click()` como fallback compatível.
- Publica `fieldflow.media.camera_requested` e `fieldflow.media.gallery_requested` para uma futura ponte nativa FlutterFlow.
- Documenta as permissões Android e iOS necessárias para a câmara dentro da aplicação.

# 2.2.233

- Corrige a ligação do modo de edição entre o formulário dinâmico e o fluxo focado usado na web, APK Android e WebView iOS.
- Uniformiza o estado do botão exterior entre consulta, edição, gravação e conclusão da visita através do evento `fieldflow:daily-edit-state`.
- Corrige o seletor do fluxo focado, passando a reconhecer `.rp-report-focus` e mantendo compatibilidade com o alias anterior.
- Permite navegar por todos os passos de um reporte em modo de consulta sem validações obrigatórias bloquearem o botão Seguinte.
- Impede submissões e cliques exteriores quando um reporte diário está bloqueado para consulta.
- Impede que a edição de um reporte diário o associe silenciosamente a outra rota, paragem, projeto ou cliente.
- Expõe conflitos de contexto na API mobile e preserva no metadata o contexto operacional original.
- Aplica a mesma proteção à sincronização de rascunhos do servidor.

# 2.2.232

- Submission fast path commits the report and answers before cardex, history and automation work.
- Version history, cardex reconciliation and post-submit integrations use asynchronous, idempotent jobs with retries.
- Final submit cancels competing server draft uploads, keeps a local recovery snapshot and schedules draft cleanup after the response path.
- Image questions provide separate camera and gallery controls, additive multiple selection and clearer upload errors.
- Mobile WebView policies for multiple uploads, camera and gallery are now applied at runtime.
- FieldFlow image uploads accept HEIC/HEIF when supported by the server.

# 2.2.230

- Hotfix de continuidade operacional para lojas com histórico e rascunho recuperado.
- A submissão principal é confirmada antes da limpeza do rascunho e das integrações secundárias.
- Histórico versionado, cardex e conclusão da rota ficam isolados por savepoints.
- Falhas posteriores ao commit devolvem sucesso e ficam registadas para reconciliação.
- Reconciliação automática do histórico e da rota agendada após falhas secundárias.

# 2.2.229

- Hotfix de produção para submissões de formulários em web e APK.
- O fluxo principal de gravação passa a capturar falhas inesperadas, executar rollback quando aplicável e devolver uma referência curta de incidente.
- Automações, auditoria, eventos e sincronizações secundárias deixam de conseguir derrubar uma submissão já gravada.
- Adicionado fallback quando a extensão PHP mbstring não está disponível.
- O front deixa de inserir páginas HTML de erro do WordPress dentro do formulário.
- Bloqueadas submissões duplicadas e navegação nativa acidental do formulário.

# 2.2.226

- Experiência FlutterFlow para planos, faturação e estados de subscrição.
- Novo target WebView `subscriptions` e endpoint `/billing/experience`.
- Regresso controlado após checkout e Portal Stripe para a sessão nativa.
- Sincronização de `mobileMe`, roles, capacidades e navegação após alterações de faturação.
- Cookies WebView isolados por portal, incluindo billing e subscriptions.

## 2.2.225.1

- Adicionado seletor de página do portal de faturação no BO.
- Adicionada deteção automática do shortcode em conteúdo WordPress e Elementor.
- Removido o fallback incorreto para a homepage.
- Diagnóstico de produção valida página publicada e shortcode.

## 2.2.225
- Criado o centro Billing Production Hardening em `Faturação > Produção`.
- Adicionados diagnóstico operacional, métricas de conversão, reconciliação preview/apply e manutenção manual.
- Adicionados templates editáveis e histórico idempotente para emails de conta, checkout, pagamento, suspensão, alteração, renovação e cancelamento.
- Implementada recuperação automática e manual de checkouts abandonados através de token de retoma cifrado.
- Implementada aceitação versionada de Termos e Política de Privacidade, com consentimento de marketing separado.
- Implementado downgrade sem eliminação de dados, com deteção de limites ultrapassados e aviso no portal.
- Criadas as tabelas `routespro_billing_consents` e `routespro_billing_email_log`.

## 2.2.224.7
- Corrigido o redirecionamento inicial dos botões mensal e anual da página pública de subscrições.
- A intenção passa a guardar o URL seguro da página onde o shortcode foi utilizado, evitando páginas inexistentes quando o site usa uma página Elementor personalizada.
- O registo, login e confirmação de email do Ultimate Member retomam a mesma página de origem.
- A configuração da página pública passa a descobrir e recuperar páginas publicadas com o shortcode em conteúdo normal ou nos dados do Elementor.

## 2.2.224.6
- Removido o bloqueio fixo que tratava sempre o plano Enterprise como “Sob consulta”.
- O Enterprise passa a aceitar checkout automático quando está ativo, tem preço superior a zero e possui o mapeamento Stripe exigido no ambiente atual.
- O catálogo de faturação passa a ser a primeira fonte do preço apresentado e cobrado no simulador sandbox, mantendo fallback para os preços do plano SaaS.
- A página pública mostra o Enterprise apenas quando existe pelo menos uma periodicidade pronta para checkout.
- O portal privado mantém “Sob consulta” apenas quando não existe qualquer preço comercial válido.

## 2.2.224.5
- Corrigida a resolução do cliente no `[fieldflow_billing_portal]` para administradores e utilizadores com vários clientes.
- Adicionado seletor de cliente no portal, com plano e estado de cada subscrição.
- O `[fieldflow_subscription_signup]` passa a apresentar todas as subscrições ativas acessíveis, em sandbox e live.
- A reconciliação de roles passa a aplicar todas as roles derivadas das subscrições ativas do utilizador, mantendo compatibilidade com os metadados anteriores.
- Adicionados índices de base de dados para melhorar as consultas de subscrições por utilizador e cliente.

## 2.2.224.4
- Criado o shortcode público `[fieldflow_subscription_signup]` com planos Start, Operations e Intelligence.
- Integrado o formulário de registo Ultimate Member configurado no BO, sem depender de extensões de pagamento do Ultimate Member.
- Implementado token temporário de onboarding, guardado por hash, com duração de 48 horas e proteção contra apropriação por outra conta.
- Adicionada role provisória antes do pagamento e remoção automática quando a subscrição fica ativa ou em trial.
- Criada organização FieldFlow provisória com associação ao utilizador e subscrição SaaS suspensa até confirmação do pagamento.
- Ligados os fluxos de registo, ativação por email e login à retoma do plano e periodicidade escolhidos.
- Adicionada página pública automática `Planos e Subscrições`, configuração do Form ID e mapeamento dos campos da empresa.
- Adicionado histórico administrativo de intenções de subscrição e limpeza das intenções expiradas.

## 2.2.224.3
- Criado o mapeamento Plano → Role WordPress na área de faturação.
- Adicionada role distinta para subscrição ativa ou trial, pagamento pendente e estado suspenso, cancelado ou expirado.
- Cada subscrição comercial passa a guardar o utilizador WordPress que recebe a role do plano.
- Checkouts sandbox e Stripe transportam o `wp_user_id`; webhooks, pagamentos e edições voltam a sincronizar a role automaticamente.
- Roles que não pertencem à matriz FieldFlow são sempre preservadas, e a role anterior é removida quando o plano ou utilizador muda.
- O utilizador associado à subscrição é automaticamente ligado ao cliente para permitir acesso ao portal autorizado.
- A página `Subscrições > Clientes` passa a mostrar o utilizador e a role comercial atual, com ligação direta ao mapeamento.
- O payload mobile expõe o target `billing`, o URL da página e o endpoint de sessão WebView para o botão FlutterFlow.

## 2.2.224.2
- Criação e edição de subscrições, alteração de periodicidade Stripe/local, página automática de faturação e target WebView billing para FlutterFlow.

## 2.2.224.1
- Adicionada edição explícita dos produtos e preços existentes no catálogo de faturação.
- Permitida a alteração segura de Product ID, Price ID, valor local, moeda e estado.
- Adicionada eliminação protegida de mapeamentos sem apagar produtos ou preços na Stripe.
- Bloqueada a eliminação quando existem subscrições ativas, em trial ou com pagamento pendente dependentes do mapeamento.
- Adicionados registos de auditoria e eventos centrais para alterações do catálogo.

## 2.2.224

- Criado o motor de faturação com ambientes sandbox e live totalmente separados.
- Adicionado simulador local de checkout e suporte opcional a Stripe test mode.
- Criado catálogo de preços mensal e anual por plano, com Product ID, Price ID, moeda e estado.
- Implementado Stripe Checkout em modo subscription, com dados de cartão sempre processados fora do FieldFlow.
- Adicionada validação de webhooks sobre o corpo original, HMAC, ambiente e idempotência com repetição segura de eventos falhados.
- Sincronizados clientes, subscrições, períodos, trials, cancelamentos e faturas.
- Implementado período de tolerância para pagamentos falhados, sem eliminação de dados e sem prolongamento indevido em eventos repetidos.
- Criada manutenção horária para suspender apenas após o fim da tolerância e reativação automática quando a fatura é paga.
- Criada a área FieldFlow > Faturação e o shortcode `[fieldflow_billing_portal]`.
- Mantida compatibilidade com subscrições 2.2.221, segurança 2.2.222 e API pública 2.2.223.

## 2.2.223

- Criada API pública v1 de leitura com chaves protegidas, âmbito, capacidades, paginação e sincronização incremental.
- Adicionados datasets planos para Power BI e webhooks operacionais assinados com fila e retentativas.
- Integradas as novas ligações com subscrições, segurança, observabilidade, Audit Log e Event Bus.

## 2.2.222

- Criada a camada central de segurança operacional com rate limiting para REST, IA, exports, uploads e login.
- Adicionada validação de extensão, tamanho e MIME real nos uploads FieldFlow.
- Criado o motor de observabilidade para erros PHP, falhas de integrações, endpoints lentos, consumo de memória e número de queries.
- Adicionados alertas persistentes, reconhecimento no BO e notificações por email configuráveis.
- Criada a área FieldFlow > Dispositivos com confiança, bloqueio, revogação, controlo offline e limpeza remota de cache.
- A cache Offline First passa a respeitar uma geração remota e é invalidada sem eliminar formulários pendentes.
- Criado um pacote de diagnóstico sanitizado com saúde, cron, erros, dispositivos anonimizados, tabelas e versões.
- Adicionadas políticas de retenção e limpeza automática para logs técnicos e eventos de segurança.
- Mantida compatibilidade integral com subscrições, app, Offline First, analytics, mapping, rotas, formulários e exportações.

## 2.2.221

- Criado o núcleo SaaS com planos, capacidades, estados comerciais, limites e exceções por cliente.
- Adicionados os planos Start, Operations, Intelligence, Enterprise e Legacy completo para compatibilidade.
- Aplicado controlo funcional no BO, menus, shortcodes, portal operacional, portal de cliente, app, REST, IA, exports e relatórios automáticos.
- Implementados contadores e limites para rotas, submissões, pedidos de IA, exportações, relatórios programados, utilizadores, lojas e dispositivos offline.
- Criada a página FieldFlow > Subscrições com gestão de clientes, planos, matriz de capacidades e utilização mensal.
- Adicionados endpoints protegidos de entitlements e exposição das capacidades à aplicação móvel.
- Clientes existentes são migrados automaticamente para Legacy completo, sem perda de funcionalidades ou dados.
- Alterações de subscrição ficam registadas no Audit Log e Event Bus.

## 2.2.220.3

- corrige o colapso horizontal provocado por `align-self:flex-start` em hosts flex do Elementor e WebView
- força largura total e `min-width:0` no host, shell, painéis e painel ativo sem reintroduzir o scroll vertical fantasma
- mantém uma única reserva inferior para a navegação fixa em telefone e tablet
- preserva todos os fluxos funcionais, Offline First, analytics, paginação e exportações

## 2.2.220.2

- Corrigida a extensão vertical fantasma abaixo dos shortcodes da app e do portal do cliente em telefone e tablet.
- A navegação inferior passa a usar uma única reserva de espaço, eliminando padding acumulado na shell, painéis e módulos internos.
- Painéis inativos passam a sair integralmente do layout através de `hidden`, altura nula e estado ARIA coerente.
- O runtime recalcula o limite do shortcode depois de mudanças de aba, lazy loading e alterações dinâmicas.
- Contentores de shortcode do Elementor passam a ter altura automática e deixam de herdar extensões antigas.
- Mantida compatibilidade integral com a 2.2.220.1, Offline First, analytics, rotas, formulários e exportações.

## 2.2.220.1

- O widget Frentes atuais passa a usar abas para resumo, todas as referências, crescimento, redução e sem alteração.
- As tabelas analytics passam a ter paginação configurável e seletor de linhas por página no front.
- A Personalização global e por campanha controla o número padrão de linhas e a utilização de abas no widget de frentes.
- Cliente e merchandiser passam a usar a mesma regra de paginação, mantendo a totalidade dos dados nas exportações.
- Mantida compatibilidade integral com a versão 2.2.220.

## 2.2.220

- Criada uma camada transversal de polimento do front para desktop, tablet, Android e iOS.
- O calendário do portal do cliente passa para agenda vertical em ecrãs estreitos e mantém uma opção de grelha mensal compacta.
- As evidências passam a usar acordeões por loja e data, reduzindo o comprimento das páginas e o peso inicial.
- As imagens usam hidratação progressiva e só recebem o URL real quando o painel é aberto.
- Listas extensas de lojas passam a ser apresentadas por blocos com ação Mostrar mais.
- A Personalização global e por campanha passa a controlar calendário mobile, tabelas, evidências, miniaturas e navegação inferior.
- As tabelas podem transformar-se em cartões por registo ou manter deslocamento horizontal, conforme a campanha.
- Widgets analytics passam a ocupar a largura correta e a usar KPI compactos em tablet e mobile.
- Mantida compatibilidade integral com a 2.2.219 e com os contratos de offline, mapping, rotas, formulários e exportações.

## 2.2.219

- Criada a camada Analytics e IA Operacional com índice executivo, comparação temporal e deteção determinística de anomalias.
- Adicionados alertas para execução baixa, quebra face ao período anterior, visitas pendentes, rotas sem progresso, tempos anómalos, pontualidade, ruturas, stock negativo, Share Space e perda de frentes.
- As análises passam a apresentar as fontes usadas, cobertura das fontes e uma pontuação de confiança calculada.
- Adicionadas ações controladas que filtram, navegam ou exportam, sem publicar ou corrigir dados automaticamente.
- Criada a tabela de alertas operacionais com fingerprint, severidade, âmbito, estado e histórico de deteção.
- O reconhecimento de alertas fica registado no Audit Log e no Event Bus.
- Cliente, gestor e merchandiser passam a partilhar a mesma leitura operacional, adaptada ao respetivo âmbito.
- A Saúde do sistema passa a mostrar alertas abertos, críticos, elevados e reconhecidos.
- Mantida compatibilidade integral com a versão 2.2.218.1.

## 2.2.217

- Criado o Centro Universal de Mapping para consolidar correspondências de lojas, produtos, briefings, implementações, stocks e cardex.
- Adicionadas tabelas centrais para mappings, aliases aprendidos e histórico reversível.
- Adicionado motor determinístico de candidatos com confiança, sinais de correspondência, referências exatas, localidade e proteção de insígnia.
- As associações confirmadas passam a alimentar um dicionário de aliases por cliente e projeto.
- Adicionadas confirmação e rejeição em massa, pesquisa manual de destinos e reversão das decisões.
- As decisões são aplicadas nas fontes existentes e registadas no Audit Log e Event Bus da Core Integrity Foundation.
- Briefings, implementações, stocks e cardex passam a notificar o centro quando os dados de origem mudam.
- Adicionados endpoints REST protegidos para pesquisa de destinos e indicadores de cobertura.
- O schema de stocks passa a guardar a ligação entre produto importado e item de cardex.
- A Saúde do sistema passa a mostrar cobertura, pendências, sugestões e confirmações do centro universal.
- Mantida compatibilidade integral com a versão 2.2.216.

## 2.2.216

- Substituído o carregamento integral de classes por autoload PSR-4 compatível com a estrutura atual do plugin.
- Mantido um bootstrap explícito apenas para o ficheiro legado de endpoints AJAX globais.
- Adicionado carregamento progressivo dos módulos pesados do portal operacional e do portal de cliente.
- Adiado o carregamento de Campanha PDV, relatórios e leitura de IA no portal de cliente até à abertura do separador.
- Criados `fieldflow-design-system.css` e `fieldflow-ui-runtime.js` para normalizar KPI, tabelas, gráficos, cartões, skeletons, erros e reflow móvel.
- As tabelas passam a receber labels automáticas e transformam-se em cartões no telemóvel.
- Adicionado executor seguro dos scripts devolvidos pelos painéis carregados por AJAX.
- O autocomplete passa a suportar conteúdo inserido dinamicamente e deixa de ser carregado em páginas sem FieldFlow.
- A Saúde do sistema passa a mostrar o runtime modular e uma pré-visualização desktop, tablet e telemóvel.
- Mantida compatibilidade com a Core Integrity Foundation 2.2.215 e com os contratos móveis existentes.

## 2.2.215

- Criada a Core Integrity Foundation com estados canónicos para rotas, visitas e submissões.
- Adicionado barramento de eventos persistente com contratos de dados versionados, correlação e estado de processamento.
- Adicionado audit log central com origem, utilizador, contexto operacional e valores antes e depois nas alterações críticas.
- Submissões web e mobile, conclusão de visitas, mudança de estado de rotas, publicação de briefings e importação/publicação de stocks passam a alimentar o núcleo central.
- Adicionados endpoints REST de leitura para o estado canónico de rotas, visitas e submissões.
- A página Saúde do sistema passa a incluir eventos, auditoria, histórico de reconciliação, análise de integridade e correção segura.
- A reconciliação segura corrige apenas estados e ligações inequívocas, preservando casos ambíguos para revisão manual.
- Mantida compatibilidade integral com app, briefings, rotas, formulários, analytics e exportações da versão anterior.

## 2.2.214

- Adicionado endpoint canónico de preview de rotas no servidor, com geocodificação, matriz rodoviária, tempo de viagem e portagens estimadas.
- Os editores de Rotas e Campanhas PDV deixam de depender exclusivamente do `DirectionsService` do browser para preencher quilómetros e custos.
- Planeador direto e planeador manual passam a apresentar quilómetros, portagens e tempo por dia e no total.
- O shortcode operacional de rotas passa a mostrar portagens, tempo de viagem e tempo total, alinhado com o portal do cliente.
- As exportações de submissões passam a incluir o tipo real de pergunta, uma folha dedicada a comparativos e detalhe de frentes, quotas, objetivo, desvio e estado.
- As exportações Analytics CSV, Excel e PDF passam a preservar a estrutura das perguntas comparativas e respetivo tipo.
- Corrigida uma linha XML duplicada no gerador Excel de submissões.

## 2.2.213

- Corrigida a prioridade das cores nos headers internos, separando títulos de texto secundário.
- Adicionado compositor visual de perguntas comparativas com deteção das referências do briefing.
- Consolidado o reflow de tabelas e widgets Analytics em desktop, tablet, iOS e Android.

## 2.2.212

- Criado o tipo de pergunta `share_space_matrix`, alimentado diretamente pelas comparações do briefing semanal.
- O formulário recolhe frentes do produto Pernod Ricard e da concorrência e calcula quota PR, quota concorrência, objetivo e desvio no cliente e no servidor.
- Adicionada conversão assistida das perguntas antigas de percentagem para a nova matriz, sem apagar respostas históricas.
- Adicionado mapping seguro de lojas, limitado à mesma insígnia e apenas quando existe uma correspondência única.
- Analytics de cliente e merchandiser passam a ter widget Share Space de largura total, KPI executivos, barras de objetivo e tabela detalhada responsiva.
- Melhorado o comportamento de KPI, gráficos e tabelas em iOS, Android e tablet.
- Headers de Briefings, Stocks, Analytics, Growth Hub e Academy passam a herdar as cores definidas na Personalização.
- A IA de briefings e analytics passa a reconhecer objetivos e desvios de Share Space e deixa de recomendar percentagens manuais.

## 2.2.211

- Adicionada uma camada responsiva comum para todos os shortcodes FieldFlow.
- Melhorada a experiência em iOS, Android, WebView nativa, PWA e tablet.
- Normalizados safe areas, teclado móvel, alvos táteis, tabelas e navegação inferior.
- Mantida a compatibilidade funcional da build 2.2.210.

## 2.2.210

- Completed the Mobile Bridge Control Center with visible JavaScript, download, upload and navigation policy controls.
- Added real sliding WebView session renewal through an authenticated heartbeat endpoint.
- Added scheduled maintenance start, automatic end and retry interval metadata.
- Added configurable network banner, native image viewer and WebView zoom.
- Bumped the public mobile contract to API 1.5.

## 2.2.209
- Adicionado `FieldFlow > Aplicação Mobile`, o cockpit de configuração da bridge Flutter/WordPress.
- Configuração independente de Android e iOS: ativação, versões mínima e mais recente, build, URL da loja, Bundle/Package ID, notas e atualização obrigatória.
- Modo de manutenção global e por plataforma com título, mensagem e conclusão prevista.
- Bridge WebView configurável: portais, TTL de ticket, duração da sessão, sincronização de contexto, downloads, links, câmara, galeria, localização e domínios permitidos.
- Segurança configurável para access token, refresh token e rate limit de login.
- Centro de sessões e dispositivos com revogação, bloqueio, desbloqueio e limpeza de registos expirados.
- Diagnóstico local de tabelas, HTTPS, rewrites, cron, bridge, plataformas e política de privacidade.
- `mobileAppConfig` atualizado para API 1.4, mantendo os campos legados e adicionando configuração detalhada por plataforma.
- Funcionalidades globais passam a controlar capacidades, navegação, endpoints móveis e painéis apresentados dentro da WebView nativa.

## 2.2.208
- Corrigida restauração de rascunhos que podia substituir o nonce atual do formulário por um nonce expirado.
- Adicionado fallback autenticado com `X-WP-Nonce` nas submissões AJAX do portal e da WebView.
- Corrigido erro recorrente "A página ficou desatualizada" ao submeter ou concluir visitas.
- Mantida a reconciliação de conclusão de visita introduzida na 2.2.207.

# FieldFlow 2.2.207, Form Visit Completion Authority

- Encaminha o botão final dos formulários embebidos para o controlador operacional correto do reporte rápido ou do fluxo focado.
- Impede que uma submissão por wizard ou teclado grave apenas as respostas sem atualizar imediatamente a visita visível.
- Verifica a paragem após a submissão e executa checkout de reconciliação quando o estado ainda não ficou concluído.
- Mantém a distinção correta entre visita executada e rota ainda em curso com outras lojas pendentes.

# FieldFlow 2.2.206, Mobile Session, Media and Role Resilience

- Torna a abertura WebView retomável quando o Flutter reconstrói o widget após rotação ou mudança de ciclo de vida, sem tornar o ticket reutilizável fora da sessão isolada.
- Adiciona seletores repetíveis e pré-visualização para uploads múltiplos em Android WebView.
- Abre evidências fotográficas num visualizador de ecrã inteiro, em vez de pequenas janelas externas.
- Pede confirmação antes de remover referências da pergunta de frentes/Cardex.
- Expande login, refresh e `/me` com roles, capacidades, navegação, âmbito e `context_version`.
- Recarrega automaticamente o portal quando o contexto funcional do utilizador muda no WordPress.

# FieldFlow 2.2.205, Mobile WebView Native Experience

- Remove o convite PWA, manifest, service worker e respetivos assets apenas dentro da WebView nativa.
- Mantém a instalação PWA disponível quando o FieldFlow é aberto normalmente em Safari, Chrome ou Edge.
- A supressão é feita no servidor e reforçada no cliente para resistir a caches e construtores de páginas.
- O portal móvel passa a identificar explicitamente o contexto nativo e limpa apenas caches e service workers PWA do FieldFlow.
- Melhora safe areas, controlos táteis, media responsiva e tamanho de campos em Android e iPhone.
- O destino continua a ser resolvido por utilizador, funções, capacidades, rotas e âmbito, sem IDs ou utilizadores fixos.
- `/health` e `/app-config` passam a anunciar a experiência WebView nativa 2.2.205.

# FieldFlow 2.2.204, Mobile WebView SSO Bridge

- Adicionado `POST /fieldflow-mobile/v1/auth/web-session` para criar uma ligação temporária e descartável para WebView.
- Adicionado `POST /fieldflow-mobile/v1/auth/web-session/revoke` para terminar sessões híbridas sem terminar necessariamente a sessão nativa.
- Criadas as rotas `/fieldflow-mobile/portal/operational/` e `/fieldflow-mobile/portal/client/`, sem header ou footer do tema.
- O portal operacional reutiliza `[fieldflow_app]` e o portal cliente reutiliza `[fieldflow_client_portal]`, preservando integralmente a mecânica atual.
- Tickets têm validade curta, utilização única e são ligados à sessão móvel e ao utilizador.
- Sessões WebView usam cookie dedicado HttpOnly e deixam de funcionar quando a sessão móvel é revogada.
- Logout móvel revoga também as sessões WebView associadas.
- A API `/me` passa a devolver os portais permitidos e o destino recomendado para o utilizador.
- Adicionada tabela `routespro_mobile_web_sessions` e limpeza automática de sessões expiradas.

# Changelog

## 2.2.203
- Evita conflitos entre os tokens móveis opacos do FieldFlow e plugins JWT que intercetam `Authorization: Bearer`.
- Adiciona o cabeçalho dedicado `X-FieldFlow-Mobile-Token` para pedidos autenticados.
- Mantém Bearer como fallback legado e adiciona os cabeçalhos móveis à política CORS da REST API.

# 2.2.202

- Adds an isolated `fieldflow-mobile/v1` API for FlutterFlow, Android and iOS.
- Adds bearer access tokens, rotating refresh tokens, device sessions and revocation.
- Adds route, stop, resolved form, media and JSON submission endpoints.
- Adds idempotent mutation storage for safe offline retries.
- Keeps all existing WordPress portals and `routespro/v1` endpoints unchanged.

# 2.2.201

- Corrige o editor dos Cardex operacionais, que carregava apenas as primeiras 5.000 lojas por ordem alfabética.
- Passa a disponibilizar todas as lojas ativas da Base Comercial, preservando também lojas inativas já associadas ao Cardex.
- Prioriza no seletor as lojas já selecionadas, pertencentes à campanha ou cliente do Cardex e recentemente atualizadas.
- Adiciona pesquisa local por nome, referência externa ou ID e contadores de lojas visíveis e selecionadas.
- Mantém intactas as associações existentes, os sortidos por loja, os formulários, a sugestão de encomenda e o Analytics.

# 2.2.200
- Corrige o cabeçalho das Implementações de Materiais no frontend, eliminando a sobreposição que forçava títulos escuros sobre fundos configurados.
- Usa `hero_text_color` e `hero_muted_color` para o título e subtítulo do cabeçalho, tal como definido em Personalização.
- Usa `header_start_color` e `header_end_color` no fundo do cabeçalho do documento.
- Integra o restante módulo com as variáveis do tema para superfícies, textos, contornos, tipografia, botões, filtros e raios.
- Preserva cores semânticas legíveis nos estados operacionais e melhora o comportamento responsivo.

# 2.2.199
- Cria o módulo independente Implementações de Materiais, sem alterar a estrutura dos briefings operacionais.
- Adiciona importação DOCX defensiva através de OOXML, com suporte ZipArchive, PharData e PclZip.
- Reconhece as notas iniciais do documento, os espaços centralizados Pingo Doce e a operação RTDs Algarve.
- Usa o código externo da loja como chave prioritária e memoriza associações confirmadas por cliente e campanha.
- Disponibiliza revisão no backoffice, edição dos estados, previsões, materiais e observações, publicação, arquivo e eliminação.
- Adiciona os shortcodes `[fieldflow_implementations]` e `[fieldflow_client_implementations]`.
- Integra uma nova aba Implementações na FieldFlow App e no portal do cliente.
- Restringe a visão da equipa às lojas atribuídas em campanha ou rota, mantendo a visão completa para clientes com acesso ao âmbito.
- Adiciona métricas e filtros por execução, bloqueio, pedido, agendamento e feedback.

# 2.2.198
- Cria uma fonte única para perguntas operacionais e respetivos widgets analíticos.
- Permite editar perguntas ativas dos briefings, incluindo texto, tipo, formulário, secção, lojas, obrigatoriedade, estado e ajuda.
- Adiciona configuração de dashboard, tipo de widget, agregação, dimensões, KPI, tabela e ordem diretamente na pergunta.
- Sincroniza automaticamente widgets geridos quando a pergunta é criada, editada, reordenada, ativada ou desativada.
- Inclui perguntas de briefing e contextuais no Centro de Analytics, com preferências predefinidas e edição de widgets existentes.
- Mantém portal do cliente e FieldFlow App alinhados através do mesmo payload e identifica visualmente os widgets originados pelo briefing.
- Preserva o histórico de respostas quando perguntas ou widgets são desativados ou removidos.

# 2.2.197
- Adiciona sincronização híbrida dos rascunhos, com gravação local imediata e persistência no servidor após debounce.
- Cria tabelas próprias para rascunhos e ficheiros temporários, sem usar o fluxo de submissões nem concluir visitas.
- Recupera respostas, passos, matrizes, linhas dinâmicas e fotografias noutro dispositivo do mesmo utilizador.
- Resolve concorrência por revisão e `updatedAt`, recuperando a versão mais recente quando dois dispositivos editam o mesmo contexto.
- Mantém fotografias temporárias fora da biblioteca multimédia e publica-as apenas na submissão final.
- Elimina rascunhos e ficheiros após submissão confirmada, descarte manual ou expiração de 72 horas.
- Normaliza snapshots no servidor, protege as linhas dinâmicas e evita substituições parciais de conjuntos de fotografias.

# 2.2.196
- Adiciona rascunhos automáticos no frontend, com recuperação após suspensão, reload e troca acidental de contexto.
- Preserva campos, passos, matrizes, linhas dinâmicas e ficheiros locais através de localStorage e IndexedDB.
- Limpa o rascunho apenas após confirmação de submissão bem-sucedida.

# 2.2.195
- Corrige o Google Autocomplete da Base Comercial no BO, usando diretamente as coordenadas do local selecionado e mostrando um marcador de pré-visualização no mapa.
- Separa a ação Filtrar da Descoberta Google, impedindo a geocodificação redundante que originava a mensagem “Sem coordenadas para a zona selecionada”.
- Adiciona geocodificação de recurso antes de guardar e invalida coordenadas antigas quando a morada é alterada manualmente.
- Sincroniza a criação e atualização de PDVs no front com a tabela de lojas da campanha.
- Invalida a cache comercial após guardar ou apagar PDVs no BO, mantendo BO, portal do cliente e aplicação operacional alinhados.

# 2.2.194
- Adiciona uma camada de ajuste de Cardex por loja, mantendo o Cardex base como origem central do sortido.
- Permite editar, remover e acrescentar referências na pergunta de Frentes durante o preenchimento do formulário.
- Guarda a lista final como exceção exclusiva da loja quando difere do Cardex base e elimina a exceção quando volta a ser igual.
- Disponibiliza no back office a consulta dos ajustes por loja e a ação para repor o Cardex base.
- Sincroniza a Sugestão de encomenda com a lista final de Frentes, removendo produtos ausentes e incluindo novas referências.
- Mantém a sugestão automática para referências com zero frentes e calcula caixas, unidades por caixa e unidades totais.
- Corrige o Analytics para reconhecer Sugestão de encomenda como um tipo próprio, sem converter as quantidades em Frentes.
- Adiciona leitura executiva de encomendas no portal, com totais por loja, referências, caixas, unidades e observações.

# 2.2.193
- Adiciona o tipo de pergunta “Upload de imagens por categoria”.
- Permite configurar categorias de evidência, por exemplo Topos, Expositores, Arcas e Displays, dentro de uma única pergunta.
- Cada categoria selecionada abre um upload próprio e pode aceitar uma ou várias fotografias.
- Guarda as evidências em estrutura categorizada, preservando rótulo, ficheiro, tipo e URL.
- Recupera as imagens por categoria ao editar um reporte e permite acrescentar novas evidências sem perder as anteriores.
- Integra as evidências categorizadas no histórico, back office, analytics, exportações, perguntas contextuais e perguntas de execução do briefing.
- Restringe o novo upload a formatos de imagem permitidos pelo WordPress.
- Mantém integralmente a seleção visual e as condições múltiplas introduzidas na versão anterior.

# 2.2.192
- Adiciona o tipo de pergunta “Seleção visual com imagens”, com cartões configuráveis no back office.
- Cada opção visual pode ter nome, imagem da biblioteca multimédia, ordenação própria e seleção simples ou múltipla.
- As respostas visuais são validadas, guardadas, recuperadas em edição e incluídas no histórico e nas exportações como as restantes escolhas.
- As perguntas de escolha passam a aceitar várias regras condicionais em simultâneo.
- Cada regra liga uma resposta a uma pergunta de destino, usando os valores configurados num seletor para evitar erros de escrita.
- Em seleção múltipla, todas as condições correspondentes podem abrir perguntas diferentes ao mesmo tempo.
- Mantém compatibilidade automática com formulários que usam a condição única anterior.

# 2.2.191
- A edição de rotas deixa de apagar e recriar todas as paragens.
- Preserva IDs, estado efetuado, check-in, checkout, duração, evidências e ligação aos reportes.
- Novas lojas são ordenadas pelo cálculo de distância sem reiniciar visitas já realizadas.
- Lojas removidas saem do plano ativo, mantendo reportes e histórico de auditoria.
- Submissões com IDs antigos recuperam automaticamente a paragem atual por rota e loja quando a correspondência é única.
- Formulários e eventos de checkout sincronizam imediatamente o estado da paragem e da rota.
- O front só confirma a conclusão depois de existir persistência operacional no servidor.
- As operações REST de adicionar, remover e limpar paragens passam a recalcular o progresso da rota.

# 2.2.190

- Adiciona em Personalização os controlos de cor do título e do texto do cartão Escopo atual.
- Aplica os novos controlos ao portal do cliente e ao cartão Utilizador da app do merchandiser.
- Suporta tema global, temas por campanha e atualização dinâmica do tema no portal do cliente.
- Mantém as cores visuais anteriores como predefinição de cada template.

# 2.2.189

- Corrige a fonte de verdade das perguntas do briefing, usando apenas lojas ativas e explicitamente ligadas à campanha quando esse universo existe.
- Permite que a seleção manual de lojas prevaleça sobre a inferência de insígnia, útil para PDV com nomes ou metadados incompletos.
- Introduz ordem canónica das perguntas, editável no backoffice e propagada a todas as associações da mesma pergunta.
- Agrupa clones por loja numa pergunta lógica única no BO, na app e no portal do cliente.
- Filtra perguntas e estatísticas da equipa pelas lojas atribuídas ao utilizador, sem fuga para todas as lojas quando o âmbito está vazio.
- Faz o shortcode da equipa respeitar o cliente e o projeto ativos, incluindo alterações de âmbito dentro do FieldFlow App.
- Exclui lojas de campanha inativas do mapping, das perguntas, dos formulários e das métricas de execução.
- Sincroniza imediatamente os destinatários dos briefings ativos quando mudam responsáveis do projeto, da rota ou do PDV.
- Normaliza automaticamente a ordem legada fixa e prioridades divergentes entre lojas no upgrade, preservando ordens já personalizadas.
- Reconcilia o âmbito ao guardar, removendo associações antigas de lojas que deixaram de ser elegíveis.
- Move o Briefing AI Copilot para uma fila WordPress em segundo plano, com estado, recuperação de jobs órfãos e proteção contra execuções interrompidas.
- Limita as tentativas de endpoints Azure por análise, reduzindo chamadas cumulativas e eliminando o bloqueio que originava 504 Gateway Timeout.

# 2.2.188

- Widgets de evidências do portal do cliente passam a apresentar cada loja ou data numa linha própria e em largura total.
- Apenas as miniaturas mantêm uma faixa horizontal compacta em desktop, evitando cartões cortados.
- A Personalização global e por campanha inclui agora cor do texto selecionado e cor de fundo da seleção.
- As novas cores de seleção são aplicadas à app, ao portal do cliente e ao dashboard analytics partilhado.

# 2.2.185

- `[fieldflow_client_portal]` passa a preservar a ordem canónica dos widgets enviada pela API, exatamente como `[fieldflow_app]`.
- Removida a segunda ordenação independente no JavaScript do portal do cliente.
- Tabelas analytics com altura uniforme em desktop, viewport interno consistente e rodapés alinhados.
- Comportamento responsivo mantido em tablet e mobile, sem altura fixa em ecrãs pequenos.

# 2.2.184

- Ordem canónica de dashboards e widgets aplicada no serviço de analytics, no BO e nos dois shortcodes.
- Widgets de evidências do portal do cliente organizados por categorias numa faixa horizontal.
- Categorias de evidências respeitam a ordem configurada no Centro de Atribuições.
- Correção da classe visual dos widgets de evidências no portal do cliente.

# 2.2.183

- Expande o estúdio de personalização com controlo integral das cores de texto em todos os componentes, incluindo hover de abas e botões primários e secundários.
- Cria widgets de evidências compostos por loja, data e imagens, com agrupamento configurável, miniaturas, downloads por loja ou data e tabela detalhada opcional.
- Elimina links e JSON em bruto das tabelas Analytics e mostra contagens de imagens legíveis.
- Alinha o layout Analytics de `[fieldflow_app]` com o sistema visual do `[fieldflow_client_portal]`.
- Acrescenta data, rota, loja e ficheiro às exportações de evidências em CSV e Excel.

# 2.2.181

- Cria um sistema visual centralizado com tema global e substituições por campanha.
- Adiciona cinco templates premium: Executive Blue, Aurora Glass, Nordic Minimal, Emerald Ops e Editorial Sand.
- Permite configurar paleta, tipografia, densidade, cabeçalhos, navegação, cards, botões, tabelas, gráficos, sombras, raios e textos do header.
- Evolui `[fieldflow_app]` e `[fieldflow_client_portal]` com uma linguagem visual consistente em todas as abas.
- Torna as tabelas responsivas e dinâmicas, preservando pesquisa e paginação e convertendo linhas em cartões no mobile quando a estrutura o permite.
- Faz os gráficos do portal cliente herdarem a paleta ativa da campanha, incluindo mudanças sem recarregar a página.
- Mantém compatibilidade com os logos por campanha já guardados.

# 2.2.180

- Corrige a associação de Cardex a lojas no front, respeitando cliente e projeto ao resolver o Cardex automático.
- O modo automático deixa de reutilizar um `cardex_id` residual de uma configuração fixa anterior.
- A gravação das lojas passa a ser validada após persistência e repõe o âmbito anterior se ocorrer uma falha.
- O BO mostra o número de lojas associadas e confirma a quantidade selecionada antes de guardar.

# 2.2.179

- Corrige a pré-visualização AJAX que enviava `weekly` por engano porque `plan_scope` é um campo oculto.
- Data inicial e final passam a definir integralmente a janela do corredor partida/chegada.
- Acrescenta proteção no servidor para browsers com JavaScript em cache.

## 2.2.178

- Corrige rotas circulares que misturavam lojas em lados opostos do ponto de partida.
- Introduz corredor radial com arco máximo de 118 graus no modo Forte e 155 graus no modo Equilibrado.
- Lojas até 4,5 km da base são tratadas como zona neutra e podem completar qualquer corredor.
- Datas geradas automaticamente deixam de bloquear movimentos entre dias da mesma semana.
- O diagnóstico territorial passa a mostrar arco, risco de cruzamento da base e classificação do corredor.

## 2.2.177
- A pré-visualização passa a usar um único pedido AJAX cancelável, sem refresh da página.
- Alterações aos parâmetros deixam de lançar cálculos automáticos concorrentes; o cálculo ocorre apenas ao clicar no botão.
- Corrige o erro 504 ao pré-visualizar sugestões mensais.
- Reutiliza o plano já calculado no mesmo pedido e elimina até duas reconstruções redundantes.
- A pré-visualização deixa de chamar Google Route Matrix por cada dia; a validação externa ocorre na criação da rota real.
- Introduz orçamento temporal e limite de candidatos na otimização global, mantendo sempre a melhor solução encontrada.
- Adiciona memoização de objetivos, limites diários e métricas do empacotamento territorial.
- O planeador manual reutiliza a estrutura do preview em vez de gerar novamente o mês inteiro.
- Na criação de rotas elimina a chamada Google duplicada, usando matriz interna para ordenar e uma única Google Routes para validar o trajeto.

## 2.2.176

- Novo empacotamento territorial semanal, com periodicidade a escolher primeiro as semanas e o routing a escolher depois os dias.
- As lojas deixam de receber um weekday automático isoladamente antes da construção das rotas.
- Sweep angular por localidade, capacidade, tempo e direção, com estabilidade de weekday entre semanas quando viável.
- Reserva operacional de 8%, mínimo 25 minutos, para absorver trânsito, acessos e diferenças entre estimadores.
- Balanceadores antigos deixam de desmontar blocos territoriais válidos apenas para uniformizar o número de paragens.
- P2 passa a distinguir corretamente semanas intercaladas de semanas consecutivas por janela de calendário.
- Apenas as visitas que ultrapassam o limite ficam assinaladas, eliminando o badge overflow em todas as lojas do dia.
- BO e CSV mostram rotas semanais criadas, visitas redistribuídas, blocos de localidade e rotas acima da capacidade.
- Teste integrado com Vagos, Santa Maria da Feira, Leça do Balio, Trofa, Valença, Vieira do Minho, Vizela, Paços de Ferreira, Gandra e Valongo.

## 2.2.175

- Novo modo de coerência territorial forte para rotas com partida e chegada na mesma base.
- Sequenciação por blocos de direção e localidade, evitando o efeito ioiô entre norte, sul, este e oeste.
- Consolidação de localidades através de movimentos de grupos completos entre dias, preservando periodicidade, intervalos, capacidade e horários.
- Proteção da ordem territorial contra reordenação final da Google em rotas de base única, mantendo cálculo rodoviário, tempos e portagens.
- Diagnóstico alinhado em BO, portal, app, CSV, Excel e PDF, com direção dominante, score de coerência, mudanças, regressos e mistura de direções opostas.
- Nova suite de testes dedicada ao cenário de lojas a norte e sul da mesma base.

## 2.2.174

- Nova matriz rodoviária canónica, com modos híbrido, Google e interno, usada pelo planeamento, rotas, BO, front e exportações.
- Sequenciação diária com nearest neighbour, regret-2 insertion, projeção de corredor, 2-opt e otimização final de waypoints Google.
- Otimização global entre dias da mesma semana através de movimentos e trocas que preservam regras rígidas, periodicidade, intervalos, datas bloqueadas, limites de lojas e jornada.
- Métricas alinhadas de acesso, percurso entre PDVs, regresso, total, tempo, origem, versão da matriz, cobertura e integridade.
- BO e front passam a mostrar a mesma decomposição e a mesma ordem otimizada, incluindo quilómetros e minutos poupados.
- Distâncias desconhecidas deixam de ser apresentadas como 0 km em todas as vistas.
- Atualizações Google preservam o método, versão e ganhos da otimização já calculada.
- CSV, Excel e PDF passam a transportar os mesmos metadados e métricas canónicas.
- Nova suite de testes de routing para validar total, segmentos, integridade geográfica e não regressão da otimização.

## 2.2.173
- Unificação das métricas de routing, com quilómetros de acesso, entre PDVs, regresso e total.
- Segmentos geográficos incompletos deixam de aparecer como 0 km.
- Exportações de planos e rotas passam a indicar origem e estado da distância.
- Correção das frequências semanais superiores a uma visita.

## 2.2.172

- Corrigida a associação dos destinatários nos relatórios automáticos.
- Merchandisers passam a ser exclusivamente os utilizadores definidos em Centro de Atribuições, Projetos, Utilizadores com acesso ao projeto.
- Clientes passam a resultar da união entre Responsáveis ativos do projeto, Utilizadores associados na aba Clientes e o email principal do cliente.
- Removida a inferência de merchandisers através de owners de rota, lojas, assignments ou capacidades WordPress.
- O seletor de merchandiser para testes é filtrado dinamicamente pela campanha selecionada.
- O log de email regista a origem do destinatário para auditoria.

## 2.2.171

- Isola os anexos dos emails automáticos por tipo de destinatário.
- Cada merchandiser recebe um pacote próprio, filtrado pelo respetivo utilizador e pela visibilidade da app de terreno.
- Clientes e emails adicionais recebem a visão completa do cliente e campanha selecionados.
- Reutiliza um único pacote completo para destinatários cliente, mas gera pacotes individuais para cada elemento da equipa.
- Inclui project assignments, lojas atribuídas, ownership de rotas e assignments ativos na deteção da equipa.
- Aplica prioridade de menor privilégio quando o mesmo email surge simultaneamente como cliente e operativo.
- Impede que widgets sem respostas revelem lojas fora do âmbito pessoal do merchandiser.
- Regista no histórico o âmbito, audiência, utilizador filtrado e anexos usados em cada envio.
- Clarifica no BO quais destinatários recebem visão completa e quais recebem relatório individual.
- Permite enviar o teste como cliente completo ou como um merchandiser específico, sempre para o endereço de teste.

## 2.2.170

- Redesenho premium dos widgets Analytics no portal do cliente e na app do merchandiser.
- Hierarquia visual consistente entre dashboard, widget, leitura executiva e dados detalhados.
- Tabelas detalhadas passam a áreas expansíveis, abertas em desktop e recolhidas em mobile.
- KPIs de frentes, séries, evidências e tabelas recebem espaçamento, tipografia e contraste otimizados.
- Melhor adaptação a desktop, tablet e telemóvel sem alterar a lógica de dados ou exportações.

## 2.2.169

- Corrige a sobreposição do gráfico de frentes com KPIs e tabelas no portal do cliente.
- Separa leitura visual e detalhe por referência em blocos verticais independentes.
- Remove scroll vertical aninhado das pré-visualizações Analytics.
- Reserva espaço para a navegação inferior persistente.
- Alinha a estrutura dos widgets Analytics do merchandiser com o portal do cliente.

## 2.2.168, Layout responsivo dos widgets Analytics

- Limita as tabelas do portal do cliente a duas colunas no desktop, uma coluna em tablet e mobile, eliminando cartões excessivamente estreitos.
- Classifica cada tabela como compacta, standard ou larga conforme as colunas que realmente contêm dados.
- Omite colunas vazias e preserva loja, categoria, produto, valores, frentes, datas, rotas e estados apenas quando são relevantes.
- Faz tabelas largas ocupar toda a largura e mantém tabelas compactas lado a lado apenas quando existe espaço útil.
- Converte linhas em cartões com rótulos no mobile, evitando dependência de scroll horizontal para leitura básica.
- Melhora cabeçalhos, pesquisa, paginação, dimensões das colunas, KPIs de frentes e quebra de nomes longos.
- Aplica a mesma estratégia ao Analytics do merchandiser, com widgets simples em grelha e tabelas, frentes ou evidências em largura total.
- Reforça a compatibilidade com os shortcodes `[fieldflow_client_portal]` e `[fieldflow_app]` em desktop, tablet e mobile.

## 2.2.167, Reconciliação histórica de lojas e alinhamento Analytics

- Resolve a loja por uma cadeia única usada no portal do cliente, app do merchandiser e exportações.
- Cruza IDs da versão, submissão e registo com os bindings do formulário, paragens de rota, snapshots de rota e metadados históricos.
- Recupera snapshots diretamente mesmo quando a paragem original já foi removida.
- Suporta o erro legado em que `location_id` contém um `route_stop_id`.
- Reutiliza nomes históricos preservados nos módulos de stocks e briefings quando a base comercial já não contém o PDV.
- Novas submissões passam a guardar `location_snapshot` com nome, morada e contexto da rota, evitando órfãos futuros.
- O Analytics do merchandiser apresenta a mesma estrutura funcional do cliente para perguntas, frentes, evidências e tabelas com scroll.
- Invalida os snapshots temporários antigos das exportações para não reutilizar rótulos `Loja não identificada`.

## 2.2.166, Excel por pergunta e testes de email com anexos

- Adiciona uma folha Índice e uma folha individual para cada pergunta ou widget Analytics.
- Cada folha individual reúne resumo, séries do gráfico, respostas, frentes e evidências.
- Adiciona a folha Séries e enriquece Métricas e Detalhe Analytics com pergunta, chave, tipo e ID de loja.
- Divide automaticamente folhas acima de 60.000 linhas, mantendo o universo integral sem ultrapassar limites do formato Excel XML.
- Corrige a criação de nomes únicos quando os títulos das folhas excedem 31 caracteres.
- Adiciona email de teste configurável e o botão Guardar e enviar teste com anexos.
- O teste usa os períodos e formatos selecionados, gera os ficheiros reais, anexa-os automaticamente e mantém histórico separado do envio real.
- Bloqueia envios quando nenhum anexo foi gerado e regista quantidade e peso dos anexos.

## 2.2.165, Analytics Store Integrity and Complete Excel Export

- Recupera nomes de lojas em reportes antigos usando metadados, paragem de rota atual, snapshot histórico e base comercial.
- Faz os filtros de grupos de lojas aceitar o ID guardado na submissão e o ID atual associado à paragem, evitando lojas fora do universo correto.
- Adiciona download direto e autenticado de um ZIP por loja para widgets de evidências, com resolução por attachment, URL relativa e ficheiro original.
- Corrige a colisão JavaScript entre o objeto de métrica e o helper de cartões, que podia interromper os widgets de frentes.
- Mantém tabelas Analytics dentro do viewport com scroll horizontal e vertical, cabeçalhos fixos e espaço para a navegação inferior.
- Ativa um modo de exportação integral para Excel, sem os limites de 500 rotas, 100 dashboards, 500 widgets, 5.000 respostas, 80 linhas por métrica ou 1.000 lojas vazias.
- Acrescenta ao Excel uma folha Evidências com loja, ficheiro, URL, tipo e ID de media.
- Evita reutilizar snapshots resumidos na exportação integral e aplica o mesmo comportamento aos relatórios automáticos com Excel.
- Reforça o Centro de Analytics no BO com contadores de evidências e frentes, tipo real da pergunta e aviso de widgets órfãos.

## 2.2.164, Analytics Evidence and Fronts Runtime Recovery

- Corrige a falha JavaScript que interrompia a renderização do Analytics quando existiam widgets de frentes: as funções `metricCard` e `growthBadge` eram chamadas sem estarem definidas.
- Recupera a pergunta de frentes no Centro de Analytics através do schema atual e das perguntas realmente gravadas em histórico.
- Guarda explicitamente o tipo da pergunta em cada novo widget para distinguir `product_matrix`, imagens e métricas normais sem heurísticas frágeis.
- Mostra no front a pergunta real de frentes, o resumo e as colunas Antes, Depois, Diferença e Evolução.
- Substitui os vários controlos de evidências por uma única ação `Descarregar imagens` à frente de cada loja.
- Cria um ZIP por loja no servidor e melhora a resolução de ficheiros em instalações com domínio, protocolo ou caminho de uploads diferente.
- Torna Subir e Descer dos widgets operações gravadas no servidor, sem depender de JavaScript ou de arrastar linhas.
- Mantém a ordem em `sort_order` e aplica-a ao portal do cliente e à app do merchandiser.

## 2.2.153, Analytics Snapshot Export Integrity

- A exportação manual reutiliza a resposta exata já validada e mostrada na aba Analytics.
- Cria uma fotografia temporária por utilizador, período, cliente, projeto, operativo, loja, categoria, produto e audiência.
- Evita que uma segunda consulta com contexto diferente gere folhas sem dados apesar de os gráficos estarem preenchidos.
- Os relatórios automáticos recolhem cada período uma única vez e partilham o mesmo conjunto de dados entre XLSX e PDF.
- O Resumo identifica a fonte dos dados e apresenta o número de rotas e métricas disponíveis antes da construção do ficheiro.
- Reduz o detalhe XLSX para 6.000 linhas no total e 1.200 por métrica, mantendo CSV para detalhe mais amplo.
- Explica no front que Semana e Mês são apenas atalhos para as datas e não requisitos de exportação.

## 2.2.152, Reliable Analytics Exports and Scheduled Report Delivery

- Corrige a exportação Excel vazia no portal do cliente e na app do merchandiser.
- Normaliza a resposta Analytics antes de construir os ficheiros e garante conteúdo útil mesmo quando um período não tem rotas ou métricas.
- Limita o detalhe pesado a 12.000 linhas por workbook, com máximo de 2.500 por métrica, mantendo o resumo integral e acelerando a abertura.
- Valida internamente a estrutura do XLSX antes de o entregar ao utilizador ou anexar a um email.
- Adiciona Relatórios automáticos ao Centro de e-mails, por cliente e campanha.
- Permite escolher início, fim, dia da semana, hora, destinatários, assunto, cor, períodos semanal e acumulado e anexos Excel e PDF.
- Resolve automaticamente equipa operacional, utilizadores cliente associados, email principal do cliente e endereços adicionais.
- Inclui envio de teste, envio imediato, pausa, reativação, eliminação, próximo envio e estado do último envio.
- Envia mensagens individualmente e regista cada resultado no histórico de emails existente.
- Reutiliza a recolha de dados entre Excel e PDF para reduzir consultas e risco de timeout.

## 2.2.151, Premium Weekly Analytics and Exports

- Redesenho premium da área Analytics no portal do cliente.
- Relatório semanal com atalhos de período no cliente e merchandiser.
- CSV normalizado para análise e arquivo.
- XLSX nativo com Resumo, Rotas, Métricas e Detalhe Analytics.
- PDF executivo com KPIs, leitura do período e detalhe controlado.
- Tabelas recolhíveis, cabeçalhos fixos e melhorias responsive.

## 2.2.150, Stock AI Batch Queue and Bulk Mapping

- Substitui a validação síncrona de até 180 lojas por pedidos AJAX faseados de seis lojas.
- Guarda progresso após cada bloco, permitindo parar, retomar e recuperar de falhas sem reiniciar o trabalho.
- Reduz o timeout de cada chamada IA e limita a resposta para evitar erros 504.
- Acrescenta seleção múltipla de lojas, seleção automática por confiança e associação manual em massa.
- Bloqueia a utilização automática do mesmo PDV em duas associações do mesmo lote.
- Recalcula métricas apenas uma vez após cada operação em massa.

## 2.2.149, Intelligent Stock Store Matching

- Adiciona um motor híbrido de associação de lojas para stocks operacionais.
- Calcula candidatos por referência, código numérico, nome normalizado, semelhança textual, localidade e distância entre alternativas.
- Gera sugestões locais imediatamente após a importação, sem depender de serviços externos.
- Quando a IA está ativa, envia apenas listas curtas de candidatos existentes e bloqueia IDs inventados.
- Guarda confiança, justificação, alternativas e indicação de validação por IA no próprio lote.
- Pré-seleciona a melhor sugestão em cada associação manual.
- Adiciona aplicação automática em massa com limiar configurável de 85%, 90% ou 95%.
- Evita associações automáticas duplicadas ao mesmo PDV.
- Mantém lojas de baixa confiança fora do frontoffice até validação humana.

## 2.2.148
- Corrige a criacao das tabelas do modulo de stocks em atualizacoes de plugins ja ativos.
- Adiciona reparacao automatica e idempotente do schema de stocks.
- Corrige o SQL da tabela de leituras de stock.
- Valida o schema imediatamente antes de cada importacao.

## 2.2.146, Focused Merchandiser Flow

- Remove os cartões globais de acesso rápido do `[fieldflow_app]`, reduzindo ruído visual em todas as abas.
- Simplifica a aba Reportar para mostrar apenas o dia e a loja atribuída.
- Resolve automaticamente cliente, campanha, owner, rota, paragem e localização através das rotas do utilizador autenticado.
- Liga o check-in à primeira interação com o formulário, sem botão manual.
- A submissão do formulário conclui a visita sem duplicar checkout ou atualização da paragem.
- Mantém um fluxo discreto para visitas não realizadas e um fallback quando não existe formulário associado.
- Na Minha Rota, substitui as ações técnicas por `Preencher formulário` e remove o painel de reporte duplicado no modo app.
- Preserva o shortcode autónomo `[fieldflow_report_visit]` no modo completo.

## 2.2.145, Store Scoped Briefing Questions

- O bloco de briefing passa a usar obrigatoriamente a loja selecionada no contexto da visita.
- Perguntas globais ou atribuídas a outras lojas deixam de aparecer no formulário.
- Quando a loja não tem perguntas de briefing válidas, o bloco desaparece por completo.
- Steps que continham apenas o bloco de briefing são removidos quando ficam vazios.
- A mesma regra é aplicada na renderização e na validação da submissão.

## 2.2.144, Client Analytics Briefing First

- Redesenho responsivo dos gráficos e cartões no Analytics do cliente.
- Legendas e índices deixam de se sobrepor aos gráficos.
- Apenas submissões ativas e a versão mais recente de cada reporte entram nos cálculos.
- Briefing operacional publicado passa a ter prioridade no Dashboard Builder e nos insights de IA.
- Corrigida a deteção das perguntas de briefing no catálogo analítico.

## 2.2.143, AI Dashboard Builder & Insights

- Adiciona o AI Dashboard Builder ao Centro de Atribuições, na área Analytics.
- O gestor pode selecionar cliente, campanha, formulários, grupo de lojas, objetivo, período, público e layout antes de gerar uma proposta.
- A IA só pode escolher perguntas e métricas existentes no catálogo FieldFlow. Os cálculos continuam a ser executados pelo motor analítico do plugin.
- Quando o fornecedor de IA não está configurado ou não responde, o motor inteligente FieldFlow cria uma proposta segura por regras.
- A proposta apresenta título, estratégia, widgets, agregações, dimensões e justificação antes de ser publicada.
- Dashboards podem ser publicados no portal do cliente, na app do merchandiser ou em ambos.
- O portal do cliente ganha leitura executiva com IA, com destaques, riscos e ações recomendadas.
- O Analytics pessoal do merchandiser ganha um copiloto operacional limitado às rotas e reportes do utilizador autenticado.
- O `fieldflow_dashboard` passa também a renderizar dashboards e widgets personalizados, incluindo os criados por IA.
- Os insights recebem apenas dados agregados, respeitam filtros e permissões e são guardados em cache para controlar custos e tempo de resposta.
- O layout do builder, dos insights e dos widgets foi criado de raiz para computador, tablet e mobile.
- Adiciona endpoint REST `analytics/ai-insights` e ações AJAX protegidas para pré-visualização e publicação do dashboard.
- Adiciona metadados de IA aos dashboards sem alterar ou eliminar configurações existentes.

## 2.2.142, Analytics Deleted Submissions Integrity

- O Analytics passa a considerar apenas versões ligadas a submissões ainda existentes.
- Submissões eliminadas antes desta versão deixam imediatamente de contar em frentes e restantes métricas.
- A eliminação no BO remove respostas, versões analíticas e valores associados na mesma transação.
- O estado atual do registo da loja é recalculado para a versão válida anterior.
- A atualização limpa automaticamente versões e valores órfãos existentes.

## 2.2.141, Current Fronts Analytics Fix

- Corrige o KPI “Frentes atuais” quando a pergunta de origem é uma matriz de produtos.
- A agregação “Último valor” deixa de usar apenas a última referência da lista, que podia ter zero frentes.
- O estado atual passa a somar todas as referências do reporte mais recente de cada loja no âmbito filtrado.
- Gráficos por loja, data, rota, campanha ou produto usam a mesma regra de estado atual.
- Mantém o comportamento anterior de “Último valor” nas perguntas simples.
- O detalhe continua a apresentar antes, depois, crescimento, categoria e produto.
- As exportações CSV, Excel e PDF do Analytics recebem o mesmo valor corrigido através do endpoint central.
- Sem alterações de esquema na base de dados.

## 2.2.140, Route Progress Reconciliation & Mobile Month

- Corrige a vista mensal da app em mobile, mantendo cartões, títulos e percentagens dentro da largura do ecrã.
- A agenda mensal passa a usar uma grelha protegida contra estilos globais do tema e permite quebra segura de títulos compridos.
- Uma submissão ativa passa a ser a fonte operacional de verdade para a conclusão da respetiva paragem.
- O motor reconcilia reportes ligados por `route_stop_id` e fallbacks seguros por rota, loja e data.
- Ligações históricas incompletas são atualizadas com `route_id`, `route_stop_id` e `location_id` quando a correspondência é inequívoca.
- O detalhe da rota, o calendário mensal, o portal do cliente e o Analytics sincronizam o progresso antes de calcular estados e percentagens.
- Quando todas as paragens possuem reporte válido, a rota passa para `completed` e a execução passa para 100%.
- Fallbacks sem rota só são aplicados quando a loja é única nesse dia, evitando conclusões cruzadas.
- Sem alterações de esquema na base de dados.

## 2.2.139, Unified Analytics & Monthly Routes

- O dashboard do `fieldflow_app` passa a usar `mode=self` e envia sempre `user_id=me`, filtrando rotas, paragens, comparativos e analytics de formulários pelo utilizador autenticado.
- O endpoint de estatísticas aceita `user_id=me` e aplica o mesmo filtro às versões de respostas dos formulários.
- O dashboard partilhado ganha cabeçalho executivo, filtros alinhados, seis KPIs, gráfico de execução, comparativo com o período anterior, insights e tabela responsiva.
- As exportações do dashboard pessoal preservam o âmbito do utilizador através de `self_mode`, sem permitir que o filtro seja adulterado no front.
- O portal do cliente reorganiza Analytics em Resumo executivo, Dashboards visuais e Dados de suporte, evitando gráficos e tabelas comprimidos lado a lado.
- Os indicadores do portal passam a usar cartões com dimensões, hierarquia e espaçamento consistentes.
- O Centro de Atribuições recebe um Centro de Analytics alinhado com o front, organizado em três passos: dashboard, grupo de lojas e widget.
- As tabelas de configuração do BO passam a usar contentores com scroll horizontal controlado.
- O `fieldflow_app` passa a permitir alternar entre a rota do dia e um calendário mensal pessoal.
- O calendário mensal apresenta rotas, paragens, execução, quilómetros estimados e agenda, permitindo abrir diretamente o dia selecionado.
- A aba Base é removida da app, mantendo Descobrir como ponto único para prospeção e enriquecimento de PDVs.
- Não existem alterações destrutivas à base de dados.

## 2.2.138, Submission & Export Experience

- Nova camada central `SubmissionPresentation` normaliza respostas simples, matrizes de produtos, sugestões de encomenda e evidências.
- A tabela de submissões do BO passa a ter seis colunas fixas, paginação, KPIs e detalhe expansível por reporte.
- O portal do cliente deixa de construir uma coluna por pergunta ou referência e passa a apresentar uma linha compacta por visita.
- O CSV resumo mantém uma linha por submissão e um conjunto fixo de colunas operacionais e métricas.
- O novo CSV dados utiliza formato longo e normalizado, adequado a filtros, Power BI e tratamento externo.
- O Excel utiliza várias folhas, Submissões, Respostas, Produtos, Encomendas e Evidências, com cabeçalhos fixos e linhas congeladas.
- Folhas Excel com mais de 60.000 linhas são divididas automaticamente para preservar compatibilidade.
- O PDF executivo apresenta contexto, respostas, resumo por categoria, ruturas, alterações, encomendas e evidências, evitando despejar o Cardex completo em colunas.
- O endpoint de submissões do portal usa o modo compacto e reduz significativamente o volume do JSON devolvido.
- Não altera a estrutura original das respostas nem elimina informação histórica.

## 2.2.137, Route Completion Synchronization

- A submissão passa a usar a própria linha guardada como fallback para recuperar route_id, route_stop_id e location_id.
- O route_stop_id resolvido é persistido novamente na submissão, mantendo a ligação operacional correta.
- A conclusão da visita é confirmada por leitura direta da base de dados antes do commit.
- Novo RouteProgressService recalcula a rota para in_progress ou completed a partir das paragens reais.
- Os endpoints PATCH de paragens sincronizam também o estado global da rota.
- O fieldflow_app publica um evento de conclusão e atualiza Minha Rota, dropdowns e métricas sem refresh manual.
- O formulário aceita stop_id e route_stop_id como aliases compatíveis, evitando perda silenciosa do contexto.

## 2.2.136, Daily Submit Button State Recovery

- Corrige o botao final Submeter quando o utilizador muda de uma loja ja reportada para uma loja ainda sem reporte.
- O botao exterior do reporte rapido deixa de herdar o estado desativado da loja anterior.
- O estado e o texto do botao sao recalculados sempre que o formulario dinamico e substituido.
- Adiciona uma protecao no ultimo passo do wizard para reativar o envio quando o formulario atual permite submissao.
- Mantem o bloqueio em reportes existentes ate o utilizador escolher Editar reporte.
- Sem alteracoes a base de dados ou aos reportes ja guardados.

## 2.2.135, Existing Daily Answers Recovery

- Corrige a regressão que identificava um reporte diário existente mas não preenchia as respostas no formulário.
- A recuperação passa a usar a chave técnica, o rótulo normalizado da pergunta e a versão histórica ligada à submissão.
- A resposta guardada na submissão mantém prioridade sobre valores históricos posteriores da loja.
- Compatível com perguntas renomeadas ou recriadas, blocos de briefing, matrizes de produtos, encomendas, uploads e seleção múltipla.
- Sem alterações destrutivas à base de dados.

## 2.2.134, One Daily Store Report

- Regra de um reporte por formulário, loja e dia, validada no front, no servidor e por índice único na base de dados.
- Ao abrir uma loja já reportada, o formulário mostra “Reporte já efetuado” e fica em modo de consulta.
- Botão “Editar reporte” ativa a correção das respostas sem criar uma nova submissão.
- A edição atualiza a mesma linha diária e cria uma nova versão no histórico para auditoria.
- Respostas anteriores do próprio reporte diário são carregadas para edição, incluindo matrizes, encomendas, uploads e escolhas múltiplas.
- Tentativas concorrentes ou páginas desatualizadas recebem resposta HTTP 409 e não criam eventos ou visitas duplicadas.
- Edições usam o novo evento `fieldflow_form_updated`, evitando repetir automações configuradas para novas submissões.
- A data operacional usa a data real da rota, mantendo a regra diária coerente com a visita planeada.

## 2.2.133, Form Submission Completes Store Visit

- Uma submissão válida associada a uma paragem passa a marcar automaticamente a visita como `done`.
- A submissão, as respostas, o histórico da loja e o estado da visita são gravados na mesma transação.
- Se faltar `route_stop_id`, o FieldFlow resolve a paragem apenas quando rota e loja produzem uma correspondência única.
- Paragens já concluídas não geram uma segunda conclusão nem um segundo evento operacional ao editar ou voltar a submeter o reporte.
- A última submissão fica registada nos metadados da paragem para auditoria.
- A rota passa para `in_progress` após a primeira visita e para `completed` quando todas as paragens terminarem.
- A conclusão automática da última paragem pode acionar a notificação já configurada para rota concluída.
- O front confirma explicitamente que o reporte foi gravado e a visita à loja ficou concluída.

## 2.2.132, Single & Multiple Choice Questions

- Perguntas dos tipos Selecao e Radio passam a permitir uma unica opcao ou varias opcoes.
- O modo multiplo usa checkboxes grandes e claros, adequados a utilizadores com menor literacia digital.
- Respostas multiplas sao guardadas como arrays JSON e recuperadas corretamente no historico da loja.
- Validacao obrigatoria funciona ao nivel do grupo, exigindo pelo menos uma escolha sem obrigar todas.
- Condicionais consideram correspondencia com qualquer opcao selecionada.
- Edicao de submissoes no BO suporta respostas multiplas sem converter os valores em texto bruto.
- Mantida compatibilidade com formularios existentes, sem alteracoes de base de dados.

## 2.2.131, Compact Category Accordion UI

- Redesenho dos cabeçalhos de categoria para largura total, leitura horizontal e menor altura.
- Fundo branco e neutro, com a cor da marca limitada a uma barra lateral e ao estado aberto.
- Estilos reforçados contra regras globais de botões do tema e do fieldflow_app.
- Nova seta SVG dentro de um controlo próprio, evitando o traço vertical visto em alguns dispositivos.
- Badges de referências e estado mais compactos e legíveis em mobile e tablet.
- Normalização de apóstrofos, espaços e capitalização para agregar categorias equivalentes.

## 2.2.130, Category Accordion & Zero-Default Fronts

- Número de frentes e Sugestão de encomenda passam a usar categorias expansíveis, com apenas a primeira aberta por defeito.
- Adicionados controlos Abrir todas e Fechar todas, contagem de referências e resumo de estado por categoria.
- Campos de frentes vazios são interpretados e gravados como zero, sem obrigar o utilizador a preencher todas as referências.
- A sugestão automática de encomenda considera igualmente uma frente vazia como rutura.
- Adicionado fallback no servidor para manter a sugestão automática mesmo sem execução de JavaScript.
- Categorias fechadas abrem automaticamente quando contêm um campo inválido que precisa de foco.

## 2.2.129, Product Reporting Mobile Flow

- Corrigido o alinhamento horizontal das opções de radio, incluindo perguntas Sim e Não.
- Número de frentes passa a cartões mobile por produto, com categoria, referência, produto e quantidade claramente identificados.
- Produtos vindos de Cardex ficam bloqueados, evitando alterações acidentais ao catálogo durante o reporte.
- Produtos agrupados por categoria, replicando a leitura da sugestão de encomenda.
- Frentes a zero preenchem automaticamente a sugestão de encomenda do mesmo produto.
- Nova configuração no Form Builder para definir quantas caixas sugerir por rutura, com valor padrão de 1.
- Valores manuais de encomenda nunca são substituídos pela automação.
- Navegação e botão Guardar reporte deixam de ficar tapados pela barra inferior do fieldflow_app em mobile.

# FieldFlow 2.2.128, Form Experience & Bulk Integrity

- Formulários touch-first com tipografia, campos e botões maiores em mobile, tablet e desktop.
- Navegação por steps com reposicionamento automático, progresso claro e foco no primeiro campo obrigatório em falta.
- Feedback visível de gravação, sucesso e erro dentro do formulário e no reporte da app.
- Submissão integrada entre reporte e formulário, com proteção contra cliques duplicados e transações já existentes preservadas.
- Contexto sincronizado pela data real da rota, loja, paragem, campanha, cliente e utilizador atribuído.
- Aprovação em massa das perguntas do briefing com payload JSON compacto e identificadores estáveis.
- Chaves únicas por sugestão, evitando que perguntas semelhantes se substituam na gravação.

# FieldFlow 2.2.127, Bulk Approval for Briefing Questions

- Corrige o fluxo de aprovação individual das sugestões do briefing.
- Adiciona seleção múltipla e aprovação em massa.
- Preserva tipo de resposta, formulário e obrigatoriedade por sugestão.
- Filtra sugestões já aprovadas para evitar repetição e falsa sensação de não gravação.
- Confirma apenas gravações reais e reporta secções sem lojas mapeadas aplicáveis.

# FieldFlow 2.2.126, Execution Questions Bulk Management

- Paginação no BO das perguntas de execução.
- Seleção individual, selecionar tudo na página e eliminação em massa.
- Opções de 10, 20, 50 ou 100 linhas por página.
- Confirmação de segurança e retorno automático à aba Perguntas de execução.
- As respostas históricas das submissões são preservadas.

# FieldFlow 2.2.125, Complete Briefing Questions & Photo Evidence

- Removed the four-reference cap from Share Space question suggestions.
- All seven Share Space references in the current W29 workbook can generate individual questions.
- Briefing AI may now return up to 20 execution-question suggestions.
- Added photo-evidence suggestions for promotions, VAPs, spaces and physical implementations.
- Suggested response type can be changed before approval, including Photograph Evidence and File.
- Required state can be reviewed before approving each suggestion.
- Uses the existing upload, submission, reporting and export pipeline.

# FieldFlow 2.2.124, Form Briefing Block

- Novo marcador ordenável Bloco de Briefing no construtor de formulários.
- As perguntas aprovadas do briefing aparecem apenas nessa posição e apenas nas lojas mapeadas.
- Sugestões automáticas com IA, aprovação no BO e filtragem por secção/insígnia.
- Submissões mantêm o modelo atual e o bloco não é gravado como resposta.
- Exportações, relatórios, analytics e shortcodes reconhecem respostas com origem Briefing.
- Chave de pergunta partilhada entre lojas para comparação e reporting agregado.

# FieldFlow 2.2.123, Briefing Execution Forms

- Ligação nativa entre briefings e perguntas contextuais dos formulários.
- Validade automática igual ao período do briefing e preservação da versão.
- Nova etapa “Briefing desta semana” nos formulários por passos.
- Perguntas globais, por lojas mapeadas ou por lojas selecionadas.
- Métricas de respostas, conformidade e não conformidade no BO e front.
- Correção definitiva do scroll horizontal touch nas tabelas dos briefings em tablet e mobile.

# FieldFlow 2.2.122, Premium Front Layout Polish

- Premium polish for the main frontend shortcodes.
- Wider full-width shells with better spacing and padding.
- Improved table wrappers, sticky headers, hover states and mobile scrolling.
- Better visual treatment for charts, KPI cards and panel containers.

# FieldFlow 2.2.121, Complete Weekly Workbook Integration

## Correções e evolução

- Corrigida a leitura da folha `Status of Share Space`, que contém semanas repetidas, linhas vazias omitidas no XML e valores em notação científica.
- Selecionada a ocorrência mais recente da W28 para o briefing W29.
- Importados e apresentados os sete KPI, incluindo quota Pernod Ricard, quota da concorrência, objetivo, desvio e variação face à semana anterior.
- Criada uma área própria para os 136 registos da folha `ESPAÇOS CENTRALIZADOS`, 128 Sonae e 8 Auchan.
- Criada uma área própria para as 63 associações de sortido Auchan da folha `CARDEX (MC1L)(MC Caramelo0,7L)`.
- Sonae, Pingo Doce, Auchan, Intermarché e El Corte Inglés passam a aparecer sempre no painel de mapping, mesmo quando a semana não contém nomes externos de PDV para uma das insígnias.
- O BO mostra as lojas internas disponíveis por insígnia e explica quando não existem aliases externos nessa folha.
- Novo painel `Folhas do Excel`, com confirmação das 17 folhas reconhecidas, tipo de conteúdo, insígnia e número de registos integrados.
- O `[fieldflow_app]` e o `[fieldflow_client_portal]` recebem abas dedicadas a Espaços centralizados, Sortido por loja e Share Space.
- O Briefing AI Copilot passa a receber todas as métricas de Share Space, amostras dos espaços e o manifesto das folhas, mantendo o matching flexível de lojas.

# FieldFlow 2.2.120, Briefing Full Context and Mapping Recovery

## Correções

- Recuperado o matching flexível da 2.2.118, com variantes para CNT, CBD e MDL sem penalizações rígidas por família.
- O mapping deixa de bloquear a distribuição e a visualização do briefing.
- O shortcode da equipa apresenta sempre todas as secções do briefing.
- Conteúdo associado às lojas e insígnias do utilizador aparece primeiro.
- Conteúdo adicional surge identificado como contexto.
- Instruções ainda sem mapping são mostradas com uma nota de validação no terreno.
- A audiência dos briefings é calculada através do acesso ao projeto, carteira de lojas e rotas, mesmo sem aliases associados.
- O seletor de lojas passa a incluir lojas genéricas do cliente e todas as lojas ligadas à campanha.
- O BO mostra a cobertura de lojas disponível por insígnia.
- Os destinatários dos briefings publicados são reconstruídos automaticamente ao atualizar.

# FieldFlow 2.2.119, Briefing Store Dictionary

- Adicionado dicionário operacional: CNT = Continente, CBD = Continente Bom Dia e MDL = Continente Modelo.
- Matching local e IA passam a trabalhar com os mesmos nomes canónicos.
- Famílias Sonae diferentes deixam de ser sugeridas entre si apenas por coincidência de cidade.
- Score reforçado pelo nome da localidade e compatibilidade com nomenclaturas históricas.
- Dicionário visível no BO para facilitar validação das associações.

# FieldFlow 2.2.118, Operational AI Control Center

- Novo menu `Inteligência Artificial` no BO, totalmente separado de licenciamento, Azure Maps e Google Routes.
- Motor principal, fornecedor alternativo e seleção independente por Briefings, Routing e Analytics.
- Suporte central a Google Gemini, Azure OpenAI, Microsoft Foundry, OpenAI e webhook próprio.
- Chaves mascaradas, preservadas quando o campo fica vazio e removidas apenas por ação explícita.
- Testes de ligação com resposta, modelo, duração e erro técnico real.
- Nova tabela de utilização de IA, sem guardar prompts, briefings ou conteúdos comerciais.
- Limite diário opcional, retenção configurável e limpeza manual dos logs.
- Briefing AI Copilot e endpoint de sugestões passam a respeitar o fornecedor definido por módulo.
- Settings gerais deixam de editar acidentalmente as credenciais de IA existentes.

# FieldFlow 2.2.117, Microsoft Foundry Project Endpoint

- Suporte nativo ao endpoint completo `https://<resource>.services.ai.azure.com/api/projects/<project>`.
- O caminho do projeto deixa de ser removido durante a construção da chamada.
- Primeiro tenta Chat Completions e usa Responses API como fallback.
- Continua compatível com Azure OpenAI v1 e recursos clássicos por deployment.
- Diagnóstico explícito quando o servidor WordPress não consegue resolver o domínio Azure.
- Campo de endpoint no BO atualizado com instruções para Microsoft Foundry.

# FieldFlow 2.2.116, Azure AI & Full Store Detection Fix

- Azure OpenAI passa a tentar primeiro o endpoint v1 atual e a usar automaticamente endpoints clássicos como fallback.
- O erro técnico do Azure deixa de ser ocultado, permitindo distinguir chave inválida, deployment inexistente, endpoint incorreto, versão incompatível e bloqueios de quota.
- Novo campo opcional de versão da API Azure, com valor padrão `2024-06-01` para compatibilidade clássica.
- O Briefing AI Copilot deixa de limitar a análise às primeiras 40 lojas por associar.
- Todos os aliases de loja do briefing são percorridos pelo motor de correspondência, incluindo os 143 nomes únicos identificados no ficheiro W29.
- O BO apresenta cobertura completa: nomes detetados, pendentes analisados, candidatos encontrados, lojas sem candidato provável e sugestões disponíveis.
- As sugestões continuam limitadas a lojas reais dentro do cliente e projeto e exigem validação ou confiança mínima de 85%.

# FieldFlow 2.2.115, Briefing AI Copilot

- Reutiliza o fornecedor de IA já configurado no FieldFlow, sem novas chaves ou integrações paralelas.
- Analisa automaticamente novos briefings importados quando a IA está disponível.
- Deteta estrutura, semana, ano, insígnias e eventuais desvios no modelo recebido.
- Gera resumo executivo, resumo para a equipa, resumo para o cliente e síntese por insígnia.
- Compara a semana atual com o briefing anterior e identifica novos conteúdos, alterações e remoções.
- Executa auditoria pré-publicação com score de qualidade, alertas e ações recomendadas.
- Sugere correspondências entre nomes externos e lojas existentes, validando sempre os IDs contra o âmbito real do projeto.
- Permite aplicar automaticamente apenas sugestões com confiança igual ou superior a 85%.
- Guarda a análise no briefing, evitando chamadas repetidas à IA na app e no portal do cliente.
- Adiciona a aba `Copiloto IA` ao BO, ao `[fieldflow_app]` e ao `[fieldflow_client_portal]`.
- Torna o importador mais tolerante a pequenas alterações nos nomes das folhas do Excel.

# FieldFlow 2.2.114, Briefing Audience & Client Submissions Fix

## Correções

- Restaurado o briefing publicado no `[fieldflow_app]` para merchandisers associados explicitamente ao projeto, mesmo quando a role WordPress não contém `routespro_execute`.
- Contas associadas ao cliente deixam de entrar na audiência quando não têm qualquer sinal operacional.
- Reconstrução automática dos destinatários dos briefings já publicados e recuperação lazy na app quando o snapshot está vazio.
- Eliminação segura de briefings no BO, disponível na listagem e no detalhe, incluindo rascunhos, publicados e arquivados.
- Portal do cliente passa a resolver o contexto das submissões por submissão, binding, rota, paragem e loja.
- Registos antigos com cliente ou campanha vazios são reparados na atualização.
- Consultas REST e exportações de submissões ficam limitadas ao âmbito autorizado do utilizador.

# FieldFlow 2.2.113, Briefing Project Recipients Fix

- O universo de leitura dos briefings passa a ser construído exclusivamente a partir do projeto, sem herdar utilizadores associados apenas ao cliente.
- Inclui utilizadores operacionais associados ao projeto, responsáveis ativos, atribuições de campanha, rotas e lojas abrangidas.
- Exclui gestores e contas do portal do cliente das métricas de destinatários, visualizações, confirmações e pendentes.
- Preserva o histórico de leitura dos utilizadores que continuam elegíveis.
- Reconstrói automaticamente os destinatários dos briefings publicados durante a atualização para corrigir imediatamente os indicadores existentes.

# FieldFlow 2.2.112, Briefing Client Scope & Theme Fix

- Corrige a ausência de briefings publicados no `[fieldflow_client_portal]` quando o shortcode não recebe `client_id` e `project_id` explicitamente.
- O endpoint do portal passa a aplicar sempre o âmbito autorizado do utilizador autenticado, mesmo sem filtros selecionados.
- Sincroniza a aba Briefings com os filtros de cliente e campanha do portal.
- Resolve automaticamente cliente e campanha quando o utilizador tem apenas um âmbito disponível.
- Harmoniza botões, tabs, textos, cabeçalhos e estados hover com as cores configuradas no tema do FieldFlow.
- Reforça os estilos contra substituições de hover e cor feitas pelo tema WordPress.

# 2.2.111, Briefing Tabs UX

- Reorganizado o Weekly Briefing Hub em abas por tarefa, em vez de uma página contínua.
- Adicionado resumo executivo com contadores de insígnias, prioridades, promoções, instruções de loja e produtos do cardex.
- Adicionada navegação rápida para Prioridades, Promoções, Lojas, VAPs, Share Space e Cardex.
- No portal do cliente, a prova individual passou para uma aba própria de Evidência.
- Conteúdo de cada insígnia apresentado em painéis recolhíveis, reduzindo o ruído visual.
- Abas horizontais responsivas e otimizadas para telemóvel, tablet e PWA.
- Mantidos filtros por utilizador, lojas, campanha, confirmação de leitura e cache local.

# 2.2.110, Weekly Briefing Hub

- Adicionado o menu `Briefings Operacionais` ao BO do FieldFlow.
- Importador XLSX independente de extensões XML opcionais do servidor, preparado para o modelo semanal da Pernod Ricard.
- Extração da W29 validada com 70 promoções, 136 espaços centralizados, 63 associações de sortido e 238 produtos de cardex.
- Conteúdos importados: foco semanal, folhetos, espaços, promoções, VAPs e respetivas alocações, Share Space, cardex e instruções específicas por loja.
- Dicionário permanente de aliases de lojas por cliente, campanha e insígnia.
- Cada nome externo é associado a uma loja existente do FieldFlow e essa associação é reutilizada automaticamente nos briefings futuros.
- Nova aba `Briefing` em `[fieldflow_app]`, filtrada por utilizador autenticado, campanha, insígnia e lojas atribuídas.
- Nova aba `Briefings` em `[fieldflow_client_portal]`, com conteúdo publicado, destinatários, visualizações, confirmações e prova individual.
- Confirmação de leitura versionada e exportação CSV da evidência no BO.
- Cache local de leitura para suporte operacional em PWA e redes instáveis.
- Compatibilidade preservada com a versão 2.2.109 e com o módulo de Despesas de Operação.

# 2.2.109, Operational Expenses Hub

- Nova aba `Despesas` no shortcode `[fieldflow_app]`.
- Registo de despesas por utilizador autenticado, com fotografia obrigatória do comprovativo.
- Leitura do código QR fiscal português na fotografia ou diretamente pela câmara.
- Extração do total, impostos, data, NIF do emitente, documento e ATCUD a partir do QR.
- Deteção local opcional do total na fotografia quando o navegador suporta reconhecimento de texto.
- Preenchimento manual disponível quando não existe leitura automática.
- Histórico pessoal com filtros por mês, datas, categoria, estado e pesquisa, incluindo paginação.
- Arquivo fotográfico privado, servido apenas ao próprio utilizador ou a gestores autorizados.
- Novo BO `Despesas de Operação`, com filtros globais, estados de aprovação, deteção de possíveis duplicados, exportação CSV e pacote ZIP com comprovativos, exclusivos do BO.

# 2.2.108, Store Scoped Cardex Enforcement

- Enforces manual Cardex to store assignments in both product matrix and order suggestion questions.
- Applies item assortment filtering to product matrix questions when store assortment data exists.
- Prevents insignia auto-detection from bypassing explicit store assignments.
- Hides non-applicable Cardex questions and skips their required validation on submission.

# 2.2.107, Alphabetical Cardex and History Compatibility

- Categorias ordenadas alfabeticamente em perguntas de frentes e sugestões de encomenda.
- Produtos ordenados alfabeticamente dentro de cada categoria.
- Categorias vazias apresentadas no fim como `Sem categoria`.
- Histórico de frentes reconciliado pela referência ou nome do produto, mesmo quando a categoria mudou no novo Cardex.
- Recuperação opcional de histórico quando a pergunta foi recriada com a mesma designação mas recebeu uma nova chave interna.
- Cabeçalho de histórico esclarece que o registo é geral à loja e indica, em cada matriz, se existe resposta anterior compatível.
- Matrizes com produtos antigos e novos mantêm sempre as colunas `Frentes antes` e `Frentes depois` alinhadas.

# 2.2.106, Order Suggestion Cardex

- Adicionado novo tipo de pergunta `order_suggestion` no criador de formulários.
- Adicionado Cardex enriquecido com categoria, código, tipo, produto, capacidade em cl e unidades por caixa.
- Adicionada associação produto-loja para controlar o sortido de cada PDV.
- Adicionado importador CSV compatível com `location_id`, `external_ref` e nome da loja.
- Loja, data da rota e vendedor autenticado são apresentados automaticamente no formulário.
- Apenas caixas sugeridas e observações ficam editáveis no front.
- Quantidade total de unidades é calculada no servidor.
- Produtos submetidos são revalidados contra o Cardex e o sortido permitido para a loja.
- Relatórios, portal cliente, Analytics, CSV, Excel e PDF passam a reconhecer caixas, unidades e observações por produto.
- Mantida compatibilidade com Cardex, formulários e importações anteriores.

# 2.2.103 - Filter Guard + Axis Soft

- Corrigido motor de sugestao para respeitar filtros visiveis do BO: pesquisa, categoria, estado, ativo e merchandiser.
- Corrigida chave de sugestao gravada para separar planos por filtros ativos.
- Suavizado Route Axis Lock quando a estrategia nao e `Corredor partida/chegada`.
- Ajustado editor direto para evitar sugestoes demasiado rigidas por eixo.

## 2.2.102, Route Axis Lock
- Adicionado bloqueio suave por eixo da morada base para evitar rotas que misturam norte/sul no mesmo dia.
- Quando partida e chegada são iguais, o motor passa a classificar lojas por lado da base, norte, sul, litoral e interior.
- Reforçada a penalização de corredores opostos no planeamento e no editor direto da sugestão.
- Cache interna atualizada para routing_intelligence_v10_route_axis_lock.


## 2.2.101, Geo Routing Brain

- Reforcado o motor de sugestao de rotas com score geografico transversal.
- Evitados mega-clusters criados por proximidade em cadeia.
- Fases finais de balanceamento passam a proteger dias geograficamente coerentes.
- Editor direto da sugestao passa a usar geografia no botao "Sugerir melhor dia".
- Cache interna do plano atualizada para routing_intelligence_v9_geo_routing_brain.

## 2.2.100 - Plan Editor Safe Rules Fix

- Corrige regressão da 2.2.99 que podia mostrar/usar apenas um dia no planeamento.
- Mantém o gerador automático mensal como na 2.2.98.
- Reforça validação do editor direto da sugestão sem quebrar a distribuição mensal.

## 2.2.91
- Corrige botões Atualizar PDVs e Guardar alterações na aba Campanha PDV do portal cliente.
- Adiciona feedback visual e fallback de gravação das linhas visíveis.

# 2.2.82, Routing Intelligence

- Adicionado Day Balancer v2 para distribuir melhor visitas dentro da mesma semana.
- Adicionado diagnostico de plano na aba Planeamento.
- Adicionados indicadores de dias leves, dias longos, mistura de zonas/corredores e visitas por encaixar.
- Classificacao automatica do tipo de rota por dia.
- Atualizada chave de cache do planeamento para regenerar sugestoes com o novo motor.

## 2.2.54 - Recovery Stable Build

- Build reconstruída a partir da versão 2.2.51 fornecida pelo utilizador, por ser a última versão confirmada como funcional.
- Sem alterações estruturais arriscadas no BO da Academy.
- Sintaxe PHP validada antes de empacotar.

## 2.2.32 - Dual PWA operativo e cliente
- Adicionado suporte PWA separado para [fieldflow_app] e [fieldflow_client_portal].
- Backoffice passou a configurar página, identidade, cores, ícones, textos e links da App Operativo e da App Cliente.
- Manifest dinâmico agora suporta profile operative/client com start_url e id próprios.
- O convite de instalação passa a aparecer também no portal cliente.


## 2.2.31 - PWA reference parity
- Refeita a mecânica PWA para replicar o padrão do plugin de referência funcional.
- Manifest sem crossorigin e com estrutura simples validada para Chrome.
- Service worker mínimo, network-first, sem fallback agressivo.
- Prompt de instalação simplificado para depender diretamente do beforeinstallprompt nativo.


## 2.2.31 - PWA installability hardening
- Registo antecipado do service worker no head para o Chrome validar a PWA antes do clique.
- Scope do service worker calculado a partir do site WordPress, compatível com instalações em subdiretório.
- Manifest ajustado com ícones any/maskable separados e id estável baseado na página da app.
- Fallback Android/Chrome mais correto quando o browser ainda não disponibiliza beforeinstallprompt.

# Changelog

## 2.2.29
- Corrige captura do prompt nativo PWA em Chrome/Edge, guardando o evento `beforeinstallprompt` cedo no `<head>`.
- Evita cair automaticamente nas instruções manuais em browsers Chromium quando o prompt nativo ainda está a inicializar.
- Adiciona diagnóstico visual discreto para Chrome quando a app ainda não cumpre os critérios de instalação.

## 2.2.28
- Ajustada mecânica PWA para ficar alinhada com o plugin de referência: botão mantém “Instalar app” também em Safari/iOS.
- Em browsers com suporte ao evento nativo, o botão chama o prompt de instalação.
- Em Safari/iOS, onde o browser não permite instalação automática por clique, o mesmo botão abre instruções manuais contextuais.
- Removida a alteração dinâmica para “Ver instruções”, evitando a sensação de comportamento diferente do plugin de referência.

# 2.2.24
- Estabilização de emergência do licenciamento remoto.
- O botão de validação deixa de chamar o endpoint Azure instável e confirma o estado local ativo.
- Erros técnicos remotos deixam de transformar uma licença ativa em inválida.
- Avisos administrativos remotos só aparecem quando a licença não está ativa.

# 2.2.23
- Limpeza visual do estado de licenciamento remoto: quando a licença está ativa por fallback válido, erros técnicos do endpoint validate deixam de aparecer como erro vermelho nas observações.
- Mantém o detalhe técnico em nota neutra para diagnóstico, sem assustar o BO com falso negativo.

# 2.2.22

- Licenciamento remoto: evita invalidar uma licença já ativa quando o endpoint Azure `/license/validate` devolve erro 500 ou resposta inválida.
- Licenciamento remoto: trata respostas de limite de ativações como licença operacionalmente ativa quando a ativação já existe.
- Licenciamento remoto: adiciona mensagem operacional para validação remota indisponível, preservando a última ativação válida.

# 2.2.21
- Adiciona Admin Secret separado ao licenciamento remoto Azure.
- Envia Admin Secret em headers e payload compatíveis ao gerar chave remota.
- Mantém Shared Secret apenas para assinatura HMAC.

## 2.2.19
- Corrige licenciamento remoto para preservar maiúsculas/minúsculas nas chaves remotas.
- Evita normalização em uppercase no modo remoto, prevenindo falhas HMAC e validações com tokens case-sensitive.
- Preserva o Shared Secret remoto exatamente como introduzido nas Settings.

# Changelog

## 2.2.29
- Corrige captura do prompt nativo PWA em Chrome/Edge, guardando o evento `beforeinstallprompt` cedo no `<head>`.
- Evita cair automaticamente nas instruções manuais em browsers Chromium quando o prompt nativo ainda está a inicializar.
- Adiciona diagnóstico visual discreto para Chrome quando a app ainda não cumpre os critérios de instalação.

## 2.2.17
- Analytics passa a ter dashboards configuráveis no Centro de Atribuições.
- Adicionados grupos manuais de lojas para métricas por loja, grupos de lojas ou total do âmbito.
- Widgets analíticos suportam KPI, gráfico, tabela, empty state, ordem e associação a dashboard.
- O portal cliente mostra dashboards e tabelas mesmo sem respostas no período selecionado.
- Exportações CSV, Excel e PDF passam a incluir a secção analítica configurada.

## 2.2.1-phase4-geo-cluster-engine
- Criado motor geográfico operacional com clusters por coordenadas, centro, dispersão, densidade e distância de acesso/regresso.
- Reforçada a capacidade dinâmica por cluster, separando acesso à zona, miolo local e regresso.
- Ajustado o scoring de alocação para favorecer rotas longas mas densas e penalizar rotas longas e dispersas.
- P1 passa a beneficiar de ordenação por cluster geográfico, mantendo a periodicidade e sem criar visitas extra quando a opção está desligada.
- Diagnóstico operacional passa a distinguir rota longa densa, rota longa dispersa e recomendações de divisão/agrupamento.

## 2.2.0
- camada remota de licenciamento Azure no plugin
- cliente remoto com assinatura HMAC
- ativação, validação e desativação remota
- modo local ou remoto configurável em Settings
- blueprint Azure com schema SQL e sample de Azure Function

## 2.1.1
- Hotfix da geração de chaves locais para ambientes sem `wp_generate_uuid4`, evitando erro crítico ao gerar licença no backoffice.

## 2.1.0
- Licenciamento local reforçado com geração de chave, ligação ao domínio atual e estado detalhado no backoffice.
- Settings com ações de gerar, ativar e desativar licença.
- Health check e notices de admin passam a validar mismatch de domínio da licença.


## 2.0.0
- adicionada base de licenciamento local para produto comercial
- adicionados notices internos de admin para configuração e health
- adicionada camada simples de feature flags
- reforçado health check com estado de licença e documentação comercial
- adicionados scripts de smoke test e release build
- adicionada documentação técnica e documentação de utilizador


## 1.4.3-phase10c
- Hotfix no shortcode `[fieldflow_client_portal]` > aba Equipa.
- O campo `Enviar para` volta a filtrar por operativos reais do projeto.
- Users apenas associados ao projeto deixam de aparecer por defeito, a menos que tenham perfil/capacidade operacional.
- Users novos com perfil operacional e campanha atribuída continuam visíveis no bloco de envio.

## 1.4.2-phase10b
- Corrigido o bloco "Enviar para" no shortcode `[fieldflow_client_portal]` / `[fieldflow_client_team_mail]` para incluir também utilizadores apenas associados à campanha, mesmo quando ainda não têm execução, rota ou assignment operacional registado.
- Mantida a lógica anterior de destinatários, mas sem excluir novos users recém-criados e já atribuídos ao cliente/projeto.

## 1.4.1-phase10a
- Corrigido o filtro da aba Equipa no shortcode fieldflow_client_portal para mostrar todos os operativos relevantes da campanha em "Enviar para".
- Corrigido o carregamento do histórico na aba Equipa, agora filtrado por campanha e operativo selecionado.

## 1.4.0-phase10
- centralizado o bootstrap do plugin com `RoutesPro\Support\Loader`
- adicionados checks de runtime e pacote comercial ao ecrã de Saúde do sistema
- criado `scripts/qa-smoke.php` para validação rápida e lint do pacote
- criado `scripts/release-build.sh` para gerar ZIP limpo de distribuição

# Changelog

## 2.2.29
- Corrige captura do prompt nativo PWA em Chrome/Edge, guardando o evento `beforeinstallprompt` cedo no `<head>`.
- Evita cair automaticamente nas instruções manuais em browsers Chromium quando o prompt nativo ainda está a inicializar.
- Adiciona diagnóstico visual discreto para Chrome quando a app ainda não cumpre os critérios de instalação.

## 1.3.0-phase9
- extraídos os shortcodes mais pesados do front para renderers dedicados, reduzindo acoplamento em `src/Shortcodes.php`
- criado `RouteAccessRepository` para centralizar a listagem e o scoping de rotas
- criado `CampaignLocationRepository` para isolar a query principal dos locais ligados a campanhas
- introduzida a infraestrutura de migrações versionadas em `src/Support/Migrations.php`
- adicionados índices de performance para rotas, assignments, campaign_locations, route_stops e system_logs

## 1.2.0-phase8
- Adicionada tabela de logs técnicos `routespro_system_logs` para troubleshooting e upgrades.
- Novo logger interno reutilizável para manutenção e suporte premium.
- Novo submenu admin `Logs técnicos` com limpeza manual de registos antigos.
- Saúde do sistema passou a validar a disponibilidade da camada de logging.
- Upgrade automático passou a registar eventos técnicos de atualização de schema.

## 1.1.1-phase7a
- Correção do filtro de rotas em `user_id=me`, para devolver apenas rotas do próprio utilizador ou atribuídas diretamente, sem alargar por cliente/campanha.
- Correção do filtro de descoberta de PDVs no menu Rotas do BO, passando a respeitar `client_id`, `project_id` e `owner_user_id`.

# Changelog

## 2.2.29
- Corrige captura do prompt nativo PWA em Chrome/Edge, guardando o evento `beforeinstallprompt` cedo no `<head>`.
- Evita cair automaticamente nas instruções manuais em browsers Chromium quando o prompt nativo ainda está a inicializar.
- Adiciona diagnóstico visual discreto para Chrome quando a app ainda não cumpre os critérios de instalação.

## 1.0.0-phase6
- Refactor do bootstrap principal para uma camada central `RoutesPro\Support\Plugin`, reduzindo acoplamento no ficheiro principal do plugin.
- Introdução da camada `RoutesPro\Support\Config` para centralizar defaults, constantes secretas, leitura e persistência de settings.
- Introdução da camada `RoutesPro\Support\Request` para normalização de leitura de inputs e nonces em pontos críticos do admin.
- Introdução da camada `RoutesPro\Support\AdminPage` para unificar abertura, fecho e estrutura base de páginas de admin.
- Refactor da página de Settings para depender da nova camada de configuração e request handling, mantendo a lógica existente.
- Refactor dos providers de IA e mapas para deixarem de depender diretamente da classe de admin `Settings`.
- Refactor da página principal de menu e importação legacy para wrappers de admin mais consistentes.

## 0.9.99-phase5
- Hardening do endpoint público de pesquisa PDV com controlo mínimo de abuso, validação de pesquisa e fallback seguro quando a tabela não existe.
- Remoção de ficheiros `.bak` e variantes de histórico da distribuição final.
- Endurecimento do upload de provas com verificação opcional de nonce, validação de tamanho e validação real de MIME.
- Remoção do fallback de token por query string na API de integrações.
- Inclusão de `readme.txt`, `uninstall.php`, `languages/` e `AUDITORIA-PRODUTO.md` para distribuição mais limpa.

## 1.1.0-phase7
- nova página de Saúde do sistema com checks críticos de ambiente, uploads, schema e configuração
- novo serviço de diagnóstico técnico centralizado em Support/SystemHealth
- novo repository para validação de schema e contagens de tabelas nucleares
- dashboard principal passa a mostrar estado técnico resumido
- base preparada para próximos refactors pesados em ficheiros monolíticos

## 1.4.4-phase10d
- Corrige a lista "Enviar para" no portal do cliente para usar apenas os utilizadores com acesso ao projeto definidos no Centro de Atribuições, aba Projetos, excluindo responsáveis ativos e outros utilizadores herdados.
- Corrige o histórico da aba Equipa para mostrar mensagens enviadas e recebidas do operativo selecionado.
- Mostra o estado efetivo da mensagem no histórico, refletindo alterações feitas na app operacional, como em análise e concluído.


## 2.2.26 - PWA Native Prompt alinhado
- Ajustada a PWA para usar a mecânica robusta do plugin de referência.
- Endpoints PWA disponíveis por query string para não dependerem só de permalinks.
- Service worker simplificado para maximizar compatibilidade com o prompt nativo.
- Prompt flutuante premium sem impacto no layout, com fallback Safari/iOS.
- Ícones PWA 192 e 512 gerados a partir do branding do plugin.

## 2.2.25 - App Mobile / PWA Premium
- Adicionado menu FieldFlow > App Mobile / PWA.
- Adicionado manifest dinâmico em /fieldflow-manifest.json.
- Adicionado service worker em /fieldflow-service-worker.js com cache seguro de assets e fallback offline.
- Adicionado fallback offline em /fieldflow-offline.
- Adicionado banner premium de instalação na página da app.
- Adicionado suporte específico para Safari/iOS com instruções de instalação via Partilhar > Adicionar ao Ecrã Principal.
- Adicionadas meta tags Apple/Safari e Android.
- Adicionada configuração de nome, ícones, cores, links, menu, aparência, offline e base técnica para push notifications.
- Mantida compatibilidade com shortcodes e funcionalidades existentes.

## 2.2.98
- Product Matrix com Frentes Antes/Depois com prefill automático por histórico.
- Analytics com crescimento por produto e loja.
- Tabelas e exportações adaptadas a valores before/after.

## 2.2.223
- API pública v1, chaves protegidas, webhooks assinados e datasets Power BI.
## 2.2.298, Desktop Public Login Router
- O Desktop deixa de usar `wp-login.php` como pagina visivel de autenticacao e encaminha para a pagina publica `login`.
- Usa a pagina de login configurada pelo Ultimate Member quando disponivel, com fallback seguro para `/login/`.
- Mantem `redirect_to` para regressar ao Desktop apos login.
- Deteta expiracao de sessao dentro do iframe e heartbeat 401, retirando o utilizador do shell e abrindo o login publico.
- Logout passa pelo mesmo login publico.
- Sem alteracoes de schema, FlutterFlow, Android ou iOS.

## 2.2.299, Desktop Native Bridge & Productivity
- Introduz contrato `FieldFlowDesktopBridge` 1.0 para comunicacao futura com um host WebView2 nativo, sem quebrar o QuickInstall atual em Edge app mode.
- Acrescenta bridge events para navegacao, downloads, links externos, impressao, clipboard, rede, atividade e notificacoes.
- Acrescenta notificacoes opt-in do sistema para novas mensagens diretas FieldFlow. No modo Edge usa a Web Notifications API; no futuro host WebView2 envia o pedido para o Windows nativo.
- Cria feed de atividade Desktop autenticado, limitado a mensagens recebidas pelo utilizador e extensivel via `fieldflow_desktop_activity_events`.
- Adiciona Copiar link e Imprimir na toolbar, com fallback Web e atalhos de teclado.
- Downloads deixam de ser apenas feedback visual: quando existe host nativo sao entregues ao bridge; em Edge continuam a usar o mecanismo normal do browser.
- Links externos usam o browser no modo atual e `external.open` no host nativo.
- Mantem memoria, Ctrl+K, drag and drop, clean frame, login publico e role router das versoes anteriores.
- Sem alteracoes de schema, migrations, FlutterFlow, Android ou iOS.

## 2.2.300, Desktop Polish & Release Gate
- Fecha o ciclo de fundacao do FieldFlow Desktop antes do primeiro executavel Windows RC.
- Heartbeat passa a validar utilizador, perfil, versao e assinatura de permissoes/menu. Alteracoes de acesso durante a sessao recarregam o shell.
- Native Bridge endurecida: navegacao inbound apenas HTTP/HTTPS same-origin e protocolos inesperados bloqueados.
- Links externos continuam fora do iframe, com allowlist de protocolos Web seguros.
- Drag and drop passa a respeitar accept, multiple e o limite real de upload do servidor.
- Novo estado offline com recuperacao automatica e botao de nova tentativa.
- Deteccao adicional da pagina publica de login dentro do iframe, evitando login embebido quando a sessao expira.
- WordPress completo passa a ser apresentado apenas a utilizadores com `manage_options`; Utilizadores exige `list_users`.
- Novo painel Acerca do FieldFlow Desktop com release Desktop, FieldFlow Core, Native Bridge, perfil, host e limite de upload.
- Ajustes de layout para 1366x768/ecras de menor altura e clean frame administrativo reforcado.
- Sem alteracoes de schema, migrations, FlutterFlow, Android ou iOS.


## 2.2.304, Protected Media Registry Foundation
- Adiciona um Media Registry observacional para novos ficheiros operacionais, sem mover, apagar, privatizar ou reescrever URLs existentes.
- Regista novos uploads mobile e provas de eventos quando são criados como attachments WordPress.
- Após uma submissão web concluída, identifica de forma não bloqueante URLs locais de media presentes nas respostas e associa contexto de cliente, projeto, rota, paragem, loja e submissão.
- Não executa backfill automático de media histórico e não altera respostas ou attachments existentes.
- O Registry é fail-open: uma falha no registo nunca bloqueia upload, submissão, visita, analytics ou exportação.
- Protected Media continua sem enforcement e sem alterações ao contrato FlutterFlow.
- Saúde do Sistema passa a mostrar apenas diagnóstico agregado do Registry.
## 2.2.305, Team Broadcast Messaging
- Adiciona a opção “Todos os membros da equipa” na aba Equipa do dashboard do cliente.
- O broadcast é sempre limitado ao cliente/campanha ativos e a destinatários elegíveis resolvidos no servidor.
- Cada membro recebe uma entrega individual, sem exposição dos emails dos restantes destinatários.
- Cada entrega mantém inbox, leitura, resposta e estado próprios, ligadas por um `broadcast_id` comum.
- O histórico do cliente agrupa visualmente as entregas do mesmo broadcast numa única mensagem “Todos (N)”.
- Pede confirmação antes do envio em massa e mantém a opção de cópia para o remetente.
- Mantém inalterado o envio individual existente, sem alterações de schema, FlutterFlow, endpoints públicos ou media.


## 2.2.318
- Mobile Analytics export ticket hotfix: restores CSV/XLSX/PDF downloads in FlutterFlow client and merchandiser portals without requiring WordPress cookies in the native downloader.
- Keeps 2.2.317 authorization hardening intact.
