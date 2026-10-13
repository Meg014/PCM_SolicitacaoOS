# PROJECT_SPEC — Sistema de Solicitação de OS

## 1. Problema
Hoje a solicitação de serviços é feita em papel. Isso gera perda de documentos, pouca rastreabilidade, digitação incorreta de equipamentos e dificuldade para saber quais solicitações já viraram OS no TOTVS.

## 2. Objetivo
Criar um sistema web interno para registrar, acompanhar e auditar solicitações de serviço antes da abertura da OS no TOTVS.

O sistema NÃO abre a OS no TOTVS nesta primeira versão. O PCM continua abrindo a OS no TOTVS e depois informa o número gerado no sistema de solicitação.

## 3. Perfis

### ADMIN_PCM
- acesso total;
- gerencia usuários;
- gerencia setores;
- vê todas as solicitações;
- trata solicitações sem etapa de assumir;
- informa número da OS TOTVS;
- cancela solicitações;
- consulta auditoria e indicadores.

### GESTOR
- cria solicitações;
- visualiza todas as solicitações permitidas pelo escopo da empresa nesta primeira versão;
- acessa dashboard geral;
- consulta detalhes e número da OS vinculada;
- não administra usuários;
- não altera cadastros estruturais;
- por padrão não vincula OS TOTVS.

### SOLICITANTE
- cria solicitações;
- vê somente as próprias solicitações;
- vê detalhes e status;
- após vínculo, vê o número da OS TOTVS.

## 4. Nova solicitação
Campos da solicitação:
- número interno automático;
- solicitante automático pelo login;
- bem/equipamento selecionado por autocomplete a partir do TOTVS;
- setor selecionado por autocomplete a partir do cadastro interno;
- tipo de manutenção;
- descrição principal/problema;
- prioridade opcional;
- anexos/fotos opcionais;
- data/hora automática;
- múltiplos apontamentos/itens.

### Tipo de manutenção inicial
- PROGRAMADA
- CORRETIVA
- PREVENTIVA
- APOIO_PROCESSO
- NAO_PROGRAMADA

Pode ajustar rótulos sem mudar os códigos internos.

## 5. Bem/Equipamento
- fonte: SQL Server/TOTVS;
- somente leitura;
- pesquisa por código e descrição;
- autocomplete conforme digitação;
- usuário deve selecionar um item válido da lista;
- ao salvar, registrar no PostgreSQL o código e a descrição mostrada naquele momento.

Não manter cadastro duplicado de bens no sistema.

## 6. Setor
Cadastro interno mantido pelo PCM.
Campos mínimos:
- id;
- código curto opcional;
- nome;
- ativo/inativo;
- created_at;
- updated_at.

Na solicitação, o usuário pesquisa e seleciona apenas setores ativos.

## 7. Apontamentos
Uma solicitação pode ter vários itens/apontamentos.

Cada item contém pelo menos:
- descrição;
- ordem de exibição;
- created_at.

Na interface:
- primeiro item já visível;
- botão `+ Adicionar apontamento`;
- permitir remover item ainda não salvo;
- não exigir criação de várias solicitações para o mesmo equipamento quando os serviços pertencem ao mesmo pedido.

## 8. Status

### AGUARDANDO_PCM
Estado inicial após envio.

### EM_ANALISE
Estado legado, mantido para consulta histórica. Não é mais gerado pelo fluxo atual.
Pedidos antigos em análise são devolvidos à fila Aguardando PCM com auditoria.

### OS_ABERTA
Quando o PCM informar e confirmar o número da OS criada no TOTVS.
Registrar usuário e horário.

### CANCELADA
Requer motivo.
Registrar usuário e horário.

## 9. Número interno
Toda solicitação recebe número próprio independente do TOTVS.
Formato sugerido:
`SOL-AAAA-000001`

O número da OS TOTVS é outro campo.

## 10. Dashboard PCM/Gestor
Exibir de forma simples:
- aguardando PCM;
- em análise;
- OS abertas;
- canceladas;
- fila de solicitações recentes;
- tempo desde a solicitação;
- solicitante;
- setor;
- equipamento;
- tipo;
- ação para ver detalhes.

Filtros:
- período;
- status;
- setor;
- solicitante;
- equipamento;
- tipo de manutenção.

Indicadores futuros/prioridade média:
- solicitações por solicitante;
- solicitações por setor;
- equipamentos mais solicitados;
- tipos de manutenção;
- tempo médio entre solicitação e abertura da OS.

## 11. Área do solicitante
Página inicial simples com:
- botão `Nova solicitação`;
- contadores das próprias solicitações;
- tabela/lista das próprias solicitações;
- status claro;
- número da OS TOTVS quando existir.

## 12. Detalhe da solicitação
Mostrar:
- número interno;
- solicitante;
- data/hora;
- setor;
- equipamento e código TOTVS;
- tipo;
- prioridade;
- descrição;
- apontamentos;
- anexos;
- status atual;
- número da OS TOTVS quando existir;
- histórico em ordem cronológica.

Para PCM:
- informar OS TOTVS;
- cancelar com motivo.

Revisão operacional vigente: a página inicial exibe apenas trabalho ativo, sem filtros.
Atendidas, encerradas e canceladas ficam em Relatórios e no histórico pessoal.
O.S. TOTVS deve ser única; eventos novos guardam snapshot da identidade do usuário,
situação anterior/nova e detalhes. Dashboard atualiza automaticamente a cada 30 segundos.

## 13. Auditoria
Registrar eventos em tabela própria.
Campos mínimos:
- id;
- request_id;
- user_id;
- action;
- old_values JSONB opcional;
- new_values JSONB opcional;
- metadata JSONB opcional;
- created_at.

Eventos importantes:
- REQUEST_CREATED
- REQUEST_UPDATED
- REQUEST_ASSIGNED
- STATUS_CHANGED
- TOTVS_OS_LINKED
- REQUEST_CANCELLED
- ATTACHMENT_ADDED

## 14. Responsividade
Prioridade alta.

### Celular
- formulário em coluna única;
- campos grandes;
- botões ocupando largura útil;
- navegação simples;
- listas em cards ou tabela adaptada;
- foto/anexo acessível pelo celular.

### Tablet/Desktop
- aproveitar largura sem poluir;
- dashboard com cards pequenos e lista principal;
- filtros em linha quando houver espaço.

## 15. Estilo visual
- aparência corporativa leve;
- fundo claro;
- cabeçalho discreto;
- navegação simples;
- sem gradientes chamativos;
- bordas e sombras sutis;
- status com badge;
- texto mais importante que ícones;
- linguagem humana, evitando termos técnicos para o usuário final.

## 16. Integração TOTVS
Primeira versão:
- consulta de bens/equipamentos.

Futuro:
- validar se a OS informada existe;
- exibir dados resumidos da OS.

Toda consulta deve estar isolada em serviço de integração e ser somente leitura.

## 17. Fora do escopo da V1
- criar OS automaticamente no TOTVS;
- alterar Bem no TOTVS;
- alterar status da OS no TOTVS;
- apontamento de mão de obra no TOTVS;
- controle de execução da manutenção;
- fechamento técnico da OS.

## 18. Critério de sucesso da V1
O papel deixa de ser necessário para abrir uma solicitação, o PCM consegue acompanhar tudo em uma fila, o solicitante acompanha o andamento e o número da OS criada, e o histórico não se perde.
