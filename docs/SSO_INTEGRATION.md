# Integração opcional com Keycloak e Central

## Logout para a Central — comportamento vigente

- O botão **Sair** (`POST /sair`, com CSRF) encerra somente a sessão CakePHP e, para sessão SSO, retorna a `PCM_CENTRAL_URL` acrescida de `/login`. Na configuração local existente, o destino é `http://localhost:3000/login`. A sessão Keycloak e a sessão da Central permanecem ativas. O login local continua saindo para `/entrar`.
- **Sair de todos os sistemas** fica separado em `POST /sair-de-todos`, usando o mesmo tratamento de sessão e CSRF. Não foi adicionado botão ao layout. Essa rota inicia o logout OIDC no Keycloak, com `client_id` e `post_logout_redirect_uri` apontando ao login da Central. Sem `id_token_hint`, o Keycloak pode pedir confirmação; concluir essa confirmação para encerrar a sessão Keycloak. A expiração de sessões próprias de outros sistemas continua sujeita às respectivas políticas, pois não há back-channel logout nesta etapa.
- No cliente Keycloak `pcm-solicitacao-os`, permitir exatamente `http://localhost:3000/login` em **Valid post logout redirect URIs** para o ambiente local. Em produção, permitir a URL HTTPS real equivalente. Manter `PCM_SSO_LOGOUT_RETURN` alinhado a esse endereço; o retorno efetivo do logout usa a URL validada da Central para evitar destinos divergentes.
- Nenhum destino é aceito de query string/formulário. A URL vem somente da configuração do servidor, rejeita credenciais, fragmentos, query, barras invertidas e protocolos inseguros fora do desenvolvimento loopback. Se a configuração ou preparação da saída falhar, a sessão local é encerrada e uma mensagem 503 é exibida, sem redirecionar a um destino inseguro.
- Verificação essencial: entrar pela Central, clicar **Sair**, conferir o login da Central e acessar o sistema novamente sem nova senha Keycloak. Testar separadamente o logout global com POST/CSRF e retorno permitido no Keycloak. As notas de preparação abaixo descrevem a configuração inicial; esta seção substitui o comportamento anterior do botão Sair.

## Conferência local — 16/11/2026

- Realm existente: `biolasa`; issuer verificado: `http://127.0.0.1:8080/realms/biolasa`. Discovery acessível e suporte S256 confirmado.
- Cliente `pcm-solicitacao-os`: confidencial, conforme confirmação do responsável. Exige o Client Secret real do Keycloak em `PCM_SSO_CLIENT_SECRET`; não reutilizar o segredo da Central.
- Preservado `APP_ENV=development` e `APP_FULL_BASE_URL=http://127.0.0.1:8766` já existentes. Servidor local iniciado nessa porta; `/entrar` responde HTTP 200, também por acesso a partir do runtime Node da Central.
- Acrescentadas somente variáveis ausentes nos `.env` locais, sem substituir configurações existentes. Callback preparado: `http://127.0.0.1:8766/sso/callback`; retorno de logout preparado: `http://127.0.0.1:8766/entrar`. O Keycloak rejeitou esse callback com erro de `redirect_uri`; conferir os valores reais cadastrados antes de habilitar. Não foi possível confirmar o cadastro exato de retorno de logout.
- Central existente: `http://localhost:3000`; issuer coincide com o CakePHP. Endpoint `/api/internal/system-access` acessível, mas responde 503 com a integração desabilitada/sem chave. Comunicação HMAC autenticada ainda não homologada.
- Migrations `CreateSsoIdentities` e `integration_decisions` já aplicadas nos bancos locais configurados; nenhuma migration precisou ser aplicada nesta conferência.
- Segredos deixados vazios quando ausentes. Preencher localmente o Client Secret e uma chave de integração exclusiva, igual em `PCM_CENTRAL_INTEGRATION_SECRET` (CakePHP) e `PCM_OS_INTEGRATION_SECRET` (Central), com pelo menos 32 caracteres aleatórios. Não enviar esses valores em mensagens nem versioná-los.
- Após confirmar callback/logout no Keycloak e preencher os segredos, habilitar **somente no ambiente local** `PCM_OS_INTEGRATION_ENABLED=true` e `PCM_SSO_ENABLED=true`; reiniciar a Central para carregar seu `.env`. A implementação aceita HTTP exclusivamente em loopback no desenvolvimento.
- Cadastrar na Central a entrada local `http://127.0.0.1:8766/sso`, conferir vínculos explícitos e permissão existente, sem alterar contas automaticamente. O endereço em `8765`, aberto anteriormente, não é o endereço configurado para esta homologação.
- Enquanto essas pendências existem, `/sso` retorna 404 por estar desabilitado e `/entrar` continua disponível. Nenhuma autenticação real nem alteração de usuário foi realizada; produção permanece inalterada.

Código preparado, desabilitado por padrão. Nenhuma ativação ou alteração de cadastro em produção foi executada. Layout, login local, perfis, PostgreSQL funcional e TOTVS foram preservados. O endpoint `/sso` é a entrada cadastrada na Central; `/entrar` mantém o login local.

## Keycloak

No mesmo realm da Central, criar um cliente **OpenID Connect** separado:

- Client ID: `pcm-solicitacao-os` (não usar `pcm-central`).
- Standard flow: habilitado; implicit flow, direct access grants e service accounts: desabilitados.
- PKCE Code Challenge Method: `S256`, obrigatório (Advanced settings/client policy, conforme versão).
- Client authentication: habilitado para cliente confidencial; copiar o segredo somente para `PCM_SSO_CLIENT_SECRET`. Cliente público também é aceito com segredo vazio e PKCE obrigatório.
- ID token signature algorithm: `RS256`.
- Valid redirect URIs: URL exata de `PCM_SSO_CALLBACK`, por exemplo `https://solicitacao-os.empresa.example/sso/callback`, incluindo subpasta real se existir. Sem curingas.
- Valid post logout redirect URIs: login da Central, igual a `PCM_CENTRAL_URL` acrescida de `/login`, por exemplo `https://central.empresa.example/login`.
- Web origins: vazio (integração feita no servidor, sem CORS).
- Root/Home URL: origem/base HTTPS real do CakePHP. O `iss` deve corresponder exatamente ao issuer configurado, inclusive barra final se houver.

O cliente usa a biblioteca [Jumbojett OpenID Connect PHP](https://github.com/jumbojett/OpenID-Connect-PHP), com adaptações para a sessão CakePHP, claims obrigatórias, RS256 exclusivo e transporte sem redirecionamentos HTTP. Tokens ficam somente em memória; não há refresh automático. O logout local via POST com CSRF sempre encerra a sessão CakePHP; Keycloak pode pedir confirmação do logout global porque não se envia `id_token_hint` ao navegador. Logout iniciado em outra aplicação/Keycloak não invalida imediatamente a sessão CakePHP: ela termina no `exp` do ID token ou por inatividade. Não há back-channel logout nesta etapa.

## Ambiente e migrations

CakePHP: usar `config/sso.env.example` como referência e carregar as variáveis no ambiente/configuração local existente. Configurar issuer, callback, retorno, URL da Central e segredo. `PCM_CENTRAL_INTEGRATION_SECRET` precisa ser igual ao `PCM_OS_INTEGRATION_SECRET` da Central; gerar valor aleatório exclusivo com pelo menos 32 caracteres. Nunca versionar os valores reais.

Central: complementar o ambiente com `integration.env.example`. Preservar suas configurações OIDC existentes. `OIDC_ISSUER` deve ser exatamente igual ao issuer do CakePHP. Usar HTTPS, cookies Secure/HttpOnly/SameSite=Lax, `APP_ENV=production` e URL/base reais já configuradas no CakePHP. O código rejeita loopback e HTTP em produção. Para desenvolvimento isolado: `APP_ENV=development` no CakePHP; apenas HTTP loopback é aceito. Na Central, manter a separação de desenvolvimento do OIDC já existente. Não usar configuração local em implantação corporativa.

Aplicar manualmente, primeiro em homologação:

```powershell
# CakePHP
composer install
php bin/cake.php migrations migrate
# Central
npm.cmd ci
npx.cmd prisma migrate deploy
npx.cmd prisma generate
```

Migrations novas: CakePHP `20261116000100_CreateSsoIdentities`; Central `20261116000100_integration_decisions`. As migrations não foram executadas nos bancos oficiais. O teste CakePHP migra exclusivamente o banco de testes configurado. Na estação atual o PHP é 8.2.12; homologar implantação no PHP 8.3+ exigido pelo projeto. A geração Prisma encontrou DLL em uso na estação; com o processo da Central parado em uma janela de manutenção, repetir `prisma generate` antes do build. Não encerrar processos automaticamente.

## Vínculo e cadastro na Central

1. No Keycloak, conferir o ID imutável do usuário (`sub`). Confirmar a identidade com o responsável; não inferir por e-mail/nome.
2. Vincular a identidade na Central pelo fluxo administrativo/de perfil já existente. O usuário e a permissão precisam estar ativos.
3. No servidor CakePHP, operador administrativo executa o comando abaixo usando IDs locais existentes. `--actor-id` é o ADMIN_PCM ativo responsável pela conferência, não é autenticação do terminal: a operação exige acesso administrativo ao servidor. A operação grava auditoria e não substitui vínculos, cria contas nem muda perfis.

```powershell
php bin/cake.php link_sso_identity 42 'SUB-EXATO-DO-KEYCLOAK' --actor-id 1 --reason 'Identidade conferida pelo responsável; chamado 123'
```

4. Na edição do sistema da Central: URL real = `https://solicitacao-os.empresa.example/sso` (com eventual subpasta real); identificador da aplicação = `pcm-solicitacao-os`; autenticação = OIDC; integração OIDC confirmada; sistema ativo. Conceder permissão ao usuário explicitamente. O botão existente abre essa URL sem precisar alterar layout ou componentes.
5. Somente depois da preparação e homologação, habilitar manualmente `PCM_OS_INTEGRATION_ENABLED=true` na Central e `PCM_SSO_ENABLED=true` no CakePHP.

Se um vínculo foi informado incorretamente, desativar a conta/revogar sua permissão imediatamente. Correção de vínculo exige manutenção administrativa auditada; não existe substituição automática nesta entrega.

## Autorização, revogação e auditoria

`POST /api/internal/system-access` recebe applicationId, issuer e subject; exige HMAC-SHA256 do corpo exato, timestamp com tolerância de 30 segundos e nonce aleatório de 32 bytes (hex). Assinatura sobre `timestamp + LF + nonce + LF + corpo`. Chave exclusiva deste sistema. O nonce é único no PostgreSQL e bloqueia replay concorrente. Sincronizar relógios dos servidores. Não expor o segredo ao browser. Restringir a rota na rede ao servidor CakePHP quando possível.

Somente a identidade vinculada e usuário, sistema e permissão ativos autorizam; administrador da Central não recebe permissão implícita. Toda decisão de consulta válida é persistida em `IntegrationDecision`; vínculo, login, falha, bloqueio e logout CakePHP usam `registration_audits`. Nenhum token ou segredo é auditado. Não apagar decisões sem política de retenção: o nonce participa da proteção contra replay.

A sessão SSO verifica vínculo, conta local ativa, expiração e autorização na Central **em toda requisição privada**, inclusive polling. Não usa cache positivo ou fallback de autorização. Revogação reflete na próxima requisição; resposta já iniciada pode concluir. Falha/timeout da Central nega acesso e encerra a sessão SSO; será necessário entrar novamente. Perfis e troca obrigatória de senha locais continuam vigentes. Login local permanece independente para esta etapa; portanto revogar apenas na Central não desativa a credencial local. Para bloquear ambos os canais, desativar também a conta CakePHP.

## Teste completo em homologação

1. Executar os testes direcionados abaixo. Conferir as migrations dos dois bancos e o cliente Keycloak.
2. Usuário ativo, vinculado nas duas aplicações e com permissão: entrar na Central e clicar Acessar sistema. Deve chegar à página inicial CakePHP adequada ao perfil, sem senha adicional quando a sessão Keycloak existe. Perfis com troca de senha pendente seguem a política existente. Confirmar sessão própria e auditoria.
3. Sem sessão Keycloak: abrir `/sso`, autenticar no Keycloak e confirmar retorno exato. Sem vínculo CakePHP ou Central: negar; mesmo e-mail/nome jamais deve vincular nem criar conta. Confirmar que perfil não mudou.
4. Alterar/remover state, omitir/repetir callback, usar callback após 5 minutos ou nonce/token de outro cliente/issuer: negar e não criar sessão. Assinatura inválida e algoritmos diferentes de RS256 devem falhar.
5. Revogar a permissão na Central com CakePHP aberto: próxima navegação/poll deve retornar 403 e limpar sessão. Testar também usuário/sistema inativo e remoção de vínculo. Restabelecer permissão e entrar novamente.
6. Indisponibilizar a Central: novos logins e próximas requisições SSO devem falhar fechados. Acessar diretamente `/painel` sem sessão não concede acesso. O login local continua testável por `/entrar`.
7. Sair pelo botão existente: POST protegido por CSRF limpa apenas a sessão CakePHP e retorna ao login da Central, mantendo Keycloak. Testar o logout global separadamente por `POST /sair-de-todos`, concluir a confirmação Keycloak e conferir retorno à Central. Sem CSRF, logout deve ser recusado. Testar também inatividade de 20 minutos e expiração do ID token.

```powershell
# CakePHP: banco de testes separado
php vendor/bin/phpunit tests/TestCase/Service/SsoSecurityTest.php tests/TestCase/Service/SsoSessionGuardTest.php tests/TestCase/Controller/SsoControllerTest.php tests/TestCase/Controller/HomeControllerTest.php tests/TestCase/Command/LinkSsoIdentityCommandTest.php
# Central: testes isolados sem banco
npx.cmd tsx scripts/test-system-access.ts
npm.cmd run typecheck
```

Os testes automatizados usam mocks no limite da Central e da persistência do endpoint; não substituem o fluxo real com Keycloak, TLS e as duas aplicações. Nenhum teste real contra o realm corporativo foi executado nesta entrega. `composer audit` identificou um aviso na dependência preexistente `symfony/filesystem`; não pertence à biblioteca OIDC adicionada e deve ser tratado separadamente.
