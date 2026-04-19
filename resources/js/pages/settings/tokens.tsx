import { Form, Head, Link } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { destroy, store, edit } from '@/routes/tokens';

type Token = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string | null;
};

type Props = {
    tokens: Token[];
    plainTextToken: string | null;
};

export default function TokensSettings({ tokens, plainTextToken }: Props) {
    const [copiedText, copy] = useClipboard();
    const justCopied = copiedText !== null && copiedText === plainTextToken;

    const handleCopyToken = async () => {
        if (!plainTextToken) {
            return;
        }

        const success = await copy(plainTextToken);

        if (success) {
            toast.success('Token copied to clipboard');
        } else {
            toast.error('Could not copy — copy it manually');
        }
    };

    return (
        <>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Personal access tokens"
                    description="Mint a Sanctum token scoped to a single workbench. Use it as a Bearer token to call /ai/mcp/nexus."
                />

                {plainTextToken ? (
                    <button
                        type="button"
                        onClick={handleCopyToken}
                        data-testid="plain-text-token"
                        aria-label="Copy token to clipboard"
                        className="group block w-full rounded-lg border border-border bg-muted/50 p-4 text-left font-mono text-sm break-all transition-colors hover:border-primary/60 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                                Copy this token now — it won't be shown again.
                            </span>
                            <span className="flex items-center gap-1.5 font-sans text-xs font-medium text-muted-foreground group-hover:text-foreground">
                                {justCopied ? (
                                    <>
                                        <Check className="size-3.5" /> Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3.5" /> Click to
                                        copy
                                    </>
                                )}
                            </span>
                        </div>
                        {plainTextToken}
                    </button>
                ) : null}

                <Form
                    action={store().url}
                    method="post"
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="token-name">Token label</Label>
                                <Input
                                    id="token-name"
                                    name="name"
                                    required
                                    placeholder="My laptop CLI"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="token-workbench">
                                    Workbench slug
                                </Label>
                                <Input
                                    id="token-workbench"
                                    name="workbench_slug"
                                    required
                                    placeholder="team-alpha"
                                />
                                <InputError message={errors.workbench_slug} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="mint-token-button"
                            >
                                {processing ? 'Minting…' : 'Mint token'}
                            </Button>
                        </>
                    )}
                </Form>

                <section className="space-y-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Existing tokens
                    </h2>
                    {tokens.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No tokens yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border">
                            {tokens.map((token) => (
                                <li
                                    key={token.id}
                                    className="flex items-center justify-between gap-4 p-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {token.name}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {token.abilities.join(', ') ||
                                                'no abilities'}
                                        </p>
                                    </div>
                                    <Link
                                        href={destroy({ token: token.id }).url}
                                        method="delete"
                                        as="button"
                                        className="text-xs text-destructive hover:underline"
                                    >
                                        Revoke
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

TokensSettings.layout = {
    breadcrumbs: [
        {
            title: 'API tokens',
            href: edit(),
        },
    ],
};
