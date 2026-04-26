import type { Meta, StoryObj } from '@storybook/react-vite';
import { Input } from './input';
import { Label } from './label';

const meta: Meta<typeof Input> = {
    title: 'UI/Input',
    component: Input,
};

export default meta;
type Story = StoryObj<typeof Input>;

export const Default: Story = {
    args: { placeholder: 'Type here...' },
};

export const WithLabel: Story = {
    render: () => (
        <div className="grid w-[280px] gap-2">
            <Label htmlFor="email">Email</Label>
            <Input id="email" type="email" placeholder="you@example.com" />
        </div>
    ),
};

export const Disabled: Story = {
    args: { placeholder: 'Disabled', disabled: true },
};

export const Invalid: Story = {
    args: { placeholder: 'Invalid', 'aria-invalid': true, defaultValue: 'oops' },
};
