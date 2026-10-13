'use strict';
(() => {
    const url = document.querySelector('meta[name="session-activity-url"]')?.content;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!url || !token) return;
    let last = Date.now(), busy = false;
    const activity = async event => {
        // Somente eventos humanos; não há timer/heartbeat automático que prolongue a sessão.
        if (!event.isTrusted || document.hidden || busy || Date.now() - last < 60000) return;
        busy = true; last = Date.now();
        try {
            const response = await fetch(url, {method: 'POST', headers: {'X-CSRF-Token': token}, cache: 'no-store'});
            if (response.redirected) window.location.assign(response.url);
        }
        catch (_) { /* O próximo acesso exibirá eventual indisponibilidade/expiração. */ }
        finally { busy = false; }
    };
    for (const name of ['pointerdown', 'keydown', 'input', 'wheel']) document.addEventListener(name, activity, {passive: true});
})();
