import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { existsSync } from 'node:fs';
import { defineConfig, loadEnv } from 'vite';

const usesSailForArtisan =
    process.platform === 'linux' &&
    process.env.CI !== 'true' &&
    process.env.GITHUB_ACTIONS !== 'true' &&
    existsSync('./vendor/bin/sail');

const wayfinderCommand = usesSailForArtisan
    ? './vendor/bin/sail artisan wayfinder:generate'
    : 'php artisan wayfinder:generate';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const vitePort = Number(env.VITE_PORT || 5173);

    return {
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: Boolean(env.VITE_PORT),
        },
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
            }),
            inertia({
                ssr: {
                    entry: 'resources/js/ssr.tsx',
                },
            }),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                command: wayfinderCommand,
                formVariants: true,
            }),
        ],
    };
});
