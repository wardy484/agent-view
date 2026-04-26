import type { Meta, StoryObj } from '@storybook/react-vite';
import { Separator } from './separator';

const meta: Meta<typeof Separator> = {
    title: 'UI/Separator',
    component: Separator,
};

export default meta;
type Story = StoryObj<typeof Separator>;

export const Horizontal: Story = {
    render: () => (
        <div className="w-[280px]">
            <p className="text-sm">Above</p>
            <Separator className="my-3" />
            <p className="text-sm">Below</p>
        </div>
    ),
};

export const Vertical: Story = {
    render: () => (
        <div className="flex h-12 items-center gap-3">
            <span className="text-sm">Left</span>
            <Separator orientation="vertical" />
            <span className="text-sm">Right</span>
        </div>
    ),
};
