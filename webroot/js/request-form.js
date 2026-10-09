'use strict';

const photos = document.getElementById('photos');
if (photos) {
    const help = document.getElementById('photos-help');
    const originalHelp = help.textContent;
    photos.addEventListener('change', () => {
        const files = [...photos.files];
        const message = files.length > 5 ? 'Selecione no máximo 5 fotos.'
            : files.some(file => file.size > 5 * 1024 * 1024) ? 'Cada foto deve ter até 5 MB.'
            : files.some(file => !/\.(jpe?g|png|webp)$/i.test(file.name)) ? 'Use fotos JPG, PNG ou WebP.' : '';
        photos.setCustomValidity(message);
        help.textContent = message || (files.length ? files.length + ' foto(s) selecionada(s). ' + originalHelp : originalHelp);
        if (message) photos.reportValidity();
    });
}

document.querySelectorAll('.autocomplete').forEach(group => {
    const input = group.querySelector('[data-search]');
    const selected = group.querySelector('[data-selected]');
    const branch = group.querySelector('[data-branch]');
    const list = group.querySelector('[role="listbox"]');
    const message = group.querySelector('.lookup-message');
    let timer, controller, version = 0, active = -1;
    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const choose = item => {
        const asset = group.dataset.kind === 'asset';
        input.value = asset ? item.description + ' (' + item.code + ')' : item.name;
        selected.value = asset ? item.code : item.id;
        if (branch) branch.value = item.branch;
        if (asset) input.dispatchEvent(new CustomEvent('asset-selected', {detail: item}));
        message.textContent = 'Selecionado: ' + input.value;
        input.setCustomValidity('');
        close();
        input.focus();
    };
    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (controller) controller.abort();
        const current = ++version;
        selected.value = '';
        if (branch) branch.value = '';
        if (branch) input.dispatchEvent(new CustomEvent('asset-cleared'));
        input.setCustomValidity('');
        close();
        list.replaceChildren();
        const term = input.value.trim();
        if (term.length < 2) { message.textContent = 'Digite pelo menos 2 caracteres.'; return; }
        if (term.length > 60) { message.textContent = 'Use até 60 caracteres para pesquisar.'; return; }
        message.textContent = 'Pesquisando…';
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const url = new URL(group.dataset.lookup, window.location.origin);
                url.searchParams.set('q', term);
                const response = await fetch(url, {signal: controller.signal, headers: {'Accept': 'application/json'}});
                if (current !== version) return;
                if (response.redirected) throw new Error('Entre novamente no sistema para continuar.');
                const data = await response.json();
                if (current !== version) return;
                if (!response.ok) throw new Error(data.error || 'Não foi possível pesquisar agora. Tente novamente.');
                const rows = data.results;
                list.replaceChildren();
                rows.forEach((item, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.id = list.id + '-' + index;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = group.dataset.kind === 'asset'
                        ? item.description + ' (' + item.code + ')'
                        : item.name;
                    option.addEventListener('mousedown', event => event.preventDefault());
                    option.addEventListener('click', () => choose(item));
                    list.append(option);
                });
                list.hidden = rows.length === 0;
                input.setAttribute('aria-expanded', String(rows.length > 0));
                message.textContent = rows.length ? 'Selecione um resultado. Use as setas e Enter ou toque no item.' : 'Nenhum resultado. Tente outro código ou nome.';
            } catch (error) {
                if (error.name !== 'AbortError' && current === version) {
                    close();
                    message.textContent = error.message;
                }
            }
        }, 250);
    });
    input.addEventListener('keydown', event => {
        const options = [...list.children];
        if (event.key === 'Escape') { close(); return; }
        if (list.hidden || !options.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
            input.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({block: 'nearest'});
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            options[active].click();
        }
    });
    group.addEventListener('focusout', event => {
        if (!group.contains(event.relatedTarget)) close();
    });
    input.form.addEventListener('submit', event => {
        if (!selected.value) {
            event.preventDefault();
            input.setCustomValidity('Pesquise e selecione um item da lista.');
            input.reportValidity();
        }
    });
});

const items = document.getElementById('request-items');
const add = document.getElementById('add-item');
if (items && add) {
    const renumber = () => {
        [...items.children].forEach((item, index) => {
            const textarea = item.querySelector('textarea');
            textarea.name = 'items[' + index + '][description]';
            textarea.id = 'item-' + index;
            item.querySelector('.item-title').textContent = 'Apontamento ' + (index + 1);
            item.querySelectorAll('[data-item-field]').forEach(input => {
                const oldId = input.id;
                input.name = 'items[' + index + '][' + input.dataset.itemField + ']';
                input.id = 'item-' + index + '-' + input.dataset.itemField;
                item.querySelectorAll('label').forEach(label => { if (label.htmlFor === oldId) label.htmlFor = input.id; });
            });
            textarea.previousElementSibling.htmlFor = textarea.id;
            item.querySelector('.remove-item').disabled = items.children.length === 1 && !document.getElementById('os-disposition');
        });
        add.disabled = items.children.length >= 50;
    };
    add.addEventListener('click', () => {
        if (items.children.length >= 50) return;
        const item = items.firstElementChild.cloneNode(true);
        item.querySelector('textarea').value = '';
        item.querySelectorAll('input').forEach(input => { input.value = ''; });
        items.append(item);
        renumber();
        item.querySelector('textarea').focus();
        document.getElementById('items-message').textContent = 'Apontamento adicionado.';
    });
    items.addEventListener('click', event => {
        if (!event.target.classList.contains('remove-item')) return;
        if (items.children.length === 1) {
            if (!document.getElementById('os-disposition')) return;
            event.target.closest('.request-item').querySelectorAll('input, textarea').forEach(field => { field.value = ''; field.required = false; });
            return;
        }
        event.target.closest('.request-item').remove();
        renumber();
        add.focus();
        document.getElementById('items-message').textContent = 'Apontamento removido.';
    });
    renumber();
}

const flow = document.getElementById('os-disposition');
if (flow) {
    const fieldset = document.getElementById('execution-initial');
    const updateItems = () => {
        const choice = flow.querySelector('input:checked');
        fieldset.hidden = !choice || choice.value === 'SOMENTE_ABERTURA';
        fieldset.querySelectorAll('input, textarea, button').forEach(field => { field.disabled = fieldset.hidden; });
        fieldset.querySelectorAll('.request-item').forEach(row => {
            const fields = [...row.querySelectorAll('input, textarea')];
            const filled = fields.some(field => field.value.trim() !== '');
            fields.forEach(field => { field.required = !fieldset.hidden && filled; });
        });
    };
    flow.addEventListener('change', updateItems);
    fieldset.addEventListener('input', updateItems);
    fieldset.addEventListener('click', () => queueMicrotask(updateItems));
    updateItems();
}

const alertBox = document.getElementById('active-alert');
if (alertBox) {
    const assetGroup = document.querySelector('[data-kind="asset"]');
    const input = assetGroup.querySelector('[data-search]');
    const checkbox = document.getElementById('confirm-existing');
    const rowsBox = document.getElementById('active-requests');
    let version = 0;
    const clear = () => {
        ++version; alertBox.hidden = true; checkbox.checked = false; checkbox.required = false; rowsBox.replaceChildren();
    };
    const check = async item => {
        clear(); const current = version;
        const url = new URL(alertBox.dataset.url, window.location.origin);
        url.searchParams.set('code', item.code); url.searchParams.set('branch', item.branch);
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            if (current !== version) return;
            if (response.redirected || !response.ok) throw new Error();
            const data = await response.json();
            if (current !== version) return;
            for (const row of data.results) {
                const article = document.createElement('article');
                for (const [label, value] of [['Solicitação', row.number], ['O.S.', row.os || 'Ainda não vinculada'], ['Mão de obra', row.labor], ['Solicitada em', row.created], ['Solicitante', row.requester], ['Situação', row.status], ['Descrição', row.description]]) {
                    const p = document.createElement('p'); const strong = document.createElement('strong');
                    strong.textContent = label + ': '; p.append(strong, document.createTextNode(value)); article.append(p);
                }
                if (row.url) {
                    const link = document.createElement('a'); link.href = row.url; link.textContent = 'Ver O.S. existente'; article.append(link);
                } else {
                    const p = document.createElement('p'); p.textContent = 'Consulte o PCM para detalhes deste pedido somente de abertura.'; article.append(p);
                }
                rowsBox.append(article);
            }
            alertBox.hidden = !data.results.length;
            checkbox.required = data.results.length > 0;
        } catch (_) {
            if (current !== version) return;
            const p = document.createElement('p'); p.textContent = 'Não foi possível conferir pedidos ativos agora. A verificação será feita novamente ao enviar.';
            rowsBox.append(p); alertBox.hidden = false;
        }
    };
    input.addEventListener('asset-selected', event => check(event.detail));
    input.addEventListener('asset-cleared', clear);
    const selected = assetGroup.querySelector('[data-selected]');
    if (selected.value) check({code: selected.value, branch: assetGroup.querySelector('[data-branch]').value});
}
