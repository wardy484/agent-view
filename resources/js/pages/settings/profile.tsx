import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="flex flex-col gap-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Profile Information</CardTitle>
                        <CardDescription>
                            Update your name and email address.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ProfileController.update.form()}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="name"
                                        label="Name"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                        error={errors.name}
                                    />

                                    <FormField
                                        id="email"
                                        label="Email Address"
                                        type="email"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                        error={errors.email}
                                    />

                                    {mustVerifyEmail &&
                                        auth.user.email_verified_at ===
                                            null && (
                                            <div className="flex flex-col gap-2 rounded-md border bg-muted/40 p-4 text-sm">
                                                <p className="text-muted-foreground">
                                                    Your email address is
                                                    unverified.{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="text-foreground underline decoration-muted-foreground/50 underline-offset-4 transition-colors hover:decoration-current"
                                                    >
                                                        Click here to resend the
                                                        verification email.
                                                    </Link>
                                                </p>

                                                {status ===
                                                    'verification-link-sent' && (
                                                    <p className="text-sm font-medium text-green-600">
                                                        A new verification link
                                                        has been sent to your
                                                        email address.
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                    <div className="flex items-center gap-3">
                                        <Button
                                            disabled={processing}
                                            data-test="update-profile-button"
                                        >
                                            Save
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <DeleteUser />
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
