<?php $this->assign('title', 'Acesso ao sistema'); ?>
<section class="login-panel" aria-labelledby="titulo">
    <?php if (is_file(WWW_ROOT . 'img' . DS . 'biolasa-logo.png')): ?><?= $this->Html->image('biolasa-logo.png', ['alt' => 'Biolasa Energia Renovável', 'class' => 'login-logo']) ?><?php endif; ?>
    <h1 id="titulo">Solicitação de Ordem de Serviço</h1>
    <p>Registre pedidos de manutenção e acompanhe o atendimento pelo PCM.</p>
    <h2>Entrar no sistema</h2>
    <?= $this->Form->create(null, ['url' => ['controller' => 'Home', 'action' => 'login']]) ?>
    <?= $this->Form->control('login', ['label' => 'Usuário ou e-mail', 'type' => 'text', 'required' => true, 'autocomplete' => 'username', 'autocapitalize' => 'none', 'spellcheck' => 'false']) ?>
    <?= $this->Form->control('password', ['label' => 'Senha', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <?= $this->Form->button('Entrar') ?>
    <?= $this->Form->end() ?>
</section>
