<?php $this->assign('title', 'Alterar senha'); ?>
<section class="panel password-panel"><h1>Alterar senha</h1>
<?php if ($this->getRequest()->getAttribute('identity')->get('must_change_password')): ?><p>Defina sua senha pessoal antes de continuar. A senha recebida do PCM é provisória.</p><?php endif; ?>
<?= $this->Form->create(null, ['autocomplete' => 'off']) ?>
<?= $this->Form->control('current_password', ['type' => 'password', 'label' => 'Senha atual / provisória', 'required' => true, 'autocomplete' => 'current-password', 'value' => '']) ?>
<?= $this->Form->control('new_password', ['type' => 'password', 'label' => 'Nova senha pessoal', 'required' => true, 'minlength' => 6, 'autocomplete' => 'new-password', 'value' => '']) ?>
<?= $this->Form->control('confirmation', ['type' => 'password', 'label' => 'Repita a nova senha', 'required' => true, 'autocomplete' => 'new-password', 'value' => '']) ?>
<p class="help">Use pelo menos 6 caracteres. Você pode usar uma senha longa, sem combinações obrigatórias.</p>
<?= $this->Form->button('Salvar nova senha') ?>
<?= $this->Form->end() ?>
</section>
