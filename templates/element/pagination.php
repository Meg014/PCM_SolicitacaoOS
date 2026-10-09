<?php if ($this->Paginator->total() > 1): ?>
<nav class="pagination" aria-label="Páginas da lista">
    <?= $this->Paginator->prev('Anterior') ?>
    <?= $this->Paginator->numbers() ?>
    <?= $this->Paginator->next('Próxima') ?>
</nav>
<?php endif; ?>
