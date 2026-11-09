# PROJECT_SPEC — Sistema de Solicitação de OS

## 1. Problema
Hoje a solicitação de serviços é feita em papel. Isso gera perda de documentos, pouca rastreabilidade, digitação incorreta de equipamentos e dificuldade para saber quais solicitações já viraram OS no TOTVS.

## 2. Objetivo
Criar um sistema web interno para registrar, acompanhar e auditar solicitações de serviço antes da abertura da OS no TOTVS.

O sistema NÃO abre a OS no TOTVS nesta primeira versão. O PCM continua abrindo a OS no TOTVS e depois informa o número gerado no sistema de solicitação.

## 3. Perfis

- `MANTENEDOR`: cria, acompanha suas solicitações e atua no acompanhamento compartilhado existente.
- `PCM`: visão geral, fila, relatórios operacionais, vínculo da O.S. e cancelamento com motivo; sem administração de cadastros e usuários.
- `ADMIN_PCM`: acesso completo às funções administrativas existentes.

Matriz e migração: `docs/ROLES_PERMISSIONS.md`.

## 4. Nova solicitação
Campos da solicitação:
- número interno automático;
- solicitante automático pelo login;
- bem/equipamento selecionado por autocomplete a partir do TOTVS;
- setor selecionado por autocomplete a partir do cadastro interno;
- tipo de manutenção;
- ação e item/componente obrigatórios, com descrição final montada no servidor;
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
Uma solicitação pode ter vários apontamentos, exibidos em grade compacta: Funcionário, Data, Hora início, Hora fim, Serviço realizado (texto livre) e Ações (+/−). No celular a linha se reorganiza sem card individual.

Funcionário é selecionado por nome/matrícula no TOTVS, somente nas funções locais ativas e situações vazio/espaço ou F. Seleção revalidada ao salvar; matrícula, nome, código e descrição da função ficam em snapshot local e na auditoria. Não importar funcionários. Usar exclusivamente RA_MAT, RA_NOME, RA_CODFUNC e RA_SITFOLH.

Apontamento inicial vazio é opcional conforme o fluxo vigente. Qualquer linha adicionada/preenchida exige funcionário, data, início, fim e serviço. Até 50 linhas, com ordem, autoria e data de registro; preservar apontamentos anteriores e os fluxos homologados.

Ações de serviço e Itens/Componentes são cadastros locais administrados apenas por ADMIN_PCM. Somente a descrição da nova solicitação exige ação e componente ativos; os apontamentos mantêm Serviço realizado como texto livre obrigatório, sem vínculo obrigatório com esses cadastros; description e os nomes/IDs usados ficam em snapshot histórico, sem reescrever registros antigos ao alterar os cadastros. Exemplo: Soldar + Chapa → Soldar chapa; Limpar + Geral → Limpeza geral.

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

## 10. Dashboard PCM
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

## 19. Revisão vigente — acompanhamento automático
- Sessão expira em 20 minutos de inatividade humana; polling da fila não renova o prazo.
- Mão de obra é select obrigatório de cadastros ativos, separado do setor do funcionário.
- Vínculo da O.S. continua manual. Comando sync_totvs_orders consulta somente O.S. vinculadas em acompanhamento ativo, por SELECT limitado em lote, para cron recomendado a cada 2 minutos (agendamento manual).
- dbo.STJ010 identifica O.S. por TJ_ORDEM/TJ_FILIAL/Bem TJ_CODBEM. Referência: L/N aberta, L/S encerrada em TJ_SITUACA/TJ_TERMINO. Ausência, divergência, ambiguidade e status desconhecido nunca encerram o pedido.
- Somente abertura permanece Atendida; os outros fluxos acompanham o TOTVS, aberta como Serviço em andamento e encerrada como Encerrada, com auditoria Sistema e data de detecção. Confirmação manual local de encerramento removida. Este sistema nunca encerra ou escreve no TOTVS.
- Número da O.S. clicável em Relatórios para consulta autorizada em tempo real. Fotos: câmera/galeria, 5 no total, até 15 MB cada, com armazenamento privado e validações de conteúdo existentes.
