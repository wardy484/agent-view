import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from './button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from './sheet';

const meta: Meta<typeof Sheet> = {
    title: 'UI/Sheet',
    component: Sheet,
};

export default meta;
type Story = StoryObj<typeof Sheet>;

export const RightSide: Story = {
    render: () => (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline">Open right sheet</Button>
            </SheetTrigger>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>Edit profile</SheetTitle>
                    <SheetDescription>Make changes to your profile here.</SheetDescription>
                </SheetHeader>
            </SheetContent>
        </Sheet>
    ),
};
