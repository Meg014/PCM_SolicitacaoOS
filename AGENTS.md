# AGENTS.md — Solicitação de OS

## Objetivo
Construir um sistema interno simples, humano e responsivo para substituir o formulário em papel de solicitação de serviços e reduzir erros na abertura de OS no TOTVS.

## Regra principal para economia de tokens
Trabalhe de forma incremental e objetiva.

1. Leia primeiro apenas `PROJECT_SPEC.md` e `CODEX_TASKS.md`.
2. Só abra outros arquivos quando a tarefa atual exigir.
3. Não faça varredura completa do repositório sem necessidade.
4. Não releia arquivos que não mudaram.
5. Use buscas direcionadas (`rg`, `find` com caminho específico) em vez de listar tudo.
6. Faça uma fase por vez. Não tente implementar o sistema inteiro em um único passo.
7. Antes de alterar, identifique os 2–6 arquivos realmente necessários.
8. Faça alterações em lote quando forem relacionadas.
9. Rode apenas testes relevantes para a parte alterada; suíte completa só quando necessário.
10. Nas respostas, seja curto: resumo do que mudou, arquivos principais, teste executado e próximo passo.
11. Não repita requisitos já documentados em `PROJECT_SPEC.md`.
12. Se faltar informação não crítica, escolha a opção mais simples e registre a decisão em `docs/DECISIONS.md`.
13. Só pergunte ao usuário quando a decisão bloquear o desenvolvimento ou tiver impacto funcional relevante.

## Stack obrigatória
- PHP 8.3+
- CakePHP 5
- PostgreSQL para os dados do sistema
- SQL Server/TOTVS somente leitura para consulta de Bens e, futuramente, validação de OS
- HTML/CSS/JS simples; preferir recursos nativos do CakePHP
- Interface responsiva para celular, tablet e desktop
- Fuso exibido: `America/Sao_Paulo`

## Separação de bancos
- PostgreSQL = banco oficial deste sistema.
- SQL Server/TOTVS = fonte externa de consulta, nunca gravar nele por este projeto.
- Nunca misturar tabelas do sistema com tabelas do TOTVS.
- Nunca executar `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP` ou procedure de escrita no TOTVS.

## Princípios de arquitetura
- Simples antes de sofisticado.
- Controllers finos.
- Regras de negócio em Services quando houver lógica real.
- Entidades e Tables com validações claras.
- Consultas ao TOTVS encapsuladas em um serviço/repositório específico.
- Histórico/auditoria não pode depender apenas de logs de aplicação.
- Não excluir solicitações fisicamente; usar estados/cancelamento quando necessário.
- Toda alteração importante deve ser rastreável por usuário e data/hora.

## UX / Design
O sistema deve parecer uma ferramenta de trabalho, não um painel genérico cheio de efeitos.

- Layout limpo e claro.
- Poucas cores; usar cor principalmente para status e ações.
- Muito espaço em branco e tipografia legível.
- Rótulos em português simples.
- Evitar excesso de cards, gradientes, sombras e ícones decorativos.
- Botões devem dizer exatamente o que fazem: `Enviar solicitação`, `Assumir solicitação`, `Vincular OS TOTVS`.
- Priorizar celular na tela de nova solicitação.
- Campos grandes, fáceis de tocar.
- Autocomplete de Bem e Setor deve funcionar por teclado e toque.
- Mensagens de erro devem ser humanas: explicar o que falta e como corrigir.
- Nunca expor códigos internos ao usuário se não ajudarem no trabalho.

## Regras funcionais essenciais
- Solicitante vem do usuário logado.
- Bem/Equipamento deve ser selecionado a partir do TOTVS.
- Setor deve ser selecionado a partir do cadastro interno do PCM.
- Uma solicitação pode ter vários apontamentos/itens.
- Número de OS TOTVS não existe na criação da solicitação.
- PCM vincula o número da OS após abri-la no TOTVS.
- Solicitante acompanha apenas suas solicitações.
- Gestor/Encarregado tem visão geral e também pode solicitar.
- PCM/Admin tem visão total, tratamento das solicitações e cadastros.
- Guardar histórico completo de status e ações.
- Gerar número interno próprio da solicitação, independente do TOTVS.

## Perfis
Use estes nomes estáveis no código:
- `ADMIN_PCM`
- `GESTOR`
- `SOLICITANTE`

Não espalhar strings de perfil pelo código. Centralizar em enum/constantes.

## Estados iniciais
Use estes estados estáveis:
- `AGUARDANDO_PCM`
- `EM_ANALISE`
- `OS_ABERTA`
- `CANCELADA`

Não criar novos estados sem necessidade real.

## Segurança
- Autenticação obrigatória em todas as telas privadas.
- Autorização por perfil em cada ação sensível.
- CSRF habilitado.
- Validar uploads por tipo, tamanho e extensão.
- Escapar saída HTML.
- Não confiar em IDs enviados pelo cliente.
- Conexão TOTVS deve usar credencial somente leitura.
- Segredos apenas em variáveis de ambiente/config local não versionado.

## Banco e auditoria
- PostgreSQL usa migrations.
- Chaves estrangeiras e índices onde fizer sentido.
- Datas em UTC no banco; converter para `America/Sao_Paulo` na apresentação.
- Auditoria mínima: criação, edição relevante, assumir solicitação, mudança de status, vínculo da OS, cancelamento.

## Critérios para terminar uma tarefa
Uma tarefa só está pronta quando:
- código está consistente com o projeto;
- validações principais existem;
- autorização foi considerada;
- erro básico foi tratado;
- teste relevante passou ou foi documentado por que não foi possível executar;
- não houve alteração desnecessária fora do escopo.

## Como responder ao usuário
Formato preferido:

`Feito: <resumo curto>`

`Arquivos principais: <lista curta>`

`Teste: <resultado>`

`Próximo: <próxima tarefa sugerida>`

Sem explicações longas, a menos que o usuário peça.
