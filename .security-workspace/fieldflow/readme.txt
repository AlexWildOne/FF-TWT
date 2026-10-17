=== FieldFlow ===
Contributors: thewildtheory
Tags: routes, logistics, field teams, reporting, elementor
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.2.659
License: Proprietary
License URI: https://thewildtheory.com

Plataforma profissional de rotas, equipas de terreno e reporting para operações comerciais e activações em campo.

== 2.2.659 — candidata NÃO CERTIFICADA ==

O Stable tag identifica o pacote, não comprova certificação. A cadeia técnica
N→N+1 espera 2.2.658 → 2.2.659; a versão instalada/certificada do utilizador
não foi verificada. Não assumir que 2.2.658 esteja certificada no ambiente real.
Não publicar diretamente em live nem executar deployments cloud por esta nota.

Antes de instalar: confirmar inventário e baseline reais; criar backup integral
da base de dados, uploads e media privada, mais cópia do código/configuração.
Restaurar esse backup num ambiente isolado e comparar contagens e SHA-256 dos
ficheiros (incluindo media privada) e evidências de integridade da BD.
Registar local protegido, data, versão, hashes, resultado e responsável;
não colocar dumps, media, segredos ou dados pessoais no ZIP.

Em staging isolado, com email/cloud pagos bloqueados, importar o ZIP canónico
pelo FieldFlow > Release Manager sobre a baseline 2.2.658 verificada.
Exigir Package Validator, manifesto/hash e Release Gate sem bypass; instalar
pelo updater privado e executar Revalidar cadeia 6B e Testar fecho Fase 6.
Testar navegação, offline/media, rotas, relatórios/exportações e isolamento:
anónimo sem acesso, sessão expirada/revogada, mudança de utilizador e tenant,
IDs/ficheiros de outro cliente e media privada sem exposição pública.
Arquivar evidências reais dos gates e testes antes de Certificar Stable.

Rollback normal repõe apenas o código anterior compatível, não a BD, uploads
ou media privada nem filas offline. Suspender escritas e investigar qualquer
incompatibilidade; restauro de dados é recuperação separada, autorizada e
reconciliada, nunca um downgrade automático. Sem acesso ao ambiente não há
prova de instalação, restauro, certificação ou autorização de live.

== Operative Dock Overlay & Gesture Recovery 9D7 ==

2.2.632 candidata: correção visual localizada da barra inferior do Operativo.
Reconhece gestos verticais nas WebViews mesmo quando não há evento de scroll,
reserva espaço final efetivo e mantém os portais, formulários e APIs intactos.
A 2.2.631 certificada permanece como base recuperável.

== Operative Dock & Status Parity 9D6 ==

2.2.631 candidate: paridade do recolher da barra inferior no Operativo em
scroll interno de modulos e scroll da janela; sem cortes nem alteracao de
fluxos. Melhorias pontuais de leitura de estados no Cliente, mantendo os
valores tecnicos originais. Compativel com PHP 8.4 e WebView Android/iOS.
Preserva a 2.2.630 Stable e todos os contratos de seguranca. Instalar apenas
apos validacao pelo Release Manager e QA real das plataformas.

== Visual Experience Consolidation 9D5 ==

2.2.630 candidate: compactacao visual mobile dos portais Cliente e Operativo,
correcao da barra de navegacao, filtros expansivos, formularios de frentes e
cartoes de exportacao. Apenas UX, sem migracoes, novas chamadas IA ou alteracao
nas regras de negocio. Instalar e certificar pelo Release Manager apos QA real.

== Commercial Production Hardening 9D4 ==

2.2.629 candidate: cache por permissões, paginação real de relatórios, filtros e retoma WebView isolados. Sem novas chamadas pagas. A certificação Stable requer instalação e testes reais.

== Description ==

FieldFlow centraliza planeamento, execução e reporting operacional para equipas de terreno.

== Installation ==

1. Na primeira instalação, carregar o ZIP canónico do plugin e activar o FieldFlow.
2. Em instalações já existentes, publicar actualizações sempre através de `FieldFlow > Release Manager`.
3. Para 2.2.659, cumprir primeiro o procedimento de candidata NÃO CERTIFICADA acima em staging; só após evidência real, instalar pelo updater privado e concluir `Revalidar cadeia 6B`, `Testar fecho Fase 6` e `Certificar Stable`.
4. Configurar e operar o FieldFlow no painel WordPress.

== Changelog ==

= 2.2.659 =
Candidata NÃO CERTIFICADA. Consolidação de segurança, persistência, routing, reporting e frontend; validação integrada e instalação staging pendentes. Continuidade técnica N+1 sobre 2.2.658, sem presumir a baseline real.

= 2.2.656 =
9DV: Order Suggestion integrity: verified zero fronts, manual quantity authority, shared web/native SKU validation, protected visit edits, single paired Fronts and historical Cardex compatibility.

= 2.2.655 =
9DU: Legacy Sonae group Cardex linked to CNT/MDL/CBD, global read-only audit of Cardex/store/form bindings, strict tenant/retailer safety.

= 2.2.654 =
9DT: Client/project Cardex selectors, live store/route context diagnostics, safe scope-only save.

= 2.2.653 =
* 9DS Visible Cardex Binding: Frentes e Sugestao de Encomenda nunca desaparecem silenciosamente do formulario por falha da ligacao ao cardex.
* Recupera apenas cardex historicos com insígnia comercial comprovada no nome, por exemplo Pernod Ricard · Sonae, sem misturar PDV ou subtipos.
* Quando a ligacao falha, apresenta aviso e respostas da propria visita apenas para consulta. Edicoes nao eliminam historico de Frentes.
* Sem migracoes nem alteracoes de stocks, rotas, despesas ou notificacoes de email. Validar em lojas reais antes da certificacao.


= 2.2.652 =
* 9DR Cardex Form Binding: restaura ligacoes historicas Sonae e Pingo Doce no Frentes e Sugestao de Encomenda.
* Protege subtipos CNT/MDL/CBD e a sincronizacao semanal, sem alterar as lojas selecionadas ou os historicos de visitas.
* Formularios multi-insignia devem utilizar Cardex automatico pela loja em ambas as perguntas.

= 2.2.651 =
* 9DQ Flexible Cardex Insignia Registry: selecionar ou escrever uma nova insígnia na duplicação (Poupança, Garcias, Malaquias, Central Cash), com registo reutilizável.
* Identidade exata de insígnia e PDV, proteção de CNT/MDL/CBD, rascunho isolado, validação explícita antes de publicar.

= 2.2.650 =
* 9DP Safe Cardex Clone & Publication. Duplicar cardex de origem para nova insignia em rascunho com novos IDs, sem copiar lojas, ajustes, stocks ou historicos.
* Publicacao explicita com verificacao de lojas e sortido, ou abrangencia global confirmada. Rascunhos nao entram no Portal Operativo ou no catalogo visual.
* Identidade de SKU e de insignia reforcada na edicao e importacao CSV. Sem migracao destrutiva de cardex existentes.

= 2.2.649 =
* 9DO Daily Close Recipient Reliability. Reprocessamento isolado de falhas parciais, identidade por rota e destinatario, e recuperacao manual com controlo de duplicados.
* Estados de envio indeterminados requerem confirmacao no SMTP antes de reenviar. Mail Center com diagnostico por rota e destinatario; Outbox obrigatoria para fecho diario.
* Sem alteracoes a Cardex, Stocks, rotas, formularios, barra Operativo ou app. Versao candidata para validar pelo Release Manager.


= 2.2.622 =
* 9CY Blind Inventory SKU Matching. A fotografia e lida sem enviar a lista de produtos candidatos ao fornecedor de IA.
* A correspondencia de marcas e variantes com Cardex passa a ser feita localmente, com revisao manual obrigatoria das variantes mais especificas.
* Uma garrafa de outra marca nao deve ser atribuida automaticamente a Jameson Black Barrel. Mantem quotas de IA e uma chamada por fotografia.

= 2.2.620 =
* 9CW Visual Evidence Accuracy. A leitura de frentes exige um rótulo visível e uma posição por garrafa para sugerir contagens positivas, também no Azure. Variantes semelhantes e capacidades diferentes exigem evidência distintiva.
* Uma posição de prateleira não pode ser contada em dois produtos. Leituras sem prova suficiente ficam para confirmação manual, sem introdução automática nem novas chamadas de IA. Os tokens de reenvio anteriores deixam de ser reutilizados.
* Sem migrações, sem novas chamadas automáticas, sem alteração das apps FlutterFlow. Limites de custos 9CS, pré-visualização 9CU, confirmação de eliminações 9CT e recuperação 9CV preservados.

= 2.2.619 =
* 9CV Report Context Recovery. Reportar e reporte rapido nao apresentam mensagens tecnicas de base de dados, SQL ou wp-config.php. Um problema temporario oferece retry manual com protecao contra repeticao, sem pedidos automaticos.
* Enquanto se tenta carregar outra loja, o formulario anterior e ocultado para impedir que dados sejam submetidos na loja errada. Rascunhos da visita em curso sao preservados e um formulario ja aberto e mantido se o refresh falhar.
* Cache offline limitada por utilizador, cliente, campanha, rota, paragem, loja e data. Sem migracoes, novos fornecedores ou novas chamadas IA. O plugin nao corrige uma falha real de MySQL no alojamento.

= 2.2.618 =
* 9CU. Rever a fotografia no telemóvel antes de autorizar a análise com IA. No modo ao vivo, a estabilidade apenas capta uma imagem, sem chamar a IA automaticamente.
* Avaliação local e gratuita da luminosidade, resolução e nitidez, com avisos antes do envio. O operativo pode repetir a fotografia ou usar contagem manual.
* Seleção de até 12 produtos por fotografia nos formulários de frentes; só os selecionados serão analisados e preenchidos.
* Respostas incertas, zeros pouco fiáveis e valores impossíveis obrigam a confirmação humana. Reenvio do mesmo pedido concluído recupera o resultado durante 15 minutos, sem segunda chamada ao fornecedor.
* Mantém orçamento por cliente e limites de utilização 9CS, confirmação de eliminação 9CT, histórico da visita e os demais módulos sem alterações. Sem migrações ou novas APIs. Sucessão 2.2.617 -> 2.2.618, aguarda certificação.

= 2.2.617 =
* 9CT. Confirmação segura e explícita antes de remover produtos do Cardex no formulário, fotografias novas, fotografias existentes, anexos selecionados e rascunhos.
* Modal acessível em telemóvel e WebView, com cancelar em destaque, identificação da ação e proteção contra toques repetidos. Esc e sair da app cancelam.
* As fotografias existentes continuam apenas marcadas para remoção até guardar; anular é imediato. Formulários, rascunhos e scroll mantêm as integrações anteriores.
* Sem novas APIs, custos de IA ou migração de base de dados. Sucessão 2.2.616 -> 2.2.617.

= 2.2.616 =
* Contagens de frentes: escolher, escrever e falar, com validação humana e campos únicos. A interpretação do texto é local e não chama a IA do FieldFlow. Microfone do browser quando disponível, com ditado do teclado como alternativa.
* Limites rígidos de utilização de IA por cliente: orçamento operacional reservado, limite diário e mensal, tudo bloqueado por defeito até autorização expressa no BO. Reserva contabilizada antes de cada chamada, incluindo falhas. Sem garantia de preço real do fornecedor.
* Integridade: zero distinto de produto não reconhecido; confirmação guardada antes de aplicar; associação da leitura ao formulário, data, utilizador, rota, paragem e projeto certos; verificação do Cardex/briefing no servidor.
* Privacidade: fotografias da prateleira usadas pela IA a partir de upload temporário sem persistência pública (referências fotográficas de produto continuam no catálogo).
* Sem alteração de schema ou código FlutterFlow. Requer validação e certificação 6B, Fase 6 e Stable na instalação.

= 2.2.615 =
* Fotografias nas perguntas (9CR): na app, "Escolher imagens" e "Tirar fotografia" passam a abrir a galeria e a câmara pelo método clássico das WebView. Se um toque não abrir nada, o segundo toque tenta a outra forma e ela fica memorizada.
* Se a câmara dentro da página não tiver autorização ou falhar, abre-se automaticamente a câmara do telemóvel.
* Botões de fotografia nunca ficam bloqueados numa pergunta visível; num reporte em consulta aparece a indicação para tocar em "Editar reporte".
* Diagnóstico: quando uma fotografia não chega ao formulário, o motivo (telemóvel, versão do Android/iOS, app ou browser, pergunta, loja e passos) fica registado em Segurança e observabilidade.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.614 -> 2.2.615.

= 2.2.614 =
* Cores do template no FieldFlow (9CQ): o tema do WordPress (Hello Elementor) deixava botões, contornos e o estado "tocado" em magenta. Os estilos do FieldFlow passam a carregar depois do tema e os botões do formulário, da câmara e das abas da app usam sempre as cores do template da campanha, também ao tocar ou passar por cima.
* Frentes legíveis no telemóvel: no Share Space cada lado fica numa linha (nome à esquerda, −/+ à direita) e o número aparece sempre; as referências acrescentadas (Ajustes da loja) passam a cartões com produto, categoria, referência e −/+; o grupo "Ajustes da loja" mostra a seta.
* Leitura por câmara: basta ligar "Leitura por câmara (piloto)" na pergunta para o botão aparecer, também em reportes já feitos (depois de "Editar reporte"). Por cliente: "Nas perguntas com a opção ligada" (por defeito), "Ligada (inclui Share Space)" ou "Desligada". Os gestores veem na pergunta porque é que o botão não aparece.
* Três formas de contar: "Contar ao vivo" (a câmara abre na folha e conta sozinha quando o telemóvel fica parado; a imagem não fica guardada), "Tirar fotografia" e "Escolher da galeria".
* Catálogo: nomes escritos de forma diferente por cada insígnia passam a ser o mesmo produto quando só mudam abreviaturas ou códigos da cadeia (MART. = MARTINI, BLK = BLACK, SDR, STD), palavras de embalagem (lata, RTD, bebida espirituosa) ou a capacidade sem unidade (700 = 70 cl). Variantes (Orange, Pink, Black Barrel, Regal) continuam separadas. O catálogo reorganiza-se ao abrir; novo "Exportar CSV".
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.613 -> 2.2.614.

= 2.2.613 =
* Catálogo geral por cliente (9CP): o catálogo deixa de ter um produto por insígnia. As linhas dos cardex de todas as insígnias e campanhas juntam-se pelo EAN, pelo código dentro da mesma insígnia e pelo nome comparado sem palavras genéricas (whisky, irish, gin, garrafa…), sem ordem das palavras e com a capacidade em cl (1 L = 100 cl, 0,7 L = 70 cl). O mesmo Jameson 70 cl do Continente, Pingo Doce e Auchan é um só produto.
* Os catálogos existentes reorganizam-se sozinhos depois da atualização (ou ao carregar em "Sincronizar com cardex"): os duplicados juntam-se mantendo imagens, nomes editados, mapeamentos do briefing e campanhas; produtos antigos sem imagem nem uso são removidos. Nomes editados no BO são mantidos.
* Cada produto mostra as insígnias onde existe e o EAN. Novo painel "Possíveis duplicados" para nomes muito diferentes entre insígnias: "Juntar" ou "São diferentes", nunca automático.
* Leitura por câmara: a folha da câmara mostra os produtos com imagem; os que não têm imagem aparecem primeiro com "Fotografar". A foto de referência tirada no terreno fica no Catálogo visual com a etiqueta "Terreno" e é usada logo na leitura e nas seguintes. Só para produtos sem imagem; ligável por cliente.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.612 -> 2.2.613.

= 2.2.612 =
* Fronts Camera Reading 9CO (piloto): nas perguntas de frentes com "Leitura por câmara (piloto)" ligada no construtor, e nos cartões Share Space do briefing, aparece "Ler com câmara". O operativo escolhe a categoria, tira a fotografia e a IA propõe as frentes por produto com as imagens do Catálogo visual.
* O operativo confirma ou corrige com −/+ e toca em "Preencher frentes"; os valores entram nos mesmos campos, como se tivessem sido escritos. Nada é submetido automaticamente. Sem rede, conta à mão.
* Cardex e Catálogo → Leitura por câmara: clientes no piloto (desligado por defeito), Share Space, limite mensal, teste com uma fotografia e histórico de leituras por loja (lido → confirmado, fotografia, fornecedor, tempo).
* Centro de IA: novo módulo "Leitura de frentes" (Azure OpenAI, OpenAI ou Gemini) e "Deployment para leitura de imagens" no Azure.
* Gemini: o modelo por defeito passa a gemini-3.5-flash-lite (a Google limitou os modelos 2.5 a contas antigas); o antigo default é mapeado e o 2.5 Flash/Pro tenta o sucessor em caso de 404.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.611 -> 2.2.612.

= 2.2.611 =
* Catalog Cross-Client Reuse 9CN: no Catálogo visual, "Reutilizar de outro cliente" copia produtos do catálogo de outro cliente para o cliente atual (manter o tipo, marca ou concorrência), com as mesmas imagens e sem duplicar ficheiros.
* Cada cliente continua com o seu catálogo: a cópia é independente. Produtos que já existem no cliente atual não são duplicados; ficam ligados à origem e recebem as imagens se ainda não tiverem. O editor mostra "Reutilizado de" e "Atualizar imagens da origem".
* Os mapeamentos do briefing continuam por cliente. A pesquisa por nome do Catálogo visual passa a filtrar corretamente.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.610 -> 2.2.611.

= 2.2.610 =
* Fronts Mobile Layout & Catalog Visuals 9CM: em mobile e tablet, cada produto do Cardex nas perguntas de frentes passa a uma linha compacta com a imagem do Catálogo visual, o nome, a referência e botões −/+ com teclado numérico. Com histórico da loja aparece "Antes N".
* Os campos do Cardex (categoria, referência, produto) continuam no formulário, com os mesmos nomes, em "Editar referência", que também mostra "Remover". O Cardex vivo por loja, os rascunhos, a validação, o zero automático, a sugestão de encomenda e o histórico não mudam.
* Share Space do briefing: cada lado mostra as imagens dos produtos mapeados no Mapeamento do briefing (9CL) e os dois campos de frentes têm −/+; em telemóvel marca e concorrência ficam lado a lado.
* Tocar na imagem abre-a em tamanho grande; sem rede aparecem as iniciais do produto. No construtor de formulários há "Não mostrar as imagens do catálogo visual nesta pergunta" (imagens ligadas por defeito).
* Sem alterações de schema, de dados submetidos ou FlutterFlow. Sucessão 2.2.609 -> 2.2.610.

= 2.2.609 =
* Visual Product Catalog 9CL: o menu "Cardex Produtos" passa a "Cardex e Catálogo", com os separadores Cardex (sem alterações), Catálogo visual e Mapeamento do briefing.
* Catálogo visual: lista única de produtos por cliente, extraída de todos os cardex e independente da loja, sem duplicados (referência ou nome + capacidade, incluindo "70CL", "70 cl", "0,7 L"). Atualiza-se sozinho depois de cada importação ou gravação de cardex e nunca apaga o trabalho manual.
* Cada produto aceita até 12 imagens de referência da biblioteca de media; a primeira é a principal e vai aparecer nas perguntas de frentes. Marca, categoria, EAN (completado a partir dos Stocks), tipo (marca ou concorrência), outros nomes e campanhas. Produtos duplicados podem ser juntos.
* Mapeamento do briefing: os rótulos de marca e concorrência das secções Share Space ligam-se a um ou vários produtos do catálogo, com sugestões e "Criar produto de concorrência". O mapeamento fica para os briefings das semanas seguintes.
* Formulários e app sem alterações nesta versão (a 9CM usa o catálogo nas perguntas de frentes). Sem alterações de schema ou FlutterFlow. Sucessão 2.2.608 -> 2.2.609.

= 2.2.608 =
* Planner & Commercial Google Cost Authority 9CK: no Descobrir (planeamento de rotas dos gestores), adicionar, remover, arrastar ou fixar PDVs e escrever a partida/chegada deixa de chamar o Google. A ordem é sugerida localmente e os km/tempo usam o mesmo estimador rodoviário calibrado do servidor, sem custo.
* O Google só é chamado no novo botão "Calcular percurso real" (uma chamada com a ordem atual). A otimização de waypoints Google só corre com a opção premium ligada. Guardar a rota continua a usar a otimização do servidor.
* Base Comercial: o botão Google deixa de pedir telefone e website de até 20 resultados por clique, salvo se o enriquecimento de contactos estiver ligado nas definições (desligado por defeito desde a 9BM).
* Remove o mapa Google invisível usado só para pesquisas (um carregamento de mapa a menos por ecrã) e a Nearby Search passa a complementar apenas pesquisas com poucos resultados.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.607 -> 2.2.608.

= 2.2.607 =
* Security & Hygiene Authority 9CJ: o formulário de contexto de uma loja (com o pré-preenchimento da última visita) passa a exigir a mesma autorização da submissão: acesso à rota e ao cliente/campanha. Pedidos sem acesso recebem 403. Operativos, gestores e reporte rápido autorizados não mudam.
* As pastas tools/, frontend/ e azure-* passam a incluir .htaccess que bloqueia o acesso web e a listagem (Apache/LiteSpeed). Em Nginx é necessária a regra equivalente no servidor.
* Datas por defeito seguem o fuso do WordPress (Europe/Lisbon) em vez de UTC: Minha Rota, Descobrir, Portal Cliente, analytics e filtros do BO.
* IA: o modelo Gemini por defeito passa de gemini-1.5-flash (descontinuado pela Google) para gemini-2.5-flash-lite; modelos Gemini 1.0/1.5/2.0 guardados nas definições são substituídos automaticamente por um modelo atual; a chave Gemini segue no header x-goog-api-key e não no URL. O Centro de IA mostra o modelo efetivo.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.606 -> 2.2.607.

= 2.2.606 =
* Visit Closure Selector Return & Route First Paint 9CI: depois de "Queres mesmo concluir esta visita?" → Sim, o Report deixa de reabrir a loja fechada. O seletor "Loja a visitar" volta a "Seleciona a loja", a loja fechada fica marcada como concluída e o ecrã sobe uma única vez até ao seletor.
* Novo cartão de fecho com a hora e o botão "Próxima loja" (primeira loja pendente pela hora planeada). Um toque abre essa loja; nada é selecionado automaticamente, para não haver check-in involuntário. O mesmo regresso aplica-se a visitas guardadas offline, visitas não realizadas, fecho sem formulário e "Guardar alterações".
* Minha Rota deixa de esperar pelo script do Google Maps para mostrar a rota do dia. O Google só é carregado quando o operativo abre "Adicionar paragem".
* O autocomplete legado de PDVs (tabela ff_pdvs, nunca criada pelo plugin) deixa de ser carregado, eliminando o "Sem resultados" no Descobrir (planeamento de rotas dos gestores) e na Base Comercial. Pode ser reativado com o filtro fieldflow_legacy_pdv_autocomplete_enabled.
* Sem alterações de schema ou FlutterFlow. Sucessão 2.2.605 -> 2.2.606.

= 2.2.605 =
* Expense Settlement, Payment & Signature Authority 9CH separa aprovação de pagamento com o novo estado Paga / Reembolsada, auditoria de data/utilizador e ação em lote no BO limitada a um operativo + período.
* A aba Despesas permite duas vias de comprovativo: upload de folha assinada em PDF/JPG/PNG ou assinatura manuscrita diretamente no FieldFlow. A assinatura direta gera e arquiva a folha PDF com assinatura e timestamp.
* O BO centraliza folhas assinadas com Ver/Exportar, e o pacote ZIP de despesas passa a incluir os comprovativos em folhas-assinadas/. Os fechos usam snapshot SHA-256 das despesas pagas e arquivo privado, sem nova tabela ou migração de schema.
* O operativo nunca pode marcar as próprias despesas como pagas. Pagamento continua autoridade exclusiva do BO. Sem alterações FlutterFlow. Sucessão 2.2.604 -> 2.2.605.

= 2.2.604 =
* Expense Statement Mobile Ticket Delivery 9CG corrige o download da Folha de Despesas PDF na app Android/WebView. O PDF deixa de depender da URL autenticada de admin-post no downloader Flutter genérico.
* A WebView prepara a folha ainda com sessão/nonce válidos, cria um ticket opaco de curta duração e entrega a URL sem cookies ao browser/gestor de downloads do sistema.
* O ticket aceita HEAD e repetições GET durante o TTL, preserva filtros, branding, totais e assinatura da 9CF e elimina o falso estado Download iniciado seguido de Não foi possível exportar o ficheiro. Sem alterações FlutterFlow ou schema. Sucessão 2.2.603 -> 2.2.604.

= 2.2.603 =
* Expense Statement PDF & Receipt Authority 9CF adiciona em Despesas uma folha PDF premium, pronta a assinar, construída apenas a partir das despesas canónicas já registadas no FieldFlow.
* O PDF respeita Mês, De/Até, categoria, estado e pesquisa ativos, agrupa custos por dia, mostra subtotais diários e total do período, identifica o operativo e usa branding TWT + cliente/campanha quando disponível.
* O Operativo exporta a própria folha no front/app. No BO, gestores podem selecionar um operativo e gerar a respetiva folha individual. A declaração final inclui valor, período, espaço de assinatura e data.
* A WebView relê filtros e scope cliente/projeto no momento do toque, sem alterações FlutterFlow. Sem nova tabela, migração ou duplicação de dados. Sucessão 2.2.602 -> 2.2.603.

= 2.2.602 =
* Analytics Header Date Export Authority 9CE: PDF, Excel completo/mobile e CSV passam a usar imediatamente os valores De/Até visíveis no header, tanto no portal Cliente como no Analytics Operativo.
* Os atalhos Esta semana, Semana anterior, Este mês, Mês anterior e YTD continuam suportados porque escrevem no mesmo par canónico De/Até. Datas introduzidas manualmente têm a mesma autoridade e deixam de cair no intervalo inicial de outubro.
* A WebView relê De/Até no instante do clique antes de criar o ticket de download, eliminando URLs de exportação desatualizados. O servidor valida e normaliza o intervalo recebido. Sem alterações de schema ou FlutterFlow.

= 2.2.601 =
* Adaptive Locale Dock 9CD: o globe aparece por completo ao abrir, recolhe durante o scroll e fica como pequena aba lateral com branding quando o scroll termina.
* Um toque/click na aba revela imediatamente o globe e abre o menu; hover/focus no desktop revela o controlo. Respeita safe areas e prefers-reduced-motion.
* Aplica o mesmo comportamento ao React principal e ao fallback legacy, sem alterar tradução 9CC, FlutterFlow, histórico de visitas, schema, routing ou custos. Sucessão 2.2.600 -> 2.2.601.

= 2.2.600 =
* App Shell & Browser-like Translation Continuity 9CC corrige o mount React do globe na WebView e mantém fallback até o componente premium estar realmente renderizado.
* Dashboard Cliente e Dashboard Operativo passam a reconciliar idioma/cliente em portal-ready, scope-change e resume da app, sem alterações FlutterFlow.
* Tradução browser-like cobre texto visível e conteúdo dinâmico React/AJAX, usando pack local, Translator API quando disponível e fallback FieldFlow com cache.
* Fora de zh-CN preserva nomes de lojas, moradas e nomes de produtos; em chinês permite tradução total. Preserva 9BD/9BZ/9CA/9CB e não altera schema. Sucessão 2.2.599 -> 2.2.600.

= 2.2.599 =
* Locale Brand & Associated Client Scope Continuity 9CB.
* O globe premium de idioma passa a herdar as cores do cliente/campanha em hover, focus, active, badge e painel.
* O idioma e a visibilidade do seletor passam a seguir o cliente ativo através de routespro:scope-change, sem reload e sem alterações FlutterFlow.
* Preserva 9BD, 9BZ e 9CA, sem alterações de schema. Sucessão 2.2.598 -> 2.2.599.

= 2.2.598 =
* Certification Marker Continuity 9CA restaura o marcador histórico 9BD esperado pela certificação Stable e liga o hotfix ao Release Gate e Package Validator, sem reativar a antiga política de prefill.
* Mantém integralmente a memória 9BZ: respostas elegíveis e Frentes reutilizam a última visita da mesma loja; fotografias, ficheiros, briefing e Sugestão de encomenda continuam excluídos.
* Sem alterações de schema, FlutterFlow ou comportamento operacional. Sucessão 2.2.597 -> 2.2.598.

= 2.2.597 =
* Premium Locale & Visit Memory Continuity 9BZ move o seletor de idioma do front/app para React + TypeScript com globe 3D premium e fallback seguro, aumenta a cobertura operacional PT/EN/ES/FR e mantém tradução por correspondência exata para não tocar em dados de clientes/lojas/produtos.
* Uma nova visita à mesma loja passa a pré-preencher as respostas elegíveis da última visita operacional, incluindo continuidade de Frentes, excluindo fotografias, ficheiros, briefing e Sugestão de encomenda. Web, app/WebView e extração de submissão usam a mesma autoridade e preservam o isolamento canónico por loja 9BJ.
* Sem alterações de schema ou FlutterFlow. Release Gate e Package Validator certificam a sucessão 2.2.596 -> 2.2.597.

= 2.2.596 =
* Multilingual Client Locale Authority 9BY adiciona Português, Inglês, Espanhol e Francês, configuração de idioma por cliente no BO, preferência por utilizador e seletor com bandeiras no front e app. O mesmo runtime funciona no WebView sem alterações ao FlutterFlow. Idioma e país operacional ficam separados, sem impacto em routing, geocoding, Google Maps ou periodicidade. Release Gate e Package Validator passam a certificar a capacidade 9BY, sem alterações de schema.

= 2.2.595 =
* Visit-Centric Reporting & Briefing Continuity 9BX mantém um relatório por visita terminal, incluindo não visitas justificadas, com briefing exato por loja e paridade entre Portal Cliente e exportações.

= 2.2.594 =
* Client Report Header Scope & Briefing Assignment Integrity 9BW faz Relatórios obedecer apenas aos filtros visíveis do header, sem herdar a rota aberta em Rotas. Ao selecionar um operativo, devolve todas as submissões do intervalo de datas aplicável. As perguntas de briefing entram no reporte apenas para a loja onde são aplicáveis e, quando há operativo selecionado, cruzam o mapeamento existente de lojas atribuídas. BO, Portal Cliente, PDF e Excel passam a manter a mesma leitura, com perguntas não respondidas visíveis como pendentes. Release Manager e certificação Stable validam o mesmo contrato, sem alterações de schema nem novas chamadas Google.

= 2.2.593 =
* Portal Export Runtime Integrity & Certification Parity 9BV corrige as exportações de Rotas, Relatórios e Analytics no Portal Cliente, impede que uma falha de links de exportação apague relatórios já carregados e acrescenta validação cruzada entre Release Manager e certificação. O portal Operativo é auditado no mesmo contrato, sem alterações de schema nem chamadas Google adicionais.

= 2.2.592 =
* Mobile Analytics Readability & Evidence Density 9BT: Cliente e Operativo ganham largura real de viewport em mobile, contraste corrigido, evidências recolhíveis, tabelas mais compactas e campos de pesquisa/pergunta com altura controlada.


= 2.2.590 =
* Executive Analytics & Route Financial Visibility 9BS: custos e percurso visíveis em Rotas BO, resumo executivo mensal no Analytics Cliente, comparação ao período anterior e explicabilidade da inteligência para Cliente/Operativo.


= 2.2.589 =
* Certification Foundation Continuity 9BR separa a certificação histórica 6B.1 da integridade da release ativa, evitando cascatas falsas de “por certificar” após updates.
* Revalidar 6B passa a validar e recertificar explicitamente 6B.1 antes de 6B.2, 6B.3 e 6B.4.
* Falhas 6B.1 passam a indicar exatamente quais pré-condições estão pendentes, com probe remoto read-only apenas quando necessário.
* Preserva 9BQ, não altera schema, licenças, ativações, routing, Google, formulários ou estados de visita.

= 2.2.588 =
* Order Mail & Visit Reopen Safety 9BQ elimina saídas silenciosas no email de Sugestão de encomenda e persiste estado explícito de entrega/retry/não aplicável.
* Uma quantidade positiva persistida governa o envio salvo quando a pergunta estava explicitamente escondida pela condição do formulário.
* Concluir uma visita pede confirmação explícita antes do fecho, mantendo Guardar alterações sem confirmação redundante.
* BO permite reabrir lojas concluídas/falhadas com motivo e auditoria, preservando reportes, respostas e fotografias e bloqueando reconciliação passiva do reporte antigo.
* Preserva 9BK a 9BP, não altera schema e não introduz chamadas externas.

= 2.2.587 =
* Daily Execution & Exception Control 9BP alinha Minha Rota e Reportar numa leitura canónica de execução, sem mutações automáticas.
* Deteta reporte sem fecho da visita, visita concluída sem reporte final, falha sem motivo, visita deixada aberta e pendências de dias anteriores.
* Os alertas de Minha Rota passam a abrir/fechar; no telefone ficam compactos por defeito, mantendo estado e contador visíveis.
* Operação diária ganha contador e tabela de exceções para coordenação, preservando integralmente 9BK a 9BO e sem chamadas externas.

= 2.2.586 =
* Daily Operational Priority 9BO adiciona foco diário em Minha Rota e Operação diária no Intelligence Center.
* A prioridade junta estado real das visitas, rota do dia e risco de periodicidade, sem alterar rotas e sem chamadas Google ou IA externa.
* Visitas em curso têm precedência, seguidas de paragens pendentes do dia e lojas críticas/atenção ainda sem cobertura.
* Mantém integralmente 9BK, 9BL, 9BM e 9BN e não altera schema, SubmissionPipeline ou custos de routing.

= 2.2.585 =
* Google Cost Control Center 9BN adiciona um circuit breaker financeiro antes do HTTP.
* Compute Routes Essentials continua permitido; Route Matrix, Pro e Enterprise/TOLLS ficam bloqueados por defeito e qualquer tentativa é registada como custo evitado.
* Segurança e Observabilidade passa a mostrar saúde da política Google, tiers, unidades Matrix, bloqueios, chamadas evitadas, orçamento e drift de configuração.
* Mantém integralmente 9BM, 9BL e 9BK sem refresh em massa de rotas nem chamadas Google durante a atualização.

= 2.2.584 =
* Places & Geocoding Cost Authority 9BM reutiliza endpoints já validados quando morada, coordenadas e país continuam coerentes, evitando geocoding repetido.
* Coordenadas derivadas de Google Geocoding/Places, incluindo endpoints, respeitam janela máxima de 30 dias; coordenadas próprias/importadas não expiram artificialmente e place_id é preservado.
* Enriquecimento automático de telefone e website via Google Places fica desligado por defeito e torna-se opt-in no BO.
* O endpoint REST de geocoding tenta primeiro uma localização FieldFlow conhecida no mesmo scope antes de consultar o provider externo.

= 2.2.583 =
* Toll Cost Intelligence 9BL estima portagens portuguesas 2026 a partir dos passos rodoviários da mesma chamada Compute Routes, sem ativar Google TOLLS Enterprise.
* Portagens passam a guardar classe, confiança, intervalo estimado e versão do tarifário, preservando custos de mobilidade e Daily Close.
* API Cost Ledger distingue Compute Routes Essentials, Pro, Enterprise e Route Matrix por tier real.

= 2.2.582 =
* Static Routing & Cost Authority 9BK: planeamento passa a usar por defeito a matriz rodoviária interna e deixa Google Route Matrix como opção premium explícita.
* Google Routes passa para TRAFFIC_UNAWARE na migração 2.2.581 -> 2.2.582; portagens Google e waypoint optimization tornam-se opt-in, mantendo distância/tempo rodoviário final e fallback interno de portagens.
* Rotas Google persistidas ganham assinatura operacional. Sem alteração de partida, chegada, ordem, lojas ou configuração, Analytics, exports e reaberturas reutilizam o snapshot sem refresh por idade.
* Snapshots Google 2.2.581 são adotados sem nova chamada paga. Alterações materiais continuam a marcar métricas stale e disparam uma única finalização Google.
* Fallbacks de planeamento deixam de recorrer a Distance Matrix do provider de mapas e usam o estimador rodoviário interno. Mantém schema 2.2.529 e SubmissionPipeline 2.2.485.

= 2.2.581 =
* Store Identity Authority 9BJ: a paragem da rota passa a governar a identidade canónica da loja no formulário, WebView e payload mobile.
* Histórico e reporte diário deixam de confiar cegamente em `form_submissions.location_id`, incluindo compatibilidade segura com o erro histórico `location_id = route_stop_id`.
* Um reporte com conflito de rota, paragem ou loja nunca carrega respostas, fotografias, ficheiros ou encomendas no formulário atual.
* Rascunhos passam a transportar uma impressão digital da loja/campanha/dia; snapshots sem identidade comprovada são mantidos para recuperação, mas não são restaurados automaticamente.
* Store Profile e última visita usam a mesma autoridade de identidade. Mantém schema 2.2.529 e SubmissionPipeline 2.2.485.

= 2.2.580 =
* Corrige a memória da loja para usar a última submissão operacional elegível, incluindo visitas do mesmo dia e excluindo o próprio reporte em edição.
* Fotografias, ficheiros e sugestões de encomenda não transitam para uma nova visita. Frentes usa o valor final da visita anterior.
* Web, mobile e gravação usam a mesma política, sem fallback para registos desatualizados; a edição mantém apenas os dados do próprio reporte.
* Protege o reporte rápido contra respostas de carregamento de outra loja. Manifesto, assets e continuidade 2.2.578 -> 2.2.580 atualizados.

= 2.2.578 =
* Open Day Planning & Visit Completion Sync 9BG: o dia operacional atual continua editável em Planear mesmo quando a rota já foi iniciada ou ficou concluída pelas visitas existentes.
* Lojas já concluídas, em curso ou não realizadas ficam visíveis com estado canónico e permanecem protegidas; lojas ainda pendentes podem ser ajustadas e novas lojas podem ser acrescentadas.
* O evento `fieldflow:visit-completed` atualiza imediatamente a loja em Planear para Concluída sem fechar o dia ao planeamento.
* Mantém 9BD, 9BE, 9BF, schema 2.2.529 e SubmissionPipeline 2.2.485.
* Phase 6 Stable avança SOURCE_RELEASE 2.2.577 -> STABLE_RELEASE 2.2.578, preservando o marcador histórico 2.2.576 -> 2.2.577 para o validador instalado.

= 2.2.577 =
* Certification Gate Literal Marker Hotfix: corrige o marcador estático 9BF que interpolava `$status` durante a certificação e podia procurar `if ( === 'justified')`.
* Mantém integralmente Visit Exception & Justified Non Visit 9BF, Planear 9BE, histórico/frentes 9BD e schema/submission certificados.
* Phase 6 Stable avança SOURCE_RELEASE 2.2.576 -> STABLE_RELEASE 2.2.577, preservando marcadores históricos para o validador instalado da 2.2.576.

= 2.2.576 =
* Visit Exception & Justified Non Visit Continuity 9BF: uma não visita pode ser justificada com motivo, observação e validade sem ser contabilizada como visita executada.
* Obras, encerramentos temporários e outras exceções operacionais deixam de alimentar o estado vermelho/crítico enquanto a justificação estiver válida.
* Exceções temporárias expiram automaticamente; encerramentos definitivos podem manter-se até reabertura manual.
* Paragens já marcadas como failed com fail_reason passam a justificar o respetivo slot de periodicidade, mantendo execução e cobertura real separadas.
* A periodicidade P1/P2/P3/P4 não é alterada. Mantém 9BD, 9BE, schema 2.2.529 e SubmissionPipeline 2.2.485.

= 2.2.575 =
* Planner Free Warning Continuity 9BE: Planear deixa de bloquear lojas atribuídas por estado/periodicidade durante a otimização.
* Sobreposições com outras rotas, dias bloqueados/preferenciais e intervalo mínimo passam a avisos explícitos, não a erros impeditivos.
* A validação de segurança mantém bloqueio apenas para lojas que não pertencem realmente à atribuição do merchandiser nem à rota existente.
* Guardar e otimizar usam a mesma autoridade de catálogo definida em 9BC; máximo de 8 lojas, datas válidas e proteção de rotas operacionais mantêm-se.
* Mantém 9BD, schema 2.2.529 e SubmissionPipeline 2.2.485.

= 2.2.574 =
* Form Visit Memory & Fronts Continuity 9BD: a nova visita volta a usar a última submissão operacional anterior da mesma loja/campanha como fonte de histórico, mesmo apos revisao do formulário.
* Respostas compatíveis aparecem como memória informativa; fotografias/uploads e Sugestão de encomenda ficam excluídos.
* Frentes volta a preservar a rotação `Frentes antes` / `Frentes depois` e normaliza `before`, `after` e `qty` na gravação Web e Mobile.
* Uma versão parcial do recorde deixa de apagar o histórico válido da loja.
* A memória da loja é projetada imediatamente apos COMMIT; a fila de reconciliação fica apenas como fallback.
* Mantém Planear 9BC, sem alterar schema nem o build certificado do SubmissionPipeline.

= 2.2.573 =
* Planner Canonical Assignment Catalog 9BC: Planear passa a mostrar sempre todas as lojas atribuídas ao merchandiser, independentemente do estado da ligação, histórico de visitas, execução ou planeamento anterior.
* A mesma loja pode ser planeada em datas diferentes do período, mantendo bloqueio de duplicação apenas dentro do mesmo dia.
* Rotas já iniciadas ou concluídas continuam visíveis como histórico, mas deixam de ser reenviadas quando se guarda apenas um novo dia de planeamento.
* Guardar envia apenas dias alterados, protegendo rotas fechadas e evitando conflitos com planeamento futuro.
* Sem alterações de schema, SubmissionPipeline, routing, custos, formulários, media ou mail.

= 2.2.572 =
* Planner Assigned Store Scope Continuity 9BB: corrige Planear quando existem lojas ativas atribuídas ao merchandiser mas a campanha ainda não está presente no ScopeIndex.
* A atribuição ativa de loja passa a resolver imediatamente o cliente/campanha e a carregar as lojas do próprio merchandiser, sem reatribuição nem migração.
* O ScopeIndex passa a persistir campaign_location em reconstruções futuras, mantendo uma ponte live para índices anteriores.
* Mantém as guardas de routing e paragens restritas às lojas atribuídas.
* Sem alterações de schema, routing, custos, formulários, SubmissionPipeline, media ou mail.

= 2.2.571 =
* Client Route Hero Date Authority 9BA: o intervalo De → Até do hero passa a ser a única autoridade temporal também em Rotas.
* Remove o segundo filtro mensal do calendário e do resumo de rotas; o calendário apresenta automaticamente os meses abrangidos pelo intervalo do hero.
* Lista, cards, totais financeiros, exportações e calendário passam a refletir exatamente o mesmo intervalo De → Até.
* Mantém os custos diários totais 9AZ e as matrizes móveis independentes Cliente/Operativo.
* Sem alterações de schema ou SubmissionPipeline.

= 2.2.570 =
* Route Calendar Daily Total & Mobile Portal Features 9AZ: o calendário de Rotas passa a mostrar o custo total diário real, somando todas as rotas do dia, em vez de apenas portagens.
* Dias com custos ainda não reconciliados mostram o total conhecido mais o número de rotas por calcular.
* Aplicação Mobile > Funcionalidades passa a ter matrizes independentes para Portal Operativo e Portal Cliente.
* A configuração anterior é migrada sem surpresas: ambos os portais herdam inicialmente os estados globais da 2.2.569.
* A WebView e o contexto mobile passam a respeitar a visibilidade do portal, mantendo compatibilidade com endpoints e builds antigas.
* Sem alterações de schema ou SubmissionPipeline.

= 2.2.569 =
* Client Portal Filter Authority Rebase 9AY: reconstrói o Dashboard Cliente sobre a semântica estável da 2.2.565 e mantém De/Até como fonte global de verdade.
* Abrir ou consultar uma rota deixa de bloquear, desativar ou reescrever o filtro de datas.
* Mantém os ganhos posteriores: custos por período, totais de rotas, decomposição financeira completa, Relatório Consolidado premium e Analytics Cliente vs Merchandiser.
* O Resumo regressa ao conjunto estável de KPIs da 2.2.565; performance da equipa fica isolada dentro de Analytics.
* Sem alterações de schema ou SubmissionPipeline.

= 2.2.568 =
* Client Portal Runtime Scope Continuity 9AX: corrige bindings locais de performance no Dashboard Cliente.

= 2.2.567 =
* Route Financial Breakdown & Audience Analytics 9AW: composição financeira completa, Relatório Consolidado e Analytics por audiência.

= 2.2.566 =
* Client Cost Date Scope & Route Totals 9AV: custos passam a respeitar De/Até e a lista de rotas ganha totais agregados.

= 2.2.565 =
* My Route Periodicity Action UX 9AT: prioriza o que falta cumprir por risco operacional, incluindo dias úteis restantes, gaps de periodicidade e atrasos semanais.
* O filtro `Só falta cumprir` fica persistente durante a sessão e o resumo mensal mostra lojas cobertas versus total.
* Cada loja por resolver pode abrir diretamente `Planear`, já na campanha e mês certos e com a loja exata focada ou pré-selecionada.
* Guardar o plano atualiza de imediato a cobertura mensal. Sem alteração de schema, routing automático, submissões, media, mail ou offline.

= 2.2.563 =
* My Route Periodicity Control 9AS: adiciona em Minha Rota um resumo mensal compacto da cobertura por loja, usando a periodicidade real da campanha (P1-P4), visitas canónicas concluídas e visitas futuras já publicadas.
* O merchandiser vê rapidamente lojas cumpridas, por resolver e sem visita, com filtros e estado "Coberta no plano" quando a periodicidade em falta já está garantida numa rota futura.
* Sem alterações de schema, routing, submissões, media, mail ou offline.

= 2.2.562 =
* WordPress Media Isolation 9AR: pedidos nativos de Media Library e image editor deixam de carregar o runtime operacional do FieldFlow, preservando memoria para GD/Imagick e impedindo que politicas de upload FieldFlow alcancem media de terceiros.
* O UploadGuard passa a registar filtros apenas em pedidos FieldFlow e deixa de usar o marcador generico form_id como criterio isolado.

= 2.2.561 =
* Condition Graph Integrity 9AQ: o builder mostra "Controlada por" em perguntas condicionais, explica semântica OR e avisa ligações suspeitas entre domínios funcionais como Conquistas e Encomenda, sem alterar o runtime certificado das condições.

= 2.2.560 =
* Report Status Canonical Sync: Reportar e Minha Rota passam a refletir o mesmo estado canónico da visita, com leitura live-first e proteção contra regressão visual por cache offline.

= 2.2.559 =
* Route & Mobility Dashboard Widgets 9AO: novos widgets configuráveis de execução de rotas, visitas, km, composição acesso/PDVs/regresso, combustível/energia, portagens, custo de mobilidade e mobilidade diária, todos alimentados pela mesma fonte canónica do Daily Close e exports.

= 2.2.558 =
* Routing Endpoint Geographic Integrity 9AN makes the campaign country authoritative for endpoint and PDV geocoding.
* Google and Azure geocoding are country-scoped, so ambiguous labels such as Montijo cannot silently resolve in another country.
* Legacy route endpoints and route PDVs are revalidated once before Daily Close/manual resend and exact route metrics are refreshed before Analytics, Excel, PDF and mail consume them.
* Centro de Atribuições gains a historical geography reconciliation action, including completed routes.
* Daily Close subjects now use the operational route/report day.
* Adds the 9AN certification gate and Montijo PT/ES regression coverage. No schema or SubmissionPipeline change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.557 -> STABLE_RELEASE 2.2.558.

= 2.2.557 =
* Full Route Mobility Reporting Reconciliation 9AM.
* Daily Close reconciles executed kilometres as access + between-PDV + return whenever the full route components are available.
* If an older/stale total contains only between-PDV kilometres, reporting corrects the projection without mutating the historical canonical route record.
* Fuel/energy estimates are recalculated on the reconciled full-route distance; non-API toll estimates are reconciled to the same full-route basis.
* Email shows the access, PDV-to-PDV and return breakdown per day and in the accumulated total. Excel Rotas and Mobilidade Diaria expose the same three components.
* Adds the 9AM certification gate. No schema, SubmissionPipeline, viewport, FlutterFlow or route-planning change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.556 -> STABLE_RELEASE 2.2.557.

= 2.2.556 =
* Known Mobility Reporting Recovery 9AL prevents closed routes with known kilometres from appearing as 0 km in Daily Close mail/Excel when canonical endpoint integrity is still partial.
* Reporting reuses MobilityCostService with known distance/tolls and explicitly marks recovered values as partial; canonical costs_available semantics remain unchanged.
* Mail, Excel and PDF no longer render unavailable mobility data as numeric zero: known values are recovered where possible, otherwise distance is shown as Por apurar and costs as Por calcular.
* Adds the 9AL certification gate. No schema, SubmissionPipeline, routing, viewport or FlutterFlow change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.555 -> STABLE_RELEASE 2.2.556.

= 2.2.555 =
* Daily Mobility Cost Reporting Continuity 9AK.
* Daily Close email now shows canonical kilometres, estimated fuel/energy cost and estimated tolls per day, plus accumulated totals for multi-day periods.
* Only operationally closed routes contribute to the metric labelled Km executados; open routes are excluded from executed-distance totals.
* Incomplete route distance or unavailable mobility cost marks daily and accumulated totals as PARCIAL instead of presenting false completeness.
* Excel gains a Mobilidade Diaria sheet with one row per day and TOTAL GERAL, while Rotas gains km, fuel/energy, toll and total mobility columns.
* Client, merchandiser and PDF reporting consume the same canonical mobility rollup. No mobility formula is recalculated in reporting.
* Adds the 9AK certification gate. No schema, SubmissionPipeline, viewport, FlutterFlow or route-calculation change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.554 -> STABLE_RELEASE 2.2.555.

= 2.2.554 =
* Briefing Store Scope Reporting Continuity 9AJ.
* Daily client and merchandiser briefing coverage now uses the intersection between question-to-store mapping and stores actually represented by the route/report scope.
* Questions mapped to stores outside the daily route no longer create false incomplete-briefing alerts.
* Canonical reporting exposes target, answered and pending briefing assignments plus target-store coverage.
* Client/merchandiser email and Excel Briefing sheet use the same scoped counts.
* Adds the 9AJ certification gate. No schema, SubmissionPipeline, viewport, FlutterFlow, routing or mail transport change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.553 -> STABLE_RELEASE 2.2.554.

= 2.2.553 =
* Mail Intelligence & Daily Commercial Reporting 9AI.
* Daily Close now builds a deterministic commercial interpretation from the canonical operational snapshot and adapts the narrative to the available data.
* Client email prioritises commercial execution, gains, losses, implementations, order opportunities and relevant exceptions. Merchandiser email prioritises personal execution, achievements and actionable alerts.
* XLSX/PDF exports gain dynamic Resumo Comercial, Visitas, Ganhos e Excecoes and merchandiser Alertas sheets, while empty domain sheets are omitted.
* Store-level visits explicitly expose executed/not executed state and justification.
* Legacy route notifications use one transport envelope per recipient. Shopping adds recipient-level idempotency and mail history distinguishes sent, queued and failed.
* Sent/cancelled outbox rows without retained attachments are eligible for bounded 90-day cleanup; failed rows remain available for investigation.
* Adds the 9AI certification gate. No schema, SubmissionPipeline, viewport, Native Shell or FlutterFlow change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.552 -> STABLE_RELEASE 2.2.553.

= 2.2.552 =
* Single Anchor Viewport Continuity 9AH.
* Form mutations, Cardex changes and conditional layout updates preserve one visual anchor with a single synchronous compensation, never a continuous scroll guard.
* Camera/gallery return restores the anchor before asynchronous media optimisation, removing the late up/down correction.
* Hot app resume keeps the mounted form DOM authoritative; cold reconstruction restores the durable draft and then the saved anchor once.
* Store selection captures its viewport before the native picker opens.
* Mobile resume hiding preserves document geometry by removing content-visibility collapse.
* Adds the 9AH certification gate. No schema, SubmissionPipeline, Native Shell, mail, routing or FlutterFlow change.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.551 -> STABLE_RELEASE 2.2.552.

= 2.2.551 =
* Passive Viewport Continuity hotfix: removes the 2.2.550 paint-mask/RAF scroll guard that could fight touch scrolling inside WebView.
* Store changes, camera/gallery handoff and Cardex mutations no longer write scrollTop as part of continuity handling; layout height is reserved while the browser/WebView remains the scroll authority.
* Native image picker inputs are kept fixed off-screen so focus return from camera/gallery cannot pull the document toward the file input.
* Mail flow, canonical reporting, Shopping, Order Suggestion, Analytics and attachment contracts remain unchanged from the certified 2.2.549/2.2.550 line.
* Phase 6 Stable identity advances SOURCE_RELEASE 2.2.550 -> STABLE_RELEASE 2.2.551 without schema, SubmissionPipeline or Native Shell changes.


= 2.2.550 =
* Native Picker Paint Continuity hotfix.
* Store selection and camera/gallery handoff now use a non-interactive viewport paint mask so WebView intermediate scroll frames are never exposed to the user.
* Removes executable report-shell position:fixed locking; the real scroll container is restored behind the mask and revealed only after two stable animation frames.
* Adds pre-picker continuity for the store selector and dynamic form selects, plus visual continuity for Cardex add/remove and conditional choice changes.
* Preserves all 2.2.549 mail/reporting behaviour without schema, SubmissionPipeline, Native Shell or FlutterFlow changes.

= 2.2.549 =
* Mail Flow Finalization & True Viewport Freeze 9AG.
* O fecho diário mantém KPIs do dia e acumulado no corpo, mas anexa apenas um XLSX e um PDF canónicos do dia, eliminando duplicação por período.
* Relatórios pessoais do merchandiser usam a totalidade das métricas operacionais do próprio utilizador, incluindo espaços extra e evidências, sem depender da visibilidade dos widgets da app.
* Shopping mantém XLSX + PDF obrigatórios e a sugestão de encomenda mantém XLSX + PDF obrigatórios apenas quando a condição do formulário é elegível.
* O transporte de mail deduplica caminhos de anexos como última proteção antes do envio.
* O formulário congela visualmente apenas o shell de reporte durante troca de loja e handoff de media, sem fixar o body da WebView, eliminando o padrão visível sobe/desce.
* Adiciona gate 9AG para os três fluxos de mail e continuidade visual, sem alteração de schema, SubmissionPipeline, Native Shell ou FlutterFlow.

= 2.2.548 =
* Certification, Viewport Lock & Mail Template Recovery.
* Restores certified Mobility Cost labels in the operational Analytics route table and aligns package validation with the installed Release Gate.
* Replaces visible viewport correction with a hard scroll-container lock during store changes, media handoff and field-form continuity transitions.
* Fixes the React operational fallback so “Operação pronta” remains readable instead of white-on-white.
* Recovers missing daily-close Mail Center schedules from persisted project/global email templates without reactivating deliberately paused schedules.
* Manual route-close delivery can reconstruct a dormant report template and keeps XLSX/PDF as the operational default.
* Adds Phase 9AF certification parity for Mobility, viewport lock and mail-template recovery without database schema, routing or FlutterFlow changes.

= 2.2.547 =
* Canonical Operational Reporting, Delivery Reliability & Viewport Continuity 9AE.
* Analytics, email, Excel, PDF e CSV passam a usar o mesmo snapshot operacional/comercial canonico.
* KPIs principais passam a separar execucao real de fecho operacional, destacando routing, frentes, espacos extra, evidencias, encomenda e briefing.
* Excel reorganizado em Resumo Executivo, Rotas & Visitas, Frentes, Espacos Extra, Encomendas, Briefing e Evidencias.
* PDF e email passam a apresentar uma leitura operacional curta e coerente com os mesmos KPIs.
* Daily Close ganha reconciliacao de envios perdidos e retries duraveis; automacoes atrasadas entram na fila persistente FieldFlow.
* Configuracoes antigas sem formatos passam a XLSX + PDF e anexos pedidos tornam-se fail-closed.
* O formulario usa o contentor real de scroll e mantem o formulario atual ate o novo estar pronto, evitando o salto para cima na troca de loja.
* Fotografia, Cardex e resume reutilizam o mesmo contrato de viewport v2.
* Sem migracao de base de dados; mantem LATEST_SCHEMA_VERSION 2.2.529, SubmissionPipeline BUILD 2.2.485 e Native Shell 1.10.

= 2.2.546 =
* Form Viewport & Shopping Delivery Integrity 9AD.
* O formulário preserva a posição visual durante troca de loja, resume, fotografia, alterações condicionais e operações Cardex.
* A troca de loja protege temporariamente a altura do formulário para impedir o salto do browser para o topo.
* Email, Excel e PDF de Shopping passam a usar o mesmo snapshot canónico da submissão.
* Se Excel/PDF configurados não puderem ser gerados, o email não é enviado incompleto e a falha fica visível no Mail Center.
* Excel Shopping reorganizado com resumo vertical e análise clara de preço regular, promoção, diferença e variação.
* PDF Shopping reorganizado por resumo, preços verificados e observações, com leitura mais clara por artigo.
* Sem migração de base de dados; mantém LATEST_SCHEMA_VERSION 2.2.529, SubmissionPipeline BUILD 2.2.485 e Native Shell 1.10.


= 2.2.545 =
* Resume Certification & Media Integrity 9AC.
* O gate histórico 9S passa a validar capacidade de resume successor-safe e deixa de exigir `focus` puro.
* ReleasePackageValidator e ReleaseGate passam a validar a mesma implementação guarded `focus-after-hidden`.
* O checkpoint após fotografia preserva explicitamente a data operacional da visita.
* A fotografia original fica protegida em IndexedDB num namespace de recuperação separado antes da otimização.
* O runtime offline prefere a cópia otimizada ativa e usa o original apenas como fallback se a ativa faltar.
* Remove persistência duplicada após otimização e limpa contexto antigo quando o utilizador limpa a seleção de loja.
* Sem migração de base de dados; mantém LATEST_SCHEMA_VERSION 2.2.529, SubmissionPipeline BUILD 2.2.485 e Native Shell 1.10.


= 2.2.544 =
* Report Selection & Media Continuity 9AB.
* A escolha manual da loja no dropdown Reportar passa a ser soberana sobre qualquer checkpoint de resume anterior.
* Eventos `focus` do seletor nativo Android deixam de ser tratados como retoma da app sem uma transição real para background.
* O próprio formulário deixa de reconciliar rascunho/passo em `focus` isolado, evitando saltos durante dropdown, câmara ou galeria.
* Um formulário de reporte já montado tem precedência sobre contexto antigo, evitando saltos de loja/passo durante o preenchimento.
* Fotografias escolhidas/capturadas são gravadas em IndexedDB e o rascunho/passo é checkpointado imediatamente após a seleção e novamente após otimização.
* Mantém a continuidade 9AA, logout seguro e sem alteração FlutterFlow nesta fase.
* Sem migração de base de dados; mantém LATEST_SCHEMA_VERSION 2.2.529, SubmissionPipeline BUILD 2.2.485 e Native Shell 1.10.


= 2.2.543 =
* Field Resume Navigation Continuity 9AA.
* O portal operativo mantém um checkpoint durável e isolado por utilizador para aba ativa, visita de reporte e vista da rota.
* Antes de abrir câmara/galeria, o formulário força a proteção do rascunho e do passo atual.
* Após suspensão ou recriação da WebView, o plugin restaura Reportar, a visita e o passo do formulário em vez de regressar a Minha Rota/Operação de Campo.
* Logout real limpa o checkpoint para impedir restauração entre sessões.
* Sem migração de base de dados; mantém LATEST_SCHEMA_VERSION 2.2.529, SubmissionPipeline BUILD 2.2.485 e Native Shell 1.10.


= 2.2.542 =
* Submission Corrections & Audit Trail 9Z.
* Todas as correções administrativas exigem motivo explícito e passam por SubmissionCorrectionService.
* Alterações de respostas preservam versões anteriores e criam uma nova versão auditável do record.
* A ação de apagar é substituída por anulação lógica `void`, preservando submissão, respostas e histórico.
* Correções que invalidem a única submissão operacional de uma visita reabrem a paragem e reconciliam a rota sem reemitir route.completed nem Daily Close.
* RecordService mantém todo o histórico e aponta o estado atual para a última versão operacional válida.
* Sem migração de base de dados; mantém LATEST_SCHEMA_VERSION 2.2.529 e SubmissionPipeline BUILD 2.2.485.


= 2.2.541 =
* Submission Status & Operational Day 9Y.
* Centraliza estados operacionais finais em submitted/reviewed/approved, mantendo draft/rejected editáveis mas fora de métricas operacionais.
* Centraliza o dia operacional com prioridade report_day, data da rota e só depois submitted_at como fallback histórico.
* Alinha rota, BO, Analytics, API pública, Context Questions e Store Profile sem migração de schema.

= 2.2.540 =
* Certification Continuity for Visit Closure after 2.2.539 installed successfully but Stable certification was blocked by historical literal markers.
* Operational Intelligence & Field Reliability now validates post-COMMIT Daily Close capabilities instead of obsolete comment text or `route_completed_now` inside EventsController.
* ReleasePackageValidator now mirrors the installed operational gate before publication, preventing package-pass/install-fail certification drift.
* Historical Operational Intelligence and Mail Reliability Outbox verifiers are successor-safe while preserving their original minimum contracts.
* No functional visit-closure, routing, schema, Mail Outbox transport or SubmissionPipeline behavior change.

= 2.2.539 =
* Visit Closure & Daily Completion Mail hardening for field route closure.
* Only submitted, reviewed and approved reports may close an executed visit; draft and rejected reports can no longer reconcile a stop to done.
* A failed/non-visited stop is terminal only when it carries a non-empty justification, allowing justified non-visits to close the operational day without counting as executed visits.
* All supported stop-completion paths converge on the same idempotent Daily Close queue after the persisted route is operationally complete.
* Daily Close gains durable retry scheduling, Mail Outbox idempotency, additional Mail Center recipients and a stable route-completed fallback when no Daily Close automation owns the route.
* Keeps LATEST_SCHEMA_VERSION 2.2.529 and SubmissionPipeline BUILD 2.2.485 unchanged.

= 2.2.538 =
* Canonical Answer Semantics for Web, Mobile and offline/preflight form paths.
* Numeric, currency and percentage answers now share locale-aware parsing and server-side min/max enforcement.
* Invalid numeric values are rejected instead of becoming zero or disappearing silently.
* Select, radio, visual and multi-choice answers are validated against the current schema option domain.
* Checkbox values normalize to canonical 1/0 semantics and malformed structured matrix payloads fail closed before specialized validation.
* Keeps LATEST_SCHEMA_VERSION 2.2.529, Routing/P1-P4, Cardex logic, Mail Outbox and SubmissionPipeline BUILD 2.2.485 unchanged.

= 2.2.537 =
* Order Source & Cardex Consistency hotfix for field order suggestions.
* Explicit store-to-Cardex links now remain resolvable when route/client/project context is temporarily incomplete, while known conflicting scopes are still rejected.
* Adds normalized PD/Pingo Doce, CBD/Continente Bom Dia, CNT/Continente and MDL/Modelo aliases as safe automatic fallback when no explicit Cardex scope exists.
* Locks each order suggestion to its paired Fronts question and makes manual merchandiser quantities sovereign through resume, submission and email reporting.
* Aligns Web and Mobile availability so order_suggestion is not discarded only because Cardex resolution is temporarily unavailable.
* No database schema, Routing/P1-P4, Mail Outbox transport or SubmissionPipeline BUILD change.

= 2.2.536 =
* Certification Gate Continuity hotfix after the installed 2.2.535 emergency release failed Stable certification on two historical/literal markers.
* Security & Storage Hardening now validates WebView native shell capability semantically at >=1.9 instead of pinning the obsolete literal 1.9, preserving native shell 1.10.
* Field Session & Form Continuity now validates the actual escaped Release Manager KPI label.
* Adds successor-safe Phase 9T certification contract; no terrain-session, form, routing, schema, reporting or SubmissionPipeline behavior change.

= 2.2.535 =
* Emergency Field Session & Form Continuity hotfix for active field teams.
* Makes rotating mobile refresh concurrency replay-safe during a short encrypted grace window instead of turning a duplicate refresh into logout.
* Field merchandisers no longer inherit the privileged 60-minute WebView idle policy; their portal remains bounded by the parent mobile session and configured WebView lifetime.
* Unifies app resume across native resume, visibility, focus and pageshow, restoring draft answers and the last wizard step after Camera, Gallery, Maps, lock/unlock or WebView recreation.
* Keeps the order-suggestion shell renderable when Cardex resolution is temporarily unavailable so paired Fronts can populate it later.
* No schema, Routing/P1-P4, Mail Outbox, reporting-calculation or SubmissionPipeline behavior change.

= 2.2.534 =
* Field Form Terrain Continuity hotfix for active field teams.
* Prevents mobile Enter/Done in Cardex/text-number controls from implicitly submitting the whole wizard and jumping to the first invalid step.
* Saves the local answer checkpoint before conditional reflow on every edit and makes wizard/conditional visibility updates idempotent.
* Reduces draft server delay to 900 ms and adds lifecycle sendBeacon protection for native app suspension.
* Adds stable local and conservative server recovery when route/stop ids are reconstructed after app resume.
* No schema, Routing/P1-P4, Mail Outbox, reporting-calculation or SubmissionPipeline behavior change.

= 2.2.533 =
* Operational Modernization Wave 2 for Academy, Expenses and Analytics.
* Academy front reads now use bounded batch state for courses, lessons, completion and mission status while preserving completion, certificate and mission-write semantics.
* Academy joins the React operational control shell with scoped search and panel lifecycle.
* Expenses uses explicit operational refresh events instead of React MutationObserver reconciliation.
* Premium Analytics removes the global DOM MutationObserver and enhances on explicit initial, lazy-panel, panel-open and analytics-rendered events.
* Adds successor-safe Phase 9Q contract fieldflow-operational-modernization-wave2-v1.
* LATEST_SCHEMA_VERSION remains 2.2.529 and SubmissionPipeline::BUILD remains 2.2.485.

= 2.2.532 =
* Frontend & Runtime Certification Continuity: fixes two certification-only identity mismatches found after installing 2.2.531.
* Frontend Build gate now validates the current package version instead of requiring the historical 2.2.524 marker.
* Canonical offline runtime is kept byte-identical to the current versioned runtime and package validation now rejects stale canonical/runtime identity before publication.
* No database, IndexedDB, Routing/P1-P4, form-core, Mail Outbox or SubmissionPipeline behavior change.

= 2.2.531 =
* Operational Modernization Wave for Briefings, Stocks, Shopping and Messages with event-driven React control-shell ownership.
* Adds Team/Client React mount parity for Briefings and Stocks, scoped Briefings cache and Shopping drafts, and Stocks/Shopping full-width coverage.
* Removes a client Stocks N+1 read and caches Shopping project membership reads without changing submission, mail, routing, offline or form-core semantics.
* Adds the successor-safe Phase 9O release contract and validator continuity.

= 2.2.530 =
* AI Governance Certification Continuity: fixes a Release Gate false negative that required database index names inside AIUsageRepository even though index ownership is correctly implemented in Migrations and SchemaManifest.
* Keeps the 2.2.529 AI attribution schema, budgets, FinOps and React Operational Modules v2 unchanged.
* Adds an explicit successor-safe 9N contract so repository behavior and schema/index ownership remain independently validated.
* No database schema change. LATEST_SCHEMA_VERSION remains 2.2.529 and SubmissionPipeline BUILD remains 2.2.485.
* Preserves Routing, P1-P4, conditional forms, Mail Outbox, offline/media semantics and direct Release Manager succession.

= 2.2.529 =
* AI Governance & Cost Control 2.0: native client/project/feature/request attribution, monthly FinOps breakdowns and 70/85/100 budget alerts.
* Adds configurable global, per-client and per-project monthly AI budgets with fail-closed enforcement at the configured limit.
* Adds additive AI usage schema attribution and indexed monthly reporting without storing prompts or commercial response content.
* Operational React modules gain a v2 presentation contract and explicit refresh lifecycle while legacy business actions remain canonical.
* Completes same-origin WebView fallback messaging in FormRenderer, Expenses and UI Runtime.
* Keeps SubmissionPipeline BUILD 2.2.485 and preserves routing, P1-P4, conditional forms, Mail Outbox and offline/media semantics.
* Release Package Validator remains successor-compatible so 2.2.530 can be published through Field Flow Release Manager.

= 2.2.528 =
* Security & Storage Hardening: adds a fail-closed REST scope guard for explicit client/project context while preserving existing object-level permissions.
* Mobile submission and briefing operations now enforce object permission callbacks before controller execution, with existing in-controller checks retained as defense-in-depth.
* Adds bounded daily governance for expired private resumable-upload sessions and read-only diagnostics for private FieldFlow storage. Evidence and active drafts are preserved.
* WebView browser fallback messages now target the current same-origin parent instead of a wildcard; the FlutterFlow native callHandler bridge remains unchanged.
* No database schema change and no Routing, P1-P4, FormRenderer, ConditionalFlow, Outbox or offline/media protocol behavior change. SubmissionPipeline remains 2.2.485.
* Package validation keeps 2.2.528 as a minimum contract boundary so successor releases remain installable through Field Flow Release Manager.

= 2.2.527 =
* Runtime & Database Performance: removes confirmed Academy N+1 query fanout with grouped batch reads and request-local scope caches.
* React bootstrap and shared UI runtime observers scope work to affected FieldFlow DOM areas instead of rescanning the full document on generic mutations.
* Performance & Load Readiness 1.2 adds a manual/CLI read-only FieldFlow database footprint with aggregate data/index size and largest tables.
* No database schema change, no automatic index creation, and no Routing, P1-P4, FormRenderer, offline/media or SubmissionPipeline behavior change.
* Release packaging remains successor-safe for direct installation through Field Flow Release Manager.

= 2.2.526 =
* Certification Asset Successor Continuity: Release Gate validates current versioned TypeScript and React client assets instead of pinning certification to 2.2.524 filenames.
* Mail Reliability / Outbox Foundation from 2.2.525 remains unchanged, including order-suggestion conditional eligibility and single retry ownership.
* Package validation treats 2.2.525 as the minimum Outbox contract release so future successor packages are not self-blocked.
* No schema migration, Routing, P1-P4, FormRenderer, SubmissionPipeline, offline queue or media behavior change.

= 2.2.525 =
* Mail Reliability / Outbox Foundation: persistent mail intent, bounded retry, atomic claims and optional idempotency around the canonical MailDispatchService.
* Adds an additive mail outbox schema and a Mail Center Outbox cockpit for backlog, failures, retry and cancellation.
* Order-suggestion and scheduled-report automations delegate transport retry to the outbox while preserving generation-level recovery and manual/test repeatability.
* Keeps WordPress wp_mail() as transport and preserves the 2.2.524 Mail Dispatch Foundation contract for validator continuity.
* No Routing, P1-P4, FormRenderer or offline/media core behavior change. SubmissionPipeline remains 2.2.485.

= 2.2.522 =
* Phase 9F Shared Portal Paint Stability: Client and Operative roots ship their structural responsive/Operational Experience state in the first HTML paint.
* Server-renders the shared command deck to avoid a post-paint layout insertion.
* Operative restores the canonical initial tab synchronously and both portals can prepare unloaded lazy panels before reveal.
* Lazy fragments become paint-ready after scripts/enhancers, and portal entry opacity/translate animations are disabled under the new contract.
* Release Gate 1.71.0 and Phase 6 Stable 1.0.79, SOURCE_RELEASE 2.2.521, STABLE_RELEASE 2.2.522. SubmissionPipeline remains 2.2.485.

= 2.2.521 =
* UI Composition Ownership: removes duplicate outer card chrome from the reported Client and Operative tabs while preserving existing DOM, lazy loading and module ownership.
* Analytics keeps full available panel width without changing the internal data-card and table-scroll semantics.
* Resume Diagnostics: keeps iframe/image/embed media behind the native resume guard and records bounded lifecycle metadata under independent feature flags.
* No Routing, P1-P4, FormRenderer, schema, Mail, AI, Azure, licensing or offline/media data-flow change. SubmissionPipeline::BUILD remains 2.2.485.

= 2.2.520 =
* App Resume Stability & Dual Portal True Full Width: alinha Cliente e Operativo no mesmo contrato de layout full width.
* Remove limites estruturais históricos e o breakout 100vw do Analytics operativo, mantendo scroll interno de tabelas e limites locais de leitura.
* Adiciona paint guard no WebView para background/foreground, resize apenas por largura e hardening de composição nos dois dashboards.
* Não altera Routing, P1-P4, Mobility, formulários, offline/media ou SubmissionPipeline::BUILD 2.2.485.


= 2.2.514 =
* Release Gate Runtime Source Authority: corrige o falso bloqueio runtime:repair_signature quando o source canónico já está atualizado mas a classe PHP em memória ou OPcache ainda reflete bytecode anterior.
* O source instalado só prevalece quando contém a assinatura certificada e a classe carregada vem da mesma pasta canónica; uma cópia alternativa do plugin continua a bloquear a certificação.
* Runtime stale da mesma instalação passa a aviso não bloqueante e invalida OPcache quando disponível. Não altera rotas, km, P1-P4, schema, offline, formulários ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.513 =
* Route Distance Finalization: após reconciliar endpoints, finaliza os km pela sequência completa Google Routes, partida, PDVs pela ordem gravada e chegada, mantendo a matriz híbrida apenas como fallback.
* Acrescenta diagnóstico explícito de Google exato, fallback de matriz e falhas de finalização, mais consistência entre distância total e componentes acesso, entre PDVs e regresso.
* Certification Continuity: preserva visualmente certificações históricas como CERTIFICADA, REVALIDAR após mudança de fingerprint, sem contornar a revalidação real da cadeia 6B ou da Fase 6 Stable.
* Não altera P1-P4, schema, IndexedDB, offline, formulários, Conditional Engine nem SubmissionPipeline::BUILD 2.2.485.

= 2.2.512 =
* Endpoint Repair Signature Compatibility: restaura a assinatura certificada de 5 parâmetros de repairPlannedRouteIntegrity(), resolvendo o bloqueio runtime:repair_signature da Fase 6 Stable.
* A migração forçada da Base habitual continua disponível através de repairPlannedRouteIntegrityWithOwnerProfile(), sem perder a correção em massa das rotas existentes da 2.2.511.
* Mantém compatibilidade com o validador 2.2.511 instalado, sem alterar P1-P4, schema, offline, formulários ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.511 =
* Owner Profile Route Migration: ao reconciliar um operativo selecionado, permite forçar em massa a Base habitual desse merchandiser nas rotas draft/planned do intervalo.
* Corrige rotas existentes que ficaram persistidas com o primeiro e último PDV como partida/chegada, mesmo quando esses endpoints antigos pareciam tecnicamente válidos.
* Rotas iniciadas, concluídas e canceladas continuam protegidas. Não altera PDVs, ordem, P1-P4, schema, offline, formulários nem SubmissionPipeline::BUILD 2.2.485.

= 2.2.510 =
* Semantic Edge Endpoint Reconciliation: identifica como legacy um endpoint da rota que tenha a mesma morada normalizada do primeiro/último PDV mesmo quando as coordenadas diferem mais de 30 m.
* A reconciliação passa assim a usar a Base da rota correta do operativo/campanha e a recalcular os km reais de acesso e regresso, sem aceitar falsos 0,1/0,2 km como rota completa.
* O limite geométrico mantém-se em 30 m para moradas diferentes. Sem alteração de P1-P4, schema, offline, formulários ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.509 =
* Endpoint Reconciliation & Explicit Address Reuse: corrige o erro crítico do botão de reconciliação e liga Google autocomplete à Base da rota no Centro de Atribuições.
* Partida/chegada preenchidas em Campanhas PDV, na própria rota ou no Planear continuam a ter prioridade; a Base da rota passa a ser apresentada explicitamente como fallback habitual, evitando duplicação desnecessária de moradas.
* Adiciona certificação para a assinatura do reconciliador, reutilização de endpoints explícitos e autocomplete, sem alteração de P1-P4, schema, offline, formulários ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.508 =
* Operational Endpoint Source of Truth: campanha/operativo passam a ter uma base de rota canónica, sem fallback silencioso para o primeiro/último PDV.
* Planear, Campanhas PDV, Rotas, REST, optimizer e reconciliação usam a mesma origem de partida/chegada.
* Rotas legacy podem recuperar extremos de histórico/audit log ou da Base da rota, sem alterar P1-P4 nem o schema.


= 2.2.507 =
* Route Financial Continuity separa avisos de confiança dos endpoints da calculabilidade financeira: rotas com partida/chegada roteáveis e todas as pernas resolvidas voltam a calcular portagens, combustível e custo de deslocação.
* Recupera automaticamente snapshots 2.2.506 com `known_distance_km` completo que ficaram bloqueados apenas por proximidade dos extremos, mantendo o aviso visível e sem inventar km.
* Extremos realmente em falta ou pernas não resolvidas continuam a bloquear custos. Sem alteração de P1-P4, schema, offline, formulário ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.506 =
* Route Endpoint Provenance & Legacy Reconciliation unifica a resolução de partida/chegada em Rotas, Campanhas PDV, otimização e reparação de rotas planeadas.
* Endpoints legacy que coincidam silenciosamente com o primeiro ou último PDV deixam de ser aceites como distância completa, salvo confirmação explícita de 0 km de acesso/regresso.
* O Centro de Atribuições reconcilia endpoints suspeitos de rotas draft/planned usando apenas defaults reais guardados, preservando a ordem dos PDVs e o histórico. Sem alteração de P1-P4, schema, offline ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.505 =
* Route Endpoint Coordinate Trust corrige coordenadas ocultas desatualizadas quando a morada de partida/chegada é editada manualmente.
* O servidor volta a validar/geocodificar os extremos antes de calcular acesso e regresso, persiste as coordenadas corrigidas e mostra os extremos efetivamente usados no planeador.
* Corrige os marcadores Mobility Cost Intelligence que bloqueavam a certificação Stable da 2.2.504, sem alterar P1-P4, offline, formulário ou SubmissionPipeline::BUILD 2.2.485.

= 2.2.504 =
* Route Endpoint & Distance Integrity fecha a distância financeira como percurso integral: partida → PDVs → chegada, sem transformar km parciais em total oficial.
* Rotas planned e geração de Campanhas exigem extremos reais; drafts podem ficar incompletas sem perder trabalho e o Centro de Atribuições inclui reparação segura usando apenas defaults já guardados.
* Combustível, energia, portagens, custo operacional, Analytics e exportações passam a distinguir custo calculado de distância incompleta, mantendo `known_distance_km` apenas para diagnóstico.
* Mantém P1-P4, offline, media, formulário e SubmissionPipeline::BUILD 2.2.485 sem alterações funcionais.

= 2.2.503 =
* Mobility Integrity & Canonical Metrics unifica a leitura de km e portagens em RouteMetricsService para rotas, mobilidade, portal, Analytics e exportações.
* Centro de Atribuições confirma a persistência por readback, mostra erros concretos de perfil e protege snapshots históricos; rotas draft/planned atualizam snapshot quando muda operativo/campanha/data.
* Normaliza aliases de estados de rota, corrige o backfill para excluir estados terminais e remove a fórmula JavaScript paralela de portagens do dashboard cliente.

= 2.2.502 =
* Mobility Cost Intelligence adiciona perfis de gasolina, gasóleo, GPL, híbrido e elétrico sobre os km e portagens canónicos do routing existente.
* Campanhas PDV definem o default; o Centro de Atribuição permite perfil por operativo, backfill de rotas existentes e override excecional por rota com snapshot histórico.
* Planeamento, front, Analytics e exportações passam a mostrar energia, litros/kWh, custo operacional, portagens e custo total de deslocação sem alterar P1-P4 ou a submissão certificada.

= 2.2.501 =
* Front Transition Stability elimina flashes de fallback React e de skeleton em módulos lazy rápidos.
* A ativação inicial do painel passa a ser idempotente e sem scroll automático, e o evento fieldflow:lazy-panel-loaded deixa de ser emitido duas vezes.
* Mantém routing P1-P4, offline, media, formulários, submissão e dados operacionais sem alterações.

= 2.2.500 =
* Corrige os Release Gates das Phase 8J/8K que ligavam comentários históricos do CSS à versão global corrente.
* Os gates passam a validar identificadores de fase, contratos e seletores funcionais estáveis, evitando novos bloqueios de certificação por comentários antigos.
* Mantém offline, resumable media, React ownership, submissão e regras P1-P4 sem alterações.


= 2.2.499 =
* Certification Runtime Identity Hotfix corrige o Release Gate que ainda procurava a identidade 2.2.497 dentro do runtime offline 2.2.498.
* A identidade dos assets versionados e a continuidade Phase 6 passam para 2.2.499, sem alterar IndexedDB, outbox, resumable media, regras de formulário ou submissão.
* Mantém SubmissionPipeline::BUILD em 2.2.485 e prepara uma verificação dedicada para impedir novos desvios entre plugin, assets e Release Gate.

= 2.2.498 =
* React Form Completion melhora Frentes/Cardex, Share Space, Sugestão de encomenda e Evidências/Media sem substituir os inputs e runtimes certificados.
* Frentes distinguem valores explícitos de zero automático, evitando falsas pendências; Evidências só ficam completas quando existe media ativa.
* Sugestão de encomenda reflete corretamente o estado condicional inativo e Share Space passa a resumir metas atingidas/abaixo do objetivo.
* Mantém ProductCardex, IndexedDB, recovery, resumable upload, validação, submissão, emails e regras P1-P4 como autoridades canónicas.

= 2.2.497 =
* Corrige a barra interna de navegação/progresso dos Briefings em mobile e WebView, preservando largura intrínseca das abas.
* Mantém as abas numa única linha com scroll horizontal natural, scroll vertical da página preservado e tap targets estáveis.
* Não altera conteúdo, mapping, confirmação de leitura, perguntas, offline, submissão, media, Shopping nem regras P1-P4.

= 2.2.496 =
* Corrige as abas/filtros de Implementações em mobile e WebView, com scroll horizontal controlado e botões sem compressão.
* Evita dispatch duplicado dos inputs React e sincroniza os contadores de Implementações com pesquisa + estado em conjunto.
* Mantém os runtimes nativos, offline, media, submissão, Shopping, rotas P1-P4 e fallback existentes como autoridades canónicas.

= 2.2.495 =
* React Operational Modules para Briefings, Implementações, Stocks, Shopping, Planear, Despesas e Mensagens.
* Pesquisa, filtros, estados, paginação e ações são apresentados por React e continuam a acionar os controlos nativos certificados.
* Mantém wp-element, fallback PHP/legacy e ownership canónico de offline, submissão, rotas, emails e media.

= 2.2.494 =
* Front Full Width & Navigation Consolidation: Briefings, Implementações, Stocks, Shopping, Planear, Despesas e Mensagens passam a usar uma superfície mobile partilhada, mais larga e consistente.
* O shell React passa a espelhar todas as abas autorizadas, sem o limite anterior de cinco atalhos, e corrige o proxy de navegação para ativar o botão canónico em vez de um painel passivo.
* Em mobile, listas operacionais prioritárias deixam de criar scroll vertical aninhado; tabelas e chips mantêm scroll horizontal apenas quando necessário.
* Mantém PHP tabbar, Typed Core, IndexedDB, recovery, media transport, ProductCardex, P1-P4, SubmissionApplicationService e SubmissionSideEffectDispatcher como autoridades canónicas.
* Adiciona o contrato fieldflow-front-fullwidth-navigation-v1, Release Gate 1.45.0 e Phase 6 Stable 1.0.51 com SOURCE_RELEASE 2.2.493.

= 2.2.493 =
* Modernization Stable Closure: final progressive React consolidation across BO, Analytics, operational shell, Minha Rota and field form.
* Adds React media capture/status surface that proxies the certified native camera/gallery/file controls instead of replacing media transport.
* Keeps IndexedDB, offline recovery, multiple-photo resilience, zero-byte repair, resumable upload, ProductCardex, submission and side-effect contracts canonical and unchanged.
* Adds Release Gate 1.44.0 and Phase 6 Stable 1.0.50 with SOURCE_RELEASE 2.2.492.

= 2.2.492 =
* React Choice & Advanced Form Wave: radio, checkbox, multiple choice and visual choice render as visible React controls while canonical native inputs keep conditional, validation, offline and submit ownership.
* Adds React advanced-field status surfaces for Frentes/Cardex, Share Space, Sugestão de encomenda and evidence/media without duplicating their business logic.
* Keeps ProductCardex, order-suggestion eligibility, media recovery, IndexedDB, REST and FlutterFlow contracts unchanged.
* Adds Release Gate 1.43.0 and Phase 6 Stable 1.0.49 with SOURCE_RELEASE 2.2.491.



= 2.2.491 =
* React Simple Field Controls: text, textarea, number, currency, percent, date, time and select now render as visible React controls while the canonical FieldFlow input remains the submission/validation/offline source of truth.
* Adds bidirectional value synchronisation, native invalid-focus handoff, dynamic disabled/min/max/step synchronisation and PHP/native fallback.
* Keeps Conditional Engine, Draft/Media, IndexedDB, submission payloads, REST contracts and FlutterFlow unchanged.
* Adds Release Gate 1.42.0 and Phase 6 Stable 1.0.48 with SOURCE_RELEASE 2.2.490.

= 2.2.490 =
* React Front Operational Wave adds a progressive operational shell, Minha Rota toolbar and live assist states for simple form fields.
* The route and form runtimes remain canonical; React reuses existing controls/events and keeps native inputs, validation, conditional logic, drafts, media, offline recovery and submission unchanged.
* React Front now remounts islands after lazy-loaded panels and dynamically rendered forms.
* Adds Release Gate 1.41.0 and Phase 6 Stable 1.0.47 with SOURCE_RELEASE 2.2.489.
* No database, IndexedDB, REST or FlutterFlow migration.

= 2.2.489 =
* React Front Foundation adds the first progressive operational-front island for form wizard progress/current-step presentation.
* Fixes the React Devices certification gate after authenticated transport moved to the shared React BO layer.
* The PHP form, typed validation, media, offline/drafts and submission runtimes remain canonical and fully available as fallback.
* No database, IndexedDB, REST or FlutterFlow migration.

= 2.2.488 =
* Release Continuity Authority removes historical per-component SOURCE_RELEASE pinning and validates N→N+1 once through Phase6StableRelease and ReleasePackageValidator.
* React BO Shared Foundation centralizes progressive props, authenticated transport and common helpers without bundling React/ReactDOM.
* React Analytics Foundation moves universal filters and Decision Score KPIs to a progressive React island with URL/context updates without full page reloads, while preserving the PHP fallback and existing Analytics 2.0 engine.
* No database, IndexedDB, REST, FlutterFlow or field-form migration.

= 2.2.487 =
* React Control Center Devices: the Devices backoffice gains a progressive React island with authenticated search, platform filter, pagination and device commands without full page reloads.
* DeviceManager remains the single backend source for PHP fallback and React; admin-AJAX actions reuse the same audited control logic.
* The server-rendered devices table, filters, pagination and forms remain available as a complete fallback.
* No database, REST, IndexedDB, FlutterFlow or field-form runtime migration.

= 2.2.486 =
* React Backoffice Foundation: first progressive React island in the Mobile Control Center, compiled from strict TypeScript and powered by WordPress `wp-element`.
* The publication-status card gains Android/iOS stateful navigation while preserving the original PHP card as a complete fallback.
* FieldFlow does not bundle React or ReactDOM; a kill switch can disable the React layer instantly.
* Release Manager adds `react_backoffice_foundation_ok`. No database, IndexedDB, REST, FlutterFlow or field-form runtime change.

= 2.2.485 =
* Submission Events & Side Effects: internal post-commit effects now run in isolated idempotent lanes with per-lane retry.
* Mail/order suggestion, EventBus/Azure, Performance, route completion and public extension hooks can no longer block each other or invalidate a durable submission.
* Legacy public hooks remain available; no database, REST, IndexedDB, Flutter or React migration.

= 2.2.484 =
* Submission Application Service: render, web submit and mobile submit now share canonical schema assembly and durable submission/answer persistence.
* Web/mobile transaction semantics are preserved, including the legacy mobile open-transaction boundary.
* Release Manager certifies the application-service integration before publication.

= 2.2.483 =
* Certification Continuity Hotfix: fixes a false Phase 6 Release Gate block caused by an exact documentation phrase check.
* Typed Specialized Validation remains unchanged functionally; Release Gate now checks structural browser/web/mobile markers.
* No database, IndexedDB, REST, Flutter or React migration.

= 2.2.482 =
* Typed Specialized Validation Core aligns Evidence, required media, categorized evidence, Share Space and required product-matrix validation across browser, web and mobile.
* Fixes a web/mobile parity gap where a required Share Space matrix could be partially submitted through the mobile API.
* Release Manager adds a dedicated Typed Specialized Validation gate. No database, IndexedDB, REST, Flutter or React migration.

= 2.2.481 =
* Typed Submission & Validation Core: frontend TypeScript, web PHP and mobile PHP now share active-question, required and persistence semantics through `fieldflow-typed-submission-validation-core-v1`.
* Required checkbox validation is consistent across web/mobile, while optional `false` remains an explicit persistable answer.
* Hidden conditional questions are excluded from active submission state and cannot block required validation.
* Release Manager adds a dedicated Typed Submission gate. No database, IndexedDB, REST, Flutter or React migration.

= 2.2.480 =
* Typed Form State + Conditional Engine: condicoes, estado ativo/oculto, required ativo e snapshot passam a ter core TypeScript strict.
* FormRenderer e Forms Runtime V3 consomem progressivamente o mesmo motor tipado, com fallback JavaScript certificado.
* ConditionalFlow PHP avanca para contrato v3 e partilha fixtures de paridade com o TypeScript, incluindo a regra que mantem Sugestao de encomenda inativa quando a resposta anterior e Nao.
* Release Manager valida `typed_form_conditional_core_ok`. Sem migrations de base de dados/IndexedDB, sem alteracoes Flutter e sem React nesta fase.

= 2.2.479 =
* Typed Draft & Media Core: snapshots, validade de rascunho, reconciliação e persistência IndexedDB passam a ter implementação TypeScript strict.
* Mantém `fieldflow-form-drafts` schema v2 e os mesmos stores, sem migração destrutiva; rascunhos e fotografias já guardados continuam compatíveis.
* O FormRenderer usa o core tipado quando disponível e mantém fallback integral para a implementação JavaScript certificada.
* A recuperação continua a cruzar localStorage, IndexedDB e servidor e preserva media anterior em handoffs zero-byte.
* Release Manager exige agora `typed_draft_media_core_ok`; Release Gate 1.30.0 acrescenta `fieldflow-typed-draft-media-core-v1`.
* Sem alteração de REST, base de dados WordPress, Flutter ou React. Phase 6 Stable avança de 2.2.478 para 2.2.479.

= 2.2.478 =
* Order Suggestion Mail Eligibility v2: o email deixa de confiar apenas no snapshot condicional gravado na submissão.
* Antes de enviar, o Mail Center cruza o snapshot com uma reavaliação das respostas realmente persistidas quando a definição do formulário continua a mesma.
* Se o snapshot disser ativo mas as respostas persistidas indicarem que a condição está inativa, por exemplo resposta “Não”, o envio é bloqueado.
* Snapshots contraditórios, incluindo uma chave simultaneamente ativa e oculta, falham de forma conservadora e não enviam email.
* Uma condição inativa deixa de ser tratada como falha de email e não cria retries automáticos.
* Release Manager valida o novo guard com `order_suggestion_mail_eligibility_ok`; Release Gate 1.29.0 acrescenta `fieldflow-order-suggestion-mail-eligibility-v2`.
* Mantém Typed Offline Core, REST, IndexedDB, Flutter, Cardex, Frentes, Analytics e base de dados sem alterações. Phase 6 Stable avança de 2.2.477 para 2.2.478.

= 2.2.477 =
* Typed Offline Core: migra para TypeScript as primeiras decisões críticas de conectividade, sync lock e outbox, sem substituir o runtime offline certificado.
* O runtime 2.2.477 usa `FieldFlowFrontend.offlineCore` quando disponível e mantém fallback JavaScript compatível com a 2.2.476.
* Rede `degraded` mantém-se utilizável, leases continuam protegidos por owner/token e o outbox preserva retry, ordem e recuperação de sincronizações interrompidas.
* O frontend passa a testar estes comportamentos automaticamente em TypeScript strict.
* Release Manager valida `typed_offline_core_ok` e bloqueia pacotes onde o bundle tipado não esteja compilado e ligado ao runtime da mesma release.
* Release Gate 1.28.0 acrescenta `fieldflow-typed-offline-core-v1`.
* Sem React, sem migração de base de dados e sem alteração de REST, Flutter, formulários, Analytics, Mail Center, rotas, Shopping ou Azure. Phase 6 Stable avança de 2.2.476 para 2.2.477.

= 2.2.476 =
* Frontend Build & TypeScript Foundation: cria uma fonte estruturada `frontend/` com TypeScript strict, build versionado e testes comportamentais.
* O build lê a versão diretamente de `fieldflow.php` e gera automaticamente o bundle `fieldflow-frontend-core-2.2.476.js`.
* O bundle compilado é isolado, idempotente em dupla avaliação e expõe `window.FieldFlowFrontend` sem substituir a lógica operacional existente.
* Release Manager passa a rejeitar bundles TypeScript com identidade diferente da release ou não referenciados por ResponsiveAssets.
* Release Gate 1.27.0 acrescenta `fieldflow-frontend-build-foundation-v1`.
* React fica deliberadamente fora desta release. Formulários, REST, IndexedDB, Analytics, rotas, Mail Center, Flutter e Azure mantêm os contratos atuais.
* Sem migração de base de dados. Phase 6 Stable avança de 2.2.475 para 2.2.476.

= 2.2.475 =
* Offline Runtime Hardening: corrige a identidade interna do runtime offline e garante alinhamento integral com a release 2.2.475.
* Sincronização passa a usar lease renovável com heartbeat, token por lease, proteção contra reentrada no mesmo WebView e Web Locks quando suportado.
* Redes móveis lentas classificadas como degraded continuam a poder sincronizar; apenas offline constitui bloqueio de rede.
* A data operacional offline passa a usar a autoridade WordPress/site timezone em vez de depender de UTC no browser.
* Release Manager passa a rejeitar pacotes cujo runtime offline versionado não declare a mesma versão da release ou não seja o runtime referenciado por ResponsiveAssets.
* Mantém os endpoints REST, idempotência server-side, ACK do formulário, IndexedDB/outbox, Flutter, base de dados, Analytics, Mail Center, rotas e Azure sem alterações de contrato.
* Sem migração de base de dados. Release Gate 1.26.0 acrescenta `fieldflow-offline-runtime-hardening-v1`. Phase 6 Stable avança de 2.2.474 para 2.2.475.

= 2.2.474 =
* Order Suggestion Conditional Mail Guard: a visibilidade condicional da submissão fica gravada no próprio reporte.
* Se a regra anterior ocultar a pergunta de Sugestão de encomenda, por exemplo resposta “Não”, o Mail Center não envia email nem anexos dessa sugestão.
* Reenvio manual e retries usam o mesmo guard. Submissões legacy sem snapshot são reavaliadas de forma conservadora pelas respostas gravadas e pelo schema do formulário.
* Se a condição voltar a permitir a pergunta e existir uma sugestão efetiva, o envio continua a funcionar normalmente.
* Mantém Cardex, Frentes, ordem do formulário 2.2.473, Analytics, ZIPs, offline, rotas e Flutter sem alterações funcionais.
* Release Gate 1.25.0 acrescenta `fieldflow-order-suggestion-conditional-mail-v1`. Phase 6 Stable avança de 2.2.473 para 2.2.474.

= 2.2.473 =
* Form Order Suggestion Placement: a pergunta Sugestão de encomenda deixa de ser promovida para um passo autónomo quando um schema legacy não a tem atribuída em `layout.steps`.
* Em formulários wizard, a sugestão é recuperada para o mesmo passo da pergunta de Frentes emparelhada e respeita a ordem funcional do schema. Nunca é apresentada antes da fonte de Frentes de que depende.
* O Form Builder passa a reparar e persistir perguntas BO sem passo, usando a pergunta vizinha mais próxima, evitando que o problema reapareça em futuras edições.
* Mantém a sincronização existente do Cardex e da lista final de referências. Frentes a zero, incluindo o zero implícito já definido pela regra operacional, continuam a preencher a quantidade sugerida, e quantidades manuais continuam a ter prioridade.
* Sem alterações Flutter, sem migrações de base de dados e sem mudanças nos contratos de Analytics, offline, rotas ou Mail Center.
* Release Gate 1.24.0 acrescenta `fieldflow-form-order-suggestion-placement-v1`. Phase 6 Stable avança de 2.2.472 para 2.2.473.

= 2.2.472 =
* Evidence ZIP System Browser Handoff: ZIPs de evidencias preparados deixam de usar o downloader Flutter generico que confirmava o handoff antes de a transferencia nativa terminar.
* Na app mobile, o ZIP preparado passa a abrir pelo handler externo ja existente `fieldflowOpenExternal`, com `systemBrowser` e capability ticket sem sessao, permitindo ao browser/gestor de downloads do dispositivo tratar o ficheiro diretamente.
* O fluxo de evidencias deixa de emitir o evento generico `fieldflow.download.requested`, evitando que o shell nativo classifique o ZIP como exportacao normal e apresente estados contraditorios.
* Operational Analytics e Portal Cliente usam a mesma estrategia; no browser normal mantem-se o download direto com fallback isolado.
* Mantem integralmente o layout full-width 2.2.471, tickets reutilizaveis, writer ZIP portatil, IA, rotas, formulários, Cardex e Mail Center. Sem alteracoes Flutter e sem migracoes de base de dados.

= 2.2.471 =
* Analytics Mobile Full-Width & Download State Continuity: os dashboards configurados no BO passam a ocupar a largura util do viewport na app mobile, com leitura mais ampla para KPIs, graficos, evidencias, tabelas e Ask FieldFlow.
* O scroll vertical do Analytics deixa de competir com areas internas de graficos, tabelas e conversa IA; apenas rails genuinamente horizontais mantem scroll proprio.
* Downloads de ZIP preparados passam a usar diretamente a ponte de download mobile quando disponivel, sem clique sintetico num link que podia ser reclassificado como exportacao geral.
* Estados de download deixam de misturar `Download iniciado` com `Nao foi possivel exportar o ficheiro`; o sucesso so e mostrado depois de a ponte confirmar o handoff ou o fallback browser concluir.
* Zero alteracoes Flutter, zero migracoes de base de dados e continuidade integral da 2.2.470.

= 2.2.470 =
* Corrige o HTTP 410 no download de ZIPs de evidências em Android/WebView depois de o ZIP já ter sido preparado corretamente.
* O download final deixa de depender do cookie de sessão e do nonce WordPress. Passa a usar um capability ticket opaco de 256 bits, curto e impossível de adivinhar na prática.
* O ticket pode ser reutilizado durante o TTL, permitindo HEAD, GET e retry do downloader nativo sem consumir o ZIP no primeiro pedido.
* HEAD devolve apenas cabeçalhos, GET mantém streaming em blocos de 1 MB e o ZIP não é apagado após o primeiro pedido.
* O token do grupo de evidências do Analytics passa a ter quatro horas por defeito, evitando expiração enquanto o ecrã permanece aberto no terreno.
* Mantém integralmente o writer ZIP portátil da 2.2.469, o REST de preparação, permissões, IA, rotas, formulários, Cardex, Mail Center e Flutter.
* Release Gate 1.21.0 acrescenta `fieldflow-evidence-zip-ticket-continuity-v1`.
* Phase 6 Stable avança de 2.2.469 para 2.2.470.

= 2.2.469 =
* Corrige definitivamente o download ZIP de evidências no Analytics com um writer ZIP STORE portátil em PHP, independente de ZipArchive/libzip no caminho principal.
* O front deixa de gerar o ZIP durante o download via admin-post. Envia apenas o token de evidências emitido pelo servidor para um pedido REST autenticado, prepara o pacote e só depois descarrega o ZIP final através de um segundo token temporário associado ao utilizador.
* ZIP da loja e ZIP da data usam o mesmo fluxo, incluindo grupos com uma única imagem, sem permitir que o browser escolha URLs arbitrários para empacotar.
* Mantém fallback para ZipArchive e PclZip apenas se o writer portátil falhar, em vez de parar quando uma extensão ZIP instalada está avariada ou limitada pelo hosting.
* A validação do ZIP passa a verificar diretamente Local Header, Central Directory e EOCD, sem depender do mesmo motor usado para criar o pacote.
* O resolvedor de evidências consulta também o Media Registry para recuperar o caminho local de fotografias históricas/recuperadas quando attachment_url_to_postid não resolve a origem.
* Não altera Flutter, formulários, rotas, Analytics IA, Cardex, Mail Center ou esquema de base de dados.
* Release Gate 1.20.0 acrescenta o contrato fieldflow-evidence-zip-runtime-v3.
* Phase 6 Stable avança de 2.2.468 para 2.2.469.

= 2.2.468 =
* Corrige o HTTP 500 ao descarregar ZIPs de evidências/fotografias no Analytics, removendo a cópia integral para staging antes da criação do arquivo.
* O ZIP passa a adicionar diretamente os ficheiros locais válidos, ignorar media vazia/ilegível, usar STORE em formatos já comprimidos e validar o pacote temporário antes de o expor ao browser.
* O download de ZIP é transmitido em blocos de 1 MB, sem compressão HTTP e sem session lock, com limpeza automática de ficheiros parciais/antigos.
* A telemetria Media Shadow deixa de executar centenas de leituras/escritas por fotografia no caminho crítico. O front mantém uma amostra limitada e a exportação integral usa o âmbito Analytics já autenticado.
* Excel, PDF, CSV e downloads de evidências preservam filtros, permissões, conteúdo e contratos existentes. Não existe migração de base de dados nem alteração Flutter.
* Release Gate 1.19.0 acrescenta `fieldflow-analytics-export-resilience-v1` e um verificador build-time dedicado.
* Phase 6 Stable avança de 2.2.467 para 2.2.468 e continua a exigir publicação canónica no Release Manager e revalidação 6B.

= 2.2.467 =
* Analytics passa a distinguir passado fechado, dia de hoje em progresso e rotas futuras planeadas. Trabalho futuro deixa de reduzir scores, gerar alertas de atraso ou ser avaliado como incumprimento.
* O Perguntar ao FieldFlow deixa de mostrar o balão azul da pergunta no dashboard operacional, Portal Cliente e Analytics 2.0 de gestão, mantendo o histórico interno da conversa e uma leitura visual mais limpa.
* O assistente ganha ajuda funcional determinística, incluindo como funciona o FieldFlow, rotas, Analytics, Cardex, sugestão de encomenda e fecho diário, sem exigir dados ou chamada de IA.
* Sugestões de encomenda legadas gravadas como manual vazio passam a herdar em runtime o mesmo Cardex válido da pergunta de Frentes. Listas manuais explícitas permanecem inalteradas.
* O fecho diário ganha fallback idempotente pós-COMMIT quando a última paragem é uma não visita justificada, preservando o evento canónico route.completed e evitando emails duplicados.
* Mantém a taxa de execução baseada apenas em visitas realmente concluídas e separa-a do fecho operacional, que aceita não visitas justificadas como terminais.
* Release Gate 1.18.0 acrescenta fieldflow-operational-intelligence-hardening-v1 e um verificador dedicado.
* Phase 6 Stable avança de 2.2.466 para 2.2.467. Não requer migração de base de dados nem alterações Flutter.

= 2.2.466 =
* Corrige o corte horizontal dos emails em Gmail, Outlook e outros clientes mobile sem depender de scroll lateral.
* O shell comum de email passa a ser fluido por atributo HTML e por CSS, mantendo largura fixa apenas no fallback condicional do Outlook desktop.
* Sugestões de encomenda deixam de usar flex e tabela larga. KPIs usam tabela fixa e cada produto passa a cartão responsivo com categoria, caixas e unidades.
* Shopping e Preços passa a usar tabela de dados com table-layout fixed, quebra segura de texto e preços sem white-space forçado.
* Relatórios automáticos, emails de rota, testes, reenvios manuais e newsletter partilham o contrato responsive_email_css e ff-email-viewport.
* Templates antigos são endurecidos no momento do envio para neutralizar tabelas de largura fixa, nowrap e imagens demasiado largas, sem alterar o template guardado.
* Release Gate 1.17.0 acrescenta fieldflow-responsive-email-rendering-v1 e um verificador dedicado.
* Phase 6 Stable avança de 2.2.465 para 2.2.466. Não requer migração de base de dados.

= 2.2.465 =
* Adiciona ao Mail Center a área Envio manual para recuperar emails históricos de fecho diário e sugestões de encomenda já gravadas.
* Permite reenviar para cliente + merchandiser, só merchandiser ou só cliente, usando os destinatários atuais associados ao cliente e o operativo persistido na rota/submissão.
* Testes manuais são enviados exclusivamente para o email indicado no BO. Nos fechos de rota é possível testar a visão pessoal do merchandiser ou a visão cliente desse merchandiser/dia.
* Rotas antigas tecnicamente em curso podem ser reconciliadas antes do reenvio. Não visitas justificadas são terminais, mas qualquer paragem ainda por resolver bloqueia o envio.
* A reconciliação manual mantém o evento de alteração de estado para integrações, mas suprime apenas o disparo automático route.completed nessa operação, evitando duplicar emails.
* Sugestões históricas regeneram Excel e PDF a partir da submissão original. O envio manual não interfere com claims ou retries automáticos.
* Envios manuais ficam auditados no histórico e não alteram submissões, métricas nem o estado last_run das automações. Templates em pausa podem ser usados manualmente sem serem reativados.
* Release Gate 1.16.0 acrescenta fieldflow-mailcenter-historical-recovery-v1, mantendo Release Manager, production manifest SHA-256 e certificação Azure obrigatórios.
* Phase 6 Stable avança de 2.2.464 para 2.2.465. Não requer migração de base de dados.

= 2.2.464 =
* Corrige a recuperação de múltiplas fotografias após reinício da app/WebView, reconstruindo os ordinais dinâmicos antes da reidratação IndexedDB, incluindo perguntas de imagem do Briefing. Recuperações incompletas passam a indicar explicitamente a contagem recuperada.
* Corrige a publicação server-side de media de rascunho para considerar todos os grupos `input_name|input_ordinal`, e não apenas o primeiro ordinal.
* Uma loja não visitada com justificação válida passa a ser terminal para o fecho operacional da rota, mantendo a taxa de execução baseada apenas em visitas realmente concluídas. O motivo de falha é validado no servidor.
* O email de fecho diário passa a nascer do evento canónico `route.completed`, permitindo enviar normalmente quando a última loja é uma não visita justificada.
* Briefing, Cardex, schema de formulário e bindings passam a invalidar o bundle offline através de uma revisão operacional única por projeto. O Cardex reconcilia também associações de loja obsoletas.
* A sugestão de encomenda passa a integrar Excel, CSV e PDF de Analytics e ganha automação própria no Mail Center. O operativo e os utilizadores associados ao cliente recebem a sugestão com Excel e PDF, mantendo assunto, introdução, cor, teste e destinatários adicionais editáveis. O controlo de envio é feito por destinatário, com retry apenas dos emails que falharem.
* Release Gate 1.15.0 acrescenta o contrato `fieldflow-form-reliability-core-v1` e um verificador de build para os cenários críticos, mantendo Release Manager, catálogo privado, production manifest SHA-256 e certificação Azure obrigatórios.
* Phase 6 Stable avança de 2.2.463 para 2.2.464. Não requer migração de base de dados.

= 2.2.463 =
* Auditoria de continuidade ponta a ponta dos formulários FieldFlow, incluindo perguntas normais, perguntas dinâmicas do briefing, evidências, Cardex, quick report, focused report, recuperação local, rascunho remoto e integrações pós-submissão.
* Respostas passam a ter uma segunda cópia durável em IndexedDB, além do espelho localStorage. A cópia visível do formulário deixa de ser apagada enquanto o servidor não devolver um ACK verificável de persistência.
* Fotografias do formulário e das perguntas do briefing são recuperadas diretamente do cofre IndexedDB ao criar a outbox, mesmo quando Android/WebView não consegue reconstruir visualmente o input de ficheiro.
* Rascunhos deixam de ser apagados por alterações de briefing/schema. Alterações compatíveis fazem rebase seguro, perguntas respondidas que tenham sido realmente removidas/renomeadas ficam em conflito sem perda da cópia local.
* O backup remoto de fotografias do rascunho passa a ser incremental, uma fotografia por pedido, preservando os índices já gravados quando a rede cai. Fotografias reais continuam sem teto artificial de MB e o envio final mantém blocos retomáveis de 512 KiB com SHA-256.
* Recuperação servidor/dispositivo deixa de apagar media local antes de existir substituição remota válida. Respostas atrasadas do servidor não podem sobrescrever um checkpoint local mais recente.
* A cache do quick report passa a incluir a versão FieldFlow. Integrações pós-submissão passam a ter até 3 tentativas no Queue Manager, mantendo a submissão canónica como fronteira de persistência.
* Phase 6 Stable avança de 2.2.462 para 2.2.463, com Release Gate 1.14.3 e certificação integral preservada. Não requer migração de base de dados.

= 2.2.462 =
* Corrige a origem do erro Android/WebView em que `canvas.toBlob()` podia devolver um Blob de 0 bytes durante a otimização e substituir uma fotografia válida. A fotografia original passa a ser sempre preservada quando a otimização falha ou produz 0 bytes.
* Rascunhos locais deixam de substituir uma cópia válida por um ficheiro vazio. Itens antigos com este erro tentam recuperar automaticamente a fotografia original a partir do IndexedDB `fieldflow-form-drafts` antes de nova sincronização.
* O runtime offline passa a ser servido por um novo caminho versionado (`fieldflow-offline-runtime-2.2.462.js`), evitando JavaScript antigo retido por WebView, PWA, proxy ou cache intermédia.
* Fotografias reais continuam sem teto artificial de MB, com blocos retomáveis de 512 KiB e validação SHA-256. Não é necessário limpar dados da app.
* Phase 6 Stable avança de 2.2.461 para 2.2.462, com Release Gate 1.14.2 e certificação integral preservada.

= 2.2.461 =
* Corrige placeholders File de 0 bytes criados por Android/Chrome/WebView em campos de fotografia vazios, que podiam bloquear o upload retomável com “A fotografia não contém dados para upload.”.
* Campos de ficheiro vazios deixam de entrar no outbox, manifesto de media, multipart final e transporte retomável. Fotografias reais continuam sem teto artificial de MB e são enviadas por blocos.
* Itens protegidos em IndexedDB provenientes da 2.2.460 são saneados antes da sincronização. Falhas causadas especificamente por este erro são reabertas automaticamente como pendentes, preservando as fotografias reais existentes no dispositivo.
* Phase 6 Stable avança de 2.2.460 para 2.2.461, com Release Gate 1.14.1, manifesto SHA-256 integral e cadeia de certificação preservada.

= 2.2.460 =
* Remove o limite artificial de tamanho por fotografia nos uploads de evidencias FieldFlow, mantendo validacao de formato, MIME, autenticacao e integridade.
* Todas as submissoes dinamicas com o runtime offline disponivel passam pelo outbox protegido e pelo transporte retomavel, evitando enviar 12 ou mais fotografias num unico pedido multipart.
* A otimizacao local de imagens passa a ser sequencial, uma fotografia de cada vez, para evitar picos de memoria com selecoes grandes em telemoveis.
* O transporte retomavel deixa de herdar upload_max_filesize/wp_max_upload_size e passa a aceitar fotografias segundo a capacidade real de armazenamento temporario do servidor, sempre em blocos pequenos.
* O diagnostico tecnico deixa de apresentar o limite PHP como bloqueio do fluxo normal de fotografias e identifica explicitamente a politica sem limite artificial.
* Phase 6 Stable avanca de 2.2.459 para 2.2.460, com Release Gate 1.14.0, production manifest SHA-256 e cadeia de certificacao de instalacao preservados.

= 2.2.459 =
* Shopping email can attach an XLSX and PDF generated from the exact store submission, including on manual resend.
* Shopping store discovery now learns explicit store-to-insignia mappings, backfills from Shopping history, uses stock/category evidence, and exposes safe unclassified project stores for one-time association in front.
* Shopping schema 1.3.0 adds the persistent insignia-location mapping table and attachment controls, while keeping Shopping shared across the whole project team.
* Phase 6 Stable continuity is aligned from source 2.2.458 to Stable 2.2.459, preserving Release Manager publication, SHA-256, licensing, heartbeat, governance and Azure-backed 6B gates.
* Release Gate 1.13.0 validates the new Shopping attachment, learning and certification contracts.

= 2.2.458 =
* O email de Shopping passa para um layout newsletter compatível com Outlook, baseado em tabelas e estilos inline.
* Cabeçalho TWT/FieldFlow, preheader, métricas de preenchimento, tabela de preços mais legível e bloco de observações passam a fazer parte do envio.
* Mantém assunto, corpo e tokens personalizados por lote, bem como o envio auditado por destinatário e o reenvio manual.
* Corrige um falso bloqueio do Release Gate Resumable Media Transport causado por um marcador de código com escaping incorreto.
* O diagnóstico de certificação passa a mostrar os marcadores concretos em falta quando um contrato de release falha.
* Release Gate 1.12.0 e Phase 6 Stable 2.2.458 preservam integralmente SHA-256, catálogo privado, licensing, heartbeat, Azure 6B e Release Manager.

= 2.2.457 =
* Shopping passa a ser sempre partilhado por toda a equipa operacional/merchandiser com acesso ao projeto, sem atribuição individual por insígnia.
* O BO deixa de permitir restringir uma insígnia Shopping a um único merchandiser e mostra explicitamente a disponibilidade para toda a equipa do projeto.
* O seletor de loja em front deixa de filtrar pelo merchandiser atribuído à rota/PDV. Qualquer operacional do projeto pode escolher qualquer loja válida da insígnia.
* A segurança continua limitada ao projeto: utilizadores fora do projeto não recebem acesso ao Shopping.
* A migração Shopping 1.2.1 limpa ownership individual legado em assignments, preservando o executor histórico em cada submissão através de user_id.
* Release Gate 1.11.0 bloqueia a certificação se existir algum Shopping ativo/rascunho ainda restringido a um utilizador.
* Phase 6 Stable avança de 2.2.456 para 2.2.457, mantendo catálogo privado, SHA-256, licensing, heartbeat, Azure 6B e Release Manager obrigatórios.

= 2.2.456 =
* Shopping passa a ser modelado por insígnia. O lote deixa de ficar preso a um PDV concreto e o front obriga a selecionar a loja real dessa insígnia antes da submissão.
* A seleção de lojas no front é limitada ao cliente, projeto, insígnia e âmbito operacional do merchandiser, evitando escolher um PDV de outra cadeia ou atribuído a outro operativo.
* Rascunhos e submissões são isolados por insígnia e loja, impedindo que preços de um PDV contaminem outro quando o merchandiser muda de loja.
* A migração Shopping 1.2.0 consolida com segurança assignments históricos duplicados da mesma insígnia, preservando todas as submissões e as respetivas lojas reais.
* O reenvio de uma submissão antiga usa por defeito a configuração atual de destinatários do lote, mostrando separadamente quem recebeu no último envio e quem está selecionado agora.
* Cada reenvio permite selecionar ou limpar individualmente email principal, utilizadores cliente e emails externos antes de enviar.
* Históricos de email antigos com estado de envio mas contador zero são normalizados para uma tentativa, eliminando a inconsistência visual.
* O email Shopping ganha o token {insignia} e o modelo por defeito identifica explicitamente insígnia e loja.
* Resultados e exportações passam a distinguir Insígnia da Loja executada.
* A mensagem de bloqueio da certificação Fase 6 passa a enumerar as pré-condições concretas em falta, sem enfraquecer o Release Gate.
* Release Gate 1.10.0 certifica o modelo Insígnia -> Loja, schema Shopping 1.2.0, destinatários atuais no reenvio e a migração segura.
* Phase 6 Stable avança de 2.2.455 para 2.2.456 e continua a exigir publicação canónica no Release Manager, catálogo Stable, SHA-256 e revalidação da cadeia Azure 6B.

= 2.2.455 =
* Shopping passa a permitir configurar os destinatários de cada lote: email principal do cliente, utilizadores WordPress associados ao cliente e emails externos adicionais.
* Novos lotes podem pré-selecionar automaticamente os utilizadores associados ao cliente, mantendo edição independente por lote.
* O envio passa a ser individual por destinatário, com registo separado no Mail Center e sem expor a lista completa de endereços entre destinatários.
* Cada submissão Shopping ganha reenvio manual auditável, permitindo escolher novamente os destinatários sem alterar os preços já submetidos.
* O estado do email distingue aceite para todos, parcial, falhou e sem destinatários, guarda tentativas e último envio aceite.
* O front Shopping passa a abrir por defeito em Percurso do linear, seguindo a ordem original importada do Excel para manter Pernod e concorrência intercalados como no percurso físico da loja.
* Mantém-se uma vista alternativa Agrupar por tipo, com sequência numerada, etiquetas PRP/Concorrência e campos maiores em mobile.
* O schema interno de Shopping sobe para 1.1.0 e é atualizado automaticamente por dbDelta no arranque, sem executar a migração global do FieldFlow.
* Release Gate 1.9.0 certifica explicitamente destinatários, reenvio, percurso do linear e as novas colunas Shopping.
* Phase 6 Stable avança de 2.2.454 para 2.2.455, mantendo obrigatória a publicação canónica e a certificação Azure 6B.

= 2.2.454 =
* Introduz `fieldflow-resumable-media-v1`, transporte retomável de fotografias e ficheiros por blocos idempotentes de 512 KiB.
* Se a ligação cair durante uma fotografia, os blocos já recebidos ficam preservados no servidor e o replay retoma apenas os blocos em falta.
* Cada sessão é ligada a utilizador, dispositivo, mutation id, campo, índice e SHA-256 do ficheiro, impedindo reutilização entre reportes.
* O servidor reconstrói o ficheiro temporário, valida tamanho e SHA-256 integral e só depois o entrega ao pipeline normal de upload do formulário.
* A submissão final continua protegida por outbox-first, `fieldflow-media-integrity-v1`, `fieldflow-form-ack-v1` e idempotência por mutation id.
* Existe fallback automático para o upload integral anterior quando o browser não suporta Web Crypto ou o endpoint retomável não está disponível.
* FieldFlow > Dispositivos passa a mostrar ficheiros concluídos, blocos enviados e blocos retomados pelo novo transporte.
* Release Gate 1.8.0 torna o transporte retomável e a sua integração com o formulário obrigatórios para certificação desta release.
* Phase 6 Stable avança de 2.2.453 para 2.2.454, mantendo a certificação Azure 6B obrigatória.
* Sem migração de base de dados.

= 2.2.453 =
* Corrige a pré-visualização de fotografias restauradas de rascunhos offline, que podiam existir no IndexedDB/input mas só aparecer visualmente depois de selecionar uma nova imagem.
* A rehidratação de media passa a reconstruir explicitamente previews, métricas e estado das evidências após recuperar ficheiros locais ou do rascunho do servidor.
* A correção não dispara `change`, não volta a otimizar ficheiros e não altera a cópia durável Zero-Loss.
* Release Gate 1.7.0 torna a rehidratação visual de media obrigatória para certificação desta release.
* Phase 6 Stable avança de 2.2.452 para 2.2.453, mantendo a certificação Azure 6B obrigatória.
* Sem migração de base de dados.

= 2.2.452 =
* Devices ganha paginação server-side, 25 por página por defeito, opções 25/50/100 e pesquisa por utilizador, email, dispositivo, plataforma ou versão.
* Filtros, pesquisa e página atual são preservados após ações administrativas sobre dispositivos.
* Introduz `fieldflow-media-integrity-v1`, manifesto local de integridade para fotografias e outros ficheiros de reportes offline, com contagem, tamanho e SHA-256 quando Web Crypto está disponível.
* O replay offline valida o manifesto recebido antes de persistir a submissão; divergências mantêm o reporte na outbox em estado recuperável.
* Timeout de sincronização de formulários passa a adaptar-se ao volume de media e à qualidade da ligação, mantendo sempre a cópia durável local.
* Introduz `fieldflow-offline-trust-v1` em modo shadow, uma lease local assinada pelo FieldFlow e emitida apenas quando a release ativa tem certificação Stable 6B válida.
* O Centro de Controlo mostra estado da lease 6B, validade e media pendente por dispositivo.
* Release Gate 1.6.0 torna paginação, integridade de media e a fundação Offline Trust obrigatórias para certificação desta release.
* Phase 6 Stable avança de 2.2.451 para 2.2.452, preservando a certificação Azure 6B obrigatória.
* A lease é não bloqueante nesta fundação e não substitui a certificação Azure nem exige rede durante o preenchimento.
* Sem migração de base de dados.

= 2.2.451 =
* Offline Operations Intelligence com histórico local de estados Protegido, A sincronizar, Confirmado, Pendente, Atenção, Conflito e Recuperado.
* FieldFlow > Dispositivos passa a mostrar score de saúde offline, idade da fila, tentativas, erros de ACK, rede degradada, armazenamento e histórico recente por equipamento.
* Erros terminais de formulário deixam de apagar automaticamente a cópia durável da outbox, ficando em estado de atenção para correção/reenvio.
* Cada formulário passa a transportar `definition_version`, `schema_hash` completo e `downloaded_at`, ficando a identidade cliente/servidor registada no metadata da submissão.
* Replay offline valida também o hash completo do schema quando disponível.
* Telemetria é limitada e sanitizada, sem respostas de formulário nem fotografias.
* Release Gate 1.5.0 torna esta camada obrigatória antes da certificação Stable.
* Phase 6 Stable avança de 2.2.450 para 2.2.451, mantendo certificação Azure 6B obrigatória.
* Sem migração de base de dados.

= 2.2.450 =
* Hotfix de bootstrap para evitar timeouts 504 em updates sem alteração de base de dados.
* A versão de runtime passa a ser independente da versão do schema durante o arranque do WordPress.
* Instalações em 2.2.448 ou superior com schema 2.2.436 atualizado deixam de executar o Activator completo apenas porque a versão do plugin mudou.
* Updates apenas de runtime atualizam `routespro_version` por uma via leve e registam `fieldflow_last_runtime_upgrade` para diagnóstico.
* Um lock legado deixado por um timeout anterior é limpo na via runtime-only, sem voltar a disparar o processo SQL pesado.
* Instalações antigas, novas instalações e schemas realmente desatualizados continuam a usar a migração completa.
* Release Gate 1.4.0 passa a validar este contrato antes da certificação Stable.
* Mantém integralmente o Offline Zero-Loss da 2.2.449 e a certificação Azure 6B obrigatória.
* Sem migração de base de dados.

= 2.2.449 =
* Offline Zero-Loss Foundation, todos os formulários passam por outbox local durável antes da primeira tentativa de envio.
* Um reporte só sai da fila local após ACK JSON forte do servidor com `persisted=true`, `submission_id` válido, protocolo `fieldflow-form-ack-v1` e o mesmo `mutation_id`.
* Respostas HTML com HTTP 200, JSON inválido, timeouts e confirmações ambíguas deixam de ser tratadas como sucesso.
* Nova deteção de conectividade real com `/offline/ping`, distinguindo online, rede fraca, offline e sessão expirada.
* Rede degradada deixa de bloquear o merchandiser, o reporte fica protegido no dispositivo e sincroniza quando a ligação recupera.
* 401 deixa de ser interpretado como dispositivo revogado; apenas a política explícita `fieldflow_device_revoked` bloqueia o equipamento.
* Service worker PWA passa a manter cache estático versionado e deixa de apagar o cache em cada abertura; o WebView nativo mantém o service worker desativado.
* Release Gate 1.3.0 passa a exigir o contrato Offline Zero-Loss antes do fecho Stable e mantém a certificação Azure 6B obrigatória.
* Sem migração de base de dados.

= 2.2.448 =
* Front Analytics Parity, cliente e merchandiser passam a usar a mesma camada Analytics 2.0, adaptada à audiência.
* Dashboard Merchandiser ganha Command View com Operational Health, Execution, Risk, Opportunity, Stock Risk e Confidence no âmbito pessoal.
* Store Intelligence é carregado progressivamente no dashboard do merchandiser através do endpoint isolado e fail-open, sem voltar a colocar enriquecimento por PDV no caminho crítico do Analytics base.
* Predictive Intelligence e Contextual Next Best Actions passam a aparecer no Analytics do merchandiser, com prioridades pessoais para hoje/próximo ciclo e separação explícita entre observado e projetado.
* Perguntar ao FieldFlow passa a estar disponível no Analytics do merchandiser, com `audience=merchandiser` e `user_id=me` impostos pelo backend, sugestões operacionais, follow-ups e evidência visível.
* Portal Cliente ganha Command View explícito com os mesmos seis Decision Scores e chips de evidência que identificam Observado, Projetado, Aprendido e Advisory.
* Conversas continuam apenas na sessão do browser, são limpas quando muda o âmbito e não alteram rotas, stocks, formulários, thresholds ou scores.
* Mantém o contrato runtime do WidgetOrchestrator, o isolamento do Store Intelligence e o Certification Gate Azure obrigatório, SOURCE_RELEASE 2.2.447 e STABLE_RELEASE 2.2.448.
* Sem migração de base de dados.

= 2.2.447 =
* Perguntar ao FieldFlow: análise conversacional grounded sobre o contexto Analytics atualmente selecionado.
* Novo endpoint read-only `analytics/ask`, com âmbito universal cliente/projeto/rota/operativo/loja/período/categoria/produto e enforcement do scope de permissões existente.
* Respostas usam apenas catálogo de evidência FieldFlow proveniente de execução, Decision Scores, alertas, stocks, Adaptive, Predictive, Store Intelligence, Contextual Next Best Actions, recomendações e briefing.
* Evidência é classificada como Observada, Projetada, Aprendida ou Advisory; projeções nunca são tratadas como probabilidades ou factos e recomendações nunca são apresentadas como ações executadas.
* Disponível no Analytics 2.0 do BO e no Portal Cliente, com perguntas sugeridas, follow-ups, evidência visível e histórico curto apenas na sessão do browser.
* Se a IA estiver indisponível, aplica fallback determinístico factual; Store Intelligence é carregado apenas quando necessário, em caminho isolado, fail-open e com cache curta por utilizador/âmbito.
* Não persiste conversas, não altera rotas, stocks, formulários, thresholds, scores ou dados operacionais e não executa qualquer mutação autónoma.
* Preserva explicitamente o contrato runtime do WidgetOrchestrator estabilizado na 2.2.440 e mantém o Analytics base independente do enriquecimento conversacional.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.446 e STABLE_RELEASE 2.2.447.
* Sem migração de base de dados.

= 2.2.446 =
* Contextual Predictive Intelligence + Next Best Action.
* Cruza Store Intelligence, Predictive Intelligence, Share Space, frentes, execução, stock exato quando disponível e recomendações ativas para gerar prioridades assistidas por loja, produto e tipo de problema.
* Cada Next Best Action separa explicitamente evidência observada de projeções futuras, sem inferir causalidade nem transformar forecast em probabilidade.
* Adiciona Ações prioritárias para o próximo ciclo ao Analytics 2.0 e Next Best Actions ao Portal Cliente através do endpoint Store Intelligence isolado e fail-open.
* Mail Center Daily Close e Weekly Intelligence passam a incluir as principais ações contextuais, respeitando sempre o âmbito cliente/projeto/merchandiser.
* Excel ganha a folha Contextual Actions; Store Intelligence ganha problema prioritário, produto foco e Next Best Action; PDF e CSV usam o mesmo envelope analítico.
* Stock por produto só é usado quando existe contexto exato de loja mapeado. O endpoint isolado não fabrica stock por PDV e mantém stock diferido quando não existe enriquecimento seguro.
* Não altera rotas, stocks, formulários, objetivos, thresholds ou scores operacionais e não executa ações autonomamente.
* Mantém o caminho crítico estabilizado na 2.2.440 e o Mail Center 2.0 da 2.2.445.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.445 e STABLE_RELEASE 2.2.446.
* Sem migração de base de dados.

= 2.2.445 =
* Mail Center 2.0 com BO unificado, fecho diário por merchandiser, consolidado cliente e Weekly Intelligence.
* Fecho diário envia Dia + Acumulado semanal e suporta cliente imediato ou consolidado.
* Test Center por data e perfil, com email sem anexos ou Excel/PDF/CSV.
* Com Fecho diário ativo para o mesmo cliente/projeto/data, a notificação legada de rota concluída é suprimida para evitar emails duplicados.
* Mantém Certification Gate Azure obrigatório, SOURCE_RELEASE 2.2.444 e STABLE_RELEASE 2.2.445.


= 2.2.444 =
* Predictive Intelligence Foundation, construída diretamente sobre a release certificada 2.2.442. A 2.2.443 não é publicada como Stable.
* Adiciona projeções determinísticas para a próxima janela equivalente: execução, risco, oportunidade, estado, horizonte e confiança preditiva.
* Adiciona leading signals explicáveis, sem alegações de probabilidade, causalidade ou futuro garantido.
* Store Intelligence ganha risco futuro, prioridade futura, direção prevista e confiança preditiva, mantendo todos os scores operacionais base intactos.
* Adiciona rankings futuros por loja, incluindo prioridade futura, risco projetado e deterioração provável com base em sinais observados.
* Integra Predictive Intelligence no Intelligence Center, Portal Cliente, emails e exports Excel/PDF/CSV, incluindo uma folha Excel dedicada.
* O motor é fail-open e não bloqueia Analytics 2.0, Store Intelligence, emails ou reporting em caso de indisponibilidade preditiva.
* Não altera rotas, stocks, formulários, thresholds ou scores operacionais e não executa mutações automáticas.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.442 e STABLE_RELEASE 2.2.444.
* Sem migração de base de dados.

= 2.2.442 =
* Adaptive Reporting & Email Runtime Hotfix.
* Corrige a falha de geração XLSX introduzida na 2.2.441 quando já existem padrões Adaptive Intelligence: um bloco copiado do export CSV referenciava `$headers`/`$rows` fora do seu âmbito e em PHP 8 provocava TypeError durante `count($headers)`.
* Mantém os padrões Adaptive Intelligence na folha dedicada e preserva Store Intelligence, recomendações, alertas e Next Best Actions sem misturar contratos de exportação.
* O envio de teste passa a guardar e apresentar o motivo técnico real, distinguindo falha na geração dos anexos de falha no `wp_mail`.
* O teste simples do Centro de E-mails deixa de apresentar falso sucesso quando `wp_mail()` devolve false e passa a mostrar o diagnóstico reportado por `wp_mail_failed`.
* Não altera fórmulas Analytics, Adaptive Intelligence, scores, rotas, stocks, formulários ou dados persistidos.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.441 e STABLE_RELEASE 2.2.442.
* Sem migração de base de dados.

= 2.2.441 =
* Adaptive Intelligence Foundation.
* Usa o dataset auditável de recomendações, feedback e outcomes da 2.2.436 para aprender padrões por cliente/projeto sem misturar evidência entre clientes.
* Introduz eficácia histórica suavizada, relevância humana, maturidade de evidência e guardrails explícitos para adaptação assistida.
* Pode reordenar recomendações, ajustar a confiança apresentada até ±8 pontos e o ranking assistido até ±10 pontos, sem alterar Risk, Opportunity, Health, Execution, Stock Risk ou dados operacionais persistidos.
* Adiciona Next Best Actions ao Intelligence Center e ao Analytics 2.0, com explicação do histórico que suportou cada prioridade.
* Store Intelligence ganha ranking adaptativo por loja com uma única leitura agregada do Learning dataset; o score base de prioridade permanece disponível e imutável.
* Emails e exports Excel/PDF/CSV passam a usar confiança/ranking adaptativos e o Excel ganha a folha Adaptive Intelligence com padrões e Next Best Actions.
* Mantém causalidade inferida OFF, auto-mutações OFF, revisão humana obrigatória e o caminho crítico do Analytics 2.0 protegido pela arquitetura da 2.2.440.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.440 e STABLE_RELEASE 2.2.441.
* Sem migração de base de dados.

= 2.2.440 =
* Widget Orchestrator Runtime Hotfix.
* Corrige a causa exata dos HTTP 500 do Analytics iniciados na 2.2.437: os helpers internos `scoreMetric()` e `topic()` do WidgetOrchestrator foram removidos acidentalmente durante a evolução Store Intelligence, embora `orchestrate()` e `compactMetrics()` continuassem a chamá-los.
* Restaura os dois helpers exatamente sobre o contrato estável da 2.2.436, mantendo as evoluções Store Intelligence e o desacoplamento crítico da 2.2.439.
* Adiciona uma guarda de contrato em runtime e uma validação de release para detetar chamadas internas sem implementação, cobrindo regressões que o PHP lint não consegue apanhar.
* Sem alteração das fórmulas Analytics, scores, IA, Store Intelligence, stocks, Learning, formulários, emails ou exports.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.439 e STABLE_RELEASE 2.2.440.
* Sem migração de base de dados.

= 2.2.439 =
* Analytics Critical Path Decoupling Hotfix.
* Repõe o caminho crítico do Analytics 2.0 no envelope estável da 2.2.436: `/stats` e `/analytics/ai-insights` deixam de depender do Store Opportunity & Risk Engine.
* Move Store Intelligence para um endpoint REST próprio, carregado apenas depois de Operational Health, Execution, Risk, Opportunity, Stock Risk, Confidence, comparação temporal, rotas e IA base estarem disponíveis.
* Um erro, timeout ou indisponibilidade de Store Intelligence deixa de transformar todo o Command View num HTTP 500.
* O primeiro passe isolado usa perfil `isolated_core`, sem stock por loja nem Learning por loja. Estes enriquecimentos ficam diferidos para uma passagem progressiva posterior.
* Mantém o Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.438 e STABLE_RELEASE 2.2.439.

= 2.2.438 =
* Corrige a regressão HTTP 500 do Analytics 2.0 introduzida no ciclo Store Intelligence 2.2.437.
* Remove o cálculo duplicado do Store Opportunity & Risk Engine no endpoint de estatísticas e calcula os perfis por loja apenas no fluxo de insights que os apresenta.
* O Store Engine passa a ser fail-open: se uma fonte por loja falhar, o Analytics continua com o motor determinístico anterior em vez de derrubar o Command View.
* Um PDV com payload incompatível deixa de invalidar os restantes perfis e passa a ser isolado com diagnóstico em log.
* O contexto de stock por localizações mapeadas ganha cache curta por batch/location e o payload enviado à IA é compactado, mantendo o Store Intelligence completo no FieldFlow.
* Não altera fórmulas de Risk, Opportunity ou Health, nem ativa Adaptive Intelligence.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.437 e STABLE_RELEASE 2.2.438.
* Sem migração de base de dados.

= 2.2.437 =
* Store Opportunity & Risk Engine por loja com Operational Health, Execution, Risk, Opportunity, Stock Risk, Share Gap, tendência, prioridade e confiança.
* Rankings de lojas prioritárias, em deterioração, recuperadas, maior oportunidade e maior risco de stock.
* Stock por rota, operativo e loja usa apenas localizações FieldFlow mapeadas, sem atribuir agregados globais a âmbitos estreitos.
* Intelligence Learning influencia apenas a confiança nesta release. Risk, Opportunity e Health continuam determinísticos.
* Store Intelligence integrado no BO, front Cliente/Merchandiser, email, Excel, PDF e CSV.
* Mantém Certification Gate Azure obrigatório, com SOURCE_RELEASE 2.2.436 e STABLE_RELEASE 2.2.437.
= 2.2.436 =
* Introduz a Intelligence Learning Foundation com recomendações determinísticas, feedback humano e outcomes observados por cliente, projeto, rota, operativo e loja.
* Cria o novo separador Intelligence Center > Aprendizagem com estados Faz sentido, Não relevante, Adiar e Resolvido, notas opcionais, cobertura de feedback e cobertura de outcomes.
* Mantém autoaprendizagem e mutações autónomas desligadas. A release apenas constrói um dataset auditável para aprendizagem futura, sem alterar scores ou decisões operacionais automaticamente.
* Adiciona duas tabelas versionadas para recomendações e eventos de feedback/outcome e inclui ambas no Schema Manifest.
* Expõe recomendações no Analytics/IA e no Portal Cliente em modo informativo, preservando a decisão humana no BO.
* Integra recomendações no Smart Reporting: email, emails de rota, Excel, PDF e CSV usam a mesma fonte Report Intelligence.
* Prepara eventos Intelligence para Azure, com sincronização opt-in e desligada por defeito até o receiver do tenant validar o contrato fieldflow.intelligence.learning.
* Preserva os hotfixes de formulários 2.2.434/2.2.435, Widget Intelligence, emails responsivos, stocks snapshot, rotas, licensing e as proteções de produção.
* Preserva o Azure Release Certification Gate obrigatório. Phase6StableRelease::STABLE_RELEASE passa para 2.2.436 e SOURCE_RELEASE para 2.2.435.
* Caminho privado direto: 2.2.435 -> 2.2.436 através de FieldFlow > Release Manager. Depois do update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.435 =
* Corrige a semântica das perguntas de Conformidade + evidência: a resposta Sim, Não ou Não aplicável passa a ser o dado principal e segue apenas a obrigatoriedade definida na própria pergunta.
* Responder Não deixa de obrigar automaticamente ao Motivo da não conformidade. O motivo continua a aparecer apenas no ramo Não, mas fica opcional por defeito.
* Responder Sim continua a disponibilizar fotografias de evidência apenas no ramo Sim, igualmente opcionais por defeito.
* O servidor Web e o Mobile SubmissionService usam o mesmo contrato EvidenceFlow v2; apenas flags explícitas futuras podem tornar foto/motivo obrigatórios.
* Atualiza o Form Builder, BO de Briefings e Briefing AI Copilot para deixarem de descrever o motivo do Não como obrigatório.
* Mantém a classificação analítica Sim=conforme, Não=não conforme e Não aplicável sem alterar os dashboards, alertas ou histórico existente.
* Preserva o Azure Release Certification Gate obrigatório. Phase6StableRelease::STABLE_RELEASE passa para 2.2.435 e SOURCE_RELEASE para 2.2.434.
* Sem migração de base de dados.
* Caminho privado direto: 2.2.434 -> 2.2.435 através de FieldFlow > Release Manager. Depois do update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.434 =
* Corrige um bloqueio urgente em formulários onde perguntas obrigatórias escondidas por condições podiam continuar a ser exigidas no servidor. A validação server-side passa a usar a mesma visibilidade ConditionalFlow do formulário apresentado ao utilizador.
* Alinha o schema de submissão com o schema renderizado e mobile, reduzindo também falsos conflitos de revisão em formulários com condições.
* Valida comparativos Share Space obrigatórios antes do envio e identifica o comparativo em falta. O valor 0 continua a ser uma resposta válida e explícita.
* Um comparativo preenchido com 0/0 passa a indicar Sem frentes registadas em vez de Por preencher.
* Quando o servidor rejeita um campo obrigatório, devolve a pergunta concreta e o front tenta abrir e focar esse campo automaticamente.
* Aplica a mesma regra de obrigatoriedade condicional ao Mobile SubmissionService.
* Preserva Briefings, Analytics 2.0, Widget Intelligence, Smart Reporting, emails, Excel/PDF/CSV, stocks, rotas e licenciamento sem alterações funcionais.
* Preserva o Azure Release Certification Gate obrigatório. Phase6StableRelease::STABLE_RELEASE passa para 2.2.434 e SOURCE_RELEASE para 2.2.433.
* Sem migração de base de dados.
* Caminho privado direto: 2.2.433 -> 2.2.434 através de FieldFlow > Release Manager. Depois do update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.433 =
* Reorganiza o BO de Briefings > Perguntas de execução para uma leitura premium em largura total, eliminando a divisão lado a lado que comprimida o editor e a tabela.
* Mantém Sugestões IA, Editor de pergunta e Perguntas ligadas ao briefing em blocos verticais independentes, preservando todas as funções existentes.
* Expande o editor para largura total, melhora o comparativo automático, seleção de lojas, campos de Analytics e adiciona uma barra de gravação visualmente destacada.
* Reestrutura a tabela de perguntas com larguras previsíveis, cabeçalho legível, datas em duas linhas, ações compactas e scroll horizontal apenas como fallback em viewports menores.
* Os KPIs de execução passam a usar quatro colunas equilibradas e a tabela deixa de quebrar títulos como Formulário ou Validade caractere a caractere no Desktop.
* Preserva integralmente Briefing Intelligence, Widget Intelligence, Analytics 2.0, emails responsivos, exportações Excel/PDF/CSV e a semântica de perguntas, respostas e histórico.
* Preserva o Azure Release Certification Gate obrigatório. Phase6StableRelease::STABLE_RELEASE passa para 2.2.433 e SOURCE_RELEASE para 2.2.432.
* Sem migração de base de dados. Sem alterações a stocks, rotas, licenciamento, contratos mobile/Desktop ou switches de segurança Azure.
* Caminho privado direto: 2.2.432 -> 2.2.433 através de FieldFlow > Release Manager. Depois do update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.432 =
* Hardens FieldFlow Intelligence emails for mobile Outlook, Gmail and Apple Mail with a viewport-aware, width-safe email envelope.
* Changes analytical score cards from three columns to a maximum of two columns, with one-column stacking on narrow email clients.
* Uses fixed-layout, width-bounded tables and safe word wrapping for priority metrics, store alerts and scope cards so long labels or values cannot expand the email beyond the viewport.
* Applies the same responsive rules to scheduled Client/Merchandiser reports and route completed/exception intelligence digests.
* Keeps Email, Excel, PDF and CSV fed by the same Report Intelligence envelope. No score semantics or export data change in this compatibility release.
* Preserves the mandatory Azure Release Certification Gate. Phase6StableRelease::STABLE_RELEASE advances to 2.2.432 and SOURCE_RELEASE to 2.2.431.
* No database migration. No change to stock snapshot replacement, route execution, Widget Intelligence, licensing or Azure production-safety contracts.
* Direct private upgrade path is 2.2.431 -> 2.2.432 using FieldFlow > Release Manager. After update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.431 =
* Introduz Widget Intelligence para reduzir automaticamente o ruído dos dashboards sem eliminar os 22 widgets existentes. O catálogo completo mantém-se disponível, mas o front mostra apenas os widgets mais relevantes para Cliente e Merchandiser.
* Adiciona modos Automático, Assistido e Manual por dashboard, limite configurável de widgets para Cliente, Merchandiser e Email, reforço por alertas ativos e opção `Fixar sempre` para widgets obrigatórios.
* A seleção é determinística e auditável, baseada em prioridade e perguntas do briefing, família estratégica da métrica, existência de dados, relevância para o tipo de utilizador, alertas operacionais e fixação manual. O FieldFlow nunca inventa métricas.
* O front recebe uma segunda passagem de Intelligence sem persistência, permitindo que alertas atuais mudem a prioridade dos widgets sem criar alertas novos apenas porque alguém abriu o dashboard.
* Cria uma fonte única de Report Intelligence para Email, Excel, PDF e CSV, garantindo os mesmos Decision Scores, métricas prioritárias e alertas por loja em todos os canais.
* Melhora os emails automáticos com um pequeno dashboard analítico por período, métricas prioritárias e alertas concretos por loja. O email de Cliente usa âmbito de campanha e o email do Merchandiser fica limitado ao seu próprio âmbito operacional.
* Os emails de rota concluída ou exceção passam a incluir um pequeno resumo analítico da rota/dia, com Execution, Risk, Opportunity e alertas por loja quando existe evidência ao nível da loja.
* Excel passa a incluir folhas `Intelligence` e `Alertas Lojas`; PDF recebe blocos Field Intelligence e alertas; CSV recebe linhas estruturadas de Intelligence, Widget inteligente e Alerta Loja. O detalhe completo continua sempre preservado nos ficheiros exportados.
* Adiciona período Diário e formato CSV às automações de reporting. Para novas automações mantém por defeito Semanal + Acumulado e Excel + PDF, deixando Diário e CSV opcionais para evitar excesso de anexos.
* Protege a leitura de stock: o snapshot agregado de Cliente/Projeto não é apresentado como se pertencesse a uma rota, pessoa ou loja específica enquanto o contrato de stock não conseguir provar esse âmbito mais estreito.
* Mantém self-learning autónomo desligado. Esta release prepara a base explicável para a próxima fase de aprendizagem por recomendação, decisão humana, ação e resultado.
* Preserva integralmente o Certification Gate Azure obrigatório. Phase6StableRelease::STABLE_RELEASE passa para 2.2.431 e SOURCE_RELEASE para 2.2.430.
* Sem migração de base de dados. Mantém stock snapshot-replace, rotas, formulários, Desktop, mobile, licensing e contratos Azure existentes.
* Direct private upgrade path is 2.2.430 -> 2.2.431 using FieldFlow > Release Manager. Depois do update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.430 =
* Introduz o Analytics 2.0 Command View dentro do FieldFlow Intelligence Center, unificando Atribuições e Analytics num único contexto operacional Cliente -> Projeto -> Rota -> Operativo -> Loja -> Período.
* Adiciona filtros universais, comparação temporal, drill-down por rota e loja, leitura de IA e uma grelha de Decision Scores para Operational Health, Execution, Risk, Opportunity, Stock Risk e Confidence.
* No drill-down de uma rota individual a comparação temporal fica deliberadamente indisponível, porque um route_id identifica uma execução concreta e não representa automaticamente a mesma rota no período anterior. Para comparar períodos, remove-se o filtro de rota e mantém-se o contexto de projeto/loja.
* Cria um motor determinístico de scoring e priorização de lojas. Os scores usam apenas factos agregados do FieldFlow, não inferem causas e não alteram dados operacionais.
* Corrige inconsistências reais dos Analytics: route_id passa a filtrar também as estatísticas base; location_id é preservado no período anterior e o drill-down de loja passa a contar apenas as paragens da loja selecionada, em vez de todas as paragens das rotas onde essa loja existe.
* A camada BO do Analytics 2.0 usa assets CSS/JS próprios, carregados apenas no scope Analytics, evitando acrescentar nova dívida de CSS inline ao renderer principal.
* Mantém explicitamente revisão humana e self-learning desligado nesta release. A fundação de aprendizagem por feedback fica preparada para uma fase posterior com dataset auditável.
* Preserva integralmente o Certification Gate Azure obrigatório. Phase6StableRelease::STABLE_RELEASE passa para 2.2.430 e SOURCE_RELEASE para 2.2.429.
* Sem migração de base de dados. Mantém stock snapshot-replace, rotas, formulários, Desktop, mobile, licensing e contratos Azure existentes.
* Direct private upgrade path is 2.2.429 -> 2.2.430 using FieldFlow > Release Manager. Depois do update: Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable.

= 2.2.429 =
* Cria o FieldFlow Intelligence Center no BO, mantendo a mesma rota interna do antigo Centro de Atribuições para não quebrar links, mas unificando Command Center, Clientes, Projetos, Rotas, Formulários e Analytics & IA.
* O Command Center cruza configuração operacional e analítica no mesmo contexto Cliente -> Projeto -> Rota, mostrando equipa/owners, bindings de formulários, dashboards, widgets, alertas persistidos e um Readiness de configuração.
* O Intelligence Feed reutiliza o motor operacional determinístico existente para expor alertas recentes por contexto sem inventar causas; a configuração do fornecedor de IA fica separada como Configuração de IA.
* Release Manager passa a incluir um Certification Gate Azure em três passos: Revalidar cadeia 6B, Testar fecho Fase 6 e Certificar Stable. O pipeline deixa de ficar completo apenas por publicar e instalar a build.
* A certificação Phase 6 passa a exigir evidência da publicação canónica feita pelo Release Manager WordPress, incluindo versão, SHA-256 do pacote, release ID e publisher no snapshot certificado.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.429 e SOURCE_RELEASE para 2.2.428.
* Sem migração de base de dados. Mantém rotas, stocks snapshot-replace, briefings, formulários, Desktop, mobile e contratos Azure existentes.
* Direct private upgrade path is 2.2.428 -> 2.2.429 using FieldFlow > Release Manager. Depois do update a release só fica fechada quando o Certification Gate Azure estiver verde.

= 2.2.428 =
* Hotfix definitivo para os fragmentos/blocos laterais no Analytics do FieldFlow Desktop: a regra externa da 2.2.427 era correta, mas uma variante inline posterior e de maior especificidade de `layout-executive` voltava a criar 3 colunas no desktop e 2 colunas abaixo de 1180px. A 2.2.428 neutraliza diretamente essas regras legadas de maior especificidade e acrescenta uma guarda canónica final que mantém todos os cartões visuais do Analytics numa única coluna, largura total e `min-width:0`.
* Corrige dois indicadores de release desatualizados: o detalhe da matriz Phase 6 passa a usar `STABLE_RELEASE` dinamicamente e o chip `Release Gate` do Desktop passa a mostrar a versão real do FieldFlow Core.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.428.
* A release só deve ser considerada concluída depois de publicada pelo Release Manager, instalada a partir do catálogo privado, revalidada a cadeia 6B, testado o fecho da Fase 6 e executada a certificação Fase 6 Stable.
* Direct private upgrade path is 2.2.427 -> 2.2.428 using FieldFlow > Release Manager.

= 2.2.427 =
* CORRIGE A RAIZ dos fragmentos de cartão vistos nesta ronda (2.2.424 a 2.2.426 foram todas paliativos ao nível de cada tipo de cartão). Causa real: .rp-analytics-visual-grid tem 16 versões concorrentes da mesma regra espalhadas pelo ficheiro original — repeat(2,...), repeat(3,...), auto-fit com minmax(420-440px,1fr), 1fr, minmax(0,1fr) — cada uma provavelmente pensada para uma largura de ecrã diferente ao longo do tempo, sem coordenação entre si. Consoante a largura exata da janela, a grelha podia decidir criar 2 ou 3 colunas para cartões pensados para serem sempre largura total e empilhados na vertical — daí os "blocos na horizontal" e fragmentos na borda direita.
* Corrigido na origem: uma coluna, sempre, disponha o que dispuser dentro — em vez de continuar a corrigir tipo de cartão a tipo de cartão. As correções anteriores (is-share-space/is-pie, is-briefing-rollup) tornam-se redundantes mas inofensivas, ficam para não haver risco em as remover.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.427.
* Direct private upgrade path is 2.2.426 -> 2.2.427 using FieldFlow > Release Manager.

= 2.2.426 =
* Encontrada a verdadeira extensão do bug de JSON em bruto da 2.2.425: existia uma TERCEIRA localização, "Leitura por pergunta" (metricQuestionBreakdownHtml), que a correção anterior não cobria — desta vez era o campo "label" da distribuição, não o "value". Mesma causa, sítio diferente. Corrigido com a mesma proteção (2 ocorrências).
* Esta secção (rp-smart-dist, dentro de is-briefing-rollup) nunca tinha CSS própria — dependia inteiramente do texto caber por acaso. Texto sem espaços (como a estrutura JSON) não tem onde quebrar linha e empurra o cartão para fora, o que explica os fragmentos cortados na borda vistos mesmo depois da correção de grid da 2.2.425 (essa corrigiu is-share-space/is-pie, mas is-briefing-rollup é um tipo de cartão diferente, sem relação). Blindado agora de forma definitiva — independente do que o texto vier a ser no futuro, nunca mais empurra o layout.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.426.
* Direct private upgrade path is 2.2.425 -> 2.2.426 using FieldFlow > Release Manager.

= 2.2.425 =
* CORRIGE bug real de dados: nas "Amostras" de perguntas do briefing e no detalhe de "Respostas" de um relatório, quando a pergunta era do tipo "Comparativo de referências" (Share Space), o valor gravado (uma estrutura JSON) era impresso diretamente no ecrã em vez de resumido — texto técnico tipo metric_key/pr_share/competitor_fronts visível para o cliente. Agora deteta esse caso e mostra "Resposta estruturada — ver detalhe completo" em vez de despejar o JSON em bruto. Dois pontos corrigidos em src/Front/ClientPortalShortcodeRenderer.php; confirmado que o mesmo padrão não existe noutros renderers.
* Corrige (com reserva sobre a causa exata): fragmentos de cartão a espreitar para fora no Analytics desktop, mesmo depois da 2.2.424. Encontrada uma contradição real — .is-share-space e .is-pie têm duas regras de grid-column (1/-1 vs auto) — mas confirmei que a segunda só se aplica em ecrãs até 760px (mobile), por isso pode não ser a causa completa no desktop. Reforcei ambos os tipos de cartão para serem sempre largura total, como rede de segurança adicional — mas não posso garantir com a mesma certeza da correção de dados acima que isto resolve por completo. Pede para confirmar com atenção.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.425.
* Direct private upgrade path is 2.2.424 -> 2.2.425 using FieldFlow > Release Manager.

= 2.2.424 =
* Corrige fragmentos de cartão a espreitar para fora do ecrã no Analytics (Portal Cliente e Portal Operativo). Causa: .rp-analytics-card-head (título + badges Briefing/Histórico/Share Space/reg.) tem 7 versões concorrentes desta regra ao longo do ficheiro — uma delas não tinha flex-wrap. Com um título longo ("Comparativo de referências do briefing") e badges que nunca encolhem (flex:0 0 auto), o conjunto ficava a sair do cartão em vez de passar para uma segunda linha.
* Corrigido de forma definitiva com !important, garantindo o comportamento certo independentemente de qual das 7 regras vence em cada largura de ecrã, em vez de tentar corrigir só uma delas.
* Confirmado sem alteração necessária: a ausência de nomes de loja nos alertas de execução (2.2.423) não é bug — a taxa de execução no exemplo mostrado estava a 100%, por isso não havia nenhuma loja abaixo do limiar de 30% para reportar.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.424.
* Não altera nenhuma lógica de dados nem de deteção de anomalias — só o comportamento de quebra de linha do cabeçalho do cartão.
* Direct private upgrade path is 2.2.423 -> 2.2.424 using FieldFlow > Release Manager.

= 2.2.423 =
* NOVO — fundação para alertas com nome de loja: confirmado por leitura de código que todos os alertas de execução eram só agregados ("execução a 62%"), nunca diziam qual loja. Corrigido na fonte, não com um remendo.
* StatsController::stats() ganha um novo agregado por_localização (by_location) — uma única query nova (não N+1, agrupada, dentro do mesmo bloco já em cache da 2.2.420), com contagem de paragens e taxa de conclusão por loja no período.
* OperationalIntelligenceService::detectAnomalies() ganha um novo tipo de alerta, stores_low_execution, que identifica as lojas concretas com execução <=30% (mínimo 2 paragens no período, para não sinalizar uma única visita ainda por fazer). O texto do alerta já vem com os nomes: "3 loja(s)... Pingo Doce Amoreiras, Continente Colombo, Auchan Alfragide.", e os location_ids ficam disponíveis no payload para uso futuro (ex. um botão "ver esta loja").
* Confirmado que isto chega à IA sem precisar de mexer no prompt: o texto de instrução já diz "Usa Dados.operational como leitura determinística: não contradigas anomalias calculadas" — a partir de agora essa leitura já vem com nomes de loja, por isso a IA passa a poder referi-los.
* Isto é só a fundação (dados). A nota discreta no formulário de preenchimento e o botão "ver em Analytics" ficam para a fase seguinte, a pedido.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.423.
* Não altera nenhum limiar de deteção já existente, nenhuma lógica de rotas nem de licenciamento.
* Direct private upgrade path is 2.2.422 -> 2.2.423 using FieldFlow > Release Manager.

= 2.2.422 =
* Última ronda do BO: Data Destinations e Integrações auditados pelos mesmos 4 padrões de bug — zero encontrados. Cores consistentes (DataDestinations só tem 1 cor fixa, o vermelho de erro padrão do WordPress).
* Tratamento tipográfico e motion aplicado a ff5-card, ff5-note, routespro-int-card, routespro-int-mono. Os badges (ff5-badge) já herdam automaticamente a rede de segurança contra palavra cortada que existe desde a 2.2.410.
* Com isto, as 7 páginas do BO com mais estilo inline da auditoria original estão todas cobertas: Localizações de Campanha, Rotas, Emails, System Health, Atribuições, Data Destinations, Integrações.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.422.
* Não altera nenhuma lógica de destinos de dados, integrações ou credenciais — só CSS aditivo.
* Direct private upgrade path is 2.2.421 -> 2.2.422 using FieldFlow > Release Manager.

= 2.2.421 =
* Segunda ronda do BO: Rotas, Emails, System Health e Atribuições auditados pelos mesmos 4 padrões de bug conhecidos (palavra cortada, overflow de largura, botões a espremer, overscroll bloqueado) — nenhum encontrado. Cores continuam consistentes com a mesma paleta já usada em todo o BO.
* Aplicado o mesmo tratamento de tipografia, ritmo e motion que o resto do BO já tem: rp-summary-box, ff-health-card, ff-runtime-item, rp-label, rp-stat, ff-section-title.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.421.
* Não altera nenhuma lógica de rotas, emails, diagnóstico ou atribuições — só CSS aditivo.
* Direct private upgrade path is 2.2.420 -> 2.2.421 using FieldFlow > Release Manager.

= 2.2.420 =
* INVESTIGAÇÃO: causa encontrada para /routespro/v1/analytics/ai-insights (32,49s) e /routespro/v1/stats CRITICAL. aiInsights() chama stats() na íntegra antes de sequer chegar à cache da resposta de IA — a computação cara de stats() (7+ queries agregadas, uma delas com subquery correlacionada por linha, repetidas na íntegra para o período anterior de comparação, mais analytics de formulários) corria sempre, mesmo quando a resposta de IA já estava em cache.
* CORRIGIDO: stats() passa a ter cache própria (5 min), à parte da cache da IA. A chave inclui a cláusula WHERE já resolvida (que encapsula datas, cliente, projeto, localização, utilizador e o âmbito de permissões de quem pergunta) mais os parâmetros usados só pelo analytics de formulários e o utilizador atual — dois utilizadores com acessos diferentes nunca partilham uma entrada de cache. A reconciliação de rotas (que escreve estado real na base de dados) continua a correr sempre, cache ou não — só a parte de leitura/agregação fica em cache. Exportações completas (export_full/report_export) ficam de fora da cache de propósito.
* Isto beneficia os dois endpoints ao mesmo tempo, já que ambos chamam a mesma função stats() por baixo.
* CONFIRMADO sem alteração necessária: "Falha de integração externa" é o próprio sistema de observabilidade a funcionar como desenhado — regista falhas em qualquer chamada externa (Google/OpenAI/Azure/Maps). A ocorrência de 04/09 foi única, não um padrão recorrente; o serviço exato fica no detalhe desse alerta no BO.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.420.
* Não altera nenhum cálculo, apenas quando é recalculado.
* Direct private upgrade path is 2.2.419 -> 2.2.420 using FieldFlow > Release Manager.

= 2.2.419 =
* INVESTIGAÇÃO: causa raiz encontrada para a lentidão de /fieldflow-mobile/v1/offline/delta (35,7s, 773 ocorrências CRITICAL) e para "Concluir visita" a não fechar a visita. OfflineSyncService::bootstrap() faz, em cache fria, até 40 chamadas individuais a Controller::route() e até 240 chamadas individuais a FormPayloadService::for_stop() — cada uma atravessando 11 subsistemas (Forms, ContextQuestions, BriefingFormBridge, EvidenceFlow, ConditionalFlow, ProductCardex, DailySubmissionService, RecordService, etc.) com múltiplas queries próprias. É um padrão N+1 em grande escala, não uma query lenta isolada.
* A ligação a "Concluir visita": quando o pedido de submissão falha (por lentidão/timeout do servidor) com um erro marcado safeToRetry, o cliente guarda a submissão localmente sem mostrar erro (comportamento pensado para agentes genuinamente sem rede). Essa submissão fica à espera de sincronizar através do mesmo endpoint que está a falhar — por isso a visita nunca fecha, sem nenhum aviso visível. Os dados não se perdem, ficam em fila local.
* CORRIGIDO com segurança: CACHE_TTL de bootstrap subido de 300s para 900s. Reduz para cerca de 1/3 a frequência com que o caminho caro (N+1) é percorrido, usando exatamente os mesmos gatilhos de invalidação que já existiam — zero alteração ao critério de frescura dos dados, só menos vezes a recalcular quando nada relevante mudou.
* NÃO CORRIGIDO de propósito: uma cache por-paragem mais fina foi considerada e descartada — o contador de invalidação (_fieldflow_route_cache_generation) é por-utilizador, não por-paragem, por isso uma cache mal desenhada a esse nível arriscava servir formulários desatualizados a agentes de terreno. Reescrever o N+1 em lote é a correção real, mas é uma cirurgia a 11 subsistemas interligados — precisa de ambiente de teste próprio, não uma sessão sem poder testar ao vivo.
* Tarefas cron atrasadas: provavelmente aliviado pela mesma correção (menos pedidos de 35s a ocupar processos do servidor), mas pode haver também uma componente de infraestrutura (cron real do sistema operativo vs pseudo-cron do WordPress acionado por visitas) — pergunta para o hosting, fora do alcance do código do plugin.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.419.
* Direct private upgrade path is 2.2.418 -> 2.2.419 using FieldFlow > Release Manager.

= 2.2.418 =
* CORRIGE LENTIDÃO reportada no scanner de uploads (2.2.417): estava a verificar TODAS as fotos individualmente, uma chamada HTTP síncrona à VirusTotal por ficheiro — 4 fotos num formulário = 4 pedidos em sequência, até vários segundos de atraso percetível. Confirmado em teste real pelo utilizador.
* Corrigido para verificar apenas documentos e arquivos (PDF, CSV, XLSX, XLS, DOCX, ZIP) — nunca fotos/imagens. Fotos não podem ser executadas e são a esmagadora maioria do volume de uploads em campo; documentos/arquivos são o mesmo conjunto que já tinha a validação mais profunda de estrutura, e são muito menos frequentes. Protege onde o risco real está, sem afetar o fluxo normal de trabalho no terreno.
* Tempo limite do pedido à VirusTotal reduzido de 8s para 5s, para encurtar também o pior caso nos ficheiros que continuam a ser verificados.
* Texto explicativo em FieldFlow > Configurações atualizado para refletir o âmbito real.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.418.
* Não altera nenhuma lógica de dados, formulários, REST, Azure ou licenciamento.
* Direct private upgrade path is 2.2.417 -> 2.2.418 using FieldFlow > Release Manager.

= 2.2.417 =
* NOVO, DESLIGADO POR OMISSÃO: verificação de uploads por hash contra malware conhecido (VirusTotal). Preenche o único gap real identificado na auditoria ASVS V5 sem interruptor próprio — UploadGuard já validava tipo/MIME/tamanho/estrutura de ZIP, mas nunca verificava o conteúdo contra uma base de malware.
* src/Security/UploadScanner.php (novo): compara o SHA-256 de cada ficheiro (nunca o conteúdo) com a base de dados pública da VirusTotal. Deteta malware já conhecido/reutilizado, que é a maioria dos casos reais — não substitui uma análise de conteúdo completa a ficheiros nunca antes vistos.
* Falha sempre em aberto, seguindo exatamente o mesmo princípio já usado em MediaShadow/SessionPolicy/Mfa neste plugin: erro de rede, limite de pedidos excedido ou hash desconhecido nunca bloqueiam um upload — só uma deteção confirmada (>=2 motores) bloqueia.
* Ligar requer duas coisas em FieldFlow > Definições: ativar a checkbox e introduzir uma chave da VirusTotal (grátis, 4 pedidos/min). Sem isso, comportamento zero diferente do que já existia.
* Chamada externa feita via OutboundHttp (o mesmo guard central contra SSRF que protege as chamadas a Maps/IA), restrita a www.virustotal.com:443, sem redirecionamentos.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.417.
* Análise separada: Media Protegida (Shadow Mode) não foi tocada nesta release — implica reescrever como os ficheiros são servidos em toda a app, risco real de partir acesso a fotos/documentos sem poder testar ao vivo. Fica para uma fase dedicada, com telemetria já recolhida a informar a decisão.
* Direct private upgrade path is 2.2.416 -> 2.2.417 using FieldFlow > Release Manager.

= 2.2.416 =
* Limpeza: removidos 121 ficheiros AUDIT-2.2.*.md e 45 INSTALL-TEST-2.2.*.md (um par por cada versão desde a 2.2.278 até à 2.2.398) que viajavam em todos os zips desde sempre sem nenhum código a lê-los. Também removido php-lint.txt (resultado de lint local, não é código nem documentação). Total: 167 ficheiros, ~772 KB.
* Os PHASE-*.md (documentam funcionalidades e decisões de arquitetura reais, ex. PHASE-5B-CLIENT-AZURE.md, PHASE-6B2-ACTIVATION-BINDING-HEARTBEAT.md) foram mantidos de propósito — não são ruído, são o registo histórico de como e porquê certas partes do sistema existem.
* production-manifest.json: key_files atualizado para remover as 30 entradas correspondentes aos ficheiros apagados. Confirmado programaticamente que todas as key_files restantes (73) existem no disco antes de publicar — zero em falta.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.416.
* Contagem de ficheiros do pacote: 593 -> 426. Não altera nenhum ficheiro de código, dados, REST, Azure ou licenciamento.
* Direct private upgrade path is 2.2.415 -> 2.2.416 using FieldFlow > Release Manager.

= 2.2.415 =
* Varredura proativa dos 4 padrões de bug já conhecidos (palavra cortada, overflow de largura, botões a espremer, overscroll bloqueado) por todo o código restante, em vez de esperar por mais capturas de ecrã.
* Padrão "overscroll bloqueado": confirmado resolvido em todo o lado; a única ocorrência restante (form builder do BO) é um scroll vertical isolado, sem o mesmo risco.
* Padrão "botões a espremer" (flex:1 sem base): confirmado que a correção anterior era um caso isolado; as restantes ocorrências são de baixo risco (contexto desktop do BO, ou itens a menos, ou já com min-width próprio).
* Padrão "overflow de largura": confirmado que o Front está todo coberto; o BO já tem rede de segurança partilhada ao nível do .wrap, risco menor por natureza (uso predominantemente desktop).
* Padrão "palavra cortada": encontrados e corrigidos 2 casos reais que tinham escapado às rondas anteriores no Portal Cliente — rótulos de gráficos de barras/pizza que trocam para overflow-wrap:anywhere em ecrã <=640px (agora quebram só em espaços), e a tabela de Analytics responsiva em cartão mobile, que tinha a mesma combinação de coluna estreita + letras muito espaçadas já vista antes.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.415.
* Não altera nenhuma lógica de dados, cálculos, REST, Azure ou licenciamento.
* Direct private upgrade path is 2.2.414 -> 2.2.415 using FieldFlow > Release Manager.

= 2.2.414 =
* Corrige os botões de exportação do Relatório Semanal (CSV dados/Excel completo/Excel mobile/PDF executivo) a partir letra a letra em mobile. Causa: .routespro-dashboard .ffad-btn{flex:1} em ecrã <=760px dividia os 4 botões em partes iguais a partir de largura zero (flex-basis:0%), sem nunca deixar passar para uma segunda linha — mesmo "Excel" deixava de caber numa linha.
* Corrigido para flex:1 1 132px + white-space:nowrap: agora os botões wrappam como blocos inteiros para uma segunda linha quando não cabem 4 por linha, em vez de cada um se espremer individualmente. Também acrescentado white-space:nowrap à regra base do .ffad-btn (não só à variante mobile) como proteção extra em larguras intermédias.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.414.
* Não altera nenhuma lógica de exportação, dados, REST, Azure ou licenciamento — só o comportamento de quebra de linha destes 4 botões.
* Direct private upgrade path is 2.2.413 -> 2.2.414 using FieldFlow > Release Manager.

= 2.2.413 =
* CORREÇÃO URGENTE: conteúdo a sair do ecrã em Planear (mobile) — o contentor raiz do Self Route Planner não tinha nenhuma proteção de largura (max-width:100%/overflow-x:hidden), ao contrário de todos os outros painéis. Qualquer elemento lá dentro a calcular uma largura maior do que devia arrastava a página inteira para o lado, cortando texto e botões na borda do ecrã.
* Auditoria a todos os renderers de front confirmou que era um problema sistemático: mais 7 ficheiros tinham exatamente a mesma falha (nenhuma proteção de largura na raiz) — Despesas, Faturação, Implementações, Shopping, Resultados de Shopping, Signup e Stocks. Corrigidos todos de uma vez, antes de aparecerem como bugs "urgentes" um a um.
* Team Inbox já tinha proteção equivalente (max-width:min(1680px,100%)) — confirmado, não precisou de alteração.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.413.
* Não altera nenhuma lógica de planeamento de rotas, dados, REST, Azure ou licenciamento — só a contenção de largura na raiz de cada painel.
* Direct private upgrade path is 2.2.412 -> 2.2.413 using FieldFlow > Release Manager.

= 2.2.412 =
* Primeira ronda dedicada a Localizações de Campanha (CampaignLocations.php), a página do BO com mais estilo inline (312 ocorrências). Auditoria das 114 cores hardcoded: são consistentes e bem escolhidas (paleta Tailwind-like), não havia bug de contraste a corrigir aqui — o problema era mecanismo (inline repetido) mais do que valores errados.
* Motion e ritmo tipográfico consistentes: cartões (routespro-card, routespro-day-card), separadores de campanha (routespro-campaign-tab), KPIs, linhas da tabela de PDVs ligados e botões, todos com o mesmo tratamento que o resto do BO já tem.
* Confirmado que esta página já protegia corretamente contra o bug de palavra cortada a meio (overflow-wrap:normal!important já estava lá) e não tinha o bug de overscroll-behavior omni-direcional — não precisou de correção nesses dois pontos, ao contrário de várias páginas de front.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.412.
* Não altera nenhuma lógica de rotas, geo-clustering, importação CSV, dados, REST, Azure ou licenciamento — só CSS aditivo.
* Direct private upgrade path is 2.2.411 -> 2.2.412 using FieldFlow > Release Manager.

= 2.2.411 =
* Formulários menos "prensados": a queixa era falta de espaço ENTRE elementos, não dentro deles. Opções de checkbox em caixa (Reposição/Pedido ao responsável/Sem ação) com mais espaço entre si (gap 10px->12px), mais padding interno (min-height 56px) e texto maior (15.5px). Secções de formulário com mais margem entre si (20px). Botões de ação (Seguinte/Concluir/etc.) com espaço garantido entre eles.
* Confirmado por inspeção que a grelha "Última visita / Estado / Origem dos dados" na Ficha da Loja já é responsiva corretamente (3 colunas em ecrã largo, 1 em mobile) — não era um bug, o comportamento em telemóvel na imagem estava certo.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.411.
* Não altera nenhuma lógica de submissão de formulários, dados, REST, Azure ou licenciamento.
* Direct private upgrade path is 2.2.410 -> 2.2.411 using FieldFlow > Release Manager.

= 2.2.410 =
* Corrige o carrossel "Centro operacional" (Rota/Reportar/Stocks/...) a cortar texto na borda do ecrã em mobile — mesmo esbatimento de scroll que a tabbar já tem, para o corte parecer um convite a deslizar em vez de texto partido.
* Arranque do BO: novo fieldflow-admin-premium-shell-v2.css, carregado apenas em modo Foundation (o mesmo mecanismo seguro que já existia para o BO). Tipografia, botões, tabelas, cartões e badges/pills com o mesmo tratamento consistente que o front já tem, incluindo a mesma rede de segurança contra palavras cortadas em badges. Kill switch próprio: FIELDFLOW_ADMIN_PREMIUM_SHELL_V2.
* Removidos os marcadores "01/02/03" na página de Personalização (Appearance) — não representavam uma sequência real, só decoração.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.410.
* Este é o primeiro passo no BO (fundação tipográfica/forma). As páginas com mais estilo inline específico (Localizações de Campanha, Rotas, Emails, etc.) ficam para uma ronda dedicada, como fizemos com os portais de front.
* Não altera nenhuma lógica de dados, REST, Azure ou licenciamento.
* Direct private upgrade path is 2.2.409 -> 2.2.410 using FieldFlow > Release Manager.

= 2.2.409 =
* Corrige o Analytics "muito prensado": o primeiro KPI/gráfico em destaque estava a ocupar 2 colunas da grelha (grid-column:span 2), o que competia mal com o cálculo automático de colunas (auto-fit) consoante a largura do ecrã, espremendo os restantes cartões para muito menos espaço do que deviam ter. Removido o "roubo" de colunas; o destaque do primeiro item passa a ser só visual (cor de fundo, número maior), sem afetar o espaço dos outros.
* Mais espaço geral na zona de Analytics: gaps maiores entre secções e cartões de gráfico com mais padding interno.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.409.
* Não altera nenhuma lógica de dados, cálculos, REST, Azure ou licenciamento.
* Direct private upgrade path is 2.2.408 -> 2.2.409 using FieldFlow > Release Manager.

= 2.2.408 =
* Implementações, take 2: a correção da 2.2.407 ainda ficava à justa (14 caracteres em ~82px úteis, no limite). Colunas alargadas outra vez com folga real (150/116/140px conforme o número de colunas) e, mais importante, o rótulo passa a ter overflow-wrap:normal;word-break:keep-all explícito — isto neutraliza a regra geral da app que permite partir qualquer palavra, especificamente aqui, independentemente da largura da coluna. É a correção definitiva para este ponto.
* Corrigido o mesmo padrão de quebra a meio no badge "Concluído" (Shopping): era um <span> numa linha flexível ao lado do nome da loja, sem proteção contra encolher. Adicionado white-space:nowrap e flex-shrink:0.
* Rede de segurança geral: qualquer elemento cuja classe contenha "-badge" ou "-pill" em qualquer separador da app passa a ter essa mesma proteção automaticamente — cobre casos existentes (ffb-store-badge, ffp-pill, ffp-completed-badge, etc.) e futuros, sem precisar de corrigir um a um.
* Corrigida a barra de separadores flutuante a tapar a última linha de conteúdo em mobile: havia 4 valores diferentes de espaço reservado no fundo do ecrã, dois sem margem para a barra de gestos do telemóvel (env(safe-area-inset-bottom)). Unificado num único valor generoso que inclui sempre essa margem.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.408.
* Não altera nenhuma lógica de dados, REST, Azure, licenciamento ou schema.
* Direct private upgrade path is 2.2.407 -> 2.2.408 using FieldFlow > Release Manager.

= 2.2.407 =
* Corrige palavra cortada a meio nos cartões da aba Implementações: a grelha de métricas (Implementações, Implementadas, Pendentes, Bloqueadas, Material em falta) tinha colunas de 110px sem tamanho de letra próprio definido para o rótulo, herdando o tamanho de texto geral (15px) — "Implementações" e "Material em falta" não cabiam. Definido tamanho de letra próprio (11.5px) para os rótulos e alargadas as colunas (base e as duas variantes responsivas de mobile).
* Implementações (ffi-) trazida ao mesmo nível visual dos outros módulos (Briefings, Stocks, Despesas, Performance), que já tinham recebido este tratamento na 2.2.402 — tinha ficado de fora por engano.
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.407.
* Não altera nenhuma lógica de cálculo, dados, REST, Azure, licenciamento ou schema.
* Direct private upgrade path is 2.2.406 -> 2.2.407 using FieldFlow > Release Manager.

= 2.2.406 =
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.406.
* Layout "bento" estendido a todos os separadores dos dois dashboards, não só ao Analytics: primeiro KPI em destaque (maior, com traço de cor) em Minha Rota/App (rp-stats), Briefings, Stocks e Performance/Academy, sempre que a grelha tem 3 ou mais indicadores. Despesas (só 2 KPIs) manteve-se simétrico de propósito — o destaque assimétrico não fazia sentido visual com tão poucos itens.
* Checkboxes e radios nativos dos formulários passam a usar a cor primária do tema (accent-color), em vez do azul do sistema operativo — sem precisar de nenhum wrapper novo.
* Não altera nenhuma lógica de cálculo, dados, REST, Azure, licenciamento ou schema.
* Direct private upgrade path is 2.2.405 -> 2.2.406 using FieldFlow > Release Manager.

= 2.2.405 =
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.405 (parte do checklist de release a partir de agora, todas as vezes).
* Gradientes de hero mais profundos em Executive Blue, Aurora Glass e Emerald Ops: início do gradiente muito mais escuro/saturado, fim mantido exatamente no valor já verificado por contraste (não corri risco a mexer no que já sabia que funcionava). Nordic Minimal e Editorial Sand mantidos como estão — o próprio conceito de ambos pede restrição, não drama.
* Dashboard do Portal Cliente (painel Analytics) com layout "bento": o primeiro KPI e o primeiro gráfico ganham destaque (maior, com traço de cor), o resto segue em grelha normal — hierarquia visual real em vez de cartões todos iguais. Ativo a partir de 640px/860px; em mobile mantém-se tudo em coluna única, como já garantido.
* Duas frases de apoio no dashboard reescritas para um tom mais direto e menos genérico ("Resumo executivo" e "Dashboards").
* Não altera nenhuma lógica de cálculo, dados, REST, Azure, licenciamento ou schema — só como a informação é apresentada.
* Direct private upgrade path is 2.2.404 -> 2.2.405 using FieldFlow > Release Manager.

= 2.2.404 =
* Phase6StableRelease::STABLE_RELEASE sincronizado para 2.2.404. Tinha ficado parado em 2.2.401 nas duas releases anteriores (2.2.402 e 2.2.403) — processo meu, corrigido, e passa a fazer parte do checklist de toda a release daqui em diante. Depois de instalar: Revalidar cadeia 6B pós-update -> Testar fecho Fase 6 -> Certificar Fase 6 Stable.
* Primeiro impacto ao abrir a app: hero com glow em movimento lento e contido (duas manchas de cor desfocadas, na cor primária e de destaque do tema ativo, nunca sai da forma do hero), entrada em fade/subida orquestrada uma única vez ao carregar (hero + KPIs visíveis de imediato), nunca repetida a cada troca de separador.
* KPIs e estatísticas com traço de gradiente no topo (primary->accent) e sombra em duas camadas, ligando-os visualmente como família.
* Item ativo da rail do Portal Cliente com anel de brilho subtil.
* Tudo pausado automaticamente com prefers-reduced-motion: reduce.
* Não altera nenhuma lógica JS de negócio, REST, Azure, licenciamento, mobile ou schema de dados.
* Direct private upgrade path is 2.2.403 -> 2.2.404 using FieldFlow > Release Manager.

= 2.2.403 =
* Corrige texto cortado a meio da palavra em mobile: a coluna de rótulo nas tabelas transformadas em cartão (App, Portal Cliente, analytics) estava fixada em 104-105px com letras muito espaçadas (letter-spacing .055em). Alargada para 118-136px e o tracking reduzido para .03em — cobre todos os pontos onde o padrão aparecia (tabelas gerais, tabela de analytics, barras de partilha de espaço).
* Novo template "Neon Flux": 6º preset em Theme::templates(), superfície clara (mesma base segura dos outros 5) com hero quase-preto em glow, magenta e cyan elétricos, cards em glass, botões pill, sombra xl. Todos os pares texto/fundo verificados por contraste WCAG (>=4.5:1). Não altera nenhum dos 5 templates existentes.
* Corrigido o swatch de pré-visualização do Emerald Ops no seletor de templates do BO, que tinha ficado desatualizado depois da correção de contraste da 2.2.401 (era uma inconsistência introduzida por essa própria correção).
* Confirmado por auditoria completa: as 33 cores e todos os estilos/densidade/raios do Theme.php já tinham campo correspondente no BO (Personalização) antes desta versão — não faltava nenhum.
* Formulários (routespro-dyn-form / rp-form-ui) com tratamento visual premium: anel de foco com brilho na cor de destaque do tema em todos os campos, barra de progresso do wizard em gradiente primary->accent com brilho, botões com elevação ao hover, cartões de escolha visual com estado selecionado via :has() (progressivo, sem alterar HTML/JS), notices com barra de acento lateral.
* Corrigida uma cor fixa (#1e293b) em routespro-visual-choice-label que ignorava o tema; liga agora a var(--rp-form-heading).
* Não altera nenhuma lógica JS, PHP de negócio, submissão de formulários, REST, Azure, licenciamento, mobile ou schema de dados.
* Direct private upgrade path is 2.2.402 -> 2.2.403 using FieldFlow > Release Manager.

= 2.2.402 =
* Premium Shell v2 estendido aos módulos que ainda não tinham o tratamento: Briefings (ffb-), Stocks (ff-stock-), Shopping (ffs-), Despesas (ffe-) e Performance/Academy (ffp-).
* Mesmo critério das versões anteriores: cabeçalhos de secção, KPIs, cartões, botões, pills/badges e tabelas próprios de cada módulo ganham a tipografia, espaçamento e forma consistentes já aplicados ao resto — sem tocar em nenhuma cor.
* Confirmado que Stocks, Shopping, Despesas e Performance não tinham o bug de overscroll-behavior omni-direcional corrigido na 2.2.400; Briefings já usava o padrão correto.
* Não altera nenhuma lógica JS, PHP de negócio, REST, Azure, licenciamento, mobile ou schema de dados.
* Direct private upgrade path is 2.2.401 -> 2.2.402 using FieldFlow > Release Manager.

= 2.2.401 =
* Phase6StableRelease::STABLE_RELEASE avançado de 2.2.398 para 2.2.401. A certificação "Fecho da Fase 6" é uma baseline formal fixada por versão (não segue automaticamente cada release) — este avanço é uma decisão de governance explícita, feita a pedido, para permitir recertificar na versão atual via Revalidar cadeia 6B pós-update -> Testar fecho Fase 6 -> Certificar Fase 6 Stable.
* Rail lateral do Portal Cliente (>=1080px): navegação categorizada (Visão geral / Operação / Comercial / Relatórios / Equipa) substituindo a lista plana de até 12 separadores. Implementada como camada 100% aditiva em fieldflow-premium-shell-v2.js — lê os botões que já existem em .rp-tabbar e só lhes dá .click(); switchPanel(), carregamento de dados e listeners existentes não foram tocados. Em ecrãs <1080px a rail fica escondida e o fluxo é exatamente o original.
* 3 correções de contraste WCAG AA nos presets de tema, com rácio calculado (fórmula oficial), não por olho: Editorial Sand tinha nav_text_color com rácio 1.31:1 contra o fundo da nav (praticamente ilegível) - corrigido para 4.5+:1. Emerald Ops tinha texto branco dos botões a 3.77:1 sobre a cor primária - primary_color escurecido para 4.61:1. Executive e Editorial tinham muted_color a raspar o mínimo (4.43 e 4.33) - ambos acima de 4.5 agora. Nenhuma outra cor foi tocada; os 5 presets mantêm identidade e todas as chaves.
* Hero, grelha de KPIs e cabeçalhos de painel com mais respiração em ecrã largo.
* Não altera nenhuma lógica JS de negócio, REST, Azure, licenciamento, mobile ou schema de dados.
* Direct private upgrade path is 2.2.400 -> 2.2.401 using FieldFlow > Release Manager.

= 2.2.400 =
* Corrige o scroll horizontal das tabelas: a regra partilhada de overflow (fieldflow-design-system.css) continha `overscroll-behavior:contain` nos dois eixos, o que podia prender o gesto de scroll vertical da página quando o dedo/rato estava sobre uma tabela. Passa a `overscroll-behavior-x:contain` + `touch-action:pan-x pan-y`, o mesmo padrão já usado corretamente noutros pontos do próprio plugin (Briefings, Portal Cliente).
* Premium Shell v2 reforçado: as regras de tipografia, espaçamento e forma passam a usar !important nas propriedades não-cor, para vencerem consistentemente o <style> embutido que cada shortcode imprime depois desta folha no HTML. Cores continuam 100% geridas por Theme.php, sem alteração.
* Escala tipográfica mais marcada no hero, cabeçalhos de painel e KPIs; botões com padding e hover mais tácteis; tabelas com sinal visual de "há mais conteúdo" ao lado (esbatimento na margem), reforçando a correção do scroll acima.
* Não altera nenhuma lógica JS, PHP de negócio, REST, Azure, licenciamento, mobile ou schema de dados.
* Direct private upgrade path is 2.2.399 -> 2.2.400 using FieldFlow > Release Manager.

= 2.2.399 =
* Premium Shell v2: piloto de upgrade visual no Portal Operativos (fieldflow_app) e no Portal Cliente (fieldflow_client_portal).
* Camada aditiva de tipografia, espaçamento, forma e micro-interação sobre os componentes existentes (tabbar, cabeçalhos de painel, pills, KPIs, botões, cartões, tabelas, estados vazios/erro).
* Não altera nenhuma cor de tema; continua a herdar 100% dos tokens de Theme.php e dos 5 presets existentes (Executive Blue, Aurora Glass, Nordic Minimal, Emerald Ops, Editorial Sand) e da personalização por campanha.
* Não altera nenhuma lógica JS, PHP, REST, Azure, licenciamento, mobile ou schema de dados. Nenhum shortcode, handler de clique ou endpoint foi tocado.
* A tabbar de ambos os portais ganha scroll-snap e esbatimento nas bordas, resolvendo o corte abrupto quando há 10+ separadores.
* Reversível sem alterar código: define('FIELDFLOW_PREMIUM_SHELL_V2', false) em wp-config.php desliga a folha de estilo nova e devolve o visual anterior de imediato.
* Direct private upgrade path is 2.2.398 -> 2.2.399 using FieldFlow > Release Manager.

= 2.2.398 =
* Stock Snapshot Replacement: every published stock upload is a complete point-in-time snapshot for Client + Project + Insignia.
* Publishing a new snapshot atomically archives older published snapshots in the same scope, so week N never adds to week N-1.
* Products or stores absent from the new file disappear from active operational stock while the older batch remains available as archived history.
* Existing legacy installations are normalised once so only the newest published batch per scope remains active.
* Draft imports do not disturb the current live snapshot until explicitly published.
* Re-publishing an archived historical batch acts as an explicit rollback and replaces the current active snapshot.
* Stock REST payloads expose snapshot_mode=replace and the BO makes the replacement rule explicit.
* Direct private upgrade path is 2.2.397 -> 2.2.398 using FieldFlow > Release Manager.


= 2.2.397 =
* First FieldFlow release intended to be published end-to-end from WordPress Release Manager, with no routine Cloud Shell publication step.
* Adds a Release Pipeline self-check that distinguishes package publication from successful installation and proves the WordPress-published release is also the active runtime.
* Improves Release Manager visual clarity: hero title contrast, publication confirmation spacing and compact pipeline status feedback.
* Keeps the canonical `fieldflow/fieldflow.php` package contract and rejects flat or version-named roots before publication or installation.
* Preserves Release Publisher 1.0.0, private Blob immutability, catalog confirmation, SHA-256 verification and manual rollback policy.
* No operational data schema changes and no changes to routes, forms, Stocks, Briefings, Cardex, Azure operational delivery, mobile contracts or licensing enforcement.
* Direct private upgrade path is 2.2.396 -> 2.2.397 using FieldFlow > Release Manager to publish the ZIP and the normal WordPress updater to install it.

= 2.2.396 =
* Release Pipeline: adds a WordPress-native Release Manager for validating and publishing future FieldFlow private releases without routine Azure Cloud Shell work.
* Enforces the canonical WordPress package contract `fieldflow/fieldflow.php`; flat or version-named ZIP roots are rejected before publication and again before remote installation.
* Adds a one-purpose Azure Release Publisher sidecar that writes immutable private ZIPs with Managed Identity and never exposes Storage keys or SAS credentials to WordPress or browsers.
* The Release Manager validates plugin version, Stable tag, SHA-256, file count, every production-manifest file hash/size and aggregate hash before upload.
* Publication is verified back against the licensing catalog, then FieldFlow clears its private update cache and asks WordPress to refresh plugin update state.
* Adds installation diagnostics and a guarded canonical-folder repair action for legacy installs that are not yet under `wp-content/plugins/fieldflow`.
* Private updater cache is reduced to 30 minutes and remote updates now reject non-canonical package roots even when their outer SHA-256 is valid.
* Restores the certified associative production-manifest file map and keeps read compatibility with the temporary list format emitted by 2.2.394/2.2.395, fixing false release-manifest integrity failures.
* Stock Excel diagnostics no longer hide sheet materialisation exceptions; rejected candidate sheets are reported explicitly while the W36 Pingo Doce source remains reconciled at 8,251 rows and 117,905 units.
* Includes the cumulative Excel Intelligence, Assortment Intelligence and Weekly Cardex Sync introduced in 2.2.394/2.2.395.
* 2.2.396 is the final bootstrap release that requires Cloud Shell to deploy the Release Publisher. Normal releases from 2.2.397 onward can be published from FieldFlow > Release Manager.

= 2.2.395 =
* Assortment Intelligence: adds a persistent store format and controlled Cardex profile to Store Intelligence, shared by form reports, BO Lojas, mobile/offline and Azure location snapshots.
* Store profile saves use PATCH semantics so older clients or partial edits cannot silently erase typology, format, contacts, notes or pending information.
* Cardex profile defaults to Geral. Pingo Doce P1-P5 profiles are cumulative, while Sonae/Continente, Auchan and Intermarché use controlled retailer-specific types exposed by the active Cardex.
* Weekly Operational Briefings recognise and materialise the embedded Sonae, Pingo Doce, Auchan, Intermarché and El Corte Inglés Cardex when the briefing is published.
* Weekly Cardex sync preserves item IDs, store-specific overrides and manually managed items; products missing from the new weekly source are deactivated instead of deleted, and an older published briefing can be reapplied as a logical rollback.
* Pingo Doce unknown classifications such as B4 are preserved as source anomalies and never guessed into a store profile.
* Stock imports reconcile source totals and now link stock rows to Cardex products by exact EAN/reference/name or high-confidence mapping, exposing Cardex coverage separately from store coverage.
* Cardex changes invalidate project mobile/offline caches so the next bundle rebuild uses the new effective assortment without a new mobile API contract.
* Structured Excel data remains deterministic; OCR is not used for Stocks or Cardex.
* Direct private upgrade path is 2.2.393 -> 2.2.395. Publish the exact private 2.2.395 ZIP to Stable before revalidating 6B.4 and Phase 6 Stable.

= 2.2.394 =
* Excel Intelligence: introduces a shared deterministic workbook reader for Briefing, Stocks and Shopping imports.
* Scans all XLSX sheets, detects headers across one to three rows, understands merged cells and tolerates common header aliases instead of assuming fixed positions.
* Stocks selects the best data sheet, normalises store/SKU/EAN identifiers and PT/EN numeric formats, collapses duplicates safely and audits raw Excel stock total against the imported database total.
* Stock store matching now prioritises external reference, compact/numeric reference and exact names, with automatic association only at high confidence.
* Operational Briefing improves semantic parsing of promotions, Share Space, Cardex, VAPs, central spaces and store assortments, while keeping the legacy parsers as compatibility fallback.
* Briefing store associations apply only safe high-confidence mappings and expose import coverage in the sources panel.
* Shopping imports use the same workbook intelligence to locate moved product/type columns and grouped Regular/Promo store columns even when a cover sheet appears first.
* No database migration and no changes to Azure, Google, licensing, mobile, Desktop, exports or existing public contracts.
* 2.2.394 was superseded before deployment by cumulative 2.2.395; production may upgrade directly from certified 2.2.393 to 2.2.395.

= 2.2.393 =
* Purga Operacional controlada por utilizador e intervalo de datas, com preview obrigatório, token temporário e confirmação textual explícita.
* Limpa rotas de teste, paragens, atribuições de rota, submissões/respostas, histórico canónico associado, eventos, rascunhos, snapshots de rota e Shopping, preservando master data, despesas e ledgers de governance.
* Recalcula os registos canónicos depois da remoção para evitar métricas ou Memória da Loja órfãs.
* Bloqueia automaticamente a purga se uma rota selecionada tiver atividade de outros utilizadores.
* Deteta materialização Azure mesmo quando a submissão local já foi apagada, através dos eventos Cloud imutáveis, e sinaliza a reconciliação remota necessária.
* Invalida caches de rota/mobile dos utilizadores afetados e integra a nova ferramenta no BO e Desktop.
* Não adiciona tabelas nem altera contratos Azure, Google, licensing, exports ou endpoints existentes.
* A release privada 2.2.393 deve ser publicada no catálogo Stable antes da revalidação 6B.4/Phase 6 Stable.

= 2.2.392 =
* Store Intelligence: adiciona Ficha da Loja no arranque dos formulários, com dados de origem protegidos e perfil operacional editável.
* Responsável, telefone, email, pendentes e notas passam a persistir numa tabela operacional separada, com revisão concorrente e Audit Log.
* Mostra última visita, estado, perfil completo/incompleto e pendentes sem alterar a Memória da Loja já existente.
* Novo BO Lojas com pesquisa, filtros por cliente, projeto e merchan, paginação, edição e exportação CSV/Excel/PDF.
* Adiciona snapshot Store Intelligence aos contratos mobile/offline de forma aditiva e otimizada, sem alterar endpoints existentes.
* Não altera Google, Azure Functions, contratos de eventos Azure, licenciamento remoto, exportações existentes ou integrações Desktop.
* A release privada 2.2.392 deve ser publicada no catálogo Stable antes da revalidação 6B.4/Phase 6 Stable.

= 2.2.391 =
* Hotfix de continuidade da cadeia de certificação após updates privados nativos.
* 6B.4 deixa de aparecer certificada quando 6B.3 upstream está por revalidar.
* Adiciona revalidação explícita 6B.2 -> 6B.3 -> 6B.4, com heartbeat, governance e package checks live.
* O fecho Stable distingue identidade/governance preservadas do estado administrativo dos certificados.
* Mantém Production Enabled OFF, Full Operational Payload OFF, governance SHADOW, runtime blocking OFF e 11 jobs 5B Pending intocados.

= 2.2.390 =
* Fecha formalmente a Fase 6 Stable sobre 6A e 6B.1 a 6B.4 certificadas.
* Adiciona gate final com snapshot SHA-256, Release Gate e validação da instalação, governance e distribuição privada.
* Release preparada para provar o upgrade privado nativo 2.2.389 para 2.2.390 através do WordPress.
* Mantém Production Enabled OFF, Full Operational Payload OFF, governance SHADOW, runtime blocking OFF e auto-updates forçados OFF.
* Não altera schema, migrations, filas da Fase 5 nem processa automaticamente os jobs históricos 5B Pending.

= 2.2.389 =
* Fase 6B.4, distribuição privada e updates licenciados via Azure.
* Integra updates privados no fluxo nativo de plugins do WordPress com canais Stable/Beta.
* Verifica manifesto HMAC-SHA256, SHA-256 e tamanho do ZIP, e confirma a versão FieldFlow interna antes do upgrader.
* Downloads usam tokens temporários e revalidam licença, Activation ID, plano e Installation UUID.
* O contentor Blob permanece privado. Chaves de storage e connection strings nunca são expostas ao WordPress.
* Não força auto-updates, não oferece downgrades automáticos e mantém rollback como ação manual explícita.
* Mantém governance SHADOW, runtime blocking OFF, Fase 5, 6B.1-6B.3, schema, migrations e jobs 5B Pending inalterados.

= 2.2.388 =
* Corrige o fingerprint 6B.3 para planos com mapas vazios de capabilities/limits, alinhando a canonicalizacao PHP com o contrato Node/Azure.
* Um mapa vazio passa a ser canonicalizado como objeto JSON `{}` em vez de array `[]`, sem alterar a policy efetiva nem o backend Azure 1.0.4.
* Mantem enforcement SHADOW, runtime blocking OFF, Fase 5, 6B.1, 6B.2, schema, migrations e jobs 5B Pending inalterados.

= 2.2.387 =
* Hotfix de recuperacao segura da identidade 6B.2 quando uma Installation UUID local muda inesperadamente mas o Azure conserva o binding certificado.
* Um mismatch 409 apenas recupera a UUID remota se License ID, Activation ID, dominio e fingerprint SHA-256 coincidirem com o binding atual. O backend nunca e rebindado automaticamente.
* Preserva a ultima Installation UUID/fingerprint aceite quando um heartbeat falha, criando uma ancora de recuperacao para upgrades futuros.
* Se a opcao dedicada da identidade desaparecer, reutiliza a ultima identidade aceite apenas quando o fingerprint e o binding de dominio continuam validos.
* Nao altera backend Azure 1.0.4, governance 6B.3, enforcement SHADOW, schema, migrations, Fase 5 ou jobs 5B Pending.

= 2.2.386 =
* Runtime performance hotfix: remove o System Health completo dos admin notices de cada pagina FieldFlow.
* Deduplica health checks 6B.1, 6B.2 e 6B.3 dentro do mesmo request e usa cache curta do production manifest entre requests.
* Release Gate e certificacoes 6B continuam a forcar verificacao SHA-256 fresca.
* Sem alteracoes de governance, enforcement, schema, migrations, Fase 5 ou Azure delivery.

= 2.2.385 =
* Performance hotfix for Phase 6B.3. Memoizes production-manifest verification once per PHP request, eliminating repeated SHA-256 scans while preserving a fresh integrity scan on every new request.
* No licensing policy, governance, enforcement, database schema, Phase 5 or Azure delivery behaviour changes.

= 2.2.384 =
* Inicia a Fase 6B.3 com governance central de licencas, policy sync, suspensao, retoma, revogacao e rotacao de keys.
* Adiciona sincronizacao assinada do snapshot de plano, capabilities, limits, expiracao e maximo de instalacoes para o backend Azure.
* Mantem o limite maximo de ativacoes aplicado centralmente a novas instalacoes e acrescenta diagnostico de policy fingerprint para impedir drift silencioso.
* Introduz enforcement em SHADOW: decisoes remotas de suspended, revoked, expired ou rotated sao observadas e auditadas como would_block, mas nao bloqueiam o runtime FieldFlow.
* Modela grace period de 72 horas para indisponibilidade futura do servico, sem o tornar bloqueante nesta release.
* A rotacao devolve a nova key uma unica vez em memoria do request. O registry continua a guardar apenas mascara, SHA-256 e license_id remoto.
* Adiciona gate e diagnostico 6B.3, mantendo a 6B.2 certificada, Fase 5 intacta, Production Enabled OFF e Full Operational Payload OFF.

= 2.2.383 =
* Inicia a Fase 6B.2 em modo piloto seguro com identidade persistente por instalacao, activation binding observacional e heartbeat remoto.
* Envia Installation UUID, fingerprint SHA-256 e metadados tecnicos minimos durante ativacao e heartbeat, preservando o contrato legado da API.
* Reutiliza /license/validate para heartbeat HMAC, com cadence configuravel de 1 a 72 horas e sweep WordPress horario sem chamadas remotas desnecessarias.
* Regista a instalacao na tabela 6B existente quando a licenca pertence a um tenant local conhecido, sem guardar a license key em bruto.
* Adiciona gate e diagnostico 6B.2, incluindo acknowledgement Azure da Installation UUID/fingerprint e heartbeat persistido antes da certificacao. Enforcement, binding obrigatorio, revogacao remota, Production Enabled e full operational payload continuam desligados.
* Nao altera o schema WordPress nem a Fase 5. Os 11 jobs historicos 5B permanecem intocados em SHADOW.

= 2.2.382 =
* Fecha a Fase 6B.1 como baseline estavel de licensing e tenant management.
* Remove a persistencia temporaria da license key bruta em transients, mantendo a apresentacao unica apenas em memoria no mesmo request.
* Corrige a verificacao de key_files do production manifest para suportar o formato lista e o formato associativo sem quebrar manifestos anteriores.
* A certificacao 6B.1 passa a exigir integridade valida do production manifest e consistencia entre versao runtime, cabecalho do plugin e Stable tag.
* Regenera integralmente o production-manifest.json e mantem Fase 5, SHADOW, kill switches, full payload block e Production Enabled inalterados.

= 2.2.381 =
* Migra o endpoint canonico de licensing para a nova Azure Function de producao.
* Ignora apenas a constante legada quando aponta exatamente para o endpoint retirado, preservando overrides validos.
* Mantem HMAC, outbound hardening, SHADOW e todos os hard locks de producao.

= 2.2.380 =
* Hotfix 6B.1 para conectividade real da API remota de licenciamento.
* A Foundation passa a exigir um probe real à rota de validação antes de poder ser certificada.
* Adiciona diagnóstico separado para host, DNS, validação URL do WordPress, HTTPS, rota Function, HTTP e HMAC.
* Corrige falsos bloqueios do `wp_http_validate_url` apenas para o hostname fixo da Azure Function de licenciamento FieldFlow, mantendo HTTPS, porta 443, host exato, zero redirects e bloqueio de redes privadas.
* Tenants existentes permanecem intactos e nenhuma licença é emitida automaticamente.
* Enforcement, installation binding, remote revocation e Production Enabled continuam desligados.

= 2.2.379 =
* Fecho formal da Fase 6A com matriz de certificação, baseline SHA-256 e export JSON.
* Adiciona Fase 6B.1, Licensing & Tenant Foundation, com registry central por cliente e plano.
* Prepara tabelas de tenants e instalações para binding posterior, sem enforcement nesta release.
* Permite emissão explícita de chave remota pela API de licenciamento já existente, com apresentação única. O registry guarda apenas máscara e SHA-256, nunca o valor bruto da key.
* Integra Licenciamento & Tenants no grupo Gestão do BO e do FieldFlow Desktop.
* Production Enabled, full operational payload e installation enforcement continuam desligados.

= 2.2.378 =
* Fase 6A, integração visual e navegação do Onboarding de Clientes.
* Integra o Onboarding no grupo Gestão do menu FieldFlow e no layout consolidado do backoffice.
* Adiciona o Onboarding de Clientes ao menu do FieldFlow Desktop, com navegação interna segura.
* Mantém toda a lógica 6A, Fase 5 Stable, SHADOW, kill switches e Production Enabled em NÃO.

= 2.2.377 =
* Fase 6A, Client Onboarding & Provisioning Foundation.
* Novo wizard por cliente/projeto para TWT Cloud, Azure SQL, Power BI, Fabric, API, Webhook e Azure do Cliente.
* Provisioning nasce sempre em Shadow, com destino e regra inativos, sem ativacao de producao.
* Referencias Key Vault only, consentimento Entra browser-only e readiness consolidada por cliente.

= 2.2.376 =
* Final Phase 5 Stable Closure and certified release baseline.
* Adds a consolidated 5A to 5E certification matrix and a deterministic SHA-256 stable baseline fingerprint.
* Adds a final stable certification gate bound to the certified 5E control-plane fingerprint and the production manifest.
* Adds a downloadable baseline and rollback JSON with the known rollback package 2.2.375 and explicit safe rollback preconditions.
* Records stable certification/reset events in the tamper-evident 5E governance ledger.
* Adds Phase 5 Stable status to System Health and upgrades the generic read-only release gate to 1.2.0.
* Keeps SHADOW, connector/global kill switches ON, full operational payload delivery blocked and Production Enabled false by default.
* No Azure deployment or Broker update is required. Entra Broker 1.8.0 remains the certified Broker baseline.

= 2.2.375 =
* Phase 5E Governance, Recovery & Final Eligibility Gate.
* Adds a tamper-evident SHA-256 hash-chained governance ledger for destination, policy, credential and recovery actions.
* Adds global and scoped kill controls, controlled dead-letter replay with mandatory reason and short-lived authorization ticket, stale-lock recovery and safe control-plane snapshots.
* Adds final Production Eligible certification bound to a deterministic fingerprint of destinations, routing, 5C.8 production gates and 5D credential versions.
* Keeps the operational dispatcher hard-locked, SHADOW mode and kill switches ON. Production Enabled remains false until the final stable release gate.

= 2.2.374 =
* Phase 5D Credential Lifecycle & Secret Governance for API External/Webhook destinations.
* Adds Key Vault-only secret references, with no credential values stored or returned by WordPress.
* Adds Broker 1.8.0 secret probing through Managed Identity with metadata-only responses, expiry/not-before enforcement and version-aware rotation detection.
* Adds HMAC-signed credential certification receipts bound to destination, secret reference and secret version.
* Adds immediate local revocation and automatic readiness blocking on expiry, disablement, rotation or invalid credential state.
* Adds a synthetic TWT credential lifecycle receiver and secret so 5D can be certified without exposing operational data.
* Keeps SHADOW + global kill switch ON, full operational payload delivery blocked and general production activation blocked until 5E.

= 2.2.373 =
* Fase 5C.8 Production Delivery Gate: certifica um target e o caminho de fila com política explícita, sem abrir ainda a ativação geral de produção.
* Adiciona Entra Broker 1.7.0 com endpoint + policy SHA-256, token HMAC ligado ao destino/target/política, DNS pinning e receipts assinados.
* A política 5C.8 exige evento explícito, perfil `metadata_v1`, limite horário, retry limitado e safe projection only; wildcards e full payload permanecem bloqueados.
* Adiciona receiver TWT não persistente para certificação, com bootstrap automático da pré-condição 5C.7.
* Corrige o estado visual do one-shot 5C.7 consumido para deixar de permanecer como ARMADO.
* Destinos autenticados ficam explicitamente reservados à 5D Credential Lifecycle; produção geral permanece bloqueada até 5D/5E.

= 2.2.372 =
* Fase 5C.7 Safe Live Pilot: prepara e certifica um endpoint HTTPS real sem abrir o dispatcher operacional completo.
* Entra Broker 1.6.0 valida rede pública, bloqueia redirects e redes privadas, fixa a resolução DNS na ligação HTTPS e liga o target a um SHA-256 + token HMAC de curta duração.
* O piloto envia apenas uma allowlist de metadados técnicos; payload operacional original, respostas, fotos, uploads, passwords, API keys, access tokens e client secrets ficam fora do contrato.
* Adiciona certificação sintética e one-shot seguro, consumido antes da chamada de rede e sem retry automático.
* Inclui receiver TWT não persistente para certificação autónoma.
* O delivery operacional completo continua bloqueado, `delivery_enabled=false`, 5C.5 hard-locked e 5C.6 preservada.

= 2.2.367 =
* Fase 5C.3 Home Tenant Resource Authorization: permite provar Azure SQL e Power BI/Fabric no tenant TWT sem exigir onboarding cross-tenant.
* Adiciona o modo explícito `entra_home_tenant` sobre o Broker existente e mantém o mesmo modelo secretless de Workload Identity Federation.
* Adiciona o atalho `Preparar Power BI TWT`, que cria apenas um destino Shadow/inativo no routing; não ativa delivery nem altera o workspace.
* A tabela 5C.3 passa a distinguir identidades Home TWT e externas READY, preservando o fluxo 5C.2 para clientes externos.
* O Broker 1.3.0 permanece inalterado; o probe Power BI continua GET-only e o Azure SQL continua limitado a SELECT 1.
* Mobile/offline 2.2.365, Relay 5B 1.1.0, Azure TWT ingest, rotas, formulários, Portal Cliente e Desktop permanecem protegidos contra regressão.

= 2.2.366 =
* Fase 5C.3 Resource Authorization: separa identidade READY 5C.2 de autorização concreta ao recurso e mantém todo o delivery bloqueado em Shadow.
* Adiciona probes read-only no Entra Broker para Azure SQL (`SELECT 1`) e Power BI/Fabric (GET do workspace), sem devolver ou persistir access tokens; APIs externas permanecem em Shadow e sem tokens nesta fase.
* O WordPress guarda apenas identificadores públicos do recurso e o resultado do probe; não guarda connection strings, passwords, tokens, client secrets nem certificados do cliente.
* O BO ganha diagnóstico 5C.3, verificação de health do Broker, configuração por destino e estado `READY, RECURSO`, que não ativa routing nem delivery.
* A 5C.3 só considera destinos `Data Warehouse` e `Power BI do Cliente`; API/Webhook continuam reservados às fases posteriores de conector/delivery.
* Entra Broker sobe para 1.3.0 mantendo browser-only onboarding, HMAC via Key Vault/UAMI, delivery_enabled=false e todas as garantias 5C.2 já certificadas.
* Mobile Offline/Suspension Hardening 2.2.365, Azure TWT, Relay 5B 1.1.0, rotas, formulários, Portal Cliente, Desktop e fila offline permanecem sem regressões funcionais.

= 2.2.365 =
* Mobile/offline suspension hardening: cada input/change cria agora um checkpoint local síncrono antes do debounce de sincronização com o servidor.
* Fotografias começam a ser persistidas em IndexedDB antes da otimização assíncrona; o IndexedDB é pré-aberto ao inicializar o formulário.
* visibilitychange/pagehide/beforeunload/freeze continuam a forçar flush e passam também a iniciar checkpoint dos ficheiros pendentes.
* fieldflow:app-resume e pageshow BFCache reconciliam o rascunho local com o servidor e restauram a revisão mais recente, incluindo passo atual e media recuperável.
* A fila de submissões offline permanece inalterada e só limpa o rascunho local depois de a submissão completa estar duravelmente gravada na fila; submissões online só limpam após confirmação do servidor.
* Integra no pacote os scripts Azure 5B HMAC hardening já certificados, sem alterar o runtime de formulários com qualquer dependência Azure.

= 2.2.364 =
* Hotfix 5C.2 browser-only: usa Microsoft identity platform v2.0 authorize com scope OIDC openid para criar a Enterprise Application externa sem pedir permissões Graph/ARM ao utilizador.
* O resource scope configurado no destino deixa de participar no browser bootstrap e continua reservado ao token probe Workload Identity Federation após o callback.
* Corrige AADSTS650056 observado no fluxo legado /oauth2/authorize sem adicionar client secrets, certificados ou API permissions artificiais.
* Entra Broker Azure 1.2.0 permanece inalterado; delivery 5C continua bloqueado em Shadow.

= 2.2.363 =
* Simplifica o onboarding Microsoft Entra externo para browser-only: o cliente não precisa de Azure Cloud Shell, Azure CLI ou subscrição Azure.
* Substitui o fluxo /adminconsent por OAuth authorize para instanciar a Enterprise Application multi-tenant sem pedir permissões artificiais de Graph.
* O Broker 1.2.0 nunca troca nem guarda o authorization code; o único critério READY continua a ser o token probe real por Workload Identity Federation.
* Mantém delivery 5C bloqueado em Shadow, sem client secrets, certificados, access tokens ou payload operacional no WordPress.

= 2.2.362 =
* Hotfix 5C.2: transforma Configurar tenant/scope numa ação real para destinos Entra já existentes.
* Adiciona editor seguro para Directory tenant ID e resource scope, com validação, sugestões de scope e retorno direto ao onboarding.
* Alterar tenant/scope reinicia apenas o estado local de onboarding para DRAFT; delivery 5C continua bloqueado em Shadow.
* Não altera Azure Broker 1.1.0, Azure TWT, Relay 5B, Power BI, rotas, formulários, Desktop ou mobile.

= 2.2.361 =
* Phase 5C.2 external tenant onboarding: HMAC-signed admin consent state, Azure Broker callback token probe and HMAC-signed receipt back to FieldFlow.
* Adds DRAFT, CONSENT PENDING, TOKEN PROBE FAILED, CONSENT DENIED, TENANT MISMATCH and READY identity states per Entra destination.
* Delivery remains locked in Shadow; no access tokens, client secrets or certificates are stored in WordPress.

= 2.2.360 =
* Fase 5C.2 Microsoft Entra cross-tenant Identity Broker Foundation, mantendo todos os conectores 5C em Shadow e delivery_ready=false.
* Adiciona Entra Broker TWT dedicado em Azure Functions Flex Consumption Node 24, sem modificar a Function de ingestao nem o Relay 5B certificados.
* Cria uma App Registration multi-tenant TWT com workload identity federation baseada numa User Assigned Managed Identity dedicada, sem client secret ou certificado.
* O WordPress guarda apenas endpoint TWT, Application client ID, tenant IDs e resource scopes nao sensiveis; tokens sao obtidos e descartados exclusivamente no Broker Azure.
* Adiciona token probe HMAC control-plane para validar a federacao no tenant TWT sem expor access tokens e prepara o callback seguro para o onboarding cross-tenant da etapa seguinte.
* Destinos 5C com auth Entra passam a transportar tenant alvo e resource scope apenas como metadata Shadow no routing.
* Mantem Azure TWT, Client Azure 5B, Power BI, Desktop, mobile/WebView, rotas, formularios e Portal Cliente sem alteracoes funcionais.

= 2.2.359 =
* Integra `Destinos de Dados` no menu nativo do FieldFlow Desktop, dentro de Integrações.
* O novo item participa automaticamente na pesquisa rápida, recentes, navegação em frame e permission stamp do Desktop.
* Revê a paridade entre o catálogo principal do BO FieldFlow e o menu Desktop; não foram encontrados outros módulos primários em falta.
* Mantém a Fase 5C.1 em Shadow e não altera Azure TWT, Relay 5B, routing, formulários, rotas, mobile, Power BI ou integrações externas.

= 2.2.358 =
* Fase 5C.1 Connector Foundation em Shadow, sem envios externos, resolução de segredos ou aquisição de tokens.
* Formaliza contratos para API Externa, Webhook, Azure SQL / Data Warehouse e Power BI do Cliente.
* Routing passa a identificar cada conector 5C por contrato, transporte, auth prevista e modo de execução, mantendo delivery_ready=false.
* Adiciona diagnóstico local 5C.1 e validação de cada destino sem chamadas de rede.
* Mantém Azure TWT e Client Azure 5B independentes e inalterados no runtime.
* Atualiza apenas os artefactos de deployment Azure 5B incluídos no ZIP para a variante v7 Flex Consumption + Node 24 já certificada.

= 2.2.357 =
* Endurece o intake da Fase 5B depois de uma entrega real não entrar na fila local apesar do routing preview correto.
* O observer client_azure passa a executar em fail-open antes dos listeners opcionais, sem poder bloquear o Azure TWT principal.
* Reidrata client_id e project_id a partir da submissão canónica, route stop e rota quando o evento chega com scope incompleto.
* Adiciona diagnóstico de intake no BO, mostrando evento, scope, estado, motivo, destinos routed e entregas enqueued sem expor respostas de formulários.
* O diagnóstico local 5B passa a confirmar também o listener fieldflow_core_event e a prioridade fail-open.
* Não altera AzureSyncFoundation, HMAC, Relay Azure, SQL, Power BI, mobile/WebView, rotas, formulários ou exportações.

= 2.2.356 =
* Fase 5B Azure do Cliente, com fila independente por destino e Relay dedicado no Azure TWT.
* O Azure TWT certificado continua a usar AzureSyncFoundation e HMAC v1 sem alterações.
* Adiciona modos Shadow, Queue e Live para ativação progressiva e segura do fan-out client_azure.
* WordPress nunca guarda nem resolve credenciais do cliente, apenas endpoint não sensível e referência Azure Key Vault.
* O Relay TWT persiste entregas em ctl.ff_delivery_jobs, usa Managed Identity para Azure SQL e Key Vault, e aplica retry exponencial sem bloquear FieldFlow ou o Data Hub TWT.
* Adiciona idempotência por delivery key determinística, recuperação de locks stale, dead state e recuperação manual.
* Inclui receiver Azure isolado para certificação 5B, sem modificar ou redeployar a Function de ingestão existente.
* Novo diagnóstico local 5B, health da fila, dispatch manual e teste end-to-end por destino no BO Destinos de Dados.

= 2.2.355 =
* Corrige a integração visual de Destinos de Dados no menu agrupado FieldFlow, colocando a página dentro de Plataforma.
* Regenera o production-manifest.json para a release atual e elimina a divergência 2.2.353 versus 2.2.354 detetada pelo System Health.
* Mantém integralmente a Foundation Multi-Destination 5A, Azure TWT/HMAC, Power BI, apps, rotas, visitas, formulários e exportações.

= 2.2.354 =
* Fase 5A Multi-Destination Foundation, sem substituir a fila Azure/HMAC certificada.
* Adiciona Destination Registry por tenant, cliente e projeto, com tipos, estado, modo, endpoint/ref, Key Vault reference e política de retry não sensível.
* Adiciona Routing Rules com include/exclude, padrões de evento, prioridade e scope por cliente/projeto.
* Azure TWT é registado automaticamente como destino primary obrigatório e continua a ser entregue exclusivamente pelo AzureSyncFoundation existente, sem copiar o endpoint ou HMAC para o novo registry.
* Destinos externos funcionam em shadow mode nesta fase, sem qualquer envio até 5B/5C.
* Adiciona delivery key determinística por Event UUID + destination UUID para idempotência futura, sem alterar o EventBus nem os UUID já usados pelas fases 3/4.
* Novo BO FieldFlow > Destinos de Dados com registry, regras, preview de routing e diagnóstico local sem chamadas externas.
* WordPress não aceita segredos externos no registry, apenas referências Azure Key Vault.
* Inclui scripts Azure SQL 5A separados para control-plane multi-destination, sem alterar dbo/rpt/sec existentes nem permissões Power BI.

= 2.2.353 =
* Corrige os downloads CSV, Excel e PDF de Rotas no Portal Cliente em web, FieldFlow Desktop e app móvel.
* O handler distingue pedidos BO e Portal Cliente pelo nonce, permitindo testar o portal com contas administrativas sem falhar a validação.
* Desktop e mobile passam a usar ticket opaco temporário para exportações de rotas, evitando depender dos cookies WordPress no downloader nativo.
* O ficheiro é criado durante a sessão autenticada, com scope e entitlements aplicados antes de gerar o ticket, e o ticket expira em 10 minutos.
* Mantém integralmente o layout responsivo de Campanhas PDV da 2.2.352 e não altera Azure, Power BI, HMAC ou contratos Cloud.

= 2.2.352 =
* Portal Cliente > Rotas passa a exportar CSV, Excel e PDF.
* Sem operativo selecionado exporta todas as rotas do cliente/campanha dentro do scope autorizado; com operativo exporta apenas as rotas desse operativo.
* Exportação de rotas no Portal Cliente aplica nonce dedicado, scope server-side e entitlements do plano.
* Campanhas PDV > PDVs e Regras deixa de esmagar colunas em BO web/Desktop e passa a layout em cartões no mobile.
* Sem alterações Azure, Power BI, HMAC, src/Mobile ou correções iOS certificadas.

= 2.2.351 =
* Phase 4B.1 adiciona dimensão segura de Operativos / Merchandisers para Azure SQL e Power BI.
* Novo snapshot operacional minimizado, sem email, telefone, morada ou outros dados de contacto.
* Backfill de operativos com fila HMAC existente e IDs determinísticos.
* Reporting contract passa a Data Hub 1.5.0 com `rpt.operatives` e `rpt.operative_execution_daily`.
* Mantém apps iOS/Android, Data Hub existente e Azure Function sem alterações.

= 2.2.350 =
* Phase 4A Azure Reporting Security Foundation com Power BI seguro por tenant, cliente e projeto.
* Camada `rpt` desacoplada do WordPress e sem credenciais SQL guardadas no plugin.

= 2.2.349 =
* Hotfix iOS: a camara dos formularios usa o seletor nativo WKWebView no iPhone/iPad, evitando uma segunda camada WebRTC de permissao.
* Galeria iOS passa a usar o file chooser do sistema com accept image/* e abertura por gesto direto, sem exigir acesso geral a Fototeca.
* Submissoes na app nativa passam por admin-ajax.php, mantendo admin-post.php como fallback web, para evitar respostas HTML no fecho de visita.
* Respostas HTML inesperadas deixam de ser apresentadas ao operacional e sao convertidas numa mensagem segura de recuperacao.
* Azure Data Hub 1.3.0, HMAC, filas Cloud e Fase 3 permanecem inalterados.

= 2.2.347 =
* Fase 3 final: Stock Bulk Backfill Auto Runner para sincronizar históricos extensos sem cliques repetidos.
* Mantém chunks técnicos de 100 linhas, cursor persistente, UUID determinístico, throttling da fila, pausa/retoma, progresso, velocidade e ETA.
* O runner pára automaticamente perante falhas definitivas e preserva todos os snapshots e integrações 3A/3B/3C existentes.

= 2.2.346 =
* Fecha a Fase 3 do Azure Data Hub com Briefings e Stocks materializados em Azure SQL.
* Adiciona snapshots históricos controlados de Briefings, lotes de stock e linhas de stock, com UUID determinístico e chunks para ficheiros grandes.
* Briefings publicados e imports de stock passam a ser enriquecidos apenas no canal Azure, preservando EventBus, webhooks e apps.
* Adiciona views Power BI-ready de briefings, execução de briefing, stock atual por loja e execução 360.
* Mantém Submissões 3A, Lojas/Rotas/Visitas 3B, HMAC, Azure Function e src/Mobile inalterados nos respetivos contratos.

= 2.2.345 =
* Corrige o snapshot 3B de Visitas / Paragens quando existem route_stops históricos órfãos ou com rota removida.
* O cursor de Visitas deixa de ficar bloqueado no primeiro registo inválido e continua pelos registos elegíveis seguintes.
* O progresso de Visitas conta apenas paragens com rota existente e mostra separadamente registos ignorados por integridade.
* Lojas, Rotas, Submissões, HMAC, Azure SQL, Azure Function e contratos Mobile permanecem inalterados.

= 2.2.344 =
* Fase 3B Data Hub: adiciona snapshots controlados de Lojas, Rotas e Visitas para Azure SQL.
* Visitas concluídas passam a poder atualizar no Azure a própria visita e snapshots associados da rota e da loja, sem alterar o EventBus usado por outras integrações.
* Adiciona backfill operacional por entidade, em lotes, com UUID determinístico e processamento imediato pela fila HMAC já certificada.
* Mantém Briefings e Stocks desligados nesta fase e preserva integralmente os contratos Mobile aprovados.
* O pacote Azure 3B cria ff_routes, ff_visits e views Power BI-ready de execução diária e desempenho de rota.

= 2.2.343 =
* Corrige o backfill Data Hub 3A para não depender do WP-Cron no processamento manual.
* Cada lote acorda o dispatcher Cloud imediatamente usando o mesmo canal HMAC e regras de retry.
* Adiciona o botão Processar fila Cloud agora para recuperar jobs históricos já pendentes sem reiniciar o snapshot.
* O BO passa a distinguir registos enfileirados de jobs efetivamente entregues ao Azure.

= 2.2.342 =
* Fase 3A Data Hub: adiciona materializacao relacional de Submissoes no Azure SQL, sem alterar o contrato mobile.
* Adiciona backfill historico controlado no BO, por lotes, sem repetir webhooks, faturacao ou hooks normais de submissao.
* O backfill usa Event UUID deterministico, permitindo retomar ou repetir sem duplicar eventos no Azure.
* Mantem apenas Submissoes ativas por defeito no Cloud Sync para novas instalacoes desta fase.
* As apps iOS e Android, endpoints mobile, formularios e fluxo operacional permanecem inalterados.

= 2.2.341 =
* Fase 2 Cloud Secure Connector: endpoint Azure Cloud dedicado com assinatura HMAC-SHA256 v1.
* Segredo HMAC protegido pelo Secret Vault e separado do conector Azure legado.
* Novo teste assinado do endpoint Azure sem necessidade de ativar a fila de sincronização.
* Tokens e secrets persistidos deixam de ser devolvidos ao HTML dos principais campos de integração; o token API regenerado só é revelado uma vez.
* O Azure Cloud Sync continua desligado após atualização e não altera os contratos mobile aprovados.

= 2.2.340 =
* Introduz FieldFlow Cloud Foundation, Fase 1, sem alterar os contratos das apps iOS ou Android.
* Cria identidade persistente multi-tenant e de instalacao para futuras ligacoes FieldFlow Cloud e Azure do cliente.
* Adiciona fila persistente de sincronizacao Cloud com idempotencia, retries, estados, diagnostico e limpeza automatica.
* Liga a fila ao EventBus central para submissoes, visitas concluidas, briefings publicados e imports de stock.
* Adiciona no BO o painel FieldFlow Cloud Foundation, selecao de dados, destino futuro, retries e diagnostico local.
* A sincronizacao Azure fica desligada por defeito. A instalacao desta build nao envia dados para fora do WordPress.
* Mantem o conector Azure atual, Power BI, API publica, webhooks e restante comportamento existente por compatibilidade.

= 2.2.339 =
* Corrige o fluxo de consentimento da câmara para revisão iOS, removendo o preflight nativo `fieldflowMediaRequest` antes da abertura da captura.
* O pedido de acesso passa a nascer apenas da ação explícita do utilizador ao tocar em Tirar fotografia ou Ler QR com câmara.
* O FieldFlow deixa de apresentar instruções para abrir as Definições quando a câmara é recusada. Em alternativa, permite tentar novamente ou escolher uma imagem.
* Mantém o seletor de galeria independente da permissão de câmara e preserva uploads, evidências, despesas, formulários e captura integrada.
* O planeador aplica a mesma regra à localização, sem preflight nativo nem instruções automáticas para abrir Definições.
* Não altera respostas, submissões, imagens históricas, endpoints, Analytics, Briefings, Stocks ou dados de operação.

= 2.2.338 =
* Corrige o aumento aparente de widgets depois da 2.2.337: a família permanente Comparativo de referências passa também pelo desativador semântico de duplicados, mantendo apenas o owner evergreen e deixando o binding antigo como legado inativo.
* O contador e a lista do Centro de Analytics passam a respeitar Cliente, Projeto e Rota selecionados. O KPI passa a chamar-se Widgets ativos no contexto.
* Contagens de dashboard, evidências e frentes deixam de incluir linhas inativas quando Mostrar inativos / legados está ativo.
* Migração não destrutiva, sem alteração de respostas, imagens, question_key ou FlutterFlow.

= 2.2.337 =
* Explicit Analytics Ownership: uma escolha Analytics só fica protegida como decisão manual quando o administrador altera explicitamente o comportamento ou a configuração permanente. Saves antigos deixam de transformar sugestões automáticas em decisões imutáveis.
* A migração repara marcadores legacy `analytics_behavior_source=user` sem confirmação explícita e volta a inferir o destino pelo significado da pergunta, mantendo intactas as escolhas realmente confirmadas.
* `Comparativo de referências do briefing` ganha a família permanente própria `Comparativo de referências` e deixa de poder contaminar `Frentes e Share Space` apenas por usar o tipo técnico share_space_matrix.
* Perguntas pontuais como vagas, folhetos de uma semana, alterações de PS/MDKs e ações temporárias voltam ao widget dinâmico `Briefing operacional`, salvo decisão manual confirmada.
* O cabeçalho dos widgets Analytics foi redesenhado para mobile: grelha de uma coluna, títulos horizontais, badges com wrap e remoção do eyebrow redundante no smartphone.
* Portal do cliente, dashboard do merchandiser e WebView da FieldFlow App passam a reservar safe area inferior adicional para que a navegação fixa não tape o último conteúdo Analytics.
* A atualização é não destrutiva: não elimina perguntas, respostas, submissões, imagens, uploads, evidências ou histórico. Widgets substituídos ficam inativos / legados.

= 2.2.336 =
* Semantic Dashboard Guardrails: famílias estratégicas passam a depender do significado da pergunta. Tipos técnicos como share_space_matrix, product_matrix, evidence_check ou image_upload deixam de decidir sozinhos o destino Analytics.
* Corrige classificações erradas herdadas, incluindo comparativos genéricos guardados como matriz Share Space, que passam para Briefing operacional quando o texto não comprova a família estratégica.
* Quando uma pergunta é retirada de uma métrica consolidada, o binding atualiza também a pergunta representativa e limpa aliases/tipos/evidências associados, evitando títulos antigos em widgets corretos.
* Se uma métrica gerida muda de família por correção semântica, o FieldFlow deixa de herdar automaticamente a visualização da família anterior e volta a aplicar defaults coerentes com a origem atual.
* O otimizador desativa duplicados semânticos antigos quando já existe um owner evergreen gerido da mesma família e do mesmo âmbito, sem apagar histórico.
* Decisões manuais do administrador continuam protegidas por analytics_behavior_source=user. A migração 2.2.336 é não destrutiva e não altera respostas, submissões, fotos, uploads ou question_key históricas.

= 2.2.335 =
* Aggressive Evergreen Consolidation: o otimizador passa a desativar também widgets legados cobertos por uma métrica permanente, incluindo bindings antigos sem os marcadores modernos de gestão.
* Perguntas pontuais antigas de Briefing são reclassificadas para Briefing operacional, exceto quando o utilizador as marcou explicitamente como métricas permanentes.
* Variantes estratégicas passam a convergir por família canónica, incluindo Espaços centralizados, Materiais VAP, Promoções e visibilidade, Preços em linear, Frentes e Share Space, Frentes atuais, Sugestão de encomenda e Problemas e oportunidades.
* Stock estimado e Produto disponível podem ser reunidos em Stock e disponibilidade; Problemas e Oportunidades podem ser reunidos num único widget operacional quando partilham o mesmo âmbito.
* Fotografias operacionais permanentes, como Linear, Depois, Campanha, Implementação, Concorrência e Problema, passam para o widget gerido Evidências operacionais, mantendo as origens e a navegação por tipo e loja.
* Métricas estratégicas com evidência mantêm a evidência associada no mesmo payload e no portal do cliente, mesmo quando o gráfico principal não é uma galeria.
* A migração 2.2.335 é não destrutiva: não apaga respostas, submissões, ficheiros, imagens ou bindings; os widgets substituídos ficam inativos / legados e continuam auditáveis.

= 2.2.334 =
* Smart Evergreen Dashboard: perguntas pontuais de Briefing deixam de criar widgets individuais e passam para os widgets dinâmicos Briefing operacional e Evidências do briefing.
* O editor de perguntas acrescenta o destino Analytics: Automático no Briefing operacional, Associar a métrica permanente existente ou Criar nova métrica permanente.
* Famílias estratégicas recorrentes, incluindo Espaços centralizados, Materiais VAP, Promoções, Preços em linear, Stock, Disponibilidade, Share Space e Número de frentes, reutilizam métricas permanentes estáveis.
* A reconciliação mantém o dashboard Briefing semanal já existente, consolida fontes equivalentes e desativa bindings automáticos redundantes sem apagar perguntas, respostas, submissões, fotos ou uploads.
* O Centro de Atribuições passa a ter Otimizar dashboard e protege os dois widgets dinâmicos geridos pelo FieldFlow contra edição ou eliminação acidental.
* BO, portal do cliente, merchandiser web e FieldFlow App consomem o mesmo payload Analytics, com cobertura por pergunta, distribuição de respostas e evidências agregadas.

= 2.2.333 =
* Briefing Store Scope: acrescenta três âmbitos independentes do mapping, todas as lojas ativas da insígnia, todas as lojas ativas do projeto/campanha e lojas ativas selecionadas do projeto.
* Mantém os três modos anteriores de lojas mapeadas, garantindo compatibilidade com perguntas já configuradas e histórico existente.
* Perguntas com âmbito ativo podem ser disparadas em lojas da carteira do projeto mesmo quando essas lojas não aparecem no ficheiro semanal do briefing.
* A proteção do formulário, front web, API mobile e FieldFlow App continua dependente da loja concreta da visita e da carteira ativa da campanha.
* BO e front de Briefings passam a identificar o âmbito real de cada pergunta junto da cobertura por loja.
* Sem alterações de schema e sem eliminação de respostas, submissões, evidências, uploads ou widgets Analytics.

= 2.2.332 =
* Evergreen Briefing Analytics: as perguntas recorrentes dos briefings passam a reutilizar uma métrica permanente, mantendo a chave semanal original para auditoria e histórico.
* O FieldFlow reutiliza automaticamente o dashboard ativo "Briefing semanal" do cliente/projeto, ou cria-o quando não existe, evitando a proliferação de dashboards e widgets semana após semana.
* A API Analytics agrega todas as question_keys históricas associadas à mesma métrica permanente, por isso BO, portal do cliente e FieldFlow App mostram um único widget com histórico consolidado.
* No BO dos Briefings, cada pergunta mostra cobertura completa: lojas alvo, respondidas, pendentes e uma lista pesquisável com estado, última resposta e data.
* No front de Briefings, a área Execução passa igualmente a permitir ver e pesquisar todas as lojas onde cada pergunta está ativa, respeitando o âmbito do utilizador.
* O Centro de Atribuições mostra apenas widgets ativos por defeito, permite consultar legados/inativos e inclui a ação "Consolidar briefings" para repetir a reconciliação sem apagar respostas, evidências ou histórico.
* A atualização executa uma migração não destrutiva que atribui identidades permanentes, consolida bindings automáticos duplicados no dashboard preferido e apenas desativa duplicados legados.

= 2.2.331 =
* Privileged MFA & Session Policy Foundation: adiciona TOTP para contas com routespro_manage no BO, Desktop e front web privilegiado, com challenge pós-login compatível com a página pública /login/.
* Segredos TOTP ficam cifrados no SecretVault; códigos de recuperação são guardados apenas como hashes one-way, uso único, com proteção contra replay TOTP e rate limiting.
* Rollout seguro com modos independentes de Observação/Enforcement para MFA e limites de sessão. A instalação não termina sessões existentes.
* Política de sessão configurável: BO/Desktop 8h idle e 12h absoluto; mobile 7 dias idle e 30 dias absoluto; WebView 60 min idle e 24h absoluto. Ao ativar enforcement, sessões existentes recebem nova baseline absoluta nesse momento.
* MFA mobile para contas privilegiadas fica disponível no contrato/API através de mfa_code, mas desligado por defeito para não exigir alterações imediatas no FlutterFlow. Merchandisers não recebem MFA.
* Saúde do Sistema e diagnóstico JSON passam a expor apenas estado agregado da política, sem chaves TOTP, recovery codes, tokens de sessão ou IDs individuais.
* Sem alterações a Mensagens/anexos 2.2.330, Analytics, export tickets, rotas, formulários, multi-foto, SSRF Guard ou Protected Media.

= 2.2.330 =
* Corrige ERR_INVALID_RESPONSE no download externo de anexos Excel/documentais das Mensagens.
* O link assinado passa a usar admin-post.php, evitando cache/proxy/canonicalização da página pública.
* Streaming binário reforçado contra compressão/buffering e preservação de Content-Length.
* Links antigos da raiz continuam suportados temporariamente; sem alteração ao FlutterFlow ou às exportações Analytics/Desktop.


= 2.2.329 =
* Corrige os anexos de Mensagens na app do merchandiser sem alterar as restantes exportações.
* Documentos/Excel ficam explicitamente marcados como anexos de equipa e usam uma cadeia de compatibilidade: abertura externa, downloader nativo e fallback browser.
* Imagens passam a ter URL inline assinada para o visualizador interno e ação de download própria.
* O resolvedor de anexos locais suporta URLs públicas servidas por CDN/proxy, mantendo validação final por realpath dentro de wp-content/uploads.
* Anexos de respostas deixam de ser ocultados quando a mensagem original já contém uma coleção de anexos vazia.

= 2.2.328 =
* Corrige a semântica de saúde do Offline First: conflitos deixam de ser contados como falhas reais e passam a ser apresentados separadamente.
* Repara conflitos operacionais seguros uma vez por item/geração de runtime, sem apagar histórico nem criar loops de retry.
* A Saúde do Sistema distingue críticos de observabilidade ativos dos críticos históricos em aberto; histórico permanece disponível para auditoria.
* Load Readiness 1.1.0 distingue 4xx funcionais (`client_error`) de falhas reais do servidor, calcula p95 de queries e transforma outliers isolados de queries/latência em sinais de capacidade em vez de falhas absolutas.
* Queue Health passa a pontuar falhas não recuperadas e terminais ainda ativos, preservando contadores históricos de falhas e recuperações.
* Reforça a sanitização do JSON de diagnóstico: mensagens mobile deixam de exportar caminhos locais do dispositivo ou erro bruto.
* Sem alterações destrutivas, schema SQL, autenticação, autorização, formulários, Analytics, exportações, Desktop, SSRF Guard ou Protected Media.

= 2.2.327 =
* System Health Diagnostic Drilldown & JSON Export: adiciona aba de diagnóstico dos warnings e export JSON sanitizado da Saúde do Sistema.
* Inclui detalhe agregado de Offline First, Observabilidade, Performance/Load Readiness, processos, queue health, cron, release integrity e SSRF Guard.
* Não inclui payloads, conteúdos funcionais, credenciais, IPs, user IDs, device IDs ou erros brutos de dispositivo.
* Sem alterações a dados, permissões, app, formulários, Analytics, multi-foto, mensagens, exportações operacionais ou políticas SSRF.

= 2.2.326 =
* BO/Desktop Menu Integrity: corrige agrupamento visual e garante Configurações e Saúde do Sistema em Sistema e Governação.
* Mantém paridade de navegação administrativa entre BO e Desktop, sem alterar permissões funcionais.

= 2.2.325 =
* Outbound HTTP / SSRF / Redirect Hardening: centraliza saídas HTTP, força HTTPS/TLS, allowlists, bloqueio de redes privadas e redirects seguros.

= 2.2.324 =
* Security Observability JSON Export: adiciona exportação JSON sanitizada dos últimos 30 dias para auditoria e análise técnica.
* Inclui resumo por superfície, classificação, drill-down por classe e eventos de segurança sanitizados, sem payloads, passwords, tokens, nonces, cookies, IPs, device IDs ou IDs individuais de utilizador.
* Exportação protegida por routespro_manage + nonce, servida como attachment com no-store, nosniff e Referrer-Policy no-referrer.
* Não altera autenticação, autorização, endpoints funcionais, app, Desktop, formulários, Analytics, mensagens ou Protected Media.

= 2.2.323 =
* Authorization Denial Drilldown: detalha recusas reais de autorização por endpoint/ação, superfície, método, status HTTP e reason code sanitizado.
* Mostra ocorrências autenticadas/anónimas, número de atores distintos e primeira/última deteção sem expor IDs individuais, payloads, tokens, nonces ou conteúdo funcional.
* Registos históricos sem reason code permanecem intactos e são identificados apenas como legado no painel.
* Nenhuma alteração a permissões, endpoints, autenticação, app, Desktop, formulários, Analytics, exportações, mensagens ou Protected Media.

= 2.2.321 =
* Authorization Telemetry Classification: separa recusas técnicas de sessão, autenticação, autorização, cross-scope, rate limit, CSRF/nonce, políticas/entitlements e outras recusas.
* Os eventos 2.2.320 existentes são classificados em leitura com base apenas em surface, route, status e reason code já armazenados; não existe migração nem reescrita do histórico.
* Novas recusas guardam classification e reason_code sanitizados, sem payloads, passwords, tokens, nonces ou conteúdo funcional.
* Fingerprints de recusas passam a separar status/classificação para não misturar 401, 403 e 429 na mesma janela de agregação.
* O painel Segurança mostra KPIs por significado de segurança e mantém a origem técnica REST/AJAX/admin-post como detalhe secundário.
* Sem alterações a autenticação, autorização, endpoints, schema SQL, Analytics, exportações, FlutterFlow, Desktop, mensagens, formulários ou Protected Media.

= 2.2.301 =
* Security hardening candidate: separa autorização de leitura e escrita de rotas.
* Merchandisers só podem alterar rotas próprias ou com assignment ativo; scope de cliente/projeto mantém-se apenas para leitura contextual.
* Otimização, eventos operacionais e sync offline de paragens usam a mesma autorização de escrita estrita.
* PATCH de Campaign PDVs passa a exigir routespro_manage, mantendo GET inalterado.
* Sem alterações de schema, endpoints, payloads ou funcionalidades de planeamento.


= 2.2.300 =
* Desktop Polish & Release Gate: reforco de sessao, permissoes, offline, bridge e diagnostico para preparar o FieldFlow Desktop 1.0 RC.
* Heartbeat Desktop passa a validar perfil/permissoes e atualiza o shell quando o acesso do utilizador muda.
* Navegacao inbound da Native Bridge limitada a URLs same-origin e protocolos seguros.
* Drag and drop passa a respeitar accept/multiple e o limite real de upload do servidor.
* Novo painel Acerca do FieldFlow Desktop com versao Core, bridge, perfil e modo de host.
* Melhorias de layout para 1366x768 e ecras de menor altura, sem alterar mobile/FlutterFlow.

= 2.2.299 =
* Adiciona a camada Desktop Native Bridge 1.0, com deteccao de WebView2 nativo e fallback seguro para o modo Edge atual.
* Adiciona notificacoes opt-in no Windows/Edge para novas mensagens diretas do FieldFlow, com polling autenticado e sem repetir mensagens antigas.
* Adiciona botoes de copiar link do modulo e imprimir, com bridge nativa quando existir e fallback Web no QuickInstall atual.
* Downloads e links externos passam a emitir eventos para a futura app Windows nativa; no modo atual mantem-se o comportamento do browser.
* Adiciona atalhos Ctrl+P, Ctrl+Shift+C, Ctrl+R e Alt+Esquerda/Direita, mantendo Ctrl+K.
* Adiciona feed de atividade Desktop autenticado e extensivel por filtro, sem nova tabela nem alteracoes de schema.
* Mantem compatibilidade integral com o QuickInstall v0.1.2, FlutterFlow, Android e iOS.

= 2.2.298 =
* O FieldFlow Desktop deixa de encaminhar utilizadores sem sessao para o wp-login.php e passa a usar a pagina publica /login/.
* Quando Ultimate Member esta ativo, reutiliza a pagina de login configurada pelo proprio site.
* Preserva redirect_to para regressar automaticamente ao FieldFlow Desktop depois da autenticacao.
* Se a sessao expirar dentro do Desktop, o shell deteta wp-login.php ou heartbeat 401 e encaminha a janela principal para o login publico.
* Terminar sessao passa igualmente pelo login publico, mantendo o retorno ao Desktop.
* Sem alteracoes de schema, FlutterFlow, Android ou iOS.

= 2.2.297 =
* Dashboard do Desktop passa a abrir o Analytics operacional FieldFlow em vez do resumo tecnico de shortcodes.
* Retoma automaticamente o ultimo modulo nao tecnico usado por cada utilizador e guarda uma lista local de modulos recentes.
* Adiciona pesquisa rapida Ctrl+K sobre modulos, clientes e campanhas/projetos.
* Adiciona navegacao Voltar, Avancar e Atualizar no shell e estado Online/Offline com heartbeat autenticado ao servidor.
* Links externos abrem fora do workspace e downloads passam a ter feedback visual no Desktop.
* Adiciona drag and drop generico para campos de upload visiveis no modulo aberto, sem submeter automaticamente dados.
* Mantem o QuickInstall v0.1.2, o role router e o clean frame da 2.2.296 sem alteracoes de schema, FlutterFlow, Android ou iOS.

= 2.2.296 =
* Limpa o chrome do wp-admin dentro do FieldFlow Desktop, incluindo barra superior e menu lateral, mesmo após navegação interna.
* Preserva o modo frame em links e filtros administrativos e acrescenta limpeza redundante pelo shell parent.
* Expande o menu Desktop para o mapa completo de módulos FieldFlow, incluindo Mapping, Importações, Categorias, Subscrições, Faturação, IA, E-mails, API/Webhooks, Personalização, Dispositivos, Privacidade, Segurança e Processos.
* Adiciona pesquisa de módulos na barra lateral.
* O WordPress completo continua disponível em Administração técnica e conserva o seu menu nativo quando aberto explicitamente.
* Sem alterações de schema SQL, REST mobile, FlutterFlow, Android ou iOS.

= 2.2.295 =
* Evolui o FieldFlow Desktop para um shell desktop-first com navegação lateral persistente e menu recolhível.
* Adiciona Role Router para Administrador, Supervisor, Operacional e Cliente, usando roles, capabilities e âmbito já existentes.
* Administradores entram diretamente no Dashboard FieldFlow, sem cair no painel geral do WordPress.
* O WordPress completo fica isolado em Administração técnica e mantém a navegação WordPress apenas quando aberto explicitamente.
* O portal operacional e o portal do cliente passam a abrir dentro do shell com o chrome do tema/PWA removido e navegação interna reposicionada para desktop.
* Adiciona Cardex Produtos, Campanha PDV e organização por Workspace, Operação, Dados, Gestão, Análise e Sistema no menu do administrador.
* Mantém a mesma entrada `/?fieldflow_desktop=1`, pelo que o QuickInstall v0.1.2 continua válido sem reinstalação.
* Sem alterações de schema SQL, API mobile, FlutterFlow, Android ou iOS.

= 2.2.294 =
* Integra o FieldFlow Desktop sobre a base 2.2.293, preservando integralmente o fluxo de evidência e as correções atuais.
* Adiciona entrada canónica `/?fieldflow_desktop=1` e mantém `/fieldflow-desktop/` como alias, com redirect de login e rewrite versionado.
* Administradores recebem um Admin Console desktop ligado ao BO existente. Utilizadores operacionais e clientes veem apenas os portais já autorizados.
* O modo Desktop usa a autenticação, capabilities, âmbito e subscrição existentes, sem nova base de dados ou autenticação paralela.
* Atualiza a navegação desktop para os módulos atuais do FieldFlow e corrige o item ativo inicial do Dashboard.
* Sem alterações de schema SQL, API mobile, FlutterFlow, Android ou iOS.

= 2.2.293 =
* Adiciona o novo tipo de pergunta Conformidade + evidência em Briefings Operacionais, Perguntas de Contexto e no construtor de Formulários.
* Uma única pergunta passa a recolher Sim, Não ou Não aplicável. Em Sim, abre fotografias de evidência. Em Não, abre o motivo da não conformidade e valida-o como obrigatório no servidor.
* O runtime converte o novo tipo em radio, image_upload e textarea, mantendo compatibilidade com web, WebView, Android, iOS e os contratos mobile atuais, sem alteração no FlutterFlow.
* As fotografias e os motivos entram no mesmo pipeline de submissão, histórico, rascunhos e exportações. Fotos continuam em Evidências, resposta e motivo ficam em Respostas e dados normalizados.
* O Briefing AI passa a preferir Conformidade + evidência quando uma execução física necessita de decisão, prova visual e explicação de falha.
* Mantém intactas as perguntas antigas de fotografia pura, evitando alterar formulários existentes que usam imagens sem lógica de conformidade.
* Sem alterações de schema SQL ou IndexedDB. Não exige rebuild da app.

= 2.2.292 =
* Corrige o flash do popup de memória da visita na app quando a mesma loja é renderizada várias vezes durante a inicialização da WebView.
* Usa uma chave estável por formulário, cliente, campanha, loja e dia, mantendo um único modal global por visita.
* O popup deixa de depender da sobrevivência do nó do formulário, por isso uma re-renderização AJAX já não o fecha imediatamente.
* Mantém integralmente a recuperação de rascunhos, respostas, fotografias e uploads da 2.2.291.
* Sem alterações de schema ou necessidade de rebuild do FlutterFlow, Android ou iOS.

= 2.2.291 =
* Corrige a abertura da memória da visita na app/WebView sem alterações no FlutterFlow, inicializando o modal no runtime global depois do carregamento AJAX.
* O fecho manual recupera primeiro o último rascunho sincronizado, incluindo respostas, matrizes, fotografias e uploads, e só depois conclui a visita.
* Se faltar um anexo esperado, o formulário tiver mudado ou a promoção do rascunho falhar, a visita mantém-se aberta para evitar perda de informação.
* Se não existir nem reporte final nem rascunho no servidor, o BO exige uma segunda confirmação antes de fechar sem dados, alertando para possíveis respostas que estejam apenas no dispositivo.
* Mantém o merchandiser como autor do reporte recuperado e regista o utilizador de BO, motivo e momento da correção na auditoria.
* A retenção local e no servidor passa para 30 dias para suportar recuperação administrativa.

= 2.2.290 =
* Remove duplicados exatos do briefing na importação e protege a leitura de briefings antigos sem apagar registos históricos.
* Limita perguntas de execução às lojas efetivamente mapeadas no briefing e à carteira ativa da campanha.
* Calcula o estado de implementação pela resposta mais recente de cada loja, permitindo que Sim/Não altere imediatamente o estado operacional dessa loja no briefing.
* Adiciona por pergunta a opção Memória da visita, mostrando a resposta anterior num popup informativo no início da visita seguinte sem pré-preencher silenciosamente a resposta atual.
* Adiciona no BO de Rotas a ação Concluir manualmente para visitas passadas ou do próprio dia, com data/hora, motivo, auditoria e recálculo da rota, sem criar respostas, fotografias ou submissões fictícias.
* Mantém schema SQL, IndexedDB, contratos mobile, FlutterFlow, Android e iOS sem necessidade de rebuild.

= 2.2.289 =
* Evolui o módulo de Stocks sobre a 2.2.288, preservando as correções críticas de Despesas.
* Adiciona pesquisa, filtros e eliminação individual ou em massa no histórico de importações do backoffice.
* Reconhece nativamente ficheiros Continente com STORE, STORE_NAME, ARTIGO, DESC_ARTIGO, EAN e SOH.
* Separa visualmente stocks por insígnia no front, incluindo filtro de cadeia e identificação em lojas e riscos.
* Melhora o matching Continente com CNT, MDL e CBD e mantém os mappings aprendidos quando um snapshot é eliminado.
* Sem alterações de schema, FlutterFlow, APK ou iOS.

= 2.2.288 =
* Corrige a resposta de erro crítico do WordPress na gravação de Despesas, protegendo todo o callback REST contra exceções PHP inesperadas.
* Substitui a validação dependente de `wp_check_filetype_and_ext()` por uma inspeção privada e tolerante baseada no conteúdo real da imagem, com suporte JPG, PNG, WEBP, HEIC e HEIF.
* Garante que uma exceção depois do INSERT nunca leva o utilizador a repetir uma despesa que já ficou gravada, reduzindo o risco de duplicados.
* Regista uma referência `EXP-...` no log do servidor quando existe uma exceção interna e devolve uma mensagem REST segura em vez da página de erro crítico do WordPress.
* O frontend deixa de apresentar tags HTML como `<p>Ocorreu um erro crítico...</p>` e converte falhas internas em mensagens operacionais limpas.
* Mantém a compressão de comprovativos da 2.2.287, o histórico de visitas da 2.2.286, base de dados, FlutterFlow, APK e iOS sem alterações de schema ou rebuild.

= 2.2.287 =
* Corrige o erro `Unexpected token '<'` na aba Despesas quando o servidor devolve HTML em vez de JSON durante o upload ou carregamento.
* Otimiza automaticamente fotografias JPG, PNG e WEBP antes do envio, reduzindo comprovativos grandes para um tamanho seguro sem alterar o original guardado no dispositivo.
* Passa a respeitar o limite real de upload configurado no WordPress e devolve mensagens específicas para fotografia demasiado grande, envio parcial, sessão expirada, acesso recusado e falhas de servidor.
* O frontend deixa de executar `response.json()` diretamente e valida primeiro a resposta do endpoint, evitando expor mensagens técnicas ao merchandiser.
* Mantém QR fiscal, preenchimento manual, histórico, BO de despesas, visitas, Cardex, offline e FlutterFlow sem alterações de schema ou rebuild da app.

= 2.2.286 =
* Corrige a comparação de Frentes a partir da terceira visita. Numa nova visita, o valor final da visita imediatamente anterior passa a preencher Frentes antes e Frentes depois começa com esse mesmo valor.
* Mantém o par Frentes antes e Frentes depois intacto quando o utilizador está apenas a editar o reporte já existente no próprio dia.
* Aplica a mesma rotação de histórico em web, APK, iOS e payload mobile, sem exigir alterações no FlutterFlow.
* Reforça as edições diárias do fluxo legacy, removendo a ligação à versão antiga antes da reconciliação para que cada atualização possa gerar a respetiva versão analítica.
* Não altera schema SQL, Cardex vivo, fotografias, rotas, Analytics ou respostas históricas existentes.

= 2.2.285 =
* Corrige o aviso persistente “Requer atenção” causado por operações offline antigas ligadas a paragens entretanto removidas ou substituídas numa rota.
* Operações de check-in, check-out e ocorrência cujo stop já não existe passam a ser tratadas como concluídas e ultrapassadas, sem recriar visitas nem apagar reportes.
* O runtime recupera uma única vez falhas operacionais antigas com um novo mutation ID, ultrapassando respostas de erro idempotentes que tinham ficado em cache.
* A repetição de uma operação preserva o momento original em que foi criada, mantendo a proteção contra eventos anteriores a uma reposição administrativa da visita.
* Não altera Cardex, formulários, respostas, fotografias, Analytics, rotas válidas ou FlutterFlow.

= 2.2.284 =
* Introduz o Cardex vivo por loja, partilhado entre merchandisers e persistente entre visitas.
* Remover, alterar ou acrescentar referências no formulário passa a gravar diferenças específicas da loja sem duplicar produtos.
* Novos produtos carregados no Cardex base continuam a ser herdados pelas lojas já ajustadas, preservando as exceções locais.
* Mantém compatibilidade com snapshots de Cardex criados até à 2.2.283 e preserva todo o histórico de reportes e respostas anteriores.
* A API mobile passa a sincronizar alterações de Cardex automaticamente, sem depender de alterações no FlutterFlow.
* Permite persistir um sortido vazio quando a matriz Cardex é submetida sem referências.
* A importação CSV do BO deixa de apagar ajustes feitos no terreno.
* O BO passa a mostrar Cardex efetivo por loja, modo, revisão e último utilizador que o alterou.

= 2.2.283 =
* Hotfix urgente para repor uma visita em curso como pendente no backoffice.
* Adiciona a ação segura Repor como pendente no cartão da loja em curso.
* Limpa check-in, checkout, duração, falha e geolocalização operacional sem apagar formulários, fotografias ou histórico.
* Bloqueia a reposição quando já existe uma submissão associada à visita.
* Regista motivo, utilizador, momento e estado anterior para auditoria.
* Impede que eventos offline anteriores à reposição voltem a iniciar a visita.

= 2.2.282 =
* Permite ajustar rotas já iniciadas ou concluídas, sem depender da hora do dia.
* Mantém lojas executadas e em curso protegidas, permitindo remover, acrescentar e reordenar apenas lojas pendentes.
* Adiciona o modo Ajuste de rota ativa no backoffice e em Minha Rota, com gravação atómica e atualização imediata da app.
* Uma rota concluída volta a em curso quando recebe uma nova loja pendente, preservando reportes, visitas e histórico anteriores.

= 2.2.281 =
* Corrige conflitos falsos na conclusão de visitas com fotografias e recupera automaticamente itens presos na fila offline.

= 2.2.280 =
* Unifica formulários, eventos e operações mobile numa fila offline persistente, com sincronização em lote e bloqueio entre separadores.
* Adiciona sincronização incremental por cursor para descarregar apenas rotas, lojas, paragens, formulários e configurações alteradas.
* Introduz conflitos explícitos quando um registo ou formulário muda no servidor durante o período offline, sem apagar o trabalho local.
* Cria um Centro de Sincronização na app com pendentes, falhas, conflitos, repetição manual, remoção controlada e diagnóstico técnico copiável.
* Reforça idempotência, tentativas progressivas e proteção contra repetição de submissões depois de respostas de rede ambíguas.
* Adiciona o painel Sincronização Mobile em Segurança e Observabilidade, com dispositivos, rede, fila, conflitos e último endpoint.
* Atualiza o contrato mobile para 1.3.0 e anuncia delta incremental, centro de sincronização, conflitos e cross-tab lock.

= 2.2.279 =
* Cria o Route Planning Workspace no backoffice e em Minha Rota, com drag and drop, posições fixas, rascunhos e publicação controlada.
* Adiciona remoção de várias lojas numa operação atómica, preservando reportes, Analytics e histórico operacional.
* Compara a rota atual com uma alternativa otimizada antes de aplicar, mostrando distância, duração e poupança estimada.
* Guarda até 12 versões recuperáveis por rota e permite restaurar uma versão enquanto a execução ainda não começou.
* Disponibiliza os mesmos contratos REST no frontend, WebView e endpoints mobile para Android e iOS.
* Atualiza o contrato mobile aditivo para 1.2.0 e anuncia o workspace no app-config.
* Mantém a ordem manual protegida, respeita posições fixas e usa comparação interna para reduzir chamadas pagas.
* Permite guardar rotas próprias como rascunho antes da publicação.

= 2.2.278 =
* Adiciona o API Cost Ledger para Google Routes, Google Maps e Azure Maps, com custos estimados, chamadas evitadas pela cache, erros e duração.
* Cria dashboards de Custos API e Media e Uploads em Segurança e Observabilidade.
* Permite configurar orçamento mensal, alertas, retenção e tarifas reais por 1.000 unidades.
* Reforça o envio de fotografias com progresso, uma repetição automática segura antes de o upload terminar, fallback offline e remoção de duplicados na mesma pergunta.
* Mede compressão, bytes poupados, retries, falhas e tempos sem guardar fotografias, nomes de ficheiro, moradas, coordenadas ou respostas.

= 2.2.277 =
* Permite acumular várias fotografias tiradas pela câmara no mesmo campo, sem perder a captura quando a WebView recupera o foco.
* Reforça a remoção de lojas em Minha Rota com fallback POST para servidores, WAFs e WebViews que bloqueiam pedidos DELETE.
* Sincroniza as rotas criadas pela sugestão automática com a matriz de atribuições, progresso e cache de permissões.
* Reduz chamadas pagas, usando otimização interna durante alterações silenciosas e Google apenas quando o utilizador pede reotimização precisa ou guarda o plano.
* Carrega Google Places apenas quando os campos de partida ou chegada são usados e adiciona cache transversal a geocoding, matrizes, direções e pesquisas de locais.

= 2.2.276 =
* Garante que a Sugestão de encomenda usa o mesmo Cardex resolvido pela pergunta de Frentes em cada loja.
* Corrige formulários legados onde Frentes estava em Cardex fixo e a Sugestão em Cardex automático, situação que escondia a pergunta em algumas lojas.
* Mantém as mesmas referências, sortido específico da loja e automatismo de sugestão quando as frentes ficam a zero.
* Aplica a reconciliação em web, APK, iOS, Flutter WebView e endpoints mobile, sem alterar o schema guardado no formulário.

= 2.2.275 =
* Corrige a abertura duplicada de galeria e pedido de permissão ao tocar em Tirar fotografia.
* A câmara passa a pedir primeiro a permissão e abre diretamente uma captura integrada, sem abrir a pasta de ficheiros.
* A galeria deixa de invocar o pedido de permissão da câmara e abre apenas o seletor de imagens.
* Reforça o upload e a pré-visualização em perguntas de imagem, Conquistas e Despesas, em web, APK, iOS e Flutter WebView.
* Mostra mensagens claras quando a permissão é recusada, a câmara não existe ou está ocupada.

= 2.2.274 =
* Shopping: nova área de resultados no dashboard do cliente, filtrada pelo cliente e projeto ativos.
* Shopping: consulta detalhada por submissão, loja, merchandiser, artigo e preços regular/promocional.
* Shopping: exportações CSV, Excel XLSX com folhas Resumo e Preços, e PDF.
* Shopping: nova área de Submissões e Resultados no backoffice, com filtros e indicadores.
* Shopping: permissões de leitura separadas da execução, mantendo o formulário integrado em web, APK e iOS.


= 2.2.273 =
* Corrige Shopping para usar apenas os merchandisers associados ao projeto no Centro de Atribuições.
* Reatribui automaticamente lotes já importados quando existia um utilizador fora da equipa do projeto.
* Limita o seletor de merchandiser à equipa do projeto e mantém a mesma regra em web, APK, iOS e Flutter WebView.

= 2.2.272 =
* Corrige a posição de Shopping e Preços na navegação agrupada do FieldFlow, dentro de Operação.
* Substitui a importação bloqueante por um fluxo AJAX com estado visível, erros legíveis e fallback tradicional.
* Otimiza a consulta e o mapeamento de PDVs para evitar esperas prolongadas em projetos com muitas lojas.
* Corrige os formatos de gravação do lote, valida todas as inserções e remove importações parciais em caso de erro.
* Reforça limites de memória e tempo apenas durante a importação, sem alterar contratos web, APK, iOS ou Flutter.

= 2.2.271 =
* Adiciona Shopping, Verificação de Preços com importação XLSX, formulários por loja e filtragem por merchandiser.
* Integra a nova aba no shortcode operacional, web, APK, iOS e Flutter WebView.
* Envia emails HTML editáveis ao cliente após a submissão, com template inicial e destinatários adicionais.
* Corrige o upload de comprovativos em Despesas através de câmara e galeria.
* Remove o botão de imagem redundante e a caixa vazia com X vermelho ao cancelar seleções.
* Reforça anexos em perguntas categorizadas, incluindo Conquistas.

= 2.2.270 =
* Introduz Live Route Optimization & Reconciliation 1.0 com um motor único no backend para BO, frontend, WebView, Android e iOS.
* Reordena automaticamente as lojas por distância e tempo ao adicionar, remover ou alterar partida e chegada no planeador Minha Rota.
* Mantém partida, chegada, regresso à base, máximo de 8 lojas e bloqueio opcional da ordem manual.
* Atualiza com segurança rotas manuais já criadas, apenas quando estão em rascunho ou planeadas e sem visitas iniciadas.
* Adiciona endpoints REST partilhados para simular e aplicar otimização, incluindo compatibilidade com futuros ecrãs Flutter nativos.
* Otimiza também o construtor frontend de descoberta de PDVs, liga autocomplete aos pontos de partida e chegada e grava a sequência canónica no servidor.
* Invalida o cache mobile e offline após alterações de rota, garantindo que Android e iOS recebem a nova sequência no próximo refrescamento.
* Acrescenta no Centro de Processos uma reconciliação em lotes com modo simulação, filtros e aplicação explícita, sem criar novos menus.
* Guarda métricas, fornecedor, versão, poupança estimada e estado da otimização nos metadados da rota, sem alterar o schema.
* Preserva formulários, submissões, FlutterFlow v1.7, shell nativa 2.2.269.1, Google Places, exportações e dados existentes.

= 2.2.269 =
* Reforça a shell nativa FlutterFlow com navegação externa segura, suporte para telefone, email e SMS e controlo do botão físico de retorno.
* Adiciona uma ponte genérica de eventos do portal para o Flutter, incluindo estado da sessão, billing, contexto, downloads e logout.
* Sincroniza heartbeat, contexto, billing e fila offline quando a app regressa ao primeiro plano, sem recarregar formulários ativos.
* Mantém os websites externos fora da WebView e preserva internamente apenas domínios FieldFlow autorizados.
* Acrescenta recuperação visual de erros da WebView e diagnóstico sanitizado por dispositivo.
* Atualiza os downloads nativos para gravação progressiva em disco, reduzindo o consumo de memória em ficheiros grandes.
* Preserva contratos REST, shortcodes, formulários, rotas, Google Places, navegação Google Maps e base de dados.

= 2.2.268 =
* Introduz Native Route Navigation Bridge Fix 1.0 sem alterar endpoints, shortcodes, payloads, schema ou contratos mobile existentes.
* Acrescenta dir_action=navigate aos URLs de direções para pedir navegação curva a curva ou pré-visualização, conforme a proximidade do ponto de partida e suporte do dispositivo.
* Substitui window.open nos botões de navegação por uma abertura externa controlada, compatível com Flutter InAppWebView, Android, iOS e browser.
* Adiciona o handler opcional fieldflowOpenExternal, mantendo fallback por mensagem, navegação externa normal e abertura no mesmo contexto quando a app ainda não implementa o handler.
* Corrige Minha Rota, planeador pessoal, rota do cliente e restantes botões Google Maps que utilizavam novas janelas dentro da WebView.
* Preserva todas as paragens e waypoints da rota, sem converter o itinerário para um deep link limitado a um único destino.
* Mantém menus, REST, FlutterFlow v1.7, formulários, submissões, offline, Google Places Autocomplete e dados existentes intactos.

= 2.2.267 =
* Introduz Google Places Autocomplete Reliability 1.0 sem alterar endpoints, shortcodes, payloads, formulários ou contratos FlutterFlow.
* Centraliza o carregamento da Google Maps JavaScript API num único runtime partilhado entre BO e frontend.
* Impede carregamentos duplicados, resolve corridas entre módulos e recupera quando a tag Google já foi carregada antes da inicialização do autocomplete.
* Passa a usar callback e importLibrary para confirmar que a biblioteca Places está realmente disponível antes de criar os campos de autocomplete.
* Separa o fornecedor visual do mapa do motor Google Places, permitindo autocomplete sempre que existe uma chave Google válida, mesmo quando o mapa principal usa Leaflet.
* Corrige os pontos de partida e chegada em Campanhas PDV, Rotas e planeador de rotas no frontend.
* Corrige a Base Comercial, descoberta de PDVs e adição de paragens nos shortcodes que utilizam Google Autocomplete.
* Eleva a lista de sugestões acima de painéis, modais e navegação administrativa para evitar resultados escondidos.
* Mantém fallback compatível para páginas que renderizem os shortcodes fora do carregamento normal dos assets FieldFlow.
* Evita voltar a agendar deduplicação e backfill histórico numa atualização direta da 2.2.266 para a 2.2.267.

= 2.2.266 =
* Introduz Schema Integrity & Release Safety 1.0 sem alterar layouts, shortcodes, endpoints, payloads ou contratos FlutterFlow.
* Adiciona um manifesto canónico e uma auditoria de schema apenas de leitura, com verificação de tabelas, colunas, tipos, nullability e índices.
* Cria um histórico persistente de migrações com estados, tentativas, duração e erro, impedindo o avanço da versão quando uma migração falha.
* Adiciona tarefas retomáveis em lotes para auditoria integral, deduplicação progressiva de locais e backfill histórico, com execução através da fila existente.
* Remove reparações históricas pesadas do carregamento normal após atualização, mantendo fallback legado apenas quando a infraestrutura em lotes não está disponível.
* Cria um release gate não destrutivo para versão, runtime, REST, contrato mobile, schema, migrações, fila, cron, uploads, assets e ambiente.
* Integra schema e release gate na Saúde do Sistema, e as execuções em lotes no Centro de Processos, sem criar menus adicionais nem alterar a navegação agrupada existente.
* Integra a nova camada nos avisos administrativos, pacote de diagnóstico e desinstalação segura.
* Mantém compatibilidade com WordPress 6.4+, PHP 7.4+, FlutterFlow v1.7, WebView, offline, formulários, exportações, faturação e dados operacionais existentes.

= 2.2.265 =
* Introduz Architecture & Queue Modernization 1.0 com composição modular do runtime e registo central dos controllers REST.
* Cria uma fila persistente com deduplicação, locks atómicos, prioridades, backoff, tentativas limitadas e dead letter queue.
* Mantém o modo Observação por defeito, com o WP-Cron como proprietário dos jobs e sem alterar a operação já validada.
* Permite piloto apenas nos jobs pós-submissão explicitamente selecionados no Centro de Processos.
* Usa Action Scheduler quando disponível e selecionado, mantendo fallback automático e integral para WP-Cron.
* Transfere jobs pendentes para WP-Cron ao sair do piloto ou executar o fallback de emergência.
* Adiciona teste interno seguro, heartbeat, watchdog, limpeza, WP-CLI, Saúde do Sistema e diagnóstico sanitizado.
* Mantém endpoints, payloads, FlutterFlow v1.7, WebView, offline, shortcodes, formulários e exportações compatíveis.

= 2.2.264 =
* Introduz Performance & Load Readiness em modo de observação, sem bloquear pedidos nem alterar fluxos.
* Mede duração, consultas, memória e taxa de erro em REST, mobile, submissões, exportações, backoffice, portais e cron.
* Não guarda SQL, payloads, utilizadores, IPs, tokens, fotografias ou conteúdos dos formulários.
* Adiciona release gate consultivo por P95, limites de consultas, memória, taxa de erro e saúde das filas.
* Inclui teste seguro de prontidão apenas com leituras agregadas, executável no backoffice e por WP-CLI.
* Deteta cron real, WP-Cron e disponibilidade do Action Scheduler, mantendo WP-Cron como driver e fallback atual.
* Integra métricas, gate e filas na Saúde do Sistema e no pacote de diagnóstico.
* Mantém FlutterFlow v1.7, Submission Pipeline, WebView, offline, shortcodes, exportações e dados operacionais intactos.

= 2.2.263 =
* Cria o Privacy Center com retenção em modo de simulação, aplicação explícita, retenção legal e pedidos RGPD.
* Mantém os dados operacionais e financeiros fora da limpeza automática.
* Integra exportação e eliminação de dados pessoais com as ferramentas nativas do WordPress.
* Adiciona governação central da IA, deteção de manipulação de instruções e redação opcional de emails e telefones.
* Mede tokens reais quando disponíveis, identifica estimativas e calcula custos com orçamento mensal configurável.
* Não guarda prompts nem conteúdos comerciais nos registos de utilização.
* Mantém shortcodes, Submission Pipeline, exportações, permissões, FlutterFlow v1.7, WebView e offline intactos.

= 2.2.262 =
* Expande o design system consolidado a todas as 41 superfícies administrativas FieldFlow conhecidas.
* Uniformiza cartões, grelhas, filtros, formulários, abas, tabelas e estados por família de módulo.
* Adiciona auditoria visual de overflow, conteúdos cortados, IDs duplicados e tempo de inspeção no browser.
* Move sete blocos de CSS e três scripts inline simples para assets administrativos partilhados.
* Mantém reversão imediata para o layout 2.2.260, sem migração nem perda de configurações.
* Preserva shortcodes públicos, Submission Pipeline, exportações, base de dados, FlutterFlow v1.7, WebView e offline.

= 2.2.261 =
* Cria uma fundação visual controlada para o backoffice, sem alterar lógica, dados, permissões ou URLs.
* Corrige larguras, grelhas, ações, tabelas e overflow nas áreas prioritárias.
* Abrange Centro de Atribuições, Analytics, Formulários, Rotas e Saúde do Sistema.
* Agrupa o menu FieldFlow por áreas, preservando todos os links existentes.
* Adiciona reversão imediata para o layout 2.2.260 na Saúde do Sistema.
* Mantém shortcodes públicos, Submission Pipeline, exportações, base de dados, FlutterFlow v1.7, WebView e offline intactos.

= 2.2.260 =
* Introduz um cofre central para credenciais de IA, Google, Azure, routing, licenciamento, otimizador, integrações e notificações PWA.
* Migra automaticamente segredos legados para Sodium ou AES-256-GCM sem pedir nova configuração e preserva os valores anteriores se a cifragem não estiver disponível.
* Remove chaves e segredos dos campos visíveis do backoffice, usando máscaras e mantendo a credencial atual quando o campo fica vazio.
* Passa a guardar também a chave de licença e a chave privada VAPID com cifragem autenticada.
* Cria um índice normalizado e reconstruível de permissões por utilizador, cliente, campanha e rota.
* Substitui a leitura completa de clientes e campanhas por um índice persistente com invalidação explícita e fallback integral para o resolvedor anterior.
* Adiciona diagnóstico do cofre e do índice de permissões à Saúde do Sistema e ao pacote técnico.
* Mantém a Submission Pipeline 1.0, endpoints, payloads, contrato FlutterFlow v1.7, WebView, offline, formulários, exportações e integrações existentes.

= 2.2.259 =
* Introduz a Submission Pipeline 1.0 partilhada entre o portal web e a API mobile.
* Confirma sempre o reporte e as respostas antes de executar histórico, cardex, conclusão de visita, auditoria e integrações.
* Mantém o fluxo atual por defeito e acrescenta rollout shadow ou piloto apenas para utilizadores explicitamente autorizados.
* Permite selecionar web e mobile, limitar por projeto, aplicar amostragem e preservar os IDs de histórico na resposta mobile.
* Adiciona diagnóstico de tarefas pendentes e reversão imediata para os motores estáveis no Centro de Controlo Mobile.
* Mantém os endpoints, payloads, respostas, contrato FlutterFlow v1.7, WebView, offline, formulários e exportações existentes.

= 2.2.258 =
* Corrige a largura do editor de dashboards no Centro de Atribuições.
* Impede os cabeçalhos das tabelas de taparem títulos, estados e botões da primeira linha.
* Melhora as larguras, deslocação horizontal e coluna de ações das tabelas Analytics.
* Preserva exportações Premium, endpoints, contrato mobile v1.7, WebView e integração FlutterFlow.

= 2.2.257 =
* Permite editar dashboards existentes sem alterar os respetivos IDs ou integrações.
* Integra a gestão dos widgets, gráficos, KPIs, tabelas, estado e ordem dentro do editor do dashboard.
* Adiciona Subir e Descer para controlar a ordem publicada dos dashboards.
* Corrige a ordem dos dashboards no FieldFlow App e aplica os layouts Misto, Executivo e Detalhado.
* Preserva widgets quando um dashboard é apagado, evitando referências órfãs.
* Preserva exportações Premium, endpoints, contrato mobile v1.7, WebView e integração FlutterFlow.

= 2.2.256 =
* Adiciona recuperação automática apenas a processos idempotentes elegíveis.
* Aplica backoff progressivo de 1, 5 e 15 minutos, com máximo de três recuperações automáticas.
* Cria uma fila de falhas definitivas quando o limite de recuperação é atingido.
* Adiciona circuitos de proteção para processos associados a serviços externos, com teste controlado após o período de segurança.
* Permite identificar e recuperar jobs bloqueados a partir do Centro de Processos.
* Introduz um indicador de saúde operacional baseado em falhas, atrasos, circuitos e fila terminal.
* Inclui processos, circuitos e recuperações no pacote de diagnóstico sanitizado.
* Mantém o retry próprio das reconciliações e IA, sem criar automatismos concorrentes.
* Impede recuperação automática de relatórios e integrações pós-submissão para evitar reenvios ou efeitos duplicados.
* Preserva FlutterFlow, contrato mobile v1.7, WebView, formulários, rotas, offline, exportações e integrações existentes.

= 2.2.255 =
* Adiciona o Centro de Processos ao backoffice, com estados pendente, em execução, concluído e falhado.
* Mostra última e próxima execução, duração, tentativas, origem, referência e detalhe de erro dos jobs monitorizados.
* Regista de forma aditiva os hooks WordPress já existentes, sem substituir callbacks, argumentos ou calendários.
* Permite execução manual apenas em processos recorrentes seguros e reexecução apenas em jobs idempotentes elegíveis.
* Impede pedidos manuais duplicados e identifica execuções pendentes ou bloqueadas fora do prazo operacional.
* Mantém histórico por 90 dias para sucessos e 180 dias para falhas, com limpeza controlada e argumentos sanitizados.
* Cria alertas de observabilidade para processos recorrentes em atraso.
* Preserva integralmente FlutterFlow, contrato mobile v1.7, WebView, formulários, rotas, offline, exportações e integrações existentes.

= 2.2.254 =
* Mantém integralmente o contrato FlutterFlow v1.7, endpoints móveis, handlers da WebView, shortcodes e estruturas de resposta existentes.
* Passa a guardar credenciais de integrações com cifragem autenticada, migrando de forma transparente os valores legados sem exigir nova configuração.
* Mantém leitura compatível dos segredos antigos e impede novos fallbacks em texto reversível quando o servidor não dispõe de cifragem forte.
* Valida endpoints Power BI, Google Cloud e Azure e usa pedidos remotos seguros com HTTPS e verificação SSL.
* Protege a pesquisa auxiliar de PDVs com sessão autenticada, preservando o funcionamento nas páginas FieldFlow e na WebView.
* Reduz consultas repetidas ao calcular uma única vez por pedido o âmbito de clientes, campanhas e rotas de cada utilizador.
* Adiciona ao Estado do Sistema a validação do cofre de credenciais.
* Preserva formulários, rotas, offline, exportações, emails, billing, Analytics, base de dados e integração FlutterFlow atual.

= 2.2.253 =
* Corrige a ordem dos elementos OOXML que fazia o Microsoft Excel desktop considerar o Excel completo danificado.
* Coloca autoFilter antes de mergeCells e normaliza a ordem das propriedades estendidas do workbook.
* Impede valores INF, -INF e NAN de serem gravados como números inválidos em células reais de Analytics.
* Valida XML malformado e regras críticas de compatibilidade Microsoft antes do download.
* Mantém folhas, dados, filtros, emails automáticos, shortcodes, performance e contrato FlutterFlow v1.7.

= 2.2.252 =
* Corrige ficheiros Excel completo que podiam chegar truncados ou contaminados ao browser e serem apresentados como danificados pelo Excel desktop.
* Isola a resposta binária de avisos PHP, HTML e compressão de saída do servidor.
* Valida assinatura ZIP, diretório central, estrutura OOXML e tamanho recebido antes de guardar o download no browser.
* Repete automaticamente uma transferência XLSX incompleta uma vez no desktop.
* Normaliza UTF-8, caracteres XML inválidos, nomes de folhas e o limite de 32.767 caracteres por célula.
* Mantém os dados, folhas, filtros, emails automáticos, Analytics, shortcodes e contrato FlutterFlow v1.7.

= 2.2.251 =
* Corrige a lentidão transversal das exportações Premium no front-end.
* Carrega respostas de submissões em lotes, sem uma query separada por reporte.
* Mantém as exportações como operações de leitura, sem reconciliar centenas de rotas antes do download.
* Reduz o consumo de memória na criação de XLSX e evita separadores vazios no desktop.
* Preserva todos os dados, filtros, permissões, folhas e o contrato FlutterFlow v1.7.

= 2.2.250 =
* Cria um release gate único e independente de versões antigas, com lint PHP, validação JavaScript, lock do contrato FlutterFlow e regressões críticas.
* Torna a gravação de planos diários, semanais e mensais atómica: ou todas as rotas são guardadas, ou nenhuma alteração fica aplicada.
* Adiciona bloqueios temporários a upgrades e migrações para evitar execuções concorrentes no primeiro acesso após uma atualização.
* Otimiza os resumos da Campanha PDV com agregações SQL, sem carregar todas as lojas em memória.
* Limita a medição de armazenamento às pastas privadas e exportações do FieldFlow.
* Reforça o uninstall, removendo todos os cron jobs, tabelas, opções, metadados e diretórios FieldFlow apenas quando a eliminação total está explicitamente ativa.
* Introduz um empacotador de produção que exclui testes, auditorias, ficheiros Flutter e documentação interna do ZIP instalado no WordPress.
* Mantém o contrato móvel v1.7 e os fluxos funcionais da versão 2.2.249.

= 2.2.249 =
* Permite ao merchandiser remover lojas diretamente em Minha Rota, preservando reportes, evidências e histórico em Analytics.
* Reabre rotas existentes no planeador e permite ajustar as lojas sem criar rotas duplicadas.
* Reutiliza automaticamente os pontos de partida e chegada já guardados em cada rota existente.
* Exige partida e chegada apenas ao criar uma rota nova que ainda não tenha extremos reutilizáveis.
* Reforça Localização atual com tentativa rápida, GPS de alta precisão e pedido nativo de permissão no FlutterFlow.
* Atualiza os contadores da rota e marca as métricas para recálculo depois de remover ou reconciliar lojas.
* Mantém o contrato móvel v1.7 e a experiência responsiva da versão 2.2.248.

= 2.2.248 =
* Organiza as lojas do planeador pessoal em abas horizontais por insígnia, com contadores e filtro Todas.
* Distingue Continente, Continente Modelo, Continente Bom Dia, Pingo Doce e restantes cadeias reconhecidas.
* Melhora os cartões de loja com insígnia, localidade, morada, prioridade e estado de seleção ou planeamento.
* Torna a seleção por insígnia responsiva em desktop, tablet, mobile e WebView FlutterFlow.
* Mantém Google Autocomplete, geolocalização, gravação de rotas e contrato móvel v1.7 intactos.

= 2.2.246 =
* Ativa Google Places Autocomplete nos pontos de partida e chegada do planeador pessoal.
* Adiciona botões de localização atual independentes para partida e chegada.
* Converte coordenadas atuais numa morada legível quando o Google Maps está disponível.
* Apresenta mensagens claras para permissão bloqueada, GPS indisponível, timeout e páginas sem HTTPS.
* Atualiza o Custom Widget FlutterFlow para conceder a permissão de geolocalização à WebView.
* Mantém rotas, formulários, imagens, endpoints e contrato móvel v1.7 sem alterações destrutivas.

= 2.2.245 =
* Corrige todos os links de Google Maps das rotas, incluindo partida, paragens e chegada num URL público e estável.
* Corrige o botão de rota enviado por email ao cliente, eliminando a dependência do backoffice e o ciclo de redirecionamento.
* Ativa seleção múltipla de fotografias por upload e adições sucessivas pela câmara em web, tablet, mobile e payload FlutterFlow.
* Impede que imagens de reportes anteriores reapareçam num novo reporte, mantendo-as disponíveis no histórico e em Analytics.
* Transforma Descobrir num planeador pessoal para merchandisers, com rotas diárias, semanais e mensais a partir das lojas atribuídas.
* Adiciona validação de propriedade, limite de 8 lojas por dia, prevenção de duplicados e endpoints móveis aditivos.
* Mantém o contrato FlutterFlow v1.7 preservado.

= 2.2.244 =
* Novo Centro Operacional para navegação rápida entre as áreas autorizadas.
* Rotas, Campanha PDV, Relatórios, exportações e Despesas com experiência premium responsiva.
* Campanha PDV em cartões no mobile e tabela preservada em desktop.
* Excel completo, Excel mobile, PDF e CSV organizados por finalidade.
* Sem alterações necessárias no FlutterFlow ou no Custom Widget.

= 2.2.243.8 =
* Valida integralmente o XLSX descarregado antes de o entregar ao Excel Android.
* Repete uma transferência incompleta e compara o tamanho recebido com o tamanho anunciado pelo servidor.
* Guarda o ficheiro na área externa própria da aplicação, com nome ASCII seguro e MIME explícito.
* Simplifica o Excel mobile e reduz o volume de dados por folha para abertura mais rápida.
* Mantém Excel completo, PDF, CSV, Stocks, IA e contrato FlutterFlow v1.7 intactos.

= 2.2.243.7 =
* Comprime corretamente as entradas internas dos ficheiros XLSX com DEFLATE.
* Corrige workbooks com XML bruto demasiado grande para o Excel Android.
* Torna o Excel mobile mais leve e mantém o Excel completo separado.

= 2.2.243.6 =
* Substitui o formato XML Spreadsheet 2003 por ficheiros OOXML .xlsx reais.
* Adiciona Excel completo e Excel mobile como duas exportações distintas.
* Mantém o formato .xls antigo apenas para compatibilidade técnica.

= 2.2.243.4 =
* Corrige exportações Excel, PDF, CSV e ZIP dentro da APK.
* Reconhece URLs de exportação por ação e formato, mesmo sem extensão no URL.
* Adiciona ponte nativa de download com cookies da sessão WebView.
* Inclui fallback Web Share para a APK atual e Custom Widget atualizado para a próxima build.
* Contrato FlutterFlow v1.7 preservado byte a byte.

= 2.2.242.1 =
* Hotfix visual para contraste do título na pré-visualização dos temas.
* Cards de rotas em Analytics compactados no mobile.
* Badges de estado sem quebra e labels apresentados em português.
* Três métricas mantidas na mesma linha, com menor altura e melhor leitura.
* Contrato FlutterFlow, dados e lógica funcional preservados.

= 2.2.242 =
* Unified Layout System & Template Consistency.
* Evidencias fotograficas com acoes compactas fora da imagem e cores do template.
* Widgets de Analytics e tabelas responsivas com leitura segura em mobile.
* Resumo do cliente, KPIs, cards, navegacao e ritmo vertical harmonizados.
* Camada visual do Centro de Atribuicoes alinhada com os templates do front.
* Contrato FlutterFlow v1.7, submissoes e runtime funcional preservados.

= 2.2.241.2 =
* Permite remover fotografias já guardadas ao editar o reporte diário do próprio utilizador.
* A remoção fica pendente até guardar e pode ser anulada antes da submissão.
* Mantém permissões, auditoria, Runtime V3 e contrato FlutterFlow sem alterações.

= 2.2.241 =
* Forms Runtime 3.0 Controlled Pilot.
* Piloto restrito a utilizadores explicitamente autorizados.
* Runtime V3 controla apenas condições, passos e navegação.
* Uploads, rascunhos, validação, submissão e conclusão continuam no motor estável.
* Fallback automático por formulário e reversão imediata no backoffice.
* Contrato FlutterFlow preservado e caches atualizadas.

= 2.2.240 =
* Forms Runtime 3.0 Shadow and Rollout Foundation.
* Novo observador externo de formulários, sem alterar o motor atual.
* Validação automática de perguntas, condições, passos e navegação em contas internas.
* Rollout controlado por utilizador, projeto, WebView e percentagem de amostragem.
* Diagnóstico seguro de incompatibilidades sem recolher respostas, ficheiros ou tokens.
* Motor legado continua proprietário do formulário e funciona como fallback imediato.

= 2.2.239 =
* Stability Baseline & Flutter Contract Lock.
* Contrato versionado e assinado para a API móvel atual, sem alterações destrutivas.
* Headers de versão, checksum e correlação em respostas da API móvel.
* Endpoint de contrato e diagnóstico seguro da app.
* Feature flags de rollout com novos motores desligados por defeito.
* Quality Gate dedicado a web, WebView, API móvel e formulários críticos.

= 2.2.238.8 =
* Corrige a estrutura HTML do upload de imagens por categoria, que quebrava o DOM após algumas categorias.
* Remove definitivamente barras e botões Seguinte duplicados, incluindo variantes recuperadas de cache.
* Mantém todas as categorias dentro da pergunta e do passo corretos em browser, APK e WebView.
* Reforça o avanço após perguntas condicionais e a grelha visual mobile.

= 2.2.238.7 =
* Conditional Runtime and Theme Integrity: novo motor determinístico para cadeias de perguntas condicionais.
* Remove ciclos, alvos inexistentes, autorreferências e regras duplicadas antes da renderização.
* Mantém uma única barra persistente de Anterior e Seguinte, sem reconstruções destrutivas durante o preenchimento.
* Protege o atributo hidden contra CSS de temas e WebViews, evitando perguntas e barras ocultas indevidamente reveladas.
* Faz o formulário herdar cores, tipografia, superfícies, bordas e botões do template ativo por cliente e campanha.
* Expõe conditional_flow v2 e o tema resolvido no payload móvel para Flutter e API.
* Reescreve condições dirigidas a blocos de briefing depois da expansão dinâmica por loja.

= 2.2.238.5 =
* Complete Step Navigation Recovery: devolve ao motor do formulário o controlo exclusivo de Anterior/Seguinte.
* O shortcode do merchandiser passa a gerir apenas Concluir visita, Guardar alterações e Reporte já efetuado.
* Recupera todos os passos especiais, incluindo Sugestão de encomenda e Evidências.
* Adiciona automaticamente ao wizard perguntas presentes no schema mas ausentes do layout de passos.
* Remove barras antigas criadas pelo controlador exterior e invalida a cache da versão anterior.

= 2.2.238.4 =
* Embedded Navigation Ownership: o shortcode do merchandiser passa a ser o único motor autorizado a criar Anterior/Seguinte.
* O FormRenderer deixa de renderizar e recriar navegação quando o formulário está embebido.
* Remove e oculta qualquer controlo residual que não pertença ao shortcode focado.
* Corrige definitivamente a segunda barra Seguinte em web, Android e iOS.

= 2.2.238.3 =
* Navigation Single Source of Truth: elimina controlos Anterior/Seguinte duplicados em formulários embebidos.
* Mantém uma única barra canónica de navegação em web, Android e iOS.
* Remove todos os botões Seguinte no último passo visível, incluindo resíduos tardios de cache ou scripts.
* Preserva Submeter apenas nos formulários autónomos e Concluir visita/Guardar alterações no fluxo do merchandiser.

= 2.2.238.2 =
* Remove fisicamente o botão Seguinte no último passo visível dos formulários embebidos.
* Garante exclusividade visual entre a navegação do wizard e Concluir visita ou Guardar alterações.
* Recria o botão Seguinte automaticamente quando o utilizador regressa a um passo anterior.
* Invalida a cache do formulário focado para eliminar controlos residuais de versões anteriores.

= 2.2.238.1 =
* Adiciona um botão Eliminar diretamente em cada pré-visualização de imagem capturada pela câmara ou escolhida da galeria.
* Permite remover individualmente ficheiros de seleções múltiplas sem limpar as restantes imagens.
* Sincroniza imediatamente a remoção com o rascunho local, a validação obrigatória e a fila offline.
* Remove automaticamente linhas adicionais de upload quando ficam vazias e invalida o cache anterior do formulário.

= 2.2.238 =
* Unifica a ação final dos formulários: Submeter apenas em formulários autónomos, Concluir visita em novas visitas e Guardar alterações em edições.
* Calcula o último passo visível, removendo o botão Seguinte quando perguntas condicionais deixam passos vazios.
* Comprime fotografias no dispositivo antes do rascunho, upload e fila offline para reduzir tempo e consumo de espaço.
* Distingue erros reais de quota, IndexedDB indisponível, clonagem de ficheiros e interrupções de armazenamento.
* Liberta a interface após a confirmação do reporte e reconcilia a rota em segundo plano.
* Invalida o HTML antigo do formulário guardado em cache na WebView.

= 2.2.237 =
* External Submit Integrity nos formulários embebidos.
* Remove o segundo botão interno Submeter/Concluir quando o shortcode controla a gravação.
* Mostra o botão exterior Concluir apenas no último passo do wizard.
* Mantém apenas Anterior e Seguinte durante a navegação e limpa barras antigas recuperadas do cache.
* Atualiza o guia FlutterFlow com a localização exata das Custom Dependencies.


= 2.2.234 =
* Native Camera Capture Integrity para perguntas de imagem em web, Android e iOS.
* Separa estritamente a entrada de câmara da galeria, usando accept=image/* e capture=environment apenas na câmara.
* Preserva a captura traseira ao adicionar várias fotografias e usa showPicker com fallback seguro.
* Publica eventos de ponte para integração futura com um seletor nativo FlutterFlow.

= 2.2.233 =
* Form Edit State Integrity para web, Android e iOS.
* Corrige edição de reportes concluídos no fluxo focado e navegação em modo consulta.
* Bloqueia a religação silenciosa de um reporte a outra rota ou paragem.

= 2.2.232 =
* A submissao confirma primeiro o reporte e as respostas, libertando a transacao antes de sincronizar historico, rota e integracoes.
* O historico versionado e as automacoes posteriores passam para reconciliacao assincroma e idempotente.
* O envio final deixa de iniciar uma segunda sincronizacao de rascunho com as mesmas fotografias e a limpeza do rascunho passa para tarefa posterior.
* Perguntas de imagem ganham botoes separados para Camara e Galeria, selecao multipla aditiva e fallback para WebView.
* As politicas mobile de multiplos ficheiros, camara e galeria passam a ser aplicadas no shell nativo 1.6.
* Uploads aceitam HEIC/HEIF quando suportados pelo servidor e devolvem erros de ficheiro claros.

= 2.2.231 =
* Corrige uma regressão no gestor de erros de submissão que transformava validações normais num falso incidente crítico FF.
* Mostra novamente o motivo real, incluindo reporte diário existente, rota sem acesso, paragem ambígua ou contexto divergente.
* Protege a finalização de mutações offline para que o próprio tratamento do erro nunca volte a falhar.
* Mantém o rascunho e as respostas disponíveis quando a validação bloqueia a gravação.

= 2.2.230 =
* Garante que a submissão e as respostas são confirmadas antes de tarefas secundárias.
* Isola rascunhos, histórico versionado, cardex e sincronização da rota com savepoints e recuperação automática.
* Uma falha depois do commit devolve sucesso, evitando reportes duplicados no terreno.
* Agenda reconciliação do histórico e da rota quando alguma integração fica pendente.
* Regista a fase exata da submissão para diagnóstico de incidentes.

= 2.2.229 =
* Hotfix de produção para submissões que devolviam a página de erro crítico do WordPress na web e na APK.
* Protege a transação do formulário com captura de exceções, rollback controlado e referência de diagnóstico.
* Integrações posteriores à gravação, auditoria, eventos, automações e notificações deixam de derrubar uma submissão já concluída.
* Corrige dependência não protegida de mbstring nas automações de performance.
* O front deixa de apresentar HTML bruto de erros do servidor e bloqueia submissões duplicadas.
* O submit nativo do formulário embebido é sempre encaminhado pelo controlador operacional da visita.

= 2.2.228 =
* Corrige recarregamentos periódicos indevidos na APK causados por uma assinatura móvel que incluía a hora de geração.
* A versão de contexto móvel passa a ser estável e só muda quando roles, permissões, âmbito, navegação ou subscrição mudam realmente.
* O WebView adia qualquer recarregamento legítimo enquanto existe um formulário visível em preenchimento.
* Guarda e recupera a última loja, rota e paragem selecionadas quando o WebView é reconstruído ou retomado.
* Evita reinicializações duplicadas do controlador de reporte e eventos repetidos do seletor de loja.

= 2.2.227 =
* Corrige a perda da loja selecionada ao avançar entre os passos do formulário.
* Ignora respostas assíncronas antigas que podiam limpar o formulário e repor o seletor de loja.
* Preserva o contexto de rota, paragem e loja nos campos ocultos durante a navegação do formulário.
* O botão “Preencher formulário” da rota aguarda agora pelo carregamento do painel e abre diretamente a loja correta.
* Mantém a seleção atual durante atualizações do painel e só recarrega o formulário quando o contexto muda ou após uma gravação.

= 2.2.226 =
* Adiciona o contrato móvel canónico para estados de subscrição, catálogo, seleção multi-cliente e ações recomendadas.
* Cria o target WebView subscriptions e o endpoint autenticado /billing/experience para FlutterFlow.
* Faz o checkout e o Portal Stripe regressarem à mesma sessão nativa, com comunicação do resultado à app.
* Atualiza mobileMe, roles, capacidades e navegação após alterações de faturação, incluindo polling protegido após pagamento.
* Isola os cookies por portal e adiciona controlo no BO para ativar ou desativar Planos e Subscrições na app.

= 2.2.225.1 =
* Adiciona seleção manual e deteção automática da página com [fieldflow_billing_portal].
* Remove o fallback incorreto do portal para a página inicial.
* O diagnóstico de produção valida a presença real do shortcode, incluindo Elementor.


= 2.2.225 =
* Adiciona o centro Faturação > Produção com diagnóstico, métricas de conversão, manutenção e reconciliação em modo preview ou correção segura.
* Cria oito emails transacionais editáveis, histórico idempotente, recuperação de checkouts abandonados e avisos de renovação.
* Regista consentimentos versionados para Termos, Privacidade e marketing, com aceitação obrigatória opcional no onboarding público.
* Implementa downgrade seguro, preservando todos os dados e identificando utilizações acima dos limites do novo plano.
* Adiciona as tabelas de consentimentos e histórico de emails sem alterar subscrições, clientes ou faturação já existente.

= 2.2.224.7 =
* Corrige o primeiro clique dos planos mensal e anual para permanecer na página real que contém o shortcode.
* Guarda o URL de continuação da intenção e reutiliza-o após registo, login e ativação por email no Ultimate Member.
* Repara automaticamente a página pública quando o ID guardado aponta para uma página inexistente, incluindo páginas construídas com Elementor.
* Mantém a intenção de subscrição, o checkout e o histórico sem criar redirecionamentos para slugs antigos.

= 2.2.224.6 =
* O plano Enterprise deixa de ficar bloqueado por uma regra fixa de “Sob consulta” quando tem preços comerciais ativos.
* O checkout Enterprise passa a funcionar em sandbox local e Stripe quando o plano, a periodicidade e o catálogo estão corretamente configurados.
* O preço efetivo passa a usar primeiro o valor ativo do catálogo de faturação, com fallback para o preço do plano SaaS.
* O shortcode público e o portal privado apresentam o Enterprise como subscrição normal quando existe pelo menos uma periodicidade disponível.

= 2.2.224.5 =
* O portal de faturação passa a resolver administradores e utilizadores associados a vários clientes através de um seletor seguro.
* A página pública apresenta todas as subscrições ativas acessíveis, separando sandbox e live e ligando cada cliente ao respetivo portal.
* A sincronização de roles passa a considerar todas as subscrições ativas do utilizador, preservando múltiplas roles comerciais quando aplicável.
* Adicionados índices para pesquisas por utilizador, cliente, ambiente e estado nas subscrições comerciais.

= 2.2.224.4 =
* Cria o funil público de subscrição com escolha de plano mensal ou anual, registo Ultimate Member e checkout seguro.
* Guarda a escolha através de um token temporário assinado no servidor, associa o utilizador e cria uma organização provisória sem acesso pago.
* Adiciona a role provisória configurável e aplica a role comercial do plano apenas após pagamento confirmado.
* Suporta registo, ativação por email, login de contas existentes, retomada de checkout e página pública criada automaticamente.
* Adiciona o separador Faturação > Onboarding, histórico de processos e limpeza automática de intenções expiradas.

= 2.2.224.3 =
* Adiciona mapeamento entre planos comerciais e roles WordPress para estados ativo, pendente e inativo.
* Cada subscrição passa a ter um utilizador WordPress responsável, sincronizado após checkout, webhook, edição, suspensão ou cancelamento.
* Preserva roles externas ao FieldFlow e remove corretamente a role anteriormente atribuída quando o plano ou utilizador muda.
* Expõe a configuração do portal de faturação à app e documenta o botão FlutterFlow através do target WebView billing.

= 2.2.224.2 =
* Gestão completa de subscrições, periodicidade, portal web e ponte FlutterFlow.

= 2.2.224.1 =
* O catálogo de faturação passa a ter edição explícita por registo.
* Product ID, Price ID, preço local, moeda e estado podem ser alterados sem apagar o mapeamento.
* Eliminação local protegida quando existem subscrições comerciais dependentes.
* Auditoria e Event Bus para alterações e eliminações do catálogo.

= 2.2.224 =
* Cria modo sandbox local e Stripe test mode para testar checkout sem misturar dados live.
* Adiciona catálogo de preços mensal e anual por plano, ambiente, Product ID e Price ID.
* Implementa Stripe Checkout alojado, chaves cifradas, idempotência e sincronização por webhooks assinados.
* Trata pagamentos falhados com período de tolerância, avisos, restrição progressiva e reativação automática após pagamento.
* Cria a área Faturação e Pagamentos, histórico de faturas e eventos, métricas comerciais e shortcode de plano e faturação.

= 2.2.223 =
* Cria API pública v1 com chaves protegidas, âmbito por cliente e projeto, capacidades, validade, IP e rate limit.
* Adiciona recursos de leitura paginados e incrementais para lojas, rotas, visitas, submissões, stocks, briefings e implementações.
* Disponibiliza datasets planos para Power BI, com cursor e updated_since.
* Cria webhooks assinados por HMAC, fila em background, retentativas exponenciais, teste e reenvio manual.
* Integra API e webhooks com subscrições, Audit Log, Event Bus, segurança e observabilidade.

= 2.2.222 =
* Adiciona rate limiting, proteção de login e validação real de uploads.
* Cria observabilidade para erros agrupados, endpoints lentos, queries, integrações, cron e filas offline.
* Cria gestão avançada de dispositivos com confiança, revogação, bloqueio offline e limpeza remota de cache.
* Adiciona alertas persistentes, retenção automática e pacote de diagnóstico sanitizado.
* Mantém compatibilidade com subscrições, WebView, Offline First, analytics, mapping e exportações.

= 2.2.221 =
* Cria planos FieldFlow Start, Operations, Intelligence e Enterprise, com preços, capacidades e limites editáveis no BO.
* Atribui uma subscrição por cliente, com teste, renovação, expiração, suspensão, notas e exceções específicas.
* Aplica capacidades no BO, shortcodes, portais, app, endpoints REST, IA, relatórios automáticos e exportações.
* Introduz limites mensais para rotas, submissões, IA, exportações, relatórios e dispositivos offline, com utilização visível por cliente.
* Migra clientes existentes para o plano Legacy completo, preservando integralmente o comportamento anterior.
* Mantém dados e configurações quando uma capacidade é desativada e regista alterações no Audit Log e Event Bus.

= 2.2.220.3 =
* Corrige a compressão horizontal dos shortcodes em contentores flex do Elementor, Android e tablet.
* Mantém a correção do scroll branco e garante largura total para host, shell, painéis e painel ativo.
* Evita o efeito de texto vertical e cartões reduzidos a uma coluna estreita.
* Preserva navegação fixa, lazy loading, paginação, Offline First, analytics e exportações.

= 2.2.220.2 =
* Remove reservas inferiores duplicadas nos shortcodes da app e do cliente.
* Faz a navegação inferior usar uma única reserva de espaço em telefone e tablet.
* Garante que painéis inativos não mantêm altura, margem ou padding no documento.
* Normaliza contentores Elementor e corrige o limite real do shortcode depois de trocas de aba e lazy loading.
* Mantém compatibilidade com abas e paginação de Frentes atuais, Offline First, analytics e exportações.

= 2.2.220.1 =
* Adiciona abas ao widget Frentes atuais para Resumo, Todas, Crescimento, Redução e Sem alteração.
* Implementa paginação real e seletor de linhas por página nos analytics do cliente e do merchandiser.
* Permite definir no BO o número padrão de linhas e ativar ou desativar as abas de frentes, globalmente ou por campanha.
* Mantém todas as linhas nas exportações CSV, Excel e PDF, sem alterar os dados de origem.
* Preserva compatibilidade com o polimento responsivo da 2.2.220 e com Offline First, Mapping, rotas e formulários.

= 2.2.220 =
* Reestrutura calendários de rotas para agenda vertical legível em telemóvel e tablet, com opção de grelha compacta.
* Organiza evidências em acordeões por loja e data e só carrega as imagens quando o respetivo bloco é aberto.
* Adiciona paginação progressiva para listas extensas de lojas e controlo do tamanho das miniaturas.
* Permite escolher entre cartões por registo ou tabela com deslocamento em mobile.
* Compacta a navegação inferior e aumenta a área segura para impedir sobreposição de conteúdo.
* Normaliza widgets analytics, KPI, tabelas, gráficos e galerias nos portais do cliente e merchandiser.
* Acrescenta opções globais e por campanha na Personalização para controlar a experiência responsiva.
* Mantém compatibilidade com Analytics e IA 2.2.219, Offline First, Mapping, rotas, formulários e exportações.

= 2.2.219 =
* Adiciona uma camada executiva comum aos analytics do cliente, gestor e merchandiser.
* Compara automaticamente o período atual com o intervalo anterior e apresenta variações homogéneas.
* Deteta anomalias operacionais em execução, visitas pendentes, tempos, pontualidade, stocks, Share Space e frentes.
* Cria fontes rastreáveis, índice de confiança e ações controladas que nunca alteram dados automaticamente.
* Persiste alertas operacionais com severidade, âmbito, histórico, reconhecimento e integração com Audit Log e Event Bus.
* Expande Saúde do sistema com alertas abertos, críticos e reconhecidos.
* Mantém compatibilidade com Offline First 2.2.218.1, Mapping, Design System, Core Integrity, app, briefings, formulários e exportações.

= 2.2.218.1 =
* Corrige a API pública do runtime Offline First, expondo setMeta e getMeta para os formulários carregados em cache.
* Evita bloqueios no reporte quando existe uma versão parcial ou antiga de FieldFlowOffline no browser ou WebView.
* Adiciona verificações de compatibilidade antes de chamar fetchJson, queueForm, enqueueOperation, setMeta e getMeta.
* Mantém todos os dados, filas e contratos da 2.2.218 sem alterações de schema.

= 2.2.218 =
* Introduz a fundação Offline First para a app e os portais WebView.
* Adiciona um bundle operacional protegido com rotas, paragens, lojas, briefings e formulários para utilização local.
* Guarda formulários e fotografias no IndexedDB quando não existe ligação e retoma o envio automaticamente.
* Cria uma fila idempotente para check-in, checkout, falhas, briefings e ações de stock, evitando operações duplicadas.
* Permite reutilizar formulários já carregados no dispositivo mesmo depois de a ligação desaparecer.
* Adiciona tokens de replay assinados para submissões atrasadas sem depender da validade curta do nonce original.
* Mostra estados de sincronização no front e indicadores de dispositivos, pendências e falhas em Saúde do sistema.
* Expõe endpoints de bootstrap, sincronização em lote e diagnóstico para adoção progressiva pela app FlutterFlow.
* Mantém compatibilidade com a app atual, briefings, mapping, rotas, formulários, analytics e exportações.

= 2.2.217 =
* Cria o Centro Universal de Mapping para lojas, produtos, briefings, implementações, stocks e cardex.
* Agrega automaticamente as correspondências existentes sem alterar os fluxos funcionais dos módulos de origem.
* Adiciona sugestões determinísticas com percentagem de confiança, sinais de correspondência e proteção de insígnia.
* Guarda aliases confirmados por cliente e projeto para reutilização segura em importações futuras.
* Permite confirmar e rejeitar mappings em massa, escolher destinos manualmente e desfazer alterações através do histórico.
* Sincroniza decisões confirmadas de volta para briefings, implementações, stocks e produtos do cardex.
* Adiciona endpoints REST protegidos para pesquisa de destinos e resumo de cobertura.
* Expande a Saúde do sistema com indicadores do centro de mapping.
* Mantém compatibilidade com app, portais, rotas, formulários, analytics, exportações, performance e Core Integrity Foundation.

= 2.2.216 =
* Introduz carregamento automático de classes por necessidade, mantendo o catálogo integral para diagnóstico e compatibilidade.
* Carrega progressivamente 15 painéis pesados nos portais operacional e de cliente.
* Adiciona um design system comum para KPI, tabelas, gráficos, cartões, estados de carregamento e erros.
* Converte tabelas extensas em cartões legíveis em telemóvel e normaliza gráficos em iOS, Android e tablet.
* Adia Campanha PDV, relatórios e análise com IA até à abertura do respetivo separador.
* Adiciona pré-visualização responsiva e diagnóstico do runtime em Saúde do sistema.
* Torna o autocomplete compatível com módulos carregados dinamicamente e evita o seu carregamento fora das páginas FieldFlow.
* Mantém compatibilidade com o núcleo de integridade, app, briefings, rotas, formulários, analytics e exportações da versão 2.2.215.

= 2.2.215 =
* Adiciona estados canónicos para rotas, visitas e submissões sem remover a lógica existente.
* Cria um barramento interno de eventos persistente para submissões, visitas, rotas, briefings e stocks.
* Adiciona audit log com origem, utilizador, correlação e valores anteriores e posteriores nas alterações críticas.
* Expande a Saúde do sistema com análise de integridade, pré-visualização e reconciliação segura.
* Adiciona contratos de dados versionados e endpoints REST de leitura para app e integrações.
* Mantém compatibilidade com briefings, formulários, analytics, rotas, app e exportações da build 2.2.214.

= 2.2.214 =
* Cálculo canónico de quilómetros, tempo de viagem e portagens no editor de Rotas e nos planeadores de Campanhas PDV.
* Sincronização dos mesmos indicadores nos shortcodes operacionais e de cliente.
* Exportações CSV, Excel e PDF passam a preservar o tipo de pergunta e o detalhe estruturado dos comparativos de briefing.
* Nova folha Comparativos nos exports de submissões e novas colunas comparativas nos exports Analytics.
* Melhorada a recuperação de métricas quando o preview Google não responde.


= 2.2.213 =
* Corrige a prioridade das cores nos headers internos e separa claramente títulos de texto secundário.
* Introduz um compositor visual de perguntas comparativas, com deteção das referências do briefing.
* Consolida tabelas e widgets analytics para desktop, tablet, iOS e Android.

= 2.2.212 =
* Adiciona perguntas estruturadas de Frentes versus Concorrência, com produtos, contagem de frentes e cálculo automático das quotas.
* Converte em segurança perguntas antigas de percentagem de Share Space, preservando o histórico.
* Adiciona mapping seguro de lojas por insígnia e correspondência única no backoffice.
* Cria widgets Share Space dedicados em analytics de cliente e merchandiser, com KPI, objetivo, desvio e tabelas responsivas.
* Melhora a densidade de gráficos, KPI e tabelas em telemóvel e tablet.
* Faz os headers de Briefings, Stocks, Analytics, Growth Hub e Academy herdarem as cores da personalização.
* Expande a IA de briefings e analytics para interpretar Share Space sem inventar percentagens.

= 2.2.211 =
* Adiciona uma camada responsiva comum para todos os shortcodes FieldFlow.
* Melhora a experiência em iOS, Android, WebView nativa, PWA e tablet.
* Normaliza safe areas, teclado móvel, alvos táteis, tabelas e navegação inferior.
* Mantém a compatibilidade funcional da build 2.2.210.


= 2.2.210 =
* Completa o centro de controlo móvel com JavaScript da bridge, downloads, uploads de ficheiros e navegação externa.
* Adiciona renovação automática real da sessão WebView por heartbeat, limitada pelo refresh token móvel.
* Permite programar início e fim da manutenção, desativação automática e intervalo de nova tentativa.
* Adiciona zoom, aviso de rede e visualizador de imagens configuráveis no backoffice.
* Expande app-config para API 1.5 e documenta o endpoint de heartbeat.

= 2.2.209 =
* Adiciona o Centro de Controlo da Aplicação Mobile no backoffice.
* Centraliza Android, iOS, manutenção, versões, Bridge WebView, segurança e links das lojas.
* Adiciona gestão de sessões e dispositivos com revogação, bloqueio e diagnóstico.
* Expande mobileAppConfig com regras por plataforma, atualização obrigatória e capacidades nativas.
* Aplica funcionalidades globais ao contexto móvel, endpoints REST e painéis da WebView.

= 2.2.208 =
* Impede rascunhos antigos de substituir o nonce atual do formulário.
* Corrige a mensagem de página desatualizada ao submeter ou concluir visitas.
* Reforça submissões AJAX autenticadas com o nonce REST atual do portal.

= 2.2.207 =
* Corrige a conclusão operacional das visitas após submissão de formulários embebidos.
* Encaminha wizard e teclado pelo controlador exterior e verifica o estado final da paragem.

= 2.2.206 =
* Recupera com segurança a sessão WebView após reconstruções do ecrã.
* Permite uploads múltiplos imagem a imagem em Android WebView.
* Abre fotografias em ecrã inteiro dentro da aplicação.
* Confirma a remoção de referências no Cardex operacional.
* Sincroniza roles, capacidades, âmbito e navegação através da API móvel.

= 2.2.204 =
* Adiciona ponte SSO segura para abrir os portais FieldFlow dentro de WebView.
* Cria tickets de abertura descartáveis, válidos durante 90 segundos e ligados à sessão móvel.
* Adiciona portais móveis sem header/footer do tema para operação e cliente.
* Revoga as sessões WebView quando o utilizador termina a sessão móvel.
* Expõe permissões e destino recomendado dos portais na API móvel.


= 2.2.203 =
* Evita conflitos entre os tokens móveis opacos e plugins JWT instalados no WordPress.
* Adiciona o cabeçalho dedicado X-FieldFlow-Mobile-Token para autenticação da app.
* Mantém compatibilidade com Bearer quando não existe um plugin JWT a intercetar o pedido.

= 2.2.202 =
* Adds the versioned FieldFlow Mobile API foundation for FlutterFlow, Android and iOS.
* Adds secure per-device bearer sessions with rotating refresh tokens.
* Adds resolved dynamic forms, mobile submissions, media uploads and idempotent mutations.

= 2.2.201 =
* Remove o limite rígido de 5.000 lojas no seletor dos Cardex operacionais.
* Garante que lojas novas e ativas da Base Comercial ficam disponíveis ao editar Cardex existentes.
* Coloca primeiro as lojas já selecionadas, da campanha, do cliente e atualizadas mais recentemente.
* Adiciona pesquisa imediata por nome, referência externa ou ID sem perder lojas já selecionadas.
* Mantém a validação transacional das associações e os ajustes específicos por loja.

= 2.2.200 =
* Corrige o contraste do cabeçalho das Implementações no front, usando as cores de título e subtítulo configuradas em Personalização.
* Faz o gradiente do cabeçalho respeitar as cores inicial e final do template ativo.
* Alinha superfícies, contornos, textos, botões, filtros e cartões do módulo com o tema global ou da campanha.
* Mantém cores semânticas próprias para estados implementado, bloqueado, pendente e agendado.
* Melhora a adaptação do cabeçalho e dos indicadores em desktop, tablet e mobile.

= 2.2.199 =
* Adiciona o módulo Implementações de Materiais no backoffice, FieldFlow App e portal do cliente.
* Importa ficheiros DOCX da Pernod Ricard, reconhecendo notas operacionais e tabelas de centralizados e RTDs.
* Associa lojas pelo código externo, reutiliza correspondências confirmadas e permite corrigir associações manualmente.
* Permite editar previsões, estados, observações e quantidades antes ou depois da publicação.
* Filtra a visão do merchandiser pelas lojas atribuídas e mantém a visão completa para clientes autorizados.
* Preserva os módulos existentes através de tabelas, rotas REST, shortcodes e estilos isolados.

= 2.2.198 =
* Permite criar e editar perguntas de briefing com tipo de resposta, estado, lojas, ordem e configuração analítica completa.
* Sincroniza o widget da pergunta com o Centro de Analytics, o portal do cliente e a app do merchandiser.
* Mostra perguntas de briefing e contextuais no Centro de Analytics com recomendações de widget e edição dos widgets existentes.
* Permite ativar, desativar, apagar e reordenar perguntas sem perder respostas históricas.
* Estende a mesma autonomia às perguntas contextuais dos formulários.

= 2.2.197 =
* Sincroniza rascunhos do frontend com o servidor, mantendo o rascunho local como primeira proteção.
* Recupera respostas e fotografias noutro dispositivo com o mesmo utilizador.
* Resolve conflitos por revisão, preservando a versão mais recente.
* Usa ficheiros temporários privados e limpa-os após submissão ou 72 horas.

= 2.2.196 =
* Guarda automaticamente rascunhos no frontend e recupera-os após suspensão, recarregamento ou troca acidental de página.
* Preserva respostas, passos, matrizes, linhas dinâmicas e fotografias locais.
* Limpa o rascunho apenas depois de o servidor confirmar a submissão.

= 2.2.195 =
* O Google Autocomplete da Base Comercial passa a usar diretamente as coordenadas da sugestão selecionada.
* A loja selecionada aparece imediatamente no mapa antes da gravação.
* Filtrar a base interna deixa de executar automaticamente a Descoberta Google.
* A morada é validada antes da gravação e alterações manuais limpam coordenadas antigas.
* Os PDVs criados no front ficam associados ou reativados automaticamente na campanha selecionada.
* A cache comercial é atualizada após guardar ou apagar, mantendo BO e front sincronizados.

= 2.2.194 =
* O Cardex passa a aceitar ajustes de sortido por loja diretamente no formulário de Frentes.
* O merchandiser pode editar, remover e acrescentar referências sem alterar o Cardex base das restantes lojas.
* Os ajustes ficam guardados como exceção da loja e podem ser consultados ou repostos no back office.
* A Sugestão de encomenda acompanha automaticamente a lista final de Frentes e elimina referências que deixaram de constar.
* Produtos com zero frentes continuam a receber a sugestão automática configurada.
* Os widgets de Sugestão de encomenda ficam separados dos widgets de Frentes no Analytics.
* O Analytics apresenta caixas, unidades por caixa, unidades totais e observações da encomenda.

= 2.2.193 =
* Novo tipo de pergunta Upload de imagens por categoria.
* Uma única pergunta pode apresentar categorias como Topos, Expositores, Arcas e Displays.
* Cada categoria selecionada abre o seu próprio upload, com uma ou várias fotografias.
* As imagens ficam identificadas pela categoria no histórico, edição, analytics e exportações.
* Disponível também nas perguntas contextuais e nas perguntas de execução do briefing.
* Mantém a seleção visual, as condições múltiplas e todos os formulários existentes.

= 2.2.192 =
* Novo tipo de pergunta com cartões visuais, imagem por opção e seleção simples ou múltipla.
* Várias regras condicionais por pergunta, com direcionamento independente para diferentes perguntas.
* Valores das condições escolhidos diretamente das opções configuradas, reduzindo erros de correspondência.
* Compatibilidade com formulários e condições já existentes.

= 2.2.191 =
* Edição de rotas com preservação integral do estado operacional e dos reportes.
* Reordenação automática por distância sem repor lojas efetuadas para por visitar.
* Recuperação de ligações antigas entre formulários, lojas e paragens.
* Sincronização transacional do estado da paragem e da rota no BO e no front.

= 2.2.190 =
* Novos controlos em Personalização para a cor do título e do texto do cartão Escopo atual.
* As cores aplicam-se ao portal do cliente e ao cartão Utilizador da app do merchandiser.
* Compatível com tema global, temas por campanha e troca dinâmica de campanha.

= 2.2.189 =
* Perguntas do briefing passam a usar o universo ativo de lojas da campanha, com seleção manual capaz de corrigir a deteção automática da insígnia.
* A ordem definida no backoffice é propagada a todas as lojas, preservada no formulário e apresentada de forma consistente nos shortcodes da equipa e do cliente.
* A vista da equipa mostra apenas perguntas e métricas das lojas atribuídas ao utilizador, incluindo proteção para utilizadores sem lojas atribuídas.
* O shortcode da equipa respeita o cliente e o projeto ativos e acompanha mudanças de âmbito dentro do FieldFlow App.
* Lojas de campanha inativas deixam de aparecer no mapping e de receber perguntas no formulário ou nos painéis.
* Perguntas clonadas por loja são agrupadas como uma única pergunta lógica no backoffice e no portal, com contagem e nomes das lojas associadas.
* Alterações ao âmbito de uma pergunta removem associações antigas que já não pertencem à seleção atual.
* Alterações nos responsáveis do projeto, da rota ou do PDV atualizam imediatamente os destinatários dos briefings ativos.
* A migração 2.2.189 normaliza automaticamente a ordem legada fixa e prioridades divergentes entre lojas, preservando ordens já personalizadas.
* A análise do Briefing AI Copilot passa a correr em segundo plano, com fila, estado, recuperação de jobs interrompidos e limite de tentativas ao fornecedor, evitando o erro 504 no navegador.

= 2.2.188 =
* Evidências do portal do cliente apresentadas uma por linha e em largura total, alinhadas com o FieldFlow App.
* Miniaturas mantêm navegação horizontal compacta dentro de cada loja ou data em desktop.
* Novos controlos global e por campanha para a cor do texto selecionado e o fundo da seleção.
* Cores de seleção propagadas para a app, portal do cliente e analytics partilhado.

= 2.2.183 =
* Personalização global e por campanha passa a incluir todas as cores de texto, incluindo headings, labels, hero, links, inputs, placeholders, navegação normal, hover e ativa, botões normais e em hover, tabelas, badges e estados.
* Widgets de evidências agregam local, data e imagens na mesma leitura, com agrupamento configurável, miniaturas, ZIP por loja ou por data e tabela detalhada opcional.
* Remove URLs e JSON em bruto das tabelas Analytics, substituindo-os por contagens de imagens e dados operacionais limpos.
* Analytics do [fieldflow_app] adota o mesmo sistema visual premium do [fieldflow_client_portal].
* Exportações CSV e Excel incluem a data, rota, loja e ficheiro de cada evidência.

= 2.2.181 =
* Novo estúdio de personalização global e por campanha no menu Personalização.
* Cinco templates premium com controlo de paleta, tipografia, densidade, headers, abas, cards, botões, tabelas e gráficos.
* Redesign dos shortcodes [fieldflow_app] e [fieldflow_client_portal], com menos ruído visual e identidade coerente em todas as abas.
* Analytics com grelhas adaptativas, gráficos ligados à paleta da campanha e tabelas pesquisáveis, paginadas e convertidas em cartões no mobile.
* Logo, títulos e tema da campanha aplicados automaticamente ao app e portal do cliente.

= 2.2.180 =
* Cardex automático passa a respeitar o cliente e o projeto da visita ao escolher associações de loja.
* Remove o fallback indevido para um Cardex fixo antigo quando a pergunta está em modo automático.
* Valida a persistência das lojas selecionadas e mantém o âmbito anterior se a gravação falhar.
* O BO apresenta a contagem de lojas associadas em cada Cardex.

= 2.2.179 =
* Corrige o escopo da pré-visualização AJAX, que enviava semanal apesar de o formulário estar configurado para mensal.
* Corredor partida/chegada passa a respeitar integralmente as datas de início e fim.
* Pré-visualizar, aplicar, exportar e reabrir o planeador usam o mesmo escopo mensal.
* O servidor protege contra JavaScript antigo em cache, inferindo janela mensal quando a data final ultrapassa sete dias.

= 2.2.178 =
* Rotas com partida e chegada na mesma base passam a usar corredores radiais com arco máximo, evitando misturar lados opostos quando existem dias disponíveis.
* Datas automáticas deixam de ser tratadas como bloqueios rígidos na otimização global.
* Diagnóstico territorial inclui arco desde a base, risco de cruzamento da base e classificação do corredor.

= 2.2.177 =
* Corrige o erro 504 na pré-visualização mensal e elimina reconstruções repetidas do mesmo plano.
* A pré-visualização passa a usar um único pedido AJAX cancelável, sem refresh da página e sem pedidos concorrentes escondidos.
* Alterar parâmetros deixa de recalcular automaticamente o mês inteiro; o cálculo ocorre apenas ao clicar em Pré-visualizar sugestão.
* A matriz Google fica reservada para a criação da rota real, usando a matriz interna rápida durante a sugestão.
* A otimização global recebe orçamento temporal, limite de candidatos e memoização, devolvendo a melhor solução válida encontrada.
* O planeador manual reutiliza o preview calculado e a criação de rotas evita chamadas Google duplicadas.

= 2.2.176 =
* Mantém primeiro as semanas da periodicidade e depois constrói rotas territoriais completas dentro de cada semana.
* Atribui os blocos territoriais aos dias apenas após validar quilómetros, tempo, número de visitas, localidades e direções.
* Introduz uma reserva operacional para trânsito, acessos e diferença entre o estimador rápido e a matriz rodoviária final.
* Impede os balanceadores antigos de desfazerem rotas territoriais válidas apenas para igualar o número de lojas por dia.

= 2.2.175 =
* Introduz coerência territorial forte para rotas com partida e chegada na mesma base, separando blocos norte, sul, este e oeste.
* Faz limpeza contínua de localidade, evitando regressar a uma cidade ou zona já concluída durante o mesmo dia.
* Adiciona movimentos de blocos completos de localidade entre dias, para reduzir dias mistos e consolidar a cobertura territorial.
* Impede que a otimização final de waypoints Google desfaça a ordem territorial quando a base é a mesma, mantendo a Google para quilómetros, tempos e portagens.
* Alinha BO, portal, app e exportações com direção dominante, coerência, mudanças e regressos de direção, localidades e fragmentação territorial.
* Adiciona testes automáticos para o cenário base única com lojas a norte e sul.

= 2.2.174 =
* Introduz uma matriz rodoviária canónica com modos híbrido, Google e estimador interno, reutilizada no planeamento, criação de rotas, BO, front e exportações.
* Adiciona regret insertion, 2-opt sem o antigo limite de 14 lojas, otimização de waypoints Google e melhoria global por movimentos e trocas entre dias da mesma semana.
* Mantém todas as regras rígidas de periodicidade, intervalos, datas, dias bloqueados, capacidade diária, partida e chegada.
* Alinha quilómetros de acesso, entre PDVs, regresso e total em todas as superfícies, com fonte, versão da matriz, cobertura, integridade e poupança da otimização.
* Corrige a apresentação de distâncias desconhecidas, que deixam de aparecer como 0 km, e preserva os metadados de otimização durante atualizações Google.
* Expande CSV, Excel e PDF com fornecedor da matriz, método de otimização, quilómetros e minutos poupados.


= 2.2.168 =
* Reorganiza os widgets Analytics do cliente em duas colunas legíveis no desktop e uma coluna em tablet e mobile.
* Tabelas extensas passam a ocupar toda a largura, enquanto tabelas compactas usam o espaço sem criar quatro cartões estreitos lado a lado.
* Remove colunas vazias de cada tabela e ajusta automaticamente a largura ao tipo de pergunta e aos dados disponíveis.
* Em telemóvel, as linhas das tabelas Analytics transformam-se em cartões com rótulos por campo, evitando scroll horizontal obrigatório.
* Melhora cabeçalhos, pesquisa, paginação, larguras de loja, categoria e produto, KPIs de frentes e leitura de evidências.
* Alinha o Analytics do merchandiser com a mesma lógica responsiva, mantendo métricas simples em grelha e widgets com tabelas, frentes ou imagens em largura total.
* Mantém desktop, tablet e mobile coerentes no portal do cliente e no [fieldflow_app].


= 2.2.167 =
* Reconcilia lojas Analytics através de submissão, versão, registo, binding, paragem, rota e snapshots históricos, incluindo paragens já removidas.
* Corrige o legado em que o ID da paragem foi guardado como ID da loja e dá prioridade ao contexto explícito da paragem.
* Usa fontes históricas de stocks e briefings quando a loja já não existe na base comercial.
* Guarda um snapshot da loja dentro das novas submissões para preservar nome e morada mesmo após alterações futuras.
* Aplica a mesma identificação de loja aos widgets do cliente, Analytics do merchandiser e exportações Excel, CSV e PDF.
* Alinha o Analytics do merchandiser com pergunta analisada, frentes, evidências por loja e tabelas detalhadas com scroll responsivo.


= 2.2.166 =
* Excel Analytics com folhas Resumo, Índice, Rotas, Métricas, Séries, Detalhe, Evidências e uma folha individual por pergunta ou widget.
* Folhas extensas são divididas automaticamente para preservar todos os registos e a compatibilidade com Excel.
* Detalhe Analytics inclui chave da pergunta e ID da loja.
* Relatórios automáticos têm email de teste configurável, botão Guardar e enviar teste com anexos e histórico separado do último teste.
* Excel e PDF são gerados no momento e anexados automaticamente aos emails reais e de teste.

= 2.2.165 =
* Recupera o nome real de lojas em submissões antigas através da paragem de rota, snapshot histórico, metadados e base comercial.
* Faz os grupos de lojas reconhecerem tanto o ID antigo da submissão como o ID atual da paragem.
* Substitui o download frágil por um ZIP autenticado, preparado no servidor e descarregado diretamente por loja.
* Corrige o erro de JavaScript dos widgets de frentes e mantém a pergunta, KPIs e tabela ativos no mesmo quadro.
* Adiciona scroll horizontal e vertical às tabelas Analytics, cabeçalhos fixos e espaço inferior para a navegação do portal.
* Torna o Excel integral, sem limites de rotas, dashboards, widgets, linhas analíticas ou evidências, e acrescenta uma folha Evidências.
* Melhora o Centro de Analytics no BO com contadores de frentes e evidências, tipo real da pergunta e alertas de ligações inválidas.

= 2.2.153 =
* Faz o Excel manual reutilizar exatamente os dados já apresentados na aba Analytics.
* Mantém uma fotografia temporária dos filtros, rotas e métricas visíveis para impedir folhas vazias por divergência entre pedidos.
* Nas automações, recolhe cada período uma única vez e reutiliza os mesmos dados no Excel e no PDF.
* Acrescenta ao Resumo a origem dos dados, o período e as contagens de rotas e métricas.
* Reduz o detalhe pesado para 6.000 linhas no total e 1.200 por métrica, melhorando a abertura do XLSX.
* Clarifica no cliente e merchandiser que os atalhos semana e mês apenas alteram as datas da exportação manual.

= 2.2.152 =
* Corrige os ficheiros XLSX vazios e reduz o volume de detalhe para abertura rápida.
* Valida o workbook antes do download e garante linhas informativas quando não existem dados no período.
* Adiciona automações semanais por cliente e campanha no Centro de e-mails.
* Permite relatório semanal, acumulado, Excel, PDF, destinatários de equipa e cliente, assunto, cor e intervalo de validade.
* Inclui envio de teste, envio imediato, pausa e histórico do último resultado.

= 2.2.151 =
* Novo layout premium e fluxo de relatório semanal na aba Analytics do cliente.
* Atalhos de período no cliente e merchandiser.
* CSV analítico normalizado.
* Excel premium XLSX com folhas separadas.
* PDF executivo mais curto e orientado à decisão.
* Tabelas analytics recolhíveis e gráficos mais legíveis.

= 2.2.150 =
* Processa a validação IA por AJAX em blocos de seis lojas, evitando pedidos longos e erros 504.
* Guarda o progresso após cada bloco e permite parar ou continuar sem perder validações concluídas.
* Adiciona seleção múltipla e associação manual em massa, com um PDV diferente por linha.
* Permite selecionar automaticamente sugestões acima de 85%, 90% ou 95%.
* Bloqueia associações duplicadas ao mesmo PDV e recalcula métricas apenas uma vez no fim.

= 2.2.149 =
* Adiciona sugestões inteligentes para associar lojas de stock aos PDVs existentes.
* Cria uma lista curta de candidatos usando código Centro, referência externa, nome e localidade.
* Permite validar ambiguidades com o fornecedor de IA configurado no Centro de IA.
* A IA só pode escolher IDs existentes na lista segura do FieldFlow, evitando lojas inventadas.
* Adiciona confiança, justificação, alternativas e pré-seleção no backoffice.
* Permite associação automática em massa com limites de 85%, 90% ou 95%.
* Impede que duas lojas sejam associadas automaticamente ao mesmo PDV no mesmo lote.

= 2.2.148 =
* Corrige e repara automaticamente as tabelas do módulo de stocks em instalações já ativas.

= 2.2.147 =
* Adiciona Stocks Operacionais no backoffice, por cliente, projeto, insígnia e data de referência.
* Importa XLSX e CSV com deteção automática de cabeçalhos, incluindo o formato Pernod Pingo Doce.
* Associa lojas por referência externa ou nome e permite validar manualmente exceções.
* Publica uma nova aba Stocks na app do merchandiser, focada nas lojas da rota e atribuições.
* Mostra no portal do cliente cobertura, riscos e prova de consulta pela equipa.
* Integra o snapshot mais recente na análise IA, sem substituir a validação física em loja.

= 2.2.139 =
* Analytics da app filtrado automaticamente pelo utilizador autenticado.
* Dashboard unificado e responsivo para app, portal do cliente e BO.
* Calendário mensal de rotas no fieldflow_app, com resumo, progresso e abertura direta do dia.
* Removida a aba Base da app para evitar duplicação com Descobrir.
* Centro de Analytics do BO reorganizado por dashboards, grupos de lojas e widgets.

= 2.2.138 =
* Substitui a grelha gigante de submissões por uma tabela compacta com detalhe expansível.
* Organiza respostas, produtos, ruturas, encomendas e evidências em blocos próprios no BO e no portal do cliente.
* Adiciona paginação e indicadores rápidos à página de submissões no backoffice.
* O CSV resumo passa a usar colunas fixas e uma linha por submissão.
* Adiciona CSV de dados normalizados para análise sem uma coluna por referência.
* O Excel passa a ter folhas separadas para Submissões, Respostas, Produtos, Encomendas e Evidências.
* O PDF passa a ser executivo, destacando respostas, ruturas, alterações, encomendas e fotografias.
* Reduz o payload do portal, deixando de enviar centenas de colunas dinâmicas que não são necessárias na visualização.

= 2.2.137 =
* Garante que a submissão do formulário marca a paragem correspondente como done na tabela real da rota.
* Recalcula automaticamente a rota para in_progress ou completed após qualquer alteração do estado da paragem.
* Corrige o transporte do route_stop_id entre o formulário dinâmico, a submissão e a rota.
* Confirma no servidor que o estado done ficou efetivamente persistido antes de concluir a transação.
* Atualiza imediatamente a Minha Rota e os indicadores do fieldflow_app sem obrigar a recarregar a página.
* Sincroniza igualmente alterações de estado feitas pelos endpoints de paragens, evitando rotas visualmente desatualizadas.

= 2.2.136 =
* Corrige o botao Submeter ao mudar de uma loja ja reportada para uma loja ainda sem reporte.
* Recalcula o estado do botao sempre que o formulario dinamico e substituido.
* Mantem bloqueados apenas os reportes existentes que nao estejam em modo de edicao.
* Adiciona uma protecao no ultimo passo para impedir bloqueios silenciosos do envio.

= 2.2.135 =
* Corrige a recuperação das respostas de reportes diários já existentes.
* Mantém compatibilidade quando chaves de perguntas mudaram após alterações ao formulário, briefing ou Cardex.
* Recupera valores pela chave, rótulo e versão histórica ligada à submissão.
* Mantém matrizes, sugestões de encomenda, uploads e seleções múltiplas disponíveis em consulta e edição.

= 2.2.134 =
* Impede reportes duplicados através da regra de um formulário por loja e dia.
* Mostra imediatamente que o reporte já foi efetuado e bloqueia uma nova submissão.
* Adiciona a opção Editar reporte para corrigir respostas sem criar outra linha de submissão.
* Mantém auditoria completa através de novas versões do registo, preservando o reporte original.
* Reforça a proteção no servidor e na base de dados com data operacional e chave única diária.
* Evita repetir automações, notificações e eventos de conclusão quando o reporte é apenas editado.

= 2.2.133 =
* Marca automaticamente a paragem como concluída quando o formulário da loja é submetido com sucesso.
* Mantém a gravação do formulário e a conclusão da visita na mesma transação, evitando estados desencontrados.
* Resolve automaticamente a paragem quando existe uma única correspondência entre rota e loja, sem adivinhar em casos ambíguos.
* Regista a origem da conclusão no histórico técnico da paragem e evita eventos de conclusão duplicados em novas versões do reporte.
* Atualiza a rota para em curso ou concluída de acordo com o estado das restantes paragens.
* Mostra uma confirmação explícita de que o reporte foi gravado e a visita concluída.

= 2.2.132 =
* Adiciona modo de selecao unica ou multipla nas perguntas Select e Radio.
* Mostra escolhas multiplas como checkboxes touch-friendly em mobile, tablet e desktop.
* Guarda respostas multiplas em JSON, mantendo historico, prefill e exportacoes compativeis.
* Valida perguntas obrigatorias por grupo e preserva condicionais quando uma das opcoes escolhidas corresponde ao valor configurado.
* Permite editar respostas multiplas no detalhe da submissao no BO.

= 2.2.131 =
* Redesenha os acordeões de categorias com cabeçalho compacto, horizontal e de largura total.
* Protege cores, dimensões e alinhamento contra estilos globais do tema ou do shortcode fieldflow_app.
* Usa apenas uma barra lateral na cor da marca, mantendo o corpo branco e reduzindo o peso visual.
* Adiciona uma seta SVG clara e um estado aberto discreto.
* Compacta os contadores de referências e de encomendas em badges legíveis.
* Normaliza pequenas diferenças de apóstrofos, espaços e capitalização para evitar categorias visualmente duplicadas.

= 2.2.130 =
* Organiza Número de frentes e Sugestão de encomenda em categorias expansíveis.
* Abre apenas a primeira categoria por defeito e inclui controlos para abrir ou fechar todas.
* Mostra contadores de referências e estado resumido em cada categoria.
* Interpreta campos de frentes vazios como zero no browser e no servidor.
* Mantém a sugestão automática de encomenda para ruturas, incluindo quando o campo de frentes fica vazio.
* Reforça o fallback no servidor para garantir a sugestão mesmo quando o JavaScript não é executado.

= 2.2.129 =
* Corrige o alinhamento das opções Sim e Não com o respetivo seletor.
* Converte Número de frentes para cartões responsivos por produto em mobile e tablet.
* Agrupa produtos por categoria e bloqueia os dados vindos do Cardex durante o reporte.
* Preenche automaticamente a sugestão de encomenda quando as frentes do produto são 0.
* Permite configurar no Form Builder o número de caixas sugeridas por rutura, com padrão de 1 caixa.
* Mantém sempre a prioridade de qualquer quantidade introduzida manualmente.
* Evita que os botões do formulário fiquem tapados pela navegação inferior do fieldflow_app.

= 2.2.128 =
* Melhora a legibilidade e as áreas de toque dos formulários em mobile, tablet e desktop.
* Reposiciona o ecrã no topo do formulário ao avançar ou recuar entre steps.
* Mostra validação, estado de gravação e confirmação de sucesso dentro do formulário e na app.
* Usa a data real da rota para perguntas de briefing e reforça a validação de loja, paragem, rota e utilizador atribuído.
* Corrige a aprovação em massa no BO com payload compacto e identificadores estáveis por sugestão.
* Evita colisões entre perguntas semelhantes e preserva a compatibilidade com aprovações antigas.


= 2.2.127 =
* Corrige a persistência e o feedback visual ao aprovar sugestões de perguntas do briefing.
* Permite selecionar e aprovar várias sugestões em simultâneo.
* Respeita o tipo, formulário e obrigatoriedade configurados em cada sugestão.
* Remove da lista as sugestões já aprovadas e regressa sempre à aba Perguntas de execução.
* Valida a gravação real na base de dados e apresenta erros de mapping por secção.


= 2.2.126 =
* Added pagination to briefing execution questions in the backoffice.
* Added individual and bulk selection with safe bulk deletion.
* Added 10, 20, 50 and 100 rows per page controls.
* Preserves historical form submission answers when deleting question assignments.
* Keeps the execution questions tab active after paging and deletion.


= 2.2.125 =
* Removes the four-item Share Space question suggestion cap and supports every detected reference.
* Expands briefing AI question suggestions up to 20 operational checks.
* Adds AI and manual support for image evidence questions using the existing form upload engine.
* Allows changing the suggested response type and required state before approval.
* Adds deterministic photo evidence suggestions for promotions, VAPs and physical implementations.


= 2.2.124 =
* Novo tipo de pergunta Bloco de Briefing, ordenável no construtor de formulários.
* Perguntas do briefing deixam de alterar automaticamente a ordem ou os steps do formulário.
* Sugestões automáticas de perguntas com a IA já configurada, sujeitas a aprovação humana.
* Perguntas aplicadas apenas às lojas mapeadas e, quando aplicável, à insígnia da secção.
* Chave comum por pergunta entre lojas para exportações, relatórios e analytics agregados.
* Compatibilidade com submissões antigas, edição, CSV, Excel, PDF e shortcodes front.


= 2.2.123 =
* Briefing Execution Forms: briefing-linked contextual questions, validity and response metrics.
* Dedicated form step for weekly briefing questions.
* Definitive horizontal touch scrolling fix for briefing tables on tablet and mobile.

= 2.2.122 =
* Premium front layout polish for the main shortcodes.
* Wider full-width shells with premium padding for desktop, tablet and mobile.
* Improved table wrappers, sticky headers, cleaner zebra/hover states and better horizontal scrolling.
* Better chart card presentation and more cohesive KPI cards across the app, client portal, briefings and growth hub.

= 2.2.121 =
* Corrige a leitura da folha Status of Share Space, selecionando a ocorrência mais recente da W28 e suportando valores Excel em notação científica.
* Mostra os sete KPI de Share Space no BO, na app da equipa e no portal do cliente, incluindo quota PR, quota concorrente, objetivo, desvio e variação semanal.
* Integra os 136 registos da folha ESPAÇOS CENTRALIZADOS numa área própria, com 128 linhas Sonae e 8 Auchan, mesmo antes do mapping.
* Adiciona uma aba própria para o sortido específico por loja, com 63 associações Auchan.
* Mantém sempre Sonae, Pingo Doce, Auchan, Intermarché e El Corte Inglés visíveis no painel de mapping.
* Mostra as lojas FieldFlow disponíveis por insígnia, mesmo quando o Excel não contém nomes externos dessa cadeia.
* Adiciona o painel Folhas do Excel, com cobertura e contagens das 17 folhas reconhecidas.
* Reforça o Briefing AI Copilot com todas as métricas de Share Space, contagens de espaços e manifesto das folhas importadas.


= 2.2.120 =
* Restaura o matching flexível da versão 2.2.118, mantendo CNT, CBD e MDL como variantes de pesquisa sem bloqueios rígidos.
* O briefing completo passa a aparecer mesmo quando ainda não existem mappings de loja.
* As instruções das lojas atribuídas surgem primeiro e o restante briefing é apresentado como contexto com notas claras.
* O universo de destinatários deixa de depender das lojas já mapeadas no Excel.
* O seletor de mapping inclui lojas genéricas do cliente e lojas da campanha, incluindo Pingo Doce e outras insígnias.
* Briefings publicados têm os destinatários reconstruídos automaticamente na atualização.


= 2.2.119 =
* Dicionário operacional de lojas com CNT = Continente, CBD = Continente Bom Dia e MDL = Continente Modelo.
* Matching sensível à família da insígnia, evitando confundir formatos Sonae na mesma cidade.
* Reforço do score pelo nome da localidade depois de remover o prefixo da insígnia.
* Compatibilidade com nomes internos antigos como Modelo Continente e Bom Dia.

= 2.2.118 =
* Novo Centro de Inteligência Artificial separado de licenciamento, mapas e routing.
* Fornecedor principal, fallback e escolha independente por Briefings, Routing e Analytics.
* Chaves mascaradas, preservação segura de credenciais e suporte a constantes no wp-config.php.
* Testes individuais com diagnóstico real de endpoint, modelo, deployment, DNS e autenticação.
* Logs técnicos de utilização sem guardar prompts ou conteúdos comerciais.
* Limite diário, retenção configurável e histórico por módulo e fornecedor.

= 2.2.117 =
* Suporta endpoints completos de projetos Microsoft Foundry, preservando `/api/projects/{project}`.
* Usa Chat Completions e Responses API no endpoint OpenAI compatível do projeto.
* Mantém compatibilidade com Azure OpenAI v1 e endpoints clássicos.
* Melhora o diagnóstico de DNS e autenticação por chave do recurso Foundry.

= 2.2.116 =
* Corrige a integração Azure OpenAI com suporte automático à API v1 e fallback para recursos clássicos.
* Mostra no BO o erro real devolvido pelo Azure, incluindo credenciais, deployment, endpoint ou versão da API.
* Adiciona campo opcional Azure API Version e mantém compatibilidade com a configuração existente.
* Remove o limite de 40 lojas do diagnóstico do Briefing AI Copilot.
* Analisa todos os nomes de loja importados e mostra totais detetados, mapeados, pendentes, com candidatos e sem correspondência provável.
* Completa sugestões de associação com um varrimento integral às lojas reais do projeto, mantendo validação humana e limiar de 85%.

= 2.2.115 =
* Novo Briefing AI Copilot integrado com o fornecedor de IA já configurado no FieldFlow.
* Deteção inteligente da estrutura do Excel, semana, ano, insígnias e qualidade da importação.
* Resumos automáticos para equipa, cliente e cada insígnia, guardados no briefing para abertura rápida na app.
* Comparação automática com o briefing anterior, destacando itens novos, alterados e removidos.
* Auditoria antes da publicação, com score de qualidade e alertas sobre lojas, preços, datas e referências.
* Sugestões de associação entre nomes externos e lojas FieldFlow, limitadas a IDs reais e aplicáveis automaticamente apenas acima de 85% de confiança.
* Importador mais tolerante a alterações nos nomes das folhas do Excel.

= 2.2.114 =
* Restaura os briefings publicados aos merchandisers com acesso explícito ao projeto, sem contabilizar contas do cliente.
* Reconstrói automaticamente destinatários de briefings já publicados e adiciona recuperação segura na app.
* Permite eliminar briefings criados diretamente na listagem e no detalhe do BO.
* Corrige submissões no portal do cliente através da resolução de contexto por binding, rota, paragem e loja.
* Repara o contexto de submissões antigas e aplica o âmbito autorizado nas consultas e exportações do portal.

= 2.2.113 =
* Corrige os destinatários dos briefings para considerar apenas utilizadores operacionais com acesso efetivo ao projeto.
* Remove contas associadas apenas ao cliente e perfis do portal das métricas de leitura, visualização e pendentes.
* Reconstrói automaticamente os destinatários dos briefings já publicados ao instalar a atualização, preservando leituras e confirmações válidas.

= 2.2.112 =
* Corrige o âmbito do portal do cliente para apresentar briefings publicados e harmoniza cores/hover com o tema FieldFlow.
* Sincroniza os briefings com os filtros de cliente e campanha e aplica sempre o âmbito autorizado do utilizador.

= 2.2.111 =
* Novo layout por abas no Briefing Hub da app operativa e do portal do cliente.
* Resumo inicial com indicadores, prioridades essenciais e navegação rápida.
* Conteúdo separado em Prioridades, Promoções, Lojas, VAPs, Share Space, Cardex e Evidência.
* Insígnias organizadas em painéis recolhíveis para reduzir o comprimento do briefing.
* Abas horizontais adaptadas a telemóvel e tablet, com contadores de conteúdo.
* Pesquisa do cardex, confirmação de leitura e prova de comunicação preservadas.

= 2.2.110 =
* Novo Weekly Briefing Hub no BO, na app operativa e no portal do cliente.
* Importação repetível do Excel semanal Pernod, com deteção de semana, validade, promoções, VAPs, espaços, Share Space, cardex e sortido por loja.
* Mapeamento permanente entre nomes externos do Excel e lojas existentes no FieldFlow, reutilizado nas semanas seguintes.
* Conteúdo da equipa filtrado por campanha, insígnia, rotas e lojas atribuídas ao utilizador autenticado.
* Confirmação de leitura, registo de visualizações e prova individual de comunicação para o cliente.
* Versionamento semanal, publicação, arquivo e exportação CSV de evidências exclusivamente no BO.

= 2.2.109 =
* Nova aba Despesas na app operativa.
* Leitura de QR fiscal por fotografia ou câmara, com extração automática do total e dados do documento.
* Histórico pessoal filtrado e paginado, sempre limitado ao utilizador autenticado.
* Comprovativos guardados em arquivo privado com acesso autenticado.
* Novo BO de Despesas de Operação, com validação, filtros, possíveis duplicados, exportação CSV e pacote ZIP com comprovativos.

= 2.2.108 =
* Cardex associados manualmente passam a aparecer apenas nas lojas selecionadas.
* Frentes respeita a associacao do Cardex a loja e o sortido produto a produto.
* Sugestao de encomenda respeita a associacao manual mesmo sem sortido CSV.
* Auto-deteccao por insignia deixa de contornar associacoes explicitas de lojas.
* Perguntas obrigatorias de Cardex nao bloqueiam lojas onde nao se aplicam.

= 2.2.107 =
* Categorias e produtos ordenados alfabeticamente nas perguntas de frentes e sugestão de encomenda.
* Histórico de frentes reconciliado pela referência ou nome, mesmo quando a categoria mudou.
* Recuperação de histórico por designação quando uma pergunta foi recriada com uma nova chave interna.
* Avisos de histórico mais claros, distinguindo o registo geral da loja do histórico específico de cada pergunta.
* Colunas antes e depois alinhadas quando coexistem produtos antigos e novos.

= 2.2.106 =
* Novo tipo de pergunta Sugestão de encomenda, com loja, data e vendedor preenchidos automaticamente.
* Cardex enriquecido com código, tipo, capacidade e unidades por caixa.
* Sortido produto a produto por loja, importável por CSV.
* Cálculo automático de unidades e integração em BO, portal cliente, relatórios, Analytics e exportações.
* Validação dos produtos no servidor para proteger a integridade dos dados submetidos.

= 2.2.105 =
* Categorias de produto dentro de cada cardex por insígnia.
* Formulários, histórico, relatórios, Analytics e exportações atualizados para categoria, referência e produto.
* Compatibilidade preservada com cardex e CSV anteriores.

= 2.2.0 =
* Hardening comercial e de segurança.
* Licenciamento remoto como predefinição.
* Rejeição de chaves locais inválidas.
* Permissões do endpoint de IA restringidas.
* Limpeza de logging residual.

= 2.2.1 =
* Integração remota Azure atualizada.
* Assinatura HMAC alinhada com a Azure Function.
* API base remota preenchida por defeito.
