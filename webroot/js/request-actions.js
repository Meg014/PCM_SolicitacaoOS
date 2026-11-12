'use strict';

// Delegação também atende à fila substituída pelo polling.
document.addEventListener('click', event => {
    const opener = event.target.closest('[data-delete-dialog]');
    if (opener) {
        const dialog = document.getElementById(opener.dataset.deleteDialog);
        if (dialog && !dialog.open) dialog.showModal();
    }
    const cancel = event.target.closest('[data-delete-cancel]');
    if (cancel) cancel.closest('dialog').close();
});
