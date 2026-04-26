import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { StorybookConfig } from '@storybook/react-vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { mergeConfig } from 'vite';

const dirname = path.dirname(fileURLToPath(import.meta.url));

const config: StorybookConfig = {
    stories: ['../resources/js/components/ui/**/*.stories.@(ts|tsx)'],
    addons: [],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
    typescript: {
        check: false,
        reactDocgen: 'react-docgen',
    },
    docs: {
        autodocs: false,
    },
    // Storybook is dev-only and standalone — do not pull in Laravel/Inertia/Wayfinder
    // plugins from the app's vite.config.ts. Provide a clean Vite config here.
    async viteFinal() {
        return mergeConfig(
            {},
            {
                plugins: [react(), tailwindcss()],
                resolve: {
                    alias: {
                        '@': path.resolve(dirname, '../resources/js'),
                    },
                },
            },
        );
    },
};

export default config;
