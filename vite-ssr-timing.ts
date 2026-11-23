import { loadEnv, type Plugin } from 'vite';

/** Development-only server-side timing for Inertia's Vite SSR endpoint. */
export function viteSsrTiming(): Plugin {
    return {
        name: 'farmer-ssr-timing',
        apply: 'serve',
        configureServer(server) {
            const env = loadEnv(
                server.config.mode,
                server.config.envDir,
                'ACTIVITY_TIMING_ENABLED',
            );
            const setting =
                process.env.ACTIVITY_TIMING_ENABLED ??
                env.ACTIVITY_TIMING_ENABLED ??
                'true';
            if (
                ['false', '(false)', '0', 'off'].includes(
                    setting.trim().toLowerCase(),
                )
            )
                return;

            server.middlewares.use('/__inertia_ssr', (req, res, next) => {
                if (req.method !== 'POST') return next();
                const started = performance.now();
                const originalEnd = res.end;
                res.end = function (...args: Parameters<typeof res.end>) {
                    if (!res.headersSent) {
                        const timing = `farmer-ssr;dur=${(performance.now() - started).toFixed(2)}`;
                        const existing = res.getHeader('Server-Timing');
                        res.setHeader(
                            'Server-Timing',
                            existing ? `${existing}, ${timing}` : timing,
                        );
                    }
                    return originalEnd.apply(this, args as never);
                } as typeof res.end;
                next();
            });
        },
    };
}
