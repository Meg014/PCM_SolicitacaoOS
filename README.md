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
Fotos são opcionais na criação: até 5 imagens JPG, PNG ou WebP de até 5 MB.
Não são aceitos executáveis, SVG ou HEIC. Metadados e auditoria ficam no
PostgreSQL; arquivos ficam em storage/attachments, fora de webroot.
Inclua essa pasta nos backups e permita escrita apenas ao processo PHP.
Não exponha storage como diretório público ou alias do servidor web.
Se o formulário precisar ser corrigido, selecione as fotos novamente.

/indicadores e /relatorios são exclusivos do PCM e dos gestores.
O período considera a criação do pedido; a média até vínculo usa somente
pedidos com data da OS vinculada. O relatório é tabular e paginado, sem CSV/PDF.

Execute php bin/cake.php migrations migrate para aplicar CreateRequestAttachments.
Configuração PHP mínima para estes uploads: upload_max_filesize=5M,
post_max_size=30M e max_file_uploads=5. Não há dependência adicional do TOTVS.

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
BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_USERNAME, BOOTSTRAP_ADMIN_PASSWORD e,
opcionalmente, BOOTSTRAP_ADMIN_EMAIL. Não coloque a senha na linha de comando,
no repositório ou no histórico do terminal. Não use DevelopmentAdminSeed em produção.

Exemplo PowerShell (solicita os dados e limpa as variáveis temporárias ao terminar):

    $env:BOOTSTRAP_ADMIN_NAME = Read-Host 'Nome do administrador'
    $env:BOOTSTRAP_ADMIN_USERNAME = Read-Host 'Usuário'
    $env:BOOTSTRAP_ADMIN_EMAIL = Read-Host 'E-mail (opcional)'
    $bootstrapSecret = Read-Host 'Senha inicial' -AsSecureString
    try {
        $env:BOOTSTRAP_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $bootstrapSecret).Password
        php bin/cake.php create_admin
    } finally {
        foreach ($key in @('BOOTSTRAP_ADMIN_NAME', 'BOOTSTRAP_ADMIN_USERNAME', 'BOOTSTRAP_ADMIN_EMAIL', 'BOOTSTRAP_ADMIN_PASSWORD')) {
            [Environment]::SetEnvironmentVariable($key, $null, 'Process')
        }
        $bootstrapSecret.Dispose()
    }

O comando respeita as validações e o hash normais da aplicação, exige troca de
senha e registra auditoria sem senha/hash. Verificação e criação são atômicas.
Qualquer ADMIN_PCM existente, mesmo inativo, impede nova criação; o comando não
redefine senhas nem reativa contas. Fora de produção, a execução exige a opção
explícita --confirm-non-production.