import { Head } from '@inertiajs/react';
import LegalLayout from '@/layouts/legal-layout';

export default function Cookies() {
    return (
        <>
            <Head title="Cookie Policy" />
            <LegalLayout
                title="Cookie Policy"
                lastUpdated="19 April 2026"
                intro="This policy explains what cookies and similar technologies Nexus-UI uses, why we use them, and how to control them."
            >
                <h2>1. What are cookies?</h2>
                <p>
                    Cookies are small text files stored in your browser by
                    the sites you visit. They let sites remember your
                    preferences, keep you logged in, and understand how
                    people use the product.
                </p>

                <h2>2. The cookies we use</h2>
                <h3>Strictly necessary</h3>
                <ul>
                    <li>
                        <strong>Session cookie</strong> — keeps you logged
                        into your Nexus-UI account.
                    </li>
                    <li>
                        <strong>CSRF token</strong> — prevents cross-site
                        request forgery on authenticated forms.
                    </li>
                    <li>
                        <strong>Theme preference</strong> — remembers whether
                        you picked light or dark mode.
                    </li>
                </ul>
                <h3>Analytics (optional)</h3>
                <ul>
                    <li>
                        Aggregated, privacy-respecting analytics so we can
                        understand which features are useful. Only loaded if
                        you consent.
                    </li>
                </ul>
                <h3>Marketing</h3>
                <p>
                    We do not currently use third-party advertising cookies.
                    If that changes we will update this policy and ask for
                    consent first.
                </p>

                <h2>3. Managing cookies</h2>
                <p>
                    You can clear or block cookies in your browser settings.
                    Blocking strictly-necessary cookies may break features
                    like staying logged in. Where we use optional cookies
                    you&apos;ll see a consent banner on your first visit.
                </p>

                <h2>4. Do Not Track</h2>
                <p>
                    We honour browser-level Global Privacy Control signals
                    where supported — if GPC is on, we treat it as a request
                    to opt out of non-essential tracking.
                </p>

                <h2>5. Changes</h2>
                <p>
                    We&apos;ll update this page when the cookies we use
                    change. The &quot;last updated&quot; date at the top
                    reflects the most recent revision.
                </p>

                <h2>6. Contact</h2>
                <p>
                    Questions? Email{' '}
                    <a href="mailto:privacy@nexus-ui.example">
                        privacy@nexus-ui.example
                    </a>
                    .
                </p>
            </LegalLayout>
        </>
    );
}
