'use strict';

function initEmployeeAutocomplete(group) {
    if (!group || group.dataset.employeeAutocompleteReady === 'true') return;
    group.dataset.employeeAutocompleteReady = 'true';

    const input = group.querySelector('[data-search]');
    const selected = group.querySelector('[data-selected]');
    const list = group.querySelector('[role="listbox"]');
    const message = group.querySelector('.lookup-message');
    let timer, controller, version = 0, active = -1;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const choose = employee => {
        if (typeof employee.registration !== 'string' || typeof employee.label !== 'string') return;
        clearTimeout(timer);
        if (controller) controller.abort();
        ++version;
        input.value = employee.label;
        selected.value = employee.registration;
        input.setCustomValidity('');
        message.textContent = 'Selecionado: ' + employee.label;
        close();
        input.dispatchEvent(new Event('change', {bubbles: true}));
        input.focus();
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (controller) controller.abort();
        const current = ++version;
        selected.value = '';
        input.setCustomValidity('');
        close();
        list.replaceChildren();

        const term = input.value.trim();
        if (term.length < 2 || term.length > 60) {
            message.textContent = term.length > 60
                ? 'Use até 60 caracteres para pesquisar.'
                : 'Digite pelo menos 2 caracteres.';
            return;
        }

        message.textContent = 'Pesquisando…';
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const url = new URL(group.dataset.lookup, window.location.href);
                url.searchParams.set('q', term);
                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: {Accept: 'application/json'},
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (current !== version) return;
                if (response.redirected) throw new Error('Entre novamente no sistema para continuar.');

                const data = await response.json();
                if (current !== version) return;
                if (!response.ok) throw new Error(data.error || 'Não foi possível consultar os funcionários.');
                if (!data || !Array.isArray(data.results)) throw new Error('Resposta inválida na consulta de funcionários.');

                const employees = data.results.filter(employee =>
                    employee && typeof employee.registration === 'string' &&
                    typeof employee.label === 'string'
                );
                employees.forEach((employee, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.id = list.id + '-' + index;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.className = 'd-block w-100 text-start border-0 border-bottom p-3 bg-white';
                    option.textContent = employee.label;
                    option.addEventListener('mousedown', event => event.preventDefault());
                    option.addEventListener('click', () => choose(employee));
                    list.append(option);
                });
                list.hidden = employees.length === 0;
                input.setAttribute('aria-expanded', String(employees.length > 0));
                message.textContent = employees.length
                    ? 'Selecione um resultado. Use as setas e Enter ou toque no item.'
                    : 'Nenhum funcionário encontrado.';
            } catch (error) {
                if (error.name !== 'AbortError' && current === version) {
                    close();
                    message.textContent = error.message;
                }
            }
        }, 300);
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            clearTimeout(timer);
            if (controller) controller.abort();
            ++version;
            close();
            return;
        }
        const options = [...list.querySelectorAll('button')];
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
        if (group.isConnected && !input.disabled && input.required && !selected.value) {
            event.preventDefault();
            input.setCustomValidity('Pesquise e selecione um funcionário da lista.');
            input.reportValidity();
        }
    });
}

document.querySelectorAll('.employee-field').forEach(initEmployeeAutocomplete);
