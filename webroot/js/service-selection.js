'use strict';
function refreshServiceSelection(root = document) {
    root.querySelectorAll('[data-service-selection]').forEach(group => {
        const action = group.querySelector('[data-service-action]');
        const component = group.querySelector('[data-service-component]');
        const preview = group.querySelector('[data-service-preview]');
        if (!action || !component || !preview) return;
        const actionName = action.selectedOptions[0]?.textContent || '';
        const componentName = (component.selectedOptions[0]?.textContent || '').toLocaleLowerCase('pt-BR');
        preview.textContent = action.value && component.value
            ? (actionName.toLocaleLowerCase('pt-BR') === 'limpar' && componentName === 'geral' ? 'Limpeza geral' : actionName + ' ' + componentName)
            : '';
    });
}
document.addEventListener('change', event => {
    if (event.target.matches('[data-service-action], [data-service-component]')) { event.target.setCustomValidity(''); refreshServiceSelection(); }
});
window.refreshServiceSelection = refreshServiceSelection;
refreshServiceSelection();
