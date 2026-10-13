# Solicitação de serviços — PCM

CakePHP 5 com PostgreSQL. Fase 1: login/logout por sessão, administração de
usuários e setores, perfis dos enums e auditoria de cadastros.

## Executar
1. Instale PHP 8.2.12+ com intl, mbstring e pdo_pgsql, Composer e PostgreSQL.
2. Execute composer install.
3. Copie .env.example para .env e config/app_local.example.php para config/app_local.php.
4. Configure DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD e SECURITY_SALT.
5. Crie previamente o banco PostgreSQL e valide a conexão.
6. Execute php bin/cake.php migrations migrate.
7. Em desenvolvimento, defina APP_ENV=development, ADMIN_SEED_EMAIL e
   ADMIN_SEED_PASSWORD (12 a 72 caracteres) e execute:
   php bin/cake.php seeds run DevelopmentAdminSeed
8. Execute php bin/cake.php server e abra http://localhost:8765/entrar.

O administrador local desta instalação usa as credenciais ADMIN_SEED_EMAIL e
ADMIN_SEED_PASSWORD do .env. A senha não é versionada nem exibida em logs.
O seed não substitui a senha de um usuário já existente.

PCM / Administrador gerencia usuários e setores. Gestor e Solicitante têm
acesso à página inicial; suas funcionalidades de solicitação entram depois.
Para remover acesso ou retirar um setor de uso, desmarque o campo ativo
na edição. A conta do administrador logado não pode ser desativada ou
perder seu próprio perfil PCM.

## Banco e segurança
PostgreSQL é o banco oficial. Runtime e persistência usam UTC; a apresentação
usa America/Sao_Paulo. Mudanças de cadastro e sua auditoria são atômicas;
a auditoria não armazena senhas ou hashes. A sessão guarda somente o ID
e consulta o usuário ativo e o perfil atual a cada request.
CSRF protege os formulários e a saída HTML é escapada.
Em produção, configure APP_DEBUG=false e APP_FULL_BASE_URL com a URL real
e sirva exclusivamente webroot. Não execute o seed de desenvolvimento.
TOTVS não possui conexão ativa nesta fase.

## Testes
Crie previamente test_solicitacao_os e configure TEST_DB_HOST, TEST_DB_PORT,
TEST_DB_NAME, TEST_DB_USER e TEST_DB_PASSWORD. O banco de testes deve ser
diferente do principal. Os testes aplicam migrations e fixtures apenas nele.

- vendor\bin\phpunit
- composer validate --strict
- composer check-platform-reqs

Sem credenciais de teste, os testes de persistência são marcados como ignorados;
não há fallback para outro banco.

Referências:
- https://book.cakephp.org/authentication/4/authenticators.html
- https://book.cakephp.org/migrations/5/guides/seeding.html
## Fase 2 — solicitações e consultas
Telas: /solicitacoes/nova, /minhas-solicitacoes e /solicitacoes/{id}.
Endpoints autenticados: /api/bens?q=texto e /api/setores?q=texto.

Configure a conexão SQL Server separada no .env usando:
PROTHEUS_DB_HOST, PROTHEUS_DB_PORT, PROTHEUS_DB_DATABASE,
PROTHEUS_DB_USERNAME, PROTHEUS_DB_PASSWORD, PROTHEUS_DB_ENCRYPT,
PROTHEUS_DB_TRUST_SERVER_CERTIFICATE. Use uma conta com apenas SELECT.
Não reutilize ou copie senhas do projeto de referência.

O driver bloqueia qualquer SQL fora das duas consultas permitidas à dbo.ST9010.
SQL Server não recebe migrations ou qualquer escrita. As migrations desta fase
devem ser executadas em default (PostgreSQL) pelo comando habitual.
O teste usa fake da consulta e PostgreSQL test; não depende do TOTVS.

O servidor confirma código e filial do Bem antes de salvar o snapshot.
Se a integração estiver indisponível, o pedido não é salvo e o formulário
preserva os dados. Configure e valide o acesso real para permitir o envio.

As datas são exibidas no fuso de São Paulo. O número interno usa sequência
global e pode conter lacunas; não é o número da OS TOTVS.

## Fase 3 — fila e tratamento PCM
/painel oferece contadores, fila de pendências mais antigas e filtros de
situação, setor e período. Disponível para ADMIN_PCM e GESTOR.
No detalhe da solicitação, somente ADMIN_PCM pode assumir, vincular OS
manualmente ou cancelar/recusar com motivo. As ações exigem POST e CSRF.
Solicitações já vinculadas ou canceladas não são alteradas por essas ações.
O histórico exibe responsável, horário de São Paulo e resultado; o PCM
pode expandir os registros de auditoria. Solicitantes continuam restritos
a seus pedidos e visualizam a OS após vínculo.
Esta fase não valida a existência da OS no TOTVS nem altera dados externos.
Execute php bin/cake.php migrations migrate para aplicar AddRequestHandling.

## Fase 4 — fotos, indicadores e relatórios
Fotos são opcionais na criação: até 5 imagens JPG, PNG ou WebP de até 15 MB.
Não são aceitos executáveis, SVG ou HEIC. Metadados e auditoria ficam no
PostgreSQL; arquivos ficam em storage/attachments, fora de webroot.
Inclua essa pasta nos backups e permita escrita apenas ao processo PHP.
Não exponha storage como diretório público ou alias do servidor web.
Se o formulário precisar ser corrigido, selecione as fotos novamente.

/indicadores e /relatorios são exclusivos do PCM e dos gestores.
O período considera a criação do pedido; a média até vínculo usa somente
pedidos com data da OS vinculada. O relatório é tabular e paginado, sem CSV/PDF.

Execute php bin/cake.php migrations migrate para aplicar CreateRequestAttachments.
Configuração PHP para estes uploads: upload_max_filesize=15M,
post_max_size=80M e max_file_uploads=10 (câmera e galeria; a aplicação aceita 5 imagens no total).
Nginx deve usar client_max_body_size 80m. Não há escrita no TOTVS.

## Acesso e layout
Acesso inicial: todas as contas devem definir senha pessoal em /alterar-senha
antes de acessar páginas, consultas e anexos privados. Informe senha atual,
nova senha e confirmação. Nova senha exige pelo menos 6 caracteres, sem
composição obrigatória ou máximo pequeno. Novos hashes usam Argon2id;
a instalação PHP precisa oferecer argon2id em password_algos().
Redefinições de senha pelo PCM também exigem nova troca pessoal.
Meu perfil permite trocar a senha posteriormente; hashes antigos continuam
válidos para autenticação e não foram redefinidos pela migration.

O início abre a fila para PCM/Gestor e as próprias solicitações para Solicitante.
Tema padrão escuro, logo completa somente no login e administração agrupada.

## Publicação em subpasta
Configure no ambiente de produção:

    APP_DEBUG=false
    APP_FULL_BASE_URL=https://pcm.biolasa.com.br/pcm-solicitacao-os
    APP_BASE_URL=/pcm-solicitacao-os

O servidor web deve montar /pcm-solicitacao-os apontando exclusivamente para
webroot, com rewrite para index.php. Os arquivos .htaccess existentes usam
caminhos relativos e não precisam receber o prefixo nas rotas.
Nunca exponha .env, config, vendor ou o restante do projeto pelo servidor web.

App.baseUrl vem de APP_BASE_URL; o bootstrap configura também App.base para
as URLs com rewrite. O Router usa a origem do domínio, acrescentando a subpasta
uma vez. As variáveis reais do ambiente têm prioridade sobre .env.
No ambiente local, mantenha APP_BASE_URL vazio ou false.
## Primeiro administrador em produção
O comando específico é: php bin/cake.php create_admin

Defina APP_ENV=production e forneça somente no processo CLI as variáveis temporárias
BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_DEPARTMENT (nome do setor) e BOOTSTRAP_ADMIN_PASSWORD. Informe também
BOOTSTRAP_ADMIN_USERNAME ou BOOTSTRAP_ADMIN_EMAIL, ou ambos. Não coloque a senha na linha de comando,
no repositório ou no histórico do terminal. Não use DevelopmentAdminSeed em produção.

Exemplo PowerShell (solicita os dados e limpa as variáveis temporárias ao terminar):

    $env:BOOTSTRAP_ADMIN_NAME = Read-Host 'Nome do administrador'
    $env:BOOTSTRAP_ADMIN_DEPARTMENT = Read-Host 'Setor do administrador'
    $env:BOOTSTRAP_ADMIN_USERNAME = Read-Host 'Usuário / matrícula (opcional se houver e-mail)'
    $env:BOOTSTRAP_ADMIN_EMAIL = Read-Host 'E-mail (obrigatório se não houver usuário)'
    $bootstrapSecret = Read-Host 'Senha inicial' -AsSecureString
    try {
        $env:BOOTSTRAP_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $bootstrapSecret).Password
        php bin/cake.php create_admin
    } finally {
        foreach ($key in @('BOOTSTRAP_ADMIN_NAME', 'BOOTSTRAP_ADMIN_DEPARTMENT', 'BOOTSTRAP_ADMIN_USERNAME', 'BOOTSTRAP_ADMIN_EMAIL', 'BOOTSTRAP_ADMIN_PASSWORD')) {
            [Environment]::SetEnvironmentVariable($key, $null, 'Process')
        }
        $bootstrapSecret.Dispose()
    }

O comando respeita as validações e o hash normais da aplicação, exige troca de
senha e registra auditoria sem senha/hash. Verificação e criação são atômicas.
Qualquer ADMIN_PCM existente, mesmo inativo, impede nova criação; o comando não
redefine senhas nem reativa contas. Fora de produção, a execução exige a opção
explícita --confirm-non-production.

## Homologação — apontamentos existentes e setores de usuários
Execute `php bin/cake.php migrations migrate` para aplicar AddUserDepartments e AddRequestActivity.
O PCM cadastra os departamentos em Administração → Setores dos usuários, separados de Mãos de obra.
Novos usuários e alterações administrativas exigem um setor ativo. Usuários antigos ficam sem
atribuição presumida e continuam acessando/trocando senha; regularize-os pela edição do cadastro.
O primeiro administrador informa BOOTSTRAP_ADMIN_DEPARTMENT: o comando reutiliza o setor ou o cria
com auditoria na mesma transação. O seed de desenvolvimento exige ADMIN_SEED_DEPARTMENT.

O alerta do Bem oferece “Fazer apontamento nesta O.S.” quando o trabalho compartilhado tem O.S.
vinculada e aceita execução. A tela solicita apenas os dados do apontamento; preserva o pedido e
registra usuário, horário e last_activity_at. Na fila, encerramento pendente, aguardando PCM e
análise conservam prioridade; O.S. aberta/execução formam uma fila por atividade mais recente.
Trabalhos finalizados continuam abaixo. Não há escrita no TOTVS.

## Simplificação operacional do PCM
Execute `php bin/cake.php migrations migrate` para aplicar SimplifyPcmAndAudit,
depois `php bin/cake.php cache clear_all` para atualizar os metadados das tabelas.
A migration verifica vínculos de O.S. duplicados antes de alterar a estrutura e
interrompe indicando as solicitações envolvidas, sem corrigir os vínculos automaticamente.
O índice único considera o número sem espaços externos e permite múltiplos NULL.

Não há mais ação de assumir. Pedidos antigos em análise retornam para Aguardando PCM
com evento de histórico; responsável e horário antigos permanecem no banco, sem controlar o fluxo.
O PCM vincula a O.S. diretamente, cancela com motivo ou confirma encerramento.

Novos eventos guardam actor_snapshot (ID, nome, usuário/matrícula, e-mail e perfil),
old_status, new_status e data/hora, além dos detalhes existentes. A linha do tempo usa
o nome histórico; e-mail fica nos detalhes de auditoria exclusivos do PCM. Registros
antigos sem snapshot são identificados como legado; não se presume sua identidade histórica.

/painel apresenta apenas estados ativos, sem filtros e sem contadores de finalizadas.
Encerramento pendente e Aguardando PCM têm prioridade; a execução considera atividade
recente. Criação, apontamento e transições atualizam last_activity_at. last_item_at
mantém o destaque de apontamento correto mesmo após outras ações.
/relatorios concentra filtros, incluindo tipo de manutenção, número da O.S. e setor
atual do solicitante. /minhas-solicitacoes mantém o histórico pessoal completo.

Polling autenticado a cada 30 segundos atualiza somente contadores/fila, até 20 registros
por página. Mantém página, rolagem e foco; não interrompe entrada de dados nem seleção de texto,
pausa em aba oculta e informa falhas discretamente. Permissões e primeiro acesso são revalidados.

Sessão usa arquivos isolados em tmp/sessions, com timeout=0 e GC automático desativado.
Não limpar essa pasta durante publicações nem sujeitá-la à limpeza de sessões compartilhadas
do PHP/sistema operacional. Permita leitura/escrita somente à conta do processo PHP.
Cookies são HttpOnly, SameSite=Lax e Secure em HTTPS (inclusive CSRF); configure
APP_FULL_BASE_URL com HTTPS na produção. Cookie de sessão permanece válido até fechar o
navegador; logout manual e bloqueio de contas continuam funcionando. Mudança do nome/caminho
do cookie nesta atualização pode exigir um novo login na primeira utilização.

## Automação de O.S. TOTVS e sessão de 20 minutos
Execute `php bin/cake.php migrations migrate` para aplicar AddTotvsOrderSync e
`php bin/cake.php cache clear_all` para atualizar metadados após publicar.

O PCM continua criando a O.S. no TOTVS e vinculando seu número manualmente.
Acompanhar: executar `php bin/cake.php sync_totvs_orders` a cada 2 minutos.
Nenhum cron foi criado automaticamente. Exemplo a adaptar ao servidor:

    */2 * * * * cd /var/www/pcm-solicitacao-os && /usr/bin/php bin/cake.php sync_totvs_orders >> logs/totvs-sync-cron.log 2>&1

A sincronização consulta apenas números vinculados em trabalhos ativos com acompanhamento,
em lotes de até 50. Usa dbo.STJ010: TJ_ORDEM, TJ_FILIAL, TJ_CODBEM, TJ_SITUACA e TJ_TERMINO,
excluindo D_E_L_E_T_='*'. A regra da referência é L/N = aberta e L/S = encerrada.
C/P, outros códigos, O.S. ausente, filial/Bem divergentes ou identidade duplicada são inconclusivos:
não provocam encerramento nem mudança de estado. Erros são registrados sem credenciais/query.
Login e consulta externos têm timeout de 5 segundos; todos os SQL pertencem à allowlist SELECT.

Filial é confirmada com o Bem e armazenada em totvs_os_branch. Para Bem de cadastro compartilhado
(filial vazia), exige-se resultado único; não há escolha de filial arbitrária.
Somente abertura permanece Atendida e não entra na sincronização. Nos outros dois fluxos,
TOTVS aberto mantém Serviço em andamento e permite apontamentos; TOTVS encerrado gera Encerrada,
closed_at/totvs_closed_detected_at com o horário da detecção e auditoria Sistema/Sincronização TOTVS.
A orientação de poder encerrar e o clique em Serviço concluído continuam no histórico, mas
não fecham uma O.S. no TOTVS. Confirmação manual local de encerramento foi removida.
Operações inconclusivas/indisponíveis preservam o estado atual; reexecução sem mudança não duplica auditoria.

O número da O.S. em Relatórios abre detalhe interno sob demanda, somente para perfis com acesso
a relatórios. Exibe serviço, Bem, descrição, datas, filial, situação, término, área e centro de custo.
Nomes vêm de ST9010/ST4010, priorizando a filial local e admitindo cadastro compartilhado vazio;
cadastros ambíguos não são apresentados como identificações confirmadas. Não há cópia integral para PostgreSQL.

Mão de obra usa select obrigatório somente com opções ativas; o cadastro administrativo é preservado.
Câmera e galeria têm entradas separadas. capture=environment favorece câmera traseira quando suportado;
no navegador compatível, fotos sucessivas da câmera são acumuladas e podem ser limpas. O limite é
5 imagens no total de até 15 MB cada, com extensão, MIME real, conteúdo e armazenamento privado validados.
Configure PHP/FPM com upload_max_filesize=15M, post_max_size=80M e max_file_uploads=10;
Nginx com client_max_body_size 80m. Reinicie/recarregue o serviço após ajustar. O PHP local consultado
ainda usa post_max_size=40M, insuficiente para cinco imagens no limite simultaneamente.

A configuração vigente substitui a sessão sem timeout: Session.timeout=20. IdleSessionMiddleware
mantém AuthActivity.last_at como relógio humano e desliga o relógio HTTP nativo por requisição,
pois ele contaria polling como atividade. Polling nunca renova esse relógio; acessos normais renovam.
Interações reais (toque, teclado, digitação, rolagem) podem enviar POST /sessao/atividade com CSRF,
no máximo uma vez por minuto; não existe timer de renovação automática. Aos 20 minutos, Auth é
invalidado e há redirecionamento/mensagem natural de expiração. Primeiro acesso, inatividade,
desativação de conta e logout seguem as proteções existentes. Cookies HttpOnly, Secure em HTTPS
e SameSite=Lax permanecem; arquivos de sessão ficam isolados em tmp/sessions com GC habilitado.
