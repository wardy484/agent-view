import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from './button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from './collapsible';

const meta: Meta<typeof Collapsible> = {
    title: 'UI/Collapsible',
    component: Collapsible,
};

export default meta;
type Story = StoryObj<typeof Collapsible>;

export const Default: Story = {
    render: () => (
        <Collapsible className="w-[320px] space-y-2">
            <CollapsibleTrigger asChild>
                <Button variant="outline" size="sm">
                    Toggle
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-2">
                <div className="rounded-md border px-4 py-3 text-sm">First item</div>
                <div className="rounded-md border px-4 py-3 text-sm">Second item</div>
            </CollapsibleContent>
        </Collapsible>
    ),
};
