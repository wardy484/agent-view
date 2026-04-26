import type { Meta, StoryObj } from '@storybook/react-vite';
import { BoldIcon, ItalicIcon, UnderlineIcon } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from './toggle-group';

const meta: Meta<typeof ToggleGroup> = {
    title: 'UI/ToggleGroup',
    component: ToggleGroup,
};

export default meta;
type Story = StoryObj<typeof ToggleGroup>;

export const Multiple: Story = {
    render: () => (
        <ToggleGroup type="multiple">
            <ToggleGroupItem value="bold" aria-label="Bold">
                <BoldIcon />
            </ToggleGroupItem>
            <ToggleGroupItem value="italic" aria-label="Italic">
                <ItalicIcon />
            </ToggleGroupItem>
            <ToggleGroupItem value="underline" aria-label="Underline">
                <UnderlineIcon />
            </ToggleGroupItem>
        </ToggleGroup>
    ),
};

export const Outline: Story = {
    render: () => (
        <ToggleGroup type="single" variant="outline" defaultValue="bold">
            <ToggleGroupItem value="bold">Bold</ToggleGroupItem>
            <ToggleGroupItem value="italic">Italic</ToggleGroupItem>
            <ToggleGroupItem value="underline">Underline</ToggleGroupItem>
        </ToggleGroup>
    ),
};
