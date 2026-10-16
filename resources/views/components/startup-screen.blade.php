<div id="startup-screen" class="startup-screen" role="status" aria-live="polite" aria-label="Memuat aplikasi Farm IC">
    <div class="startup-screen__brand">
        <img class="startup-screen__logo" src="/images/logo.svg" width="150" height="150" alt="" fetchpriority="high">
        <p class="startup-screen__name">Farm IC</p>
        <div class="startup-screen__dots" aria-hidden="true">
            <span style="--dot-index: 0"></span>
            <span style="--dot-index: 1"></span>
            <span style="--dot-index: 2"></span>
            <span style="--dot-index: 3"></span>
        </div>
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
