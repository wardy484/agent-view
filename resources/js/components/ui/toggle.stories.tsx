import type { Meta, StoryObj } from '@storybook/react-vite';
import { BoldIcon } from 'lucide-react';
import { Toggle } from './toggle';

const meta: Meta<typeof Toggle> = {
    title: 'UI/Toggle',
    component: Toggle,
};

export default meta;
type Story = StoryObj<typeof Toggle>;

export const Default: Story = {
    render: () => (
        <Toggle aria-label="Toggle bold">
            <BoldIcon />
        </Toggle>
    ),
};

export const Outline: Story = {
    render: () => (
        <Toggle variant="outline" aria-label="Toggle bold">
            <BoldIcon />
            Bold
        </Toggle>
    ),
};

export const Disabled: Story = {
    render: () => (
        <Toggle disabled aria-label="Toggle bold">
            <BoldIcon />
        </Toggle>
    ),
};
