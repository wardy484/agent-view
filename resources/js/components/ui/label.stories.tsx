import type { Meta, StoryObj } from '@storybook/react-vite';
import { Checkbox } from './checkbox';
import { Label } from './label';

const meta: Meta<typeof Label> = {
    title: 'UI/Label',
    component: Label,
};

export default meta;
type Story = StoryObj<typeof Label>;

export const Default: Story = {
    args: { children: 'Email address' },
};

export const WithCheckbox: Story = {
    render: () => (
        <Label className="flex items-center gap-2">
            <Checkbox /> Subscribe to newsletter
        </Label>
    ),
};
