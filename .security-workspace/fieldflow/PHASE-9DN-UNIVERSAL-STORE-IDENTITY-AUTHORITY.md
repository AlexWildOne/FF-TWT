# 2.2.648 | Universal Store Identity Authority (9DN)

Contrato: `fieldflow-universal-store-identity-authority-9dn-v1`.

## Regra de identificação

A nova autoridade comum `src/Stores/StoreIdentityAuthority.php` reutiliza o motor estrito dos stocks 9DM.
A insígnia tem de ser identificada **por evidência comercial da própria loja**, não pela localidade,
código numérico duplicado, histórico de alias, nome semelhante, inferência por AI ou seleção de merchandiser.

- **Sonae**: CNT = Continente; MDL = Continente Modelo; CBD = Continente Bom Dia.
  A família Sonae não autoriza confundir CNT, MDL e CBD quando existe subtipo.
- **Pingo Doce**: PD / Pingo Doce.
- **Cash & Carry** independentes: Arcol e M. Cunha/Mcunha, Makro e Recheio.
- `M` isolado no briefing pode identificar merchandiser; nunca equivale a `M. Cunha`.
- Identidade em falta ou conflituosa: **por associar / por rever**, sem ligação automática.

## Entradas abrangidas

1. Importação semanal de **briefings**, sugestões de loja, aliases antigos e mapeamento manual;
2. Motor geral e Centro Universal de **Mapping**, incluindo confirmação de alias e sugestões;
3. **Shopping**, seleção de lojas, importações e históricos de correspondência;
4. **Cardex**, seleção por insígnia, inclusive vínculos antigos por PDV;
5. **Implementações**, atribuições manuais e visualização operacional;
6. **CSV de lojas/PDV**, impedindo renomeação silenciosa para outra família/subtipo;
7. **Stocks**: mantém a proteção anterior 9DM sem alterar dados já guardados.

## Segurança, continuidade e âmbito

- Registos históricos incompatíveis são mantidos para auditoria e correção manual.
- O painel de briefings sinaliza `Por rever` e não reutiliza associações inválidas para o Operativo.
- Centro de Mapping: sincroniza referências históricas incompatíveis apenas como projeções pendentes, guardando ID histórico sem alterar os aliases, stocks ou cardex originais; desfazer uma associação incompatível é bloqueado.
- Cardex: não aceita uma associação sem insígnia comprovada e considera até 20 vínculos ordenados antes de escolher um cardex válido, evitando que o primeiro vínculo antigo esconda os restantes.
- Leitura do Backoffice não elimina dados. Sem migrações automáticas nem alteração de atribuições de rota.
- Dock 9DF, captura de formulários, rotas, tabelas e Release Manager não mudam de comportamento.
- O Gate de runtime 9DL corrigido e o Gate 9DM permanecem funcionais e testados no 9DN.

## Testes

- `php tools/test-universal-store-identity-9dn.php`: identidades e subtipos, códigos numéricos duplicados,
  perfil genérico sem prova, Shopping, CSV e Cash & Carry.
- `php tools/test-cardex-store-identity-9dn.php`: rejeita vínculos PD/CBD e cardex sem identificação, escolhendo o CNT válido entre vários vínculos.
- `php tools/test-release-gate-runtime-9dn.php`: Gate real das versões 9DL, 9DM e 9DN.
- Testes gerais de código, TypeScript, frontend, manifesto e preflight da 2.2.647.
- Só publicar pelo Release Manager após validação N->N+1, manifesto fechado e SHA256 do ZIP.

Esta fase não altera os dados de produção por conta própria. A correspondência real com PDV e os
rascunhos importados devem ser validados no servidor antes da publicação operacional.
