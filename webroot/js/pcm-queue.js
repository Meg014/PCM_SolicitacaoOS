'use strict';
(() => {
    const queue = document.getElementById('pcm-queue');
    if (!queue) return;
    const message = document.getElementById('queue-update-message');
    let busy = false, lastInteraction = 0;
    queue.addEventListener('pointerdown', () => { lastInteraction = Date.now(); });
    queue.addEventListener('keydown', () => { lastInteraction = Date.now(); });
    const editing = () => document.activeElement?.matches('input, textarea, select, [contenteditable="true"]');
    const interacting = () => editing() || window.getSelection()?.toString() || Date.now() - lastInteraction < 2000;
    const update = async () => {
        // Não substitui controles focados ou conteúdo selecionado.
        if (busy || document.hidden || interacting()) return;
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const url = new URL(queue.dataset.refreshUrl, window.location.origin);
            const page = new URL(window.location.href).searchParams.get('page');
            if (page) url.searchParams.set('page', page);
            const response = await fetch(url, {headers: {'Accept': 'text/html'}, cache: 'no-store', signal: controller.signal});
            if (response.redirected || response.status === 403) {
                queue.replaceChildren();
                message.textContent = 'Seu acesso mudou. Entre novamente para continuar.';
                clearInterval(timer); return;
            }
            if (!response.ok) throw new Error();
            const html = await response.text();
            if (interacting() || document.hidden) return;
            const scroll = queue.querySelector('.table-scroll');
            const left = scroll?.scrollLeft || 0;
            const top = window.scrollY;
            const active = queue.contains(document.activeElement) ? document.activeElement : null;
            const href = active?.getAttribute('href');
            queue.innerHTML = html;
            if (response.headers.get('X-Queue-Page') === '1') {
                const location = new URL(window.location.href); location.searchParams.delete('page');
                window.history.replaceState(null, '', location);
            }
            const current = queue.querySelector('.table-scroll');
            if (current) current.scrollLeft = left;
            if (active) {
                const focus = active === scroll ? current : [...queue.querySelectorAll('a')].find(link => href && link.getAttribute('href') === href);
                (focus || queue).focus({preventScroll: true});
            }
            window.scrollTo({top, behavior: 'instant'});
            message.textContent = 'Fila atualizada às ' + new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) + '.';
        } catch (_) {
            message.textContent = 'Não foi possível atualizar agora. Tentaremos novamente automaticamente.';
        } finally { clearTimeout(timeout); busy = false; }
    };
    const timer = setInterval(update, 30000);
})();
