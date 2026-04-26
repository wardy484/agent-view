import type { Preview } from '@storybook/react-vite';
import '../resources/css/app.css';

const preview: Preview = {
    parameters: {
        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },
        backgrounds: {
            default: 'app',
            values: [
                { name: 'app', value: 'oklch(1 0 0)' },
                { name: 'dark', value: 'oklch(0.145 0 0)' },
            ],
        },
    },
};

export default preview;
