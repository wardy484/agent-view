import { router } from '@inertiajs/react';
import {
    Copy,
    Link2,
    Lock,
    Mail,
    RotateCcw,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

export type SnapshotVisibility = 'private' | 'link' | 'shared';

export type SnapshotShareSummary = {
    id: number;
    email: string;
    accepted: boolean;
    created_at: string | null;
};

type Props = {
    workbenchSlug: string;
    snapshotSlug: string;
    visibility: SnapshotVisibility;
    shareUrl: string | null;
    shares: SnapshotShareSummary[];
};

/**
 * REQ-M4-010: owner-only Share dialog. Three visibility modes:
 *   - Private: no one except the owner can view the snapshot.
 *   - Anyone with link: exposes /s/{token} publicly; copy / rotate the link.
 *   - Specific people: email-based invites; list of invitees with revoke.
 *
 * All mutations hit the SnapshotShareController routes and page.reload on
 * success so the dialog always renders the latest backend state.
 */
export function ShareDialog({
    workbenchSlug,
    snapshotSlug,
    visibility,
    shareUrl,
    shares,
}: Props) {
    const [open, setOpen] = useState(false);
    const [email, setEmail] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [, copy] = useClipboard();

    const base = `/workbenches/${encodeURIComponent(workbenchSlug)}/snapshots/${encodeURIComponent(snapshotSlug)}`;

    const changeVisibility = (next: SnapshotVisibility) => {
        if (next === visibility) {
            return;
        }

        router.patch(
            `${base}/visibility`,
            { visibility: next },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(visibilityLabel(next)),
                onError: () => toast.error('Could not change visibility'),
            },
        );
    };

    const rotateLink = () => {
        router.post(
            `${base}/share-token/rotate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        'New link minted — the old one no longer works',
                    ),
                onError: () => toast.error('Could not rotate link'),
            },
        );
    };

    const copyLink = async () => {
        if (!shareUrl) {
            return;
        }

        const absolute = shareUrl.startsWith('http')
            ? shareUrl
            : `${window.location.origin}${shareUrl}`;
        const ok = await copy(absolute);

        if (ok) {
            toast.success('Link copied to clipboard');
        } else {
            toast.error('Could not copy — copy it manually');
        }
    };

    const submitEmail = (e: React.FormEvent) => {
        e.preventDefault();
        const trimmed = email.trim();

        if (!trimmed) {
            return;
        }

        setIsSubmitting(true);
        router.post(
            `${base}/shares`,
            { email: trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEmail('');
                    toast.success(`Invite sent to ${trimmed}`);
                },
                onError: (errors) => {
                    toast.error(errors.email ?? 'Could not add invitee');
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const revokeShare = (share: SnapshotShareSummary) => {
        router.delete(`${base}/shares/${share.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Revoked access for ${share.email}`),
            onError: () => toast.error('Could not revoke'),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-testid="nexus-share-button"
                    className="inline-flex items-center gap-2"
                >
                    <Users className="size-4" aria-hidden />
                    <span>Share</span>
                </Button>
            </DialogTrigger>
            <DialogContent
                className="sm:max-w-md"
                data-testid="nexus-share-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Share Snapshot</DialogTitle>
                    <DialogDescription>
                        Choose who can see the latest revision of this snapshot.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <fieldset
                        className="flex flex-col gap-2"
                        aria-label="Visibility"
                    >
                        <VisibilityOption
                            current={visibility}
                            value="private"
                            title="Private"
                            description="Only you can open this snapshot."
                            icon={<Lock className="size-4" aria-hidden />}
                            onSelect={changeVisibility}
                        />
                        <VisibilityOption
                            current={visibility}
                            value="link"
                            title="Anyone With the Link"
                            description="A signed URL anyone can open — no sign-in required."
                            icon={<Link2 className="size-4" aria-hidden />}
                            onSelect={changeVisibility}
                        />
                        <VisibilityOption
                            current={visibility}
                            value="shared"
                            title="Specific People"
                            description="Invite teammates by email. They'll sign in to view."
                            icon={<Mail className="size-4" aria-hidden />}
                            onSelect={changeVisibility}
                        />
                    </fieldset>

                    {visibility === 'link' && shareUrl ? (
                        <div className="flex flex-col gap-2 rounded-md border bg-muted/30 p-3">
                            <Label
                                htmlFor="share-url"
                                className="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Public Link
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="share-url"
                                    readOnly
                                    value={shareUrl}
                                    className="font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    onClick={copyLink}
                                    title="Copy link"
                                >
                                    <Copy className="size-4" aria-hidden />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    onClick={rotateLink}
                                    title="Mint a new link (the old one stops working)"
                                >
                                    <RotateCcw className="size-4" aria-hidden />
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    {visibility === 'shared' ? (
                        <div className="flex flex-col gap-3">
                            <form
                                onSubmit={submitEmail}
                                className="flex items-center gap-2"
                            >
                                <Input
                                    type="email"
                                    placeholder="teammate@example.com"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    aria-label="Invite by email"
                                    required
                                />
                                <Button type="submit" disabled={isSubmitting}>
                                    Invite
                                </Button>
                            </form>

                            {shares.length > 0 ? (
                                <ul
                                    className="flex flex-col gap-1"
                                    data-testid="nexus-share-list"
                                >
                                    {shares.map((share) => (
                                        <li
                                            key={share.id}
                                            className="flex items-center justify-between gap-2 rounded-md border bg-background px-3 py-2 text-sm"
                                        >
                                            <div className="flex flex-col">
                                                <span className="font-medium">
                                                    {share.email}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {share.accepted
                                                        ? 'Has account'
                                                        : 'Invite pending'}
                                                </span>
                                            </div>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                onClick={() =>
                                                    revokeShare(share)
                                                }
                                                title={`Revoke ${share.email}`}
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    No one invited yet.
                                </p>
                            )}
                        </div>
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function VisibilityOption({
    current,
    value,
    title,
    description,
    icon,
    onSelect,
}: {
    current: SnapshotVisibility;
    value: SnapshotVisibility;
    title: string;
    description: string;
    icon: React.ReactNode;
    onSelect: (next: SnapshotVisibility) => void;
}) {
    const selected = current === value;

    return (
        <button
            type="button"
            onClick={() => onSelect(value)}
            aria-pressed={selected}
            className={cn(
                'flex items-start gap-3 rounded-md border p-3 text-left transition-colors',
                selected ? 'border-primary bg-primary/5' : 'hover:bg-muted/50',
            )}
        >
            <span className="mt-0.5 text-muted-foreground">{icon}</span>
            <span className="flex flex-col gap-0.5">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}

function visibilityLabel(v: SnapshotVisibility): string {
    if (v === 'private') {
        return 'Snapshot is now private';
    }

    if (v === 'link') {
        return 'Snapshot is now shareable via link';
    }

    return 'Snapshot is now shared with specific people';
}
