import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from './button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from './card';

const meta: Meta<typeof Card> = {
    title: 'UI/Card',
    component: Card,
};

export default meta;
type Story = StoryObj<typeof Card>;

export const Default: Story = {
    render: () => (
        <Card className="w-[360px]">
            <CardHeader>
                <CardTitle>Project Atlas</CardTitle>
                <CardDescription>A short description of the project.</CardDescription>
            </CardHeader>
            <CardContent>
                <p>Card content goes here. Body text wraps and pads naturally.</p>
            </CardContent>
            <CardFooter>
                <Button>Save</Button>
            </CardFooter>
        </Card>
    ),
};
