import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from './select';

const meta: Meta<typeof Select> = {
    title: 'UI/Select',
    component: Select,
};

export default meta;
type Story = StoryObj<typeof Select>;

export const Default: Story = {
    render: () => (
        <Select>
            <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="Pick a fruit" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectLabel>Fruits</SelectLabel>
                    <SelectItem value="apple">Apple</SelectItem>
                    <SelectItem value="banana">Banana</SelectItem>
                    <SelectItem value="orange">Orange</SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    ),
};

export const Disabled: Story = {
    render: () => (
        <Select disabled>
            <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="Disabled" />
            </SelectTrigger>
        </Select>
    ),
};
