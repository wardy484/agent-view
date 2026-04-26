import type { Meta, StoryObj } from '@storybook/react-vite';
import { FormField } from './form-field';
import { Input } from './input';

const meta: Meta<typeof FormField> = {
    title: 'UI/FormField',
    component: FormField,
};

export default meta;
type Story = StoryObj<typeof FormField>;

export const Default: Story = {
    args: {
        id: 'email',
        label: 'Email Address',
        type: 'email',
        placeholder: 'you@example.com',
    },
};

export const WithHelper: Story = {
    args: {
        id: 'name',
        label: 'Display Name',
        helper: 'Shown next to your comments and on your profile.',
        placeholder: 'Ada Lovelace',
    },
};

export const WithError: Story = {
    args: {
        id: 'email-error',
        label: 'Email Address',
        type: 'email',
        defaultValue: 'not-an-email',
        error: 'Enter a valid email address.',
    },
};

export const WithLabelAction: Story = {
    args: {
        id: 'password',
        label: 'Password',
        type: 'password',
        labelAction: (
            <a
                href="#"
                className="text-xs text-muted-foreground hover:text-foreground"
            >
                Forgot password?
            </a>
        ),
    },
};

export const WithCustomControl: Story = {
    args: {
        id: 'custom',
        label: 'Custom Control',
        helper: 'Pass any element via `control` to keep the row chrome.',
        control: <Input type="search" placeholder="Search…" />,
    },
};
