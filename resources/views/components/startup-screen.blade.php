<div id="startup-screen" class="startup-screen" role="status" aria-live="polite" aria-label="Memuat aplikasi Farm IC">
    <div class="startup-screen__brand">
        <img class="startup-screen__logo" src="/images/logo.png" width="150" height="150" alt="" fetchpriority="high">
        <p class="startup-screen__name">Farm <span>IC</span></p>
        <svg class="startup-screen__spiral" width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true" focusable="false">
            <circle cx="32" cy="32" r="28" pathLength="100" stroke-dasharray="72 28" />
            <circle cx="32" cy="32" r="19" pathLength="100" stroke-dasharray="67 33" />
            <circle cx="32" cy="32" r="10" pathLength="100" stroke-dasharray="60 40" />
        </svg>
    </div>
    <div id="startup-error" class="startup-screen__error" hidden>
        <p>Aplikasi belum siap. Silakan coba muat ulang.</p>
        <button id="startup-retry" type="button">Muat ulang</button>
    </div>
    <noscript><p class="startup-screen__error">Aktifkan JavaScript untuk membuka Farm IC.</p></noscript>
</div>
<script>
    (() => {
        const screen = document.getElementById('startup-screen');
        const app = document.getElementById('app');
        screen.dataset.startedAt = String(performance.now());
        app?.setAttribute('inert', '');
        app?.setAttribute('aria-busy', 'true');

        // Keep recovery available even if the main JavaScript bundle fails to load.
        const watchdog = window.setTimeout(() => {
            document.getElementById('startup-error').hidden = false;
        }, 15000);
        screen.addEventListener('startup:ready', () => window.clearTimeout(watchdog), { once: true });
        document.getElementById('startup-retry').addEventListener('click', () => window.location.reload());
    })();
</script>
