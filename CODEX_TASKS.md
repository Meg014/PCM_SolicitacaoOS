# CODEX_TASKS — Ordem recomendada

Execute UMA fase por vez.

## Fase 0 — Base do projeto
- criar/prosseguir com CakePHP 5;
- configurar PostgreSQL;
- configurar timezone;
- criar layout base responsivo;
- preparar autenticação simples;
- criar enum/constantes de perfil e status.

## Fase 1 — Usuários e autorização
- login/logout;
- perfis `ADMIN_PCM`, `GESTOR`, `SOLICITANTE`;
- middleware/policy ou camada equivalente de autorização;
- CRUD de usuários apenas para ADMIN_PCM;
- seed de usuário admin para desenvolvimento.

## Fase 2 — Setores
- migration;
- entidade/tabela;
- CRUD para ADMIN_PCM;
- autocomplete/listagem de ativos.

## Fase 3 — Integração TOTVS: Bens
- conexão SQL Server somente leitura;
- `TotvsAssetsService` ou nome equivalente;
- busca por código/descrição com limite;
- endpoint JSON autenticado para autocomplete;
- timeout e tratamento de indisponibilidade;
- nunca gravar no TOTVS.

## Fase 4 — Solicitações
- migrations de solicitações e apontamentos;
- formulário Nova Solicitação;
- solicitante automático;
- seleção obrigatória de Bem válido;
- setor válido;
- vários apontamentos;
- número interno `SOL-AAAA-XXXXXX`;
- status inicial `AGUARDANDO_PCM`.

## Fase 5 — Minhas solicitações
- listagem do solicitante;
- filtros básicos;
- detalhe;
- número da OS TOTVS quando disponível.

## Fase 6 — Fila PCM / Dashboard
- cards de status;
- tabela/fila ordenada por data;
- somente solicitações/contadores ativos, sem filtros na página inicial;
- filtros e histórico geral em Relatórios;
- vínculo direto de O.S. pelo PCM, sem etapa de assumir;
- atualização leve por polling; `EM_ANALISE` preservado apenas no histórico legado.

## Fase 7 — Vincular OS TOTVS
- campo número da OS;
- confirmação;
- status `OS_ABERTA`;
- auditoria;
- mostrar ao solicitante.

## Fase 8 — Cancelamento e histórico
- cancelar com motivo obrigatório;
- timeline de histórico;
- auditoria completa dos eventos principais.

## Fase 9 — Anexos
- upload de imagens/documentos;
- validação de tamanho e tipo;
- armazenamento organizado;
- visualização no detalhe.

## Fase 10 — Indicadores
- por solicitante;
- por setor;
- por equipamento;
- por tipo;
- tempo médio até OS aberta.

## Fase 11 — Polimento
- responsividade real em 360px, tablet e desktop;
- textos e mensagens humanas;
- estados vazios;
- loading no autocomplete;
- acessibilidade básica;
- testes principais.

## Regra de parada
Ao terminar cada fase, pare e responda de forma curta. Não comece a próxima sem solicitação explícita.
