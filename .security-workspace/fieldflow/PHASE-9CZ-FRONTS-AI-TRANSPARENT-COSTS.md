# FieldFlow 9CZ, custos transparentes por modelo e leitura

Contract: `fieldflow-fronts-ai-usage-finops-9cz-v1`.
Release: `2.2.622 -> 2.2.623`.

## Propósito

Os 5 € reservados por cinco testes na 2.2.622 não são o valor faturado pela Azure.
Um orçamento em EUR continua a proteger as chamadas, enquanto o custo por tokens
separado permite estimar gastos e comparar com o Azure Cost Management.

## Funcionamento

* Um pedido de fotografia autorizado continua a fazer uma só chamada de modelo.
* O motor já devolve `usage.prompt_tokens` / `usage.completion_tokens`, incluindo os
  tokens contabilizados pelo modelo de visão. O FieldFlow guarda estes números
  com origem `provider`, sem os substituir por heurísticas de comprimento de texto.
* O gestor configura tarifas **EUR por milhão de tokens de entrada e saída**
  para o provider e deployment atuais em Cardex e Catálogo, Leitura por câmara.
  Não há preço do modelo assumido por defeito, nem conversão FX automática.
* A estimativa de custo é calculada a partir de tokens reais * tarifa introduzida;
  permanece uma estimativa, nunca uma fatura da Microsoft. Em ausência de tarifa,
  a leitura fica `tariff_missing`, e sem tokens fornecidos fica `unmetered`.
* O registo central de utilização inclui os tokens e o modelo efetivamente utilizado,
  a tarifa da altura, o estado da medição, o cliente/projeto e o log. O histórico
  da loja guarda um apontador ao log de IA, tokens e custo estimado quando existe.
* O Backoffice apresenta em separado reservas fixas e custo medido/estimado,
  com totais do mês por cliente e sinalização de leituras antigas e incompletas.
* Não há retrocálculo dos custos históricos, que usavam tarifas genéricas.
* Não são guardados os prompts, fotografias, chaves, recibos da Azure nem dados
  comerciais de prateleiras no registo de custos.

## Limites e segurança

A reserva contabiliza também timeouts, mantém bloqueio inicial em 0 EUR,
limites diários/mensais e controlo de duplicados. NÃO é libertada ou convertida
em cobrança calculada automaticamente. A proteção usa o orçamento conservador
até existir calibração validada com os dados reais da Azure.

A redução de custos do Azure SQL S0 pertence à infraestrutura e **não** integra
os custos de IA por fotografia.

## Verificação

`php tools/test-fronts-cost-metering-9cz.php`,
`php tools/test-fronts-ai-runtime-metering-9cz.php` (provider Azure simulado),
os testes históricos 9CS, 9CU,
9CW, 9CX, 9CY, lint PHP, frontend 39 testes, Package Validator 2.2.622
(do servidor anterior) e 2.2.623 (do novo pacote), e manifesto SHA-256.
O upgrade só passa a Stable depois da cadeia 6B, fecho Fase 6 e certificação
no servidor. Alteração sem migração de schema.
