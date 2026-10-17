# FieldFlow 2.2.642 | Fase 4C: Admin Hotspots & Manifest Safety 9DH

Contrato: `fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1`.

## Incidente 2.2.641 e remedio estrutural

A 2.2.641 instalada e certificada exigiu reparar `production-manifest.json`:
o inventario/hashes de 1.623 ficheiros estavam corretos, mas `key_files`
referenciava quatro bundles 2.2.640 inexistentes. Foi corrigido localmente,
sem republicar a identidade SHA original de 2.2.641.

Nesta release, `tools/build-production-manifest-9dh.py` escreve o manifesto
apenas com ficheiros presentes. Se um key_file nao existe, so remapeia
bundles versionados padrao quando o bundle da release atual existe;
outros ficheiros ausentes causam erro, nunca remocao silenciosa. A operacao
`--verify` recusa backups/symlinks/ficheiros extra, duplicados e hashes
incorrectos; `--write --zip` verifica novamente **cada ficheiro dentro do ZIP**.
O `ReleasePackageValidator` rejeita antecipadamente qualquer candidato >=2.2.642
com key_files obsoletos, mesmo que o inventario e o agregado SHA-256 sejam validos.

## Admin Hotspots e performance

Dados existentes da telemetria `LoadReadiness` (janela ate 168h). A aba
Performance e Carga passa a incluir P95 real, consultas P95, amostras e
identidade sanitizada das paginas administrativas mais lentas. A consulta
e indexada e limitada a 2.500 amostras e **so ocorre ao abrir o separador**.
N menos de 5 amostras a pagina fica marcada AMOSTRA CURTA. A pagina de
Seguranca e Observabilidade deixou de executar tres calculos de contadores
de resumo em todos os separadores que nao utilizam esses valores.
Nao altera endpoints, regras de rotas, calculos de distancia, formularios,
clientes, caches, politicas ou qualquer servico pago. O objetivo inicial
nao e prometer melhora instantanea do P95, mas localizar e atacar o pior
fluxo documentado na versao seguinte, com comparacao antes/depois.

## QA e seguranca

- Validar com o Release Manager **2.2.641 certificado**, sem republicar a 2.2.641.
- Verificar `key_files` e todos os hashes no ZIP canonico.
- Confirmar apenas o painel Fase 4C, na aba Performance e Carga.
- Preservar sem alteracoes os JS/CSS 9DE e 9DF, Portais, e operacoes de terreno.
- Revalidar 6B e Fase 6, depois certificar Stable 2.2.642.
