# Arquitetura

## Componentes

### Aplicação principal
CakePHP 5 executando no servidor interno.

### PostgreSQL
Armazena:
- usuários;
- setores;
- solicitações;
- apontamentos;
- anexos;
- status/histórico;
- auditoria.

### SQL Server / TOTVS
Usado como fonte externa somente leitura.

## Camadas sugeridas

### Controller
Recebe requisição, autoriza, chama serviço, retorna View/JSON.

### Table/Entity
Persistência e validação estrutural.

### Service
Lógica de negócio que ultrapassa uma simples gravação.

Serviços sugeridos:
- `RequestNumberService`
- `ServiceRequestWorkflowService`
- `TotvsAssetsService`
- `AuditService`

## Fluxo de criação
1. usuário autenticado abre Nova Solicitação;
2. autocomplete consulta setor interno;
3. autocomplete consulta Bem no TOTVS;
4. usuário informa descrição/apontamentos;
5. backend valida tudo novamente;
6. grava solicitação + itens em transação;
7. cria evento de auditoria;
8. retorna número interno.

## Fluxo PCM
1. lista `AGUARDANDO_PCM`;
2. PCM abre detalhe;
3. assume;
4. sistema altera para `EM_ANALISE` e audita;
5. PCM abre OS manualmente no TOTVS;
6. retorna ao sistema e informa número;
7. sistema registra número, usuário, data e muda para `OS_ABERTA`.
