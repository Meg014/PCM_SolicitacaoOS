# Decisões do projeto

Registre aqui apenas decisões que não estão claras no `PROJECT_SPEC.md`.

Formato:

## AAAA-MM-DD — Título
- Contexto:
- Decisão:
- Motivo:

## 2026-10-07 — Base e limites da Fase 0
- Decisão: usar o esqueleto oficial CakePHP 5, plugin Authentication com sessão e acesso privado por padrão.
- Login, autenticação por senha, usuários, autorização por perfil e seed ficam na Fase 1; /entrar apresenta uma mensagem de preparação.
- PostgreSQL configurado por ambiente; runtime e persistência UTC, apresentação America/Sao_Paulo.
- Não registrar datasource TOTVS até a Fase 3, quando a leitura será encapsulada.
- .env carregado da raiz; .env e app_local.php não versionados. Salt local aleatório.
- Composer resolve dependências usando PHP 8.3.0 para evitar pacotes que exijam versões superiores ao mínimo do projeto.
- Ambiente encontrado: PHP 8.2.12 sem pdo_pgsql. Não é possível validar execução em PHP 8.3+ nem conectar PostgreSQL neste ambiente.
- Testes HTTP da base não requerem banco nem executam migrations; migrations de negócio ficam para as fases correspondentes.
- Validação: composer validate --strict aprovado; sintaxe de 36 arquivos PHP aprovada; PHPUnit impedido pelo requisito PHP >=8.3 confirmado por check-platform-reqs. DebugKit não carregado para evitar dependência de banco na página inicial.

## 2026-10-07 — Fase 1: usuários, autorização e setores
- Compatibilidade PHP 8.2.12 autorizada pelo usuário; o Composer já estava configurado para esta versão.
- Cadastro de setores incluído nesta fase por solicitação explícita. Autocomplete/listagem pública de ativos continua para a Fase 2.
- Perfis são mantidos no enum UserRole, sem tabelas de permissões adicionais. Somente ADMIN_PCM administra os cadastros.
- Authentication.PrimaryKeySession guarda somente o ID e recarrega a conta ativa a cada request, aplicando alterações de perfil e desativação imediatamente.
- Usuários e setores são removidos do uso por desativação. Administrador não pode desativar/rebaixar a própria conta.
- Auditoria de cadastros tem tabela própria, transação junto ao registro e usuário/data UTC; senha e hash nunca entram nos snapshots. Seed inicial usa ator nulo por não haver usuário autenticado.
- Seed mínimo cria somente administrador em development, é seguro para reexecução e não redefine senhas existentes. Credenciais do administrador local estão em ADMIN_SEED_EMAIL/ADMIN_SEED_PASSWORD do .env ignorado pelo Git.
- Nenhum setor fictício é semeado: o PCM cadastra os setores reais.
- Conexões default e test validadas em bancos PostgreSQL separados. Migrations executadas; testes de persistência usam fixtures exclusivamente no banco test.

## 2026-10-07 — Fase 2 conforme escopo explícito do usuário
- O usuário ampliou esta fase para leitura de Bens no TOTVS, autocomplete local de setores, criação de solicitações e Minhas Solicitações. Este escopo substitui, nesta execução, a separação originalmente sugerida em CODEX_TASKS.md. Não foram implementados tratamento PCM, vínculo de OS, cancelamento, relatórios ou anexos.
- Referência consultada somente para leitura: C:/PCM/Sistemas/PCM_OrdensServico, driver ProtheusReadOnly e queries de equipamentos. Nenhum arquivo desse projeto foi alterado.
- Conexão protheus separada, variáveis PROTHEUS_DB_* iguais às da referência. Driver aceita apenas duas consultas SELECT fixas (pesquisa e confirmação); exec e SQL fora da lista são bloqueados. Conta no SQL Server também precisa ter somente SELECT.
- Campos confirmados em dbo.ST9010: T9_CODBEM, T9_NOME, T9_FILIAL, D_E_L_E_T_. A identidade do Bem é código + filial, inclusive filial compartilhada vazia. Duplicidades no mesmo par são rejeitadas no salvamento.
- Consultas sob demanda retornam no máximo 20 Bens; timeout de conexão e consulta de 5 segundos. Sem cópia de cadastro completo ou logs de consultas/credenciais. Erros externos são substituídos por mensagem humana sem encadear a exceção original.
- PostgreSQL usa service_requests, request_items e request_audits. Gravação de pedido, itens e auditoria é atômica. Revalidação do setor ativo usa FOR SHARE.
- Número SOL-AAAA-XXXXXX usa sequência do ID PostgreSQL e ano em São Paulo. A sequência é global, não reinicia anualmente e pode ter lacunas após rollback; evita colisões entre envios simultâneos.
- Solicitante, status e snapshot do Bem são definidos no servidor. Número da OS TOTVS fica nulo; sem ação de vínculo nesta fase.
- De 1 a 50 apontamentos, até 2.000 caracteres por item; descrição principal até 5.000. Tipos centralizados em MaintenanceType, status inicial pelo enum RequestStatus.
- Minhas Solicitações mostra somente os pedidos do usuário logado, em todos os perfis. Detalhe alheio é permitido para GESTOR e ADMIN_PCM; SOLICITANTE recebe 404.
- Autocomplete exige seleção, limpa o valor oculto ao editar texto e descarta respostas antigas. Debounce de 250 ms, teclado, toque, loading e mensagens acessíveis.
- Testes automatizados usam fakes/mocks da camada AssetsLookupInterface; nenhuma conexão com o TOTVS real.
- Pendência externa: PROTHEUS_DB_HOST, PROTHEUS_DB_DATABASE, PROTHEUS_DB_USERNAME e PROTHEUS_DB_PASSWORD ausentes no ambiente local. Validar rede/TLS, permissão SELECT e retorno real antes de usar a consulta em operação.

## 2026-10-07 — Fase 3 operacional conforme pedido explícito
- Escopo desta fase definido pelo usuário: dashboard, assumir, vínculo manual de OS e cancelamento/recusa. Substitui a ordem original de CODEX_TASKS.md nesta execução; Fase 4 não iniciada.
- ADMIN_PCM trata solicitações e consulta snapshots da auditoria. GESTOR tem dashboard e detalhes sem ações; SOLICITANTE mantém acesso somente aos próprios pedidos e ao histórico humano.
- Assumir só é permitido em AGUARDANDO_PCM e muda para EM_ANALISE. Vínculo manual pode partir de AGUARDANDO_PCM ou EM_ANALISE e muda para OS_ABERTA; não exige consulta da OS no TOTVS nesta fase.
- Cancelamento/recusa exige motivo de até 2.000 caracteres e é permitido apenas em estados pendentes. Solicitações OS_ABERTA/CANCELADA são terminais nesta fase; não há correção de número, reabertura, reassunção nem cancelamento de OS vinculada.
- Responsáveis e datas das ações persistidos em service_requests, com FKs. Cada ação e STATUS_CHANGED possuem registro separado na auditoria, na mesma transação da alteração.
- SELECT FOR UPDATE no PostgreSQL serializa ações concorrentes; transição é revalidada após o bloqueio. Falha na auditoria desfaz a alteração inteira.
- Dashboard prioriza pendências mais antigas. Não foi introduzido campo de prioridade. Contadores consideram setor e período e permanecem independentes do filtro de status.
- Período usa dias de São Paulo, convertidos para UTC com final exclusivo no dia seguinte. Setores inativos continuam disponíveis no filtro para consulta histórica.
- 50 testes / 230 asserções aprovados com PHP 8.2.12 e PostgreSQL test. Nenhuma operação TOTVS executada nesta fase.

## 2026-10-07 — Fase 4 conforme escopo explícito
- Escopo definido pelo usuário nesta execução: anexos de imagens na criação, histórico visual, indicadores, relatórios tabulares e acabamento responsivo. Fase 5 não iniciada.
- Fotos opcionais: até 5 por solicitação, 5 MB por arquivo e 40 megapixels por imagem; formatos JPG/JPEG, PNG e WebP. Extensão, tamanho real, MIME detectado por fileinfo e cabeçalho de imagem devem ser compatíveis. SVG, executáveis, GIF e HEIC não são aceitos.
- Fotos em storage/attachments, fora de webroot e ignoradas pelo Git. Nome aleatório de 48 caracteres hexadecimais. Banco recebe apenas metadados, autor e data UTC.
- Upload disponível somente junto à criação do próprio pedido. Fotos não são adicionadas/alteradas após criação nesta fase. Não há exclusão de anexos.
- Gravação de solicitação, itens, metadados e eventos ATTACHMENT_ADDED na mesma transação PostgreSQL. Arquivos criados são removidos se ocorrer falha; nenhuma solicitação parcial é mantida.
- Entrega GET /fotos/{id} autenticada, com mesma autorização de leitura do pedido, MIME fixo, nosniff e cache privado sem armazenamento. Usuários sem acesso recebem 404.
- Linha do tempo mostra criação, mudanças de situação, responsáveis, vínculo, cancelamento e fotos com datas em São Paulo. Snapshots técnicos continuam visíveis apenas ao ADMIN_PCM.
- Indicadores/relatórios disponíveis somente para ADMIN_PCM e GESTOR. Indicadores agrupam setor, solicitante, tipo, situação e os 10 Bens mais solicitados por código+filial; média calculada com os pedidos que possuem os_linked_at.
- Período dos indicadores considera criação do pedido; média exibida em horas. Sem dados, a média fica indisponível em vez de assumir zero.
- Relatório paginado por período, situação, setor, solicitante, Bem e busca textual. Pesquisa literal com escape de curingas, datas convertidas de São Paulo para UTC.
- Sem CSV/PDF: exportação não está prevista em PROJECT_SPEC.md ou CODEX_TASKS.md. Sem notificações ou qualquer alteração na integração TOTVS.
- Interface usa tabelas/grupos simples em vez de gráficos decorativos. Relatório mantém rolagem horizontal acessível no celular; fotos, filtros e indicadores adaptam-se à largura.
- PHP local permite upload_max_filesize=40M e post_max_size=40M, suficientes para o limite da aplicação (25 MB por pedido mais campos). Em outro servidor, configure upload_max_filesize>=5M, post_max_size>=30M e max_file_uploads>=5.
- Validação final: 60 testes / 304 asserções aprovados; 98 arquivos PHP com sintaxe válida; Composer e requisitos de plataforma aprovados em PHP 8.2.12. Testes de fotos usam pasta temporária separada. Ajustes responsivos feitos em CSS; verificação visual em aparelho real ainda não executada.

## 2026-10-07 — Identidade visual Biolasa
- Paleta baseada na referência enviada: teal #007F80, teal escuro #006566 para links, laranja #F68B45 como destaque, fundo #F5F7F8 e texto #334155. Botões com texto branco usam teal com contraste legível; foco usa laranja escuro.
- Cabeçalho identifica Biolasa Energia Renovável; login com superfície branca e destaque discreto. Sem gradientes ou efeitos adicionais.
- Rótulo de fuso alterado para Brasília; configuração/conversão interna continua America/Sao_Paulo.
- A logo anexada ao chat não está disponível como arquivo local nesta sessão. Cabeçalho está preparado para webroot/img/biolasa-logo.png; até o arquivo original existir, mostra nome da empresa em texto, sem logo recriada.
- Testes HomeController: 3 testes / 6 asserções aprovados; sintaxe dos templates alterados aprovada.

## 2026-10-07 — Login por usuário ou e-mail
- username obrigatório e único, até 60 caracteres, sem @; normalizado para minúsculas. E-mail opcional convertido a NULL quando vazio, único quando informado.
- Migration AddUsernames preenche contas existentes com parte local sanitizada do e-mail e sufixo do ID (ou usuario.ID), sem alterar hashes de senha. Usuários podem editar o nome gerado pelo cadastro PCM.
- Campo único login tenta username ou email; normaliza espaços externos e caixa antes da consulta. Conta ativa continua obrigatória e a sessão existente foi preservada.
- Auditoria de cadastro passa a incluir username. Seed mínimo usa ADMIN_SEED_USERNAME=admin por padrão, admite e-mail opcional e preserva administrador ativo já existente, sem redefinir senha.
- Validação: 64 testes / 323 asserções aprovados; sintaxe dos 14 arquivos PHP alterados aprovada; Composer válido; migration aplicada no PostgreSQL principal e de testes.

## 2026-10-07 — Revisão de acesso e UX conforme texto anexado
- Referência visual lida em PCM_OrdensServico/templates/layout/default.php e webroot/css/pcm.css, sem alterar o projeto. Tema padrão escuro (#111827, #1F2937, #182233), azul/cinza, tipografia compacta, tabelas e contadores discretos.
- Logo Biolasa completa somente no login. Cabeçalho autenticado usa PCM textual, navegação por perfil, Administração agrupada e menu adaptável ao celular.
- Início redireciona ADMIN_PCM/GESTOR diretamente ao dashboard e SOLICITANTE às próprias solicitações com resumo de situação; nenhuma tela intermediária de boas-vindas.
- Senha mínima de 6 caracteres, sem composição obrigatória ou limite máximo de 72 bytes/caracteres. Novos hashes usam Argon2id (verificado no PHP local); password_verify mantém compatibilidade com bcrypt existente.
- must_change_password tem default true. Contas existentes precisam fazer uma troca pessoal após esta migration; novas contas e redefinições PCM exigem troca antes do acesso às páginas, APIs e anexos privados.
- Alteração exige senha atual, confirmação e senha diferente da atual; usa bloqueio PostgreSQL, auditoria atômica PASSWORD_CHANGED e renovação de sessão. Perfil não permite alteração de papel/IDs pelo cliente.
- Debugger/DebugKit mascara password/current_password/new_password/confirmation e campos de credenciais. Auditoria guarda apenas indicador de mudança e flag, nunca senha/hash.
- Fuso interno preservado; interface exibe Brasília. Integração TOTVS, regras de solicitações e anexos privados preservados.
- Prévia visual com Edge headless bloqueada pela revisão automática (blocked by policy, sem razão detalhada). Verificação HTTP do login aprovada, tema escuro e logo presentes; conferência visual real em desktop/celular pendente.

## 2026-10-07 — Unidade, nomenclatura e ícone
- Unidade operacional da solicitação é independente de T9_FILIAL do Bem. Opções centralizadas no enum RequestUnit: USINA e FABRICA. Obrigatória para novos pedidos e incluída no snapshot de auditoria.
- Migration AddRequestUnit mantém unit nullable para pedidos anteriores; não presume classificação. A apresentação usa Não informada e os filtros oferecem Não informada (anteriores).
- Unidade exibida no detalhe, Minhas solicitações, fila e relatório; filtros adicionados à fila e relatório. Regras de transição e autorização preservadas.
- Autocomplete mostra somente descrição e código; filial continua oculta, enviada e validada no servidor. Nenhuma alteração na integração TOTVS.
- Interface usa Ordem de Serviço ou O.S.; códigos internos de status/auditoria permanecem estáveis.
- Ícone original existente webroot/img/icon.png usado por rel=icon e apple-touch-icon no layout compartilhado de páginas públicas e privadas. Logo completa permanece apenas no login.
- PHPUnit: 76 testes / 438 asserções aprovados, incluindo Unidade obrigatória, opções válidas, persistência/auditoria, pedidos anteriores, filtros e favicon.

## 2026-10-07 — Apontamentos da homologação e navegação PCM
- Apontamentos novos exigem executante livre (até 150 caracteres), data do serviço, hora inicial/final HH:MM e descrição. Serviços de dias diferentes usam apontamentos separados na mesma solicitação.
- Migration AddItemExecutionDetails acrescenta executor/started_at/ended_at nullable para preservar registros anteriores. Sem presumir executante ou data de execução com base na criação.
- Datas e horários informados no fuso de Brasília (America/Sao_Paulo), persistidos como instantes UTC. Cada apontamento possui uma data; a hora final deve ser igual ou posterior à inicial nessa data. Não é presumida passagem para o dia seguinte.
- Snapshot de criação inclui executante e instantes de cada item; detalhes e histórico exibem dados no fuso de Brasília. Itens antigos exibem não informado.
- Formulário dinâmico renumera todos os campos/labels e limpa os dados ao adicionar um item. Permissões, limite de apontamentos e transações existentes preservados.
- Área renomeada para Ações do PCM, com seções de assumir, vincular Ordem de Serviço e cancelar/recusar conforme estado atual. Visibilidade continua exclusiva do ADMIN_PCM.
- Removido apenas o item Fila de O.S. do menu. Início mantém redirecionamento à mesma fila/dashboard para PCM/Gestor.

## 2026-10-07 — Tempo de atendimento
- Cálculo único na entidade ServiceRequest: pendentes usam momento atual; OS_ABERTA usa os_linked_at e CANCELADA usa cancelled_at. Nenhuma alteração em banco ou transições.
- Pedidos encerrados sem a data correspondente exibem Não informado; não usam o momento atual nem uma data presumida.
- Duração calculada por instantes absolutos, sem depender do fuso; valores negativos são limitados a zero. Exibição compacta: minutos, horas/minutos ou dias/horas, sem textos relativos em inglês.
- Tempo de atendimento aplicado à fila/dashboard, detalhes, Minhas solicitações e relatório tabular.

## 2026-10-08 — Abertura e execução compartilhada (refatoração autorizada)
- A solicitação escolhe SOMENTE_ABERTURA ou ACOMPANHAMENTO, independentemente do perfil. Apontamentos iniciais opcionais; um bloco completamente vazio não cria item. Um item iniciado exige todos os campos.
- Mão de obra é o nome funcional do cadastro anterior de setores. Mantidas tabela sectors, IDs, associações e rotas para preservar dados e compatibilidade; nenhum nome existente é alterado automaticamente.
- Novos perfis MANTENEDOR e ENCARREGADO cabem no campo role existente. Todos os perfis ativos podem solicitar qualquer fluxo e atuar no acompanhamento compartilhado; user_id identifica o criador, não o responsável exclusivo pela execução.
- SOLICITANTE/MANTENEDOR veem pedidos próprios e todos os pedidos ACOMPANHAMENTO. ENCARREGADO/GESTOR/ADMIN_PCM veem todos. Dashboard geral: ENCARREGADO/GESTOR/ADMIN_PCM; relatórios/indicadores: GESTOR/ADMIN_PCM; cadastro, assumir, vínculo, cancelamento e confirmação de encerramento: somente ADMIN_PCM. Não existe cadastro de equipes nesta versão.
- Pedidos anteriores classificados SOMENTE_ABERTURA; antigos OS_ABERTA convertidos para ATENDIDA com auditoria STATUS_CHANGED e autor Sistema. Apontamentos, números, anexos e auditorias preservados. Migração não admite rollback automático que descarte dados.
- Somente abertura vinculada termina em ATENDIDA (sem declarar fechamento no TOTVS). Acompanhamento vinculado vai a OS_ABERTA ou EM_EXECUCAO quando já tem itens; conclusão vai a AGUARDANDO_ENCERRAMENTO; PCM confirma fechamento manual para ENCERRADA.
- Apontamentos são somente acrescentados, com recorded_by distinto do executante livre. Registros antigos mantêm autor/executante/datas desconhecidos sem inventar informação. Bloqueio da solicitação serializa apontamentos/transições; falha de auditoria desfaz a ação.
- Tempo de atendimento mantém a regra anterior: aguardando PCM/em análise contam até agora; após o vínculo da O.S., todos os estados de execução mantêm o valor congelado em os_linked_at; CANCELADA usa cancelled_at. Não se mistura tempo de execução com atendimento à abertura. Média dos indicadores preservada.
- Alerta usa Bem + filial e exclui ATENDIDA/ENCERRADA/CANCELADA; mostra todos os pedidos ativos com descrição. Metadados do alerta são compartilhados para prevenir duplicidade; link de detalhe e anexos seguem RequestAccess. Pedido somente de abertura de terceiro sem acesso orienta consultar o PCM.
- Nova criação sobre Bem ativo requer confirmação explícita no servidor, motivo opcional e auditoria DUPLICATE_CONFIRMED. Bloqueio transacional PostgreSQL por Bem + filial também protege criações simultâneas. Alerta não bloqueia um novo problema confirmado.
- Mão de obra: comparação lower/translate no PostgreSQL, normalização Unicode do termo e limite 20; nenhuma extensão adicional exigida. Bem: SQL SELECT allowlisted com COLLATE Latin1_General_100_CI_AI, parâmetros e limite 20. Validação exata de código/filial preservada.
- Validação manual real de leitura em dbo.ST9010: centrifuga e CENTRÍFUGA retornaram os mesmos 20 códigos/filiais. Nenhuma escrita, DDL ou procedure executada no TOTVS. Testes automatizados usam fakes/mocks.
- Apontamentos não são impostos como pré-requisito para concluir o serviço: conclusão por usuário autorizado registra quem informou e quando, com ou sem itens opcionais. Itens preenchidos exigem todos os campos.

- Validação final: migrations aplicadas nos dois PostgreSQL; nenhuma solicitação com flow nulo ou OS_ABERTA de somente abertura; PHPUnit 103 testes / 659 asserções aprovados em PHP 8.2.12; sintaxe PHP e JavaScript aprovadas; Composer validate --strict e check-platform-reqs aprovados. Aviso preexistente do PHP: OpenSSL carregado em duplicidade. Conferência visual real em celular/tablet e homologação operacional entre turnos pendentes.

## 2026-10-08 — Orientação de abertura e fila da homologação
- Substituída a escolha antiga no formulário por uma pergunta obrigatória: Como esta Ordem de Serviço deve seguir após a abertura? Valores OsDisposition: SOMENTE_ABERTURA, MANTER_ABERTA, PODE_ENCERRAR.
- Somente abrir -> Atendida após vínculo. Manter em andamento -> EM_EXECUCAO após vínculo, independentemente da quantidade de itens. Pode encerrar -> AGUARDANDO_ENCERRAMENTO após vínculo; apenas PCM confirma o fechamento manual no TOTVS para ENCERRADA.
- flow continua interno para compatibilidade/permissões/filtros; o servidor o determina pela escolha, ignorando flow enviado pelo cliente. Todos os perfis operacionais podem selecionar as três opções.
- Migrações AddOsDisposition e MapLegacyOsDisposition. Antigos SOMENTE_ABERTURA mantêm comportamento; acompanhamento concluído/encerrado mapeia PODE_ENCERRAR, demais acompanhamentos conservadoramente MANTER_ABERTA. Nenhum pedido é declarado concluído pela quantidade de apontamentos. Mapeamento gera auditoria de Sistema; dados e estados existentes preservados.
- Continuidade segue compartilhada por RequestAccess. Ao concluir, orientação passa de MANTER_ABERTA para PODE_ENCERRAR com auditoria do usuário que concluiu.
- Orientação destacada no detalhe, fila e Ações do PCM; histórico registra criação e alterações. Três radios grandes, sem perguntas separadas nem checkbox ambíguo.
- Fila: AGUARDANDO_ENCERRAMENTO, AGUARDANDO_PCM, EM_ANALISE, OS_ABERTA, EM_EXECUCAO, ATENDIDA, ENCERRADA, CANCELADA; cada grupo por created_at DESC/id DESC. Filtros preservados.
- Validação: migrations aplicadas no PostgreSQL principal e de testes; todos os registros principais mapeados. Suíte completa: 109 testes / 724 asserções aprovados em PHP 8.2.12. Após o último ajuste visual, testes específicos das três escolhas aprovados. Sintaxe PHP/JavaScript aprovada; integração TOTVS não foi alterada.

## 2026-10-08 — Revisão de toque/tablet
- Ajustes de apresentação apenas; sem migrations, alteração de status, ordenação, permissões ou serviços de negócio. Criação mantém redirecionamento ao detalhe e confirmação por Flash com número e duas ações, sem ocultar a mensagem ao tocar.
- Nova solicitação: Unidade, Bem, Mão de obra, manutenção, descrição, três escolhas, apontamentos opcionais, fotos, envio. Solicitante automático preservado.
- Controles de 48 px (52 px para ponteiro touch), sugestões de 56 px, inputs com fonte de 16 px e zoom livre. Até 900 px: escolhas e apontamentos em uma coluna, filtros empilhados e ações principais na largura disponível.
- Menu recolhível até 1280 px, links grandes e atalho para solicitar fora do menu; não aparece no primeiro acesso enquanto houver troca obrigatória de senha. Escape fecha menu e devolve foco ao botão.
- Fila mantém tabela inteira em região com rolagem horizontal contida e orientação visível; nenhuma coluna é escondida. Contadores legíveis em duas colunas nas telas menores e ação Ver solicitação em botão.
- Detalhes têm atalho ao formulário de execução e botões confortáveis; listas, confirmação e autocomplete seguem o tema escuro. Conferência física em tablet corporativo/celular permanece parte da homologação visual.
## 2026-10-08 — Recuperação segura de CSRF no login
- Formulário recém-carregado validou via HTTP; captura enviada mostra divergência entre cookie e token. Causa específica no navegador não foi comprovada. Cookie próprio pcm_solicitacao_os_csrf evita colisão do nome genérico usado por outros projetos no mesmo host (cookies não são isolados por porta).
- CSRF continua obrigatório e agora valida antes da autenticação. Envios inválidos do login são rejeitados e redirecionados com 303 para um GET novo e mensagem humana; credenciais não são armazenadas/reenviadas. Demais ações mantêm erro 403 e proteção existente.
- Login e redirecionamento de recuperação usam Cache-Control: no-store. Testes passaram a gerar o cookie com o nome próprio.
- Verificação HTTP em localhost:8765: token inválido recuperou a tela e a tentativa seguinte com token novo alcançou autenticação. Testes específicos: ausência/divergência não autenticam; token válido funciona mesmo com cookie genérico de outro projeto.
## 2026-10-09 — Base path configurável
- APP_BASE_URL alimenta App.baseUrl; bootstrap normaliza a subpasta e configura App.base para manter webroot correto com rewrite. Vazio/false mantém a detecção local padrão.
- APP_FULL_BASE_URL pode incluir a subpasta. Configure preserva esse valor; Router recebe somente a origem quando há base explícita, evitando prefixo duplicado em URLs absolutas.
- Ambiente real prevalece sobre dotenv mediante skipExisting; nenhuma credencial/local .env foi alterada.
- Redirects de acesso, início, saída e troca de senha usam arrays de rota. Menu, formulários, filtros e autocomplete usam helpers com rotas; assets relativos ao webroot incluem a base.
- FormAuthenticator recebe a rota de login diretamente, pois seu UrlChecker já resolve Router::url. Redirecionamento não autenticado usa a URL gerada. Sair usa _method POST na geração da rota restrita.
- Rotas permanecem relativas à aplicação; não se coloca /pcm-solicitacao-os no scope. Rewrite existente é relativo e o servidor deve montar somente webroot na subpasta.
- Testes cobrem URLs públicas/privadas, CSS/JS/logo/favicon, formulário login, POST login/logout, troca obrigatória de senha, CSRF, ações PCM, endpoints e bootstrap real com variáveis de produção e base vazia/false.
## 2026-10-13 — Bootstrap do primeiro administrador
- CreateAdminCommand separado do DevelopmentAdminSeed. Ambiente production obrigatório, salvo confirmação explícita --confirm-non-production.
- Credenciais exclusivamente nas variáveis temporárias BOOTSTRAP_ADMIN_NAME/USERNAME/EMAIL/PASSWORD, sem argumentos de senha nem valores pessoais hardcoded. E-mail opcional; validações da aplicação preservadas.
- Cria ADMIN_PCM ativo com must_change_password=true e hash normal Argon2id da entidade User. RegistrationService audita PRODUCTION_ADMIN_BOOTSTRAPPED com autor Sistema e sem senha/hash.
- Transação PostgreSQL com bloqueio da tabela users serializa verificação/criação. Qualquer ADMIN_PCM, mesmo inativo, torna o comando inócuo; não redefine nem reativa contas.
- Erros CLI são genéricos, sem exceções/dados sensíveis. BOOTSTRAP_ADMIN_PASSWORD também mascarada pelo Debugger. README inclui coleta segura via PowerShell e limpeza das variáveis do processo.
- Testes específicos: 5 testes / 48 asserções aprovados, incluindo duplicidade/inatividade, ambiente/confirmação, senha/hash/troca, dados inválidos/colisão e rollback se auditoria falhar. Sintaxe aprovada. Nenhum administrador real foi criado por esta revisão.
## 2026-10-13 — Usuário/matrícula ou e-mail
- Username e email opcionais individualmente, exigindo ao menos um dos dois. Nome e senha inicial continuam obrigatórios; senha em branco na edição continua mantendo o hash atual.
- Migration AllowOptionalUsernames permite username NULL, preserva índice único e acrescenta CHECK users_login_identifier_required. Não altera os identificadores existentes nem fabrica matrículas.
- User normaliza username vazio para NULL; UsersTable exige ao menos um identificador sobre a entidade completa ao salvar (inclusive edições parciais) e mantém unicidade de identificadores preenchidos, permitindo múltiplos NULL.
- CreateAdmin utiliza a mesma regra: BOOTSTRAP_ADMIN_NAME/PASSWORD obrigatórios; USERNAME ou EMAIL, ou ambos. Hash, perfil, primeiro acesso, bloqueio de duplicidade e auditoria preservados.
- Formulário administrativo indica os dois identificadores como opcionais individualmente e explica a exigência conjunta. Login permanece pelo identificador disponível; usuário inativo continua bloqueado.

## 2026-10-13 — Ajustes de homologação
- Campos obrigatórios da solicitação validados em português no servidor e formulário. Apontamentos iniciais vazios são opcionais; linhas preenchidas parcialmente ou adicionadas explicitamente exigem todos os dados. Horário final não pode anteceder o inicial.
- Atalho de criação único no conteúdo das filas; cabeçalho/menu não repetem a ação nessas páginas. Outras telas mantêm um atalho conforme o tamanho da tela.
- Novo formulário GET/POST de apontamento reutiliza autorização e execução compartilhada. Não aceita alteração dos dados/números/solicitante da solicitação. Apontamento, atividade e auditoria são gravados na mesma transação.
- last_activity_at registra novos apontamentos após o vínculo. Encerramento pendente, aguardando PCM e análise continuam prioritários. O.S. aberta e serviço em andamento compartilham o grupo de execução por COALESCE(last_activity_at, created_at) decrescente. Estados terminais permanecem abaixo. Destaque visual informa a data do último apontamento; não há mecanismo de leitura/ack implícito.
- Setores de funcionários usam user_departments, separados de sectors (Mão de obra). FK users.department_id é nullable para legado; cadastro novo e edição administrativa exigem setor ativo. Não se atribui departamento com base em perfil ou solicitação. Login e troca de senha de contas antigas permanecem possíveis.
- Cadastro de setores restrito ao PCM e auditado. Nenhum departamento real foi presumido ou atribuído na migration; cabe ao PCM cadastrar e regularizar. Bootstrap do primeiro admin recebe nome do setor pelo operador, reutiliza/cria com auditoria na transação existente.

## 2026-10-13 — Simplificação operacional e auditoria
- Etapa de assumir removida das rotas, controller, service e interface. EM_ANALISE permanece no enum somente para registros históricos; migration devolve pedidos nessa situação à fila AGUARDANDO_PCM, auditando a alteração sem apagar assigned_to/assigned_at ou eventos antigos.
- O.S. TOTVS única globalmente entre solicitações, inclusive finalizadas/canceladas. Índice parcial único em btrim(totvs_os_number) permite NULL. Migration bloqueia duplicidades existentes; serviço informa a solicitação já vinculada e serializa vínculos concorrentes por bloqueio consultivo PostgreSQL.
- RequestAuditsTable registra snapshot de identidade nos eventos novos de todos os caminhos existentes (criação, execução, fluxo, status, fotos e ações PCM), sem senha/hash. old_status/new_status explicitam transições. Não fabrica snapshots retrospectivos. Alterações de cadastro não mudam identidades já registradas.
- Conclusão compartilhada registra completed_by/at e evento SERVICE_COMPLETED com identidade e transição para AGUARDANDO_ENCERRAMENTO. Timeline usa o snapshot; detalhes administrativos incluem e-mail/perfil e valores anteriores/novos.
- Dashboard é exclusivamente operacional: quatro estados ativos, sem filtros. Encerramento pendente e aguardando PCM precedem execução por atividade recente. Relatórios concentram histórico e filtros completos, incluindo O.S., tipo de manutenção e setor atual do solicitante; Minhas solicitações permanece pessoal.
- last_activity_at atualizado na criação e ações relevantes; last_item_at separado preserva o destaque de apontamento, sem confundir vínculo/encerramento com execução nova.
- Polling simples de fragmento autenticado a cada 30 segundos, no máximo 20 linhas por página e sem chamadas simultâneas. Preserva foco/rolagem/paginação e não troca conteúdo durante interação ou preenchimento. Aba oculta pausa; acesso desativado/alterado ou senha obrigatória interrompe atualização. Endpoint proibido retorna apenas mensagem, sem dados da fila.
- Session.timeout=0, preset cake com arquivos privados em tmp/sessions e GC desativado: sem término por inatividade da aplicação/PHP. Cookie de navegador (não persistente após fechamento), HttpOnly, SameSite=Lax e Secure em HTTPS; CSRF permanece ativo. Logout e revalidação de conta a cada request preservados. Limpeza externa de tmp/sessions deve ser excluída da rotina de publicação.

## 2026-10-13 — Sessão, Mão de obra e sincronização TOTVS
- Regra vigente de sessão passa a 20 minutos de atividade humana. Middleware usa AuthActivity.last_at, exclui Dashboard.refresh e invalida Auth com renovação de ID e aviso de expiração. O relógio nativo de sessão é desativado na requisição para não contar polling; configuração Session.timeout continua 20. GC isolado habilitado com retenção de 1 dia para permitir identificar expiração mesmo sem polling.
- Interações trusted no navegador enviam POST de atividade protegido por CSRF, limitado a uma chamada por minuto e sem timer. Isso preserva formulários em preenchimento, sem manter tablets inativos autenticados. Endpoint só renova sessão; pode ser usado na tela obrigatória de troca de senha sem liberar qualquer função privada.
- Mão de obra é select obrigatório de ativos; endpoint/cadastro antigo permanece compatível, mas o formulário não o usa mais como autocomplete.
- Referência PCM_OrdensServico consultada sem alterações: dbo.STJ010, identidade TJ_ORDEM/TJ_FILIAL, equipamento TJ_CODBEM; ProtheusOperationalEligibility usa L/N aberta e L/S encerrada. C/P/códigos desconhecidos são inconclusivos e não provocam encerramento. SELECT limitado real em uma O.S. e detalhe STJ010/ST9010/ST4010 foram validados fora do PHPUnit, sem alterações no TOTVS.
- Ordens consultadas em lote de até 50 números vinculados em acompanhamento ativo. Resultado ambíguo por filial/número, Bem divergente, ausência, truncamento, erro ou status não confirmado preserva o pedido. Filial vazia de Bem compartilhado exige identificação única; filial confirmada da O.S. tem campo próprio.
- Somente abertura continua Atendida e não depende do encerramento. Outros fluxos acompanham status oficial: aberta fica EM_EXECUCAO; encerrada fica ENCERRADA com data de detecção e evento TOTVS_AUTO_CLOSED de Sistema. Clique de conclusão continua registrado, mas não determina estado final e não encerra nada no TOTVS. Confirmação manual local removida; cancelamento administrativo preservado.
- Snapshot de sincronização guarda número/filial, situação/término conhecidos antes/depois, status local antes/depois, origem Sistema/Sincronização TOTVS e horário UTC. Não há auditoria duplicada quando nada mudou. Transação com releitura sob lock protege cancelamentos/vínculos concorrentes e rollback se auditoria falhar.
- Tela de detalhe sob demanda acessível só a perfis com relatórios; consulta real com Bem/filial e valida unicidade da identidade/cadastros. Dados de serviço/equipamento/descrição/datas/centro de custo não são replicados em PostgreSQL.
- Fotos: entradas de câmera e galeria; capture environment com fallback nativo, acumulação quando DataTransfer disponível. Total máximo 5, 15 MB cada, validações anteriores mantidas. Servidor exige corpo de 80 MB e máximo de pelo menos 10 uploads para validar os dois inputs sem truncamento. PHP local tem post_max_size=40M: ajustar para cenário máximo.
- Comando sync_totvs_orders pronto para cron a cada 2 minutos; nenhum agendamento foi instalado. TOTVS permanece exclusivamente SELECT, com parâmetros, allowlist e timeout. Indisponibilidade registra erro seguro e não interfere na aplicação web.

## 2026-10-13 — Busca de Bens por descrição e situação real
- Autocomplete pesquisa somente parte de T9_NOME, com collation CI_AI e parâmetros escapados, mantendo limite de 20 e apresentação atual. Código e filial continuam sendo a identidade da seleção, mas não entram na pesquisa textual.
- SEARCH e FIND exigem T9_SITBEM = 'A', além de excluir registros deletados. Campo confirmado pela documentação TOTVS de Bens/Solicitação de Serviço e SELECT real em dbo.ST9010: A ativo, I inativo. Não se usa descrição/tag como regra de atividade. T9_SITMAN é uma situação distinta de manutenção, não acrescentada como restrição fora do escopo.
- Solicitações antigas exibem snapshots locais sem revalidar atividade do Bem. Validação real limitada confirmou busca por descrição em maiúsculas/minúsculas e rejeição de seleção inativa, exclusivamente SELECT; testes automatizados usam fakes.

## 2026-10-13 — Fila por atividade recente
- A fila ativa e o polling usam last_activity_at decrescente, sem prioridade por situação ou novo apontamento. IDs decrescentes desempatam horários iguais. Registros antigos sem last_activity_at usam created_at como atividade inicial; não é necessária nova migration. Criação, apontamentos, vínculo, cancelamento e mudanças operacionais já atualizam o campo. Consultas sem mudança não alteram a atividade.
- A paginação aceita apenas page: parâmetros sort/direction não substituem a ordenação operacional. NOVO APONTAMENTO continua sendo somente destaque visual.
## 2026-10-26 — Etapa 1: funções autorizadas
- Fonte fornecida: `C:\Users\maria.goncalves\Desktop\Função.xlsx`, primeira aba, colunas `Funcao` e `Descricao`, 45 funções. O arquivo recebido tem nome diferente de `Função(1).xlsx` mencionado no pedido.
- Cadastro independente em `authorized_functions` no PostgreSQL, com código textual único, descrição, ativo e datas. Não reutilizar Mão de obra/setores: representam outros cadastros.
- Preservar o conteúdo original do código, sem completar zeros ou presumir tamanho TOTVS. Na planilha recebida os códigos são células numéricas sem máscara; `5` permanece `5`. Códigos textuais como `0005` conservam zeros.
- Importação transacional por operador do servidor: `php bin/cake.php import_authorized_functions "C:\Users\maria.goncalves\Desktop\Função.xlsx" --user-id ID_ADMIN`. Exige administrador ativo e registra alterações em `registration_audits` pela rotina existente. Sem mudanças, não gerar eventos duplicados.
- Reimportar atualiza descrições e reativa códigos presentes. Por padrão mantém os ausentes; `--replace` inativa ausentes com auditoria, sem exclusão física. Duplicatas idênticas são consolidadas; descrições conflitantes, fórmulas, campos vazios e planilhas vazias rejeitam o lote inteiro.
- Prévia sem banco: `php bin/cake.php import_authorized_functions "CAMINHO.xlsx" --dry-run`. Requer extensões PHP Zip e SimpleXML. Leitura limitada a 5 MB por XML; não executa macros, fórmulas nem entidades XML.
- Etapas 2 a 6 permanecem para a próxima fase, conforme execução incremental do projeto. Campos/permissões dos funcionários TOTVS ainda não confirmados; nenhuma consulta ou gravação TOTVS nesta etapa. Imagem de referência não estava disponível nesta conversa.

## 2026-10-30 — Perfis funcionais
- Somente MANTENEDOR, PCM e ADMIN_PCM. SOLICITANTE migra para MANTENEDOR; GESTOR/ENCARREGADO para PCM por já terem visão geral. Perfil desconhecido bloqueia a migration; contas e snapshots históricos são preservados.
- PCM recebe tratamento e relatórios operacionais; cadastros seguem exclusivos do administrador. Permissão compartilhada de acompanhamento permanece igual.
- Execução incremental: esta fase cobre perfis; grade, autocomplete e snapshots de funcionários ficam para a fase seguinte.
- Validação desta fase: migration aplicada; 73 testes selecionados passaram (perfis, migration, administração, execução compartilhada, simplificação operacional, homologação e bootstrap admin); Composer validate e sintaxe PHP aprovados. Ambiente local PHP 8.2.12: repetir homologação em PHP 8.3+. Suíte completa interrompida; resultado informado corresponde somente à seleção desta fase.

## 2026-11-03 — Apontamentos com funcionário TOTVS
- Grade compacta compartilhada pela criação, detalhe e inclusão em O.S. existente. Botões +/−; no máximo 50 linhas. Linha inicial vazia continua opcional na criação; linha adicionada ou preenchida exige todos os campos. Horários iguais continuam aceitos; fim anterior ao início continua rejeitado. UTC no banco e America/Sao_Paulo na apresentação.
- Consulta sob demanda em dbo.SRA010, exclusivamente RA_MAT, RA_NOME, RA_CODFUNC e RA_SITFOLH. Até 20 resultados; pesquisa parametrizada por nome ou matrícula. Sem importação de funcionários. Driver mantém allowlist estrita e timeout existente.
- Somente funções locais ativas, por código textual exato. Não completar zeros nem converter códigos do TOTVS em números. Situações permitidas centralizadas em TotvsEmployeeQueries::ALLOWED_SITUATIONS: vazio/espaço e F; A/D e situações desconhecidas excluídas.
- Ao salvar, reconsultar matrícula e validar novamente função/situação; snapshots recebidos do cliente são ignorados. Matrícula ambígua é rejeitada, pois a consulta autorizada não usa filial.
- Migration 20261103000100 adiciona quatro snapshots anuláveis a request_items; antigos executantes livres permanecem legíveis, sem identidade presumida. Novos snapshots também seguem para a auditoria existente. Auditoria, last_activity_at, fila, anexos e sincronização mantêm suas rotinas.
- Diagnóstico real confirmou SELECT dos quatro campos, sem retornar pessoas. Banco principal está sem funções ativas: busca falha fechada com lista vazia e nenhuma consulta de funcionários. Importação/ativação e conferência dos códigos exatos ficam para homologação; não alterar cadastros automaticamente nesta fase.
- Gerador de teste de navegador: php tests/Browser/employee-grid.php. Escreve tmp/employee-browser.html com fetch simulado e 16 verificações de interação/layout; nenhuma consulta real. Testar em desktop, tablet e iframe de 360 px (o navegador headless limita a largura mínima da janela).

- Validação final: suíte PHPUnit completa com 195 testes aprovados (PHPUnit exit 0); 48 verificações de navegador, 16 em cada largura 360/768/1366 px; Composer validate, sintaxe PHP dos arquivos alterados e git diff --check aprovados. Diagnóstico real confirmou SELECT dos quatro campos. Dois testes de perfil ainda esperavam o comportamento anterior e foram alinhados aos perfis já vigentes; nenhuma permissão foi alterada nesta fase.

## 2026-11-03 — Usuário sem setor obrigatório
- Cadastro/edição passa a usar somente nome, usuário/matrícula, e-mail opcional, senha provisória, perfil e ativo. Preservar a regra existente de pelo menos um identificador de login (usuário ou e-mail), troca obrigatória da senha provisória e administração exclusiva de ADMIN_PCM.
- department_id já é anulável pela migration 20261013000200 e no banco principal (information_schema: is_nullable YES); nenhuma migration nova necessária. Manter coluna, associação e FK para compatibilidade com registros/filtros históricos. Não apagar usuários nem limpar seus vínculos antigos.
- Remover campo das telas de usuário e da listagem; perfil pessoal mostra o nome amigável do perfil. Ignorar department_id recebido em cadastro/edição e bloquear sua atribuição em massa na entidade. Criação inicial por comando/seed também não depende de setor nem cria cadastro auxiliar como efeito colateral.
- MANTENEDOR, PCM e ADMIN_PCM e suas permissões permanecem iguais; cadastros auxiliares e fluxos de solicitações permanecem inalterados.
- Validação: 53 testes afetados aprovados (administração, perfil/acesso, homologação, três perfis, criação inicial e seed), sintaxe dos PHP alterados e git diff --check aprovados.

## 2026-11-03 — Equivalência de códigos da planilha e TOTVS
- Regra atual solicitada: código numérico de função com até cinco dígitos é completado à esquerda para os cinco caracteres de RA_CODFUNC, sem converter para inteiro nem truncar. 32, 032 e 00032 correspondem a 00032. Substitui a decisão anterior de correspondência textual exata para esses códigos numéricos.
- AuthorizedFunctionCode centraliza a regra na leitura da planilha e na consulta das funções ativas existentes. Descrições conflitantes para códigos equivalentes rejeitam o arquivo/lote; conflito em cadastro legado bloqueia a busca antes do TOTVS.
- Somente funções da planilha devem ficar ativas após importação com --replace; inativar ausentes sem excluir registros nem alterar snapshots anteriores. Funcionários continuam exclusivamente sob demanda e somente leitura, com situação vazio/espaço ou F; A/D são excluídas. Grade e demais fluxos inalterados.
- Arquivo disponível informado no pedido: C:\Users\maria.goncalves\Desktop\PLANILHAS\Função.xlsx (45 funções válidas em prévia). Função(2).xlsx não encontrado na pasta indicada. Confirmação da fonte solicitada antes de gravar a lista no banco principal.
- Validação: 23 testes afetados, 167 verificações aprovadas; sintaxe dos PHP alterados e git diff --check aprovados. Cobertura inclui importação, conflitos equivalentes, busca por nome/matrícula, filtros de situação/função e snapshot canônico ao salvar.
- Fonte confirmada pelo usuário: arquivo anexado ao prompt, C:\Users\maria.goncalves\Desktop\PLANILHAS\Função.xlsx. Importação concluída com --replace --user-id 1 (conta administrativa admin.1 usada pela rotina auditada): 45 criadas, zero atualizadas/inativadas. Conferência integral de códigos/descrições contra a planilha aprovada; todos os códigos possuem cinco dígitos; 45 eventos de auditoria.
- Verificação real somente leitura: busca por nome retornou 10 funcionários permitidos; revalidação por matrícula aprovada. Nenhum funcionário foi importado e nenhum apontamento real foi criado durante a verificação. Testes automatizados permanecem os 23 testes/167 verificações aprovados antes da importação, sem novas mudanças no código.

## 2026-11-04 — Descrição por ação e componente
- Nova descrição obrigatória por dois cadastros independentes: service_actions e service_components. Migration 20261104000100 cria 14 ações e 17 componentes iniciais fornecidos no pedido, com ativo/inativo, datas e unicidade. ADMIN_PCM administra pelo mecanismo de cadastros/auditoria existente; não há ação de excluir e os modelos bloqueiam exclusão física.
- Solicitação e cada linha preenchida/adicionada exigem as duas opções ativas; removida a entrada Outro/texto livre para novos serviços. ServiceSelection resolve no servidor, ignora descrições enviadas pelo cliente e revalida com lock ao salvar. Composição: ação + componente em minúsculas; exceção explícita Limpar/Geral → Limpeza geral. Exemplos Soldar chapa e Emendar cabo.
- service_action_id, service_component_id e snapshots service_action_name/service_component_name ficam na solicitação/apontamento e no histórico auditado, junto de description final. IDs/FKs RESTRICT e índices preparados para filtros futuros. Renomear/desativar não reescreve textos históricos.
- Campos novos anuláveis apenas para compatibilidade histórica: manter description e serviços antigos, sem inferir IDs e sem apagar solicitações/apontamentos. O rollback recusa remover vínculos novos já utilizados. Catálogo antigo de serviços permanece preservado; menu passa a mostrar os dois cadastros novos.
- Grade atual mantida; a célula Serviço realizado contém os seletores Ação e Item/Componente em colunas, reorganizados em telas menores. Funcionário TOTVS, anexos, auditoria, atividade e fluxos de O.S. preservados.
- Validação final: suíte completa com 208 testes aprovados (PHPUnit exit 0), 60 verificações de navegador (20 por largura 360/768/1366), Composer validate, sintaxe dos arquivos PHP alterados e git diff --check aprovados. Banco principal: 14 ações/17 componentes ativos e exemplos reais de composição confirmados, sem criar solicitações/apontamentos reais no teste.

## 2026-11-04 — Ação e componente somente na solicitação
- Correção de escopo solicitada: Ação + Item/Componente obrigatórios somente na criação da solicitação. Manter os cadastros locais exclusivos de ADMIN_PCM, composição no servidor, IDs, nomes e descrição no histórico da solicitação.
- Apontamentos voltam ao campo Serviço realizado em texto livre obrigatório, com limite e validações existentes (2000 caracteres); sem exigir nem consultar os catálogos de ação/componente. Preservar funcionário TOTVS, datas/horários, auditoria, atividade e grade.
- Não alterar migrations já aplicadas nem limpar dados históricos. A migration 20261104000100 já criou os cadastros e campos necessários para a solicitação; campos opcionais anteriores em request_items permanecem apenas para preservar históricos. Nenhuma nova migration necessária.
- Validação desta correção: 93 testes afetados aprovados (solicitação, apontamentos, cadastros, execução, anexos e fluxos), 60 verificações de navegador em 360/768/1366 px, sintaxe PHP dos arquivos principais e git diff --check aprovados. Apontamento aceita texto livre sem selecionar ação/componente, inclusive após desativar as opções utilizadas na solicitação.

## 2026-11-10 — Tipos de manutenção e seleção obrigatória de Bem
- Tipos de manutenção passam a cadastro local exclusivo de ADMIN_PCM, com nome, código estável, ativo/inativo e auditoria de cadastros existente. Preservar os cinco códigos iniciais; novos códigos são gerados internamente e não mudam na edição. Desativar não altera solicitações antigas e o modelo bloqueia exclusão física. Relatórios incluem tipos inativos para consulta histórica.
- Novas solicitações listam somente tipos ativos e revalidam a situação com lock na transação de criação. O campo maintenance_type existente continua armazenando o código, sem reescrever dados antigos.
- Bem continua sendo consultado somente por leitura no TOTVS; ao salvar, conferir código/filial e se o texto exibido corresponde exatamente à descrição e ao código retornados. Texto alterado ou sem seleção é rejeitado também no backend; descrição salva continua vindo do TOTVS. JavaScript limpa código/filial ao digitar e verifica novamente o texto no envio.
- Validação: 91 testes/958 verificações aprovados nos sete grupos afetados (solicitações, cadastros, anexos e fluxos existentes); sintaxe PHP e git diff --check aprovados. Migration aplicada no PostgreSQL local. Teste de interface em tests/Browser/asset-selection.html criado; execução do Chrome bloqueada pela aprovação automática, sem motivo específico informado, portanto o teste de navegador não foi executado.

## 2026-11-11 — Funcionários sem filtro por função
- Substitui a restrição anterior por funções locais/planilha: busca e revalidação consultam DADOSP12.dbo.SRA010 por nome/matrícula, sem filtrar RA_CODFUNC. authorized_functions fornece apenas descrições opcionais no rótulo; catálogo vazio, inativo ou indisponível não bloqueia funcionários.
- Situação vazio/espaço permite seleção normal; F (FÉRIAS) e A (AFASTADO) aparecem em cinza com situação no rótulo e permitem seleção e salvamento. D (DEMITIDO) não aparece nem passa pela revalidação. Mantida lista explícita de situações aceitas.
- Somente SELECT no TOTVS e os quatro campos existentes. Sem migrations, alterações de apontamentos salvos ou mudanças nas demais regras.

## 2026-11-12 — Edição e exclusão administrativas de solicitações
- ADMIN_PCM pode corrigir os dados da solicitação com auditoria REQUEST_UPDATED por campo (antes/depois), identidade do administrador e data UTC. Número interno, solicitante, status, vínculo TOTVS, apontamentos e anexos não são editáveis por essa ação.
- Fluxo pode mudar somente enquanto aguarda PCM e sem O.S. vinculada; apontamentos impedem mudar para somente abertura. Bem é revalidado no TOTVS por leitura e não pode ser trocado após vínculo. Valores históricos inativos podem permanecer; novas escolhas exigem cadastros ativos.
- Exceção explícita à regra geral de não excluir: ADMIN_PCM pode excluir definitivamente pedidos sem O.S. vinculada, sem motivo, com a confirmação solicitada. Remove os três conjuntos de filhos existentes (apontamentos, anexos e auditorias) na transação local. Arquivos são movidos temporariamente no mesmo diretório, restaurados em falha da transação e apagados após confirmação do banco. Falha de remoção física é informada para suporte.
- Cancelamento e permissões operacionais de PCM/MANTENEDOR permanecem vigentes. Nenhuma escrita no TOTVS.
- Validação: 66 testes de solicitações, tratamento e acompanhamento aprovados (492 verificações); após ampliar a cobertura, os 8 testes administrativos passaram (55 verificações). Sintaxe dos 8 arquivos PHP alterados/criados e git diff --check aprovados. Teste antigo de rollback de criação passou a injetar FakeEmployees para não consultar o TOTVS real.

## 2026-11-12 — URLs administrativas em subdiretório e correção do Bem
- Se APP_BASE_URL estiver ausente, usar a subpasta da APP_FULL_BASE_URL configurada como base do CakePHP. Sem URL pública com subpasta, manter detecção local; não fixar /pcm-solicitacao-os nos links. Base explícita continua tendo precedência.
- Formulário administrativo envia POST explicitamente à rota edit; o padrão PUT do FormHelper para entidades existentes não era aceito pela rota. Links, formulários e redirects usam rotas do CakePHP.
- Ao trocar Bem validado, gravar também a descrição retornada pelo TOTVS em campo protegido contra dados do cliente. Preservadas as restrições existentes para O.S. vinculada, auditoria e dependentes.
- Ações da fila compactas, lado a lado, com texto, ícone e title, usando as cores do tema escuro e mantendo área de toque mínima de 44 px.
- Validação: 47 testes de subdiretório/solicitações aprovados no conjunto de regressão; 10 testes de tratamento aprovados após ajustar a expectativa visual dos botões. Cobertura inclui URLs e redirects no prefixo, POST do formulário, permissões, troca de Bem com auditoria, preservação de apontamentos/anexos e exclusão com remoção física. Sintaxe PHP e git diff --check aprovados.
