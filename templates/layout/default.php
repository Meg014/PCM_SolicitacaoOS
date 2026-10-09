<?php
$identity = $this->getRequest()->getAttribute('identity');
$forced = $identity && $identity->get('must_change_password');
$role = $identity ? $identity->get('role') : null;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <?= $this->Html->meta('icon', 'img/icon.png', ['type' => 'image/png']) ?>
    <link rel="apple-touch-icon" href="<?= h($this->Url->image('icon.png')) ?>">
    <title><?= h($this->fetch('title') ?: 'Solicitação de serviços') ?> · PCM</title>
    <?= $this->Html->css('app') ?>
    <?= $this->Html->script('navigation', ['defer' => true]) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
    <header class="app-header"><div class="container header-inner">
        <?= $this->Html->link('PCM', ['controller' => 'Home', 'action' => 'index'], ['class' => 'brand']) ?>
        <span class="app-name">Solicitação de Serviços</span>
        <?php if ($identity): ?>
        <?php if (!$forced): ?>
        <?= $this->Html->link('Solicitar Ordem de Serviço', ['controller' => 'Requests', 'action' => 'add'], ['class' => 'button header-request-shortcut']) ?>
        <?php endif; ?>
        <button type="button" class="menu-toggle secondary" aria-controls="main-navigation" aria-expanded="true">Menu</button>
        <nav id="main-navigation" aria-label="Navegação principal">
            <?php if (!$forced): ?>
                <?php if (\App\Domain\Enum\UserRole::tryFrom($role)?->hasDashboard()): ?>
                    <?= $this->Html->link('Início', ['controller' => 'Home', 'action' => 'index']) ?>
                <?php endif; ?>
                <?php if (\App\Domain\Enum\UserRole::tryFrom($role)?->hasReports()): ?>
                    <?= $this->Html->link('Indicadores', ['controller' => 'Reports', 'action' => 'indicators']) ?>
                    <?= $this->Html->link('Relatórios', ['controller' => 'Reports', 'action' => 'index']) ?>
                <?php endif; ?>
                <?= $this->Html->link('Nova Ordem de Serviço', ['controller' => 'Requests', 'action' => 'add']) ?>
                <?= $this->Html->link('Minhas solicitações', ['controller' => 'Requests', 'action' => 'index']) ?>
                <?= $this->Html->link('Acompanhar execução', ['controller' => 'Requests', 'action' => 'work']) ?>
                <?php if ($role === \App\Domain\Enum\UserRole::ADMIN_PCM->value): ?>
                <details class="admin-menu"><summary>Administração</summary><div>
                    <?= $this->Html->link('Usuários', ['controller' => 'Users', 'action' => 'index']) ?>
                    <?= $this->Html->link('Mãos de obra', ['controller' => 'Sectors', 'action' => 'index']) ?>
                </div></details>
                <?php endif; ?>
                <?= $this->Html->link('Meu perfil', ['controller' => 'Profile', 'action' => 'index']) ?>
            <?php else: ?>
                <?= $this->Html->link('Alterar senha', ['controller' => 'Profile', 'action' => 'password']) ?>
            <?php endif; ?>
            <?= $this->Form->postLink('Sair', ['controller' => 'Home', 'action' => 'logout', '_method' => 'POST']) ?>
        </nav>
        <span class="signed-user"><?= h($identity->get('name')) ?></span>
        <?php endif; ?>
    </div></header>
    <main id="conteudo" class="container">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </main>
    <footer class="container">Horários exibidos no fuso de Brasília.</footer>
</body>
</html>
