import { Head } from '@inertiajs/react';
import { Mail, MessageSquare, ShieldAlert } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const contacts = [
    {
        icon: MessageSquare,
        title: 'Sales & partnerships',
        body: 'Talk about Team or Enterprise plans, procurement, or a custom deploy.',
        email: 'sales@nexus-ui.example',
    },
    {
        icon: Mail,
        title: 'General support',
        body: 'Bugs, billing questions, or anything else about the product.',
        email: 'support@nexus-ui.example',
    },
    {
        icon: ShieldAlert,
        title: 'Security & abuse',
        body: 'Responsible disclosure, abuse reports, takedown requests.',
        email: 'security@nexus-ui.example',
    },
];

export default function Contact() {
    return (
        <>
            <Head title="Contact" />
            <div className="mx-auto w-full max-w-4xl px-6 py-16">
                <header className="mb-10 text-center">
                    <p className="text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        Contact
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                        We&apos;d love to hear from you.
                    </h1>
                    <p className="mx-auto mt-4 max-w-xl text-muted-foreground">
                        Pick the right inbox and we&apos;ll get back to you as
                        quickly as we can.
                    </p>
                </header>

                <div className="grid gap-4 md:grid-cols-3">
                    {contacts.map(({ icon: Icon, title, body, email }) => (
                        <Card key={title} className="border-border/60">
                            <CardHeader>
                                <div className="mb-3 flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-5" />
                                </div>
                                <CardTitle className="text-lg">
                                    {title}
                                </CardTitle>
                                <CardDescription>{body}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <a
                                    href={`mailto:${email}`}
                                    className="text-sm font-medium text-foreground underline underline-offset-4 hover:no-underline"
                                >
                                    {email}
                                </a>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="mt-12 rounded-lg border border-border/60 bg-muted/30 p-6 text-sm text-muted-foreground">
                    <p>
                        <strong className="text-foreground">
                            Prefer async?
                        </strong>{' '}
                        All of our inboxes are monitored in business hours
                        (CE(S)T). For urgent production incidents on paid plans,
                        use the status page runbook included with your
                        onboarding.
                    </p>
                </div>
            </div>
        </>
    );
}
