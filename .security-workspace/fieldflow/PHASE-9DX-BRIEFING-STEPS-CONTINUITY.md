# 2.2.658 | Briefing Steps Continuity 9DX

**Contrato:** `fieldflow-briefing-steps-continuity-9dx-v1`  
**Sucessão:** 2.2.657 instalada e certificada -> 2.2.658 candidata.  
**Objetivo:** categorizar perguntas criadas manualmente ou por IA no Briefing e apresentá-las como Steps no formulário de visita, sem apagar nem converter respostas anteriores.

## Utilização

No Backoffice, abrir o Briefing e o gestor de perguntas. Criar os Steps por título, descrição e ordem, por exemplo **Implementação**, **Frentes e Concorrência**, **Evidências**. Associar cada pergunta ao respetivo Step no editor da pergunta ou na tabela de atribuição rápida e guardar. A IA pode sugerir novas perguntas com Step. Não existe migração de perguntas antigas: sem Step associado o comportamento atual é mantido.

## Integridade e compatibilidade

- As definições dos Steps vivem em opções WordPress por `briefing_id`, sob `fieldflow_briefing_steps_9dx_*`. Não existe alteração ao esquema da base de dados.
- O vínculo de uma pergunta a um Step vive dentro do JSON de regras de visibilidade da própria pergunta, na chave `briefing_step_key`. A identidade `question_key`, `id`, `briefing_id`, `location_id`, cliente, insígnia, cardex e stocks não são alterados.
- Os dados e anexos de submissões antigas, perguntas já respondidas, relatórios e histórico por visita permanecem na mesma estrutura. Mudar o nome ou a ordem de um Step só modifica a apresentação no futuro.
- A expansão por loja continua a selecionar exclusivamente as perguntas do PDV da visita. Steps vazios para aquela loja são ocultados.
- No formulário já configurado como wizard, as categorias entram no lugar do bloco de Briefing, preservando os restantes Steps. No formulário de página única, as categorias são opt-in e a apresentação é convertida em wizard apenas quando há pelo menos uma associação válida de pergunta a Step.
- Perguntas não categorizadas do Briefing aparecem em **Outras perguntas do briefing** se houver outras com Steps; sem qualquer Step ativo continuam no fluxo anterior.
- O mesmo resolvedor central é utilizado por formulário web, operação móvel e restituição de formulário. Sem mudança nas APIs, dados históricos ou obrigatoriedade de respostas.

## Ensaios obrigatórios antes de certificar em produção

1. Confirmar que o **Release Manager** aceita sucessão direta da versão Stable instalada **2.2.657 -> 2.2.658** e o manifesto SHA-256.
2. Num Briefing publicado, criar dois Steps, atribuir perguntas de diferentes lojas e confirmar que cada PDV vê apenas as suas perguntas.
3. Abrir um formulário com Steps existentes e confirmar ordem correta, perguntas, fotos, Frentes e Sugestão de Encomenda.
4. Abrir um formulário sem Steps definidos e confirmar fluxo anterior idêntico.
5. Reabrir uma visita antiga com respostas e fotografias: nenhuma resposta deve desaparecer ou trocar de loja, mesmo depois de renomear/reordenar um Step.
6. Validar um formulário mobile (Android/iOS) e verificar que a submissão guarda os mesmos `question_key` e `answers`.
7. Gerar os relatórios e confirmar que as perguntas do Briefing continuam presentes por visita e por loja.
8. Certificar Fase 6 Stable após verificações reais, nunca presumir certificação pela execução de testes locais.

## Recuperação e rollback

Reinstalar a ZIP Stable **2.2.657** certificada caso exista regressão. As novas opções dos Steps permanecem inertes em 2.2.657, sem modificar as respostas, e podem voltar a ser utilizadas ao reinstalar uma versão sucessora compatível. Guardar cópia da ZIP Stable e fazer cópia de segurança antes da publicação.

## Validação técnica

`php tools/test-briefing-steps-9dx.php`  
`php tools/test-release-gate-runtime-9dx.php`  
`python3 tools/build-production-manifest-9dh.py --root . --verify`
