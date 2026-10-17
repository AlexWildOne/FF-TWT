# 9CT | Confirmação Segura de Eliminações nos Formulários | FieldFlow 2.2.617

Sucessão: `2.2.616 -> 2.2.617`.

Contrato: `fieldflow-form-deletion-confirmation-9ct-v1`.

## O que muda

As cinco ações destrutivas explícitas do formulário passam pelo mesmo diálogo de confirmação com duas escolhas: «Manter, não eliminar», foco inicial, e «Sim, ...», decisão positiva intencional.

- Remover produto/referência do Cardex diretamente na matriz de frentes. A linha só é removida se o utilizador confirmar e ainda pertencer ao formulário.
- Eliminar uma fotografia nova ou ficheiro da pré-visualização. A confirmação apresenta o nome; depois, verifica se o mesmo ficheiro continua no mesmo índice.
- Remover uma linha de anexos com conteúdo. Se estiver vazia, o controlo só fecha a linha vazia, sem tocar em ficheiros.
- Eliminar fotografia já persistida. Continua apenas marcada para exclusão até o reporte ser guardado; «Anular» reverte sem nova confirmação.
- Descartar rascunho do dispositivo. Cancela antes de chamar `clearDraft()` e só confirma se o formulário continua no DOM.

## Proteções de usabilidade

- Um único modal acessível `role=alertdialog`, com título/descritivo, `aria-modal`, foco inicial no botão de manter, navegação Tab e Escape, restaurando o foco sem scroll.
- Cancelar, fechar tocando no fundo, suspensão da WebView e `pagehide` nunca executam a eliminação. Cliques adicionais durante a confirmação são rejeitados.
- Diálogo independente do componente nativo `window.confirm`, com CSS legível e botões grandes em Android/iOS WebView.
- Sem alteração ao sistema de rascunhos, recuperação de media, formulário React, base de dados, REST, catálogo, IA ou quotas de utilização.

## Testes recomendados na app real antes da certificação

1. Fotografar duas vezes, tocar «Eliminar» numa fotografia, cancelar e confirmar: nunca mexer na outra. Repetir com várias fotografias da galeria.
2. Abrir Cardex, editar e tocar «Remover»: cancelar mantém a linha e o valor; confirmar remove só a linha pretendida e mantém o scroll.
3. Editar um reporte com fotografia antiga: cancelar não altera; confirmar marca para eliminação; «Anular» recupera; só guarda ao enviar o reporte.
4. Descartar rascunho com texto e foto, cancelar preserva ambos. Testar confirmação, suspender a app e voltar.
5. Verificar que tocar duas vezes seguidas não desencadeia duas eliminações; experimentar Escape e navegação por teclado no browser.
6. Com orçamento IA a zero, câmara IA continua bloqueada e contagem manual funciona; testar uma leitura autorizada sem introduzir chamadas IA adicionais.
7. Revalidar cadeia 6B, testar fecho Fase 6 e certificar Stable somente depois da instalação pelo updater privado.

Candidata a certificação; não instalar por extração manual nem declarar Stable antes do fluxo obrigatório.
