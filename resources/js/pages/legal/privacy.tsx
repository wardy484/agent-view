import { Head } from '@inertiajs/react';
import LegalLayout from '@/layouts/legal-layout';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy" />
            <LegalLayout
                title="Privacy Policy"
                lastUpdated="19 April 2026"
                intro="This policy explains what personal data Nexus-UI collects, why we collect it, and what rights you have over it."
            >
                <h2>1. Data we collect</h2>
                <h3>Account data</h3>
                <ul>
                    <li>Email address and display name</li>
                    <li>Hashed password and 2FA recovery codes</li>
                    <li>Billing contact and payment-processor identifiers</li>
                </ul>
                <h3>Workbench data</h3>
                <ul>
                    <li>
                        Structured payloads your agents push to the MCP tool
                        (rows, cards, metadata)
                    </li>
                    <li>
                        Versioned snapshots, preview caches, and workbench slugs
                    </li>
                </ul>
                <h3>Operational data</h3>
                <ul>
                    <li>Request logs (IP, user agent, endpoint, timestamp)</li>
                    <li>Error traces and performance metrics</li>
                    <li>Cookie identifiers (see our Cookie Policy)</li>
                </ul>

                <h2>2. How we use it</h2>
                <ul>
                    <li>To operate and secure the Service</li>
                    <li>To authenticate requests and issue Sanctum tokens</li>
                    <li>To render versioned workbenches and cache previews</li>
                    <li>
                        To bill your subscription and send transactional notices
                    </li>
                    <li>To detect abuse and debug incidents</li>
                </ul>

                <h2>3. Legal basis (EEA/UK users)</h2>
                <p>
                    We process personal data on the basis of contract (providing
                    the Service), legitimate interest (security and fraud
                    prevention), consent (non-essential cookies), and legal
                    obligation (tax, accounting).
                </p>

                <h2>4. Sharing</h2>
                <p>
                    We do not sell personal data. We share it only with
                    sub-processors who help us run the Service:
                </p>
                <ul>
                    <li>Laravel Cloud / managed Postgres &amp; Redis hosts</li>
                    <li>Transactional email provider</li>
                    <li>Payment processor</li>
                    <li>
                        Error and performance monitoring (e.g. Sentry-style
                        tooling)
                    </li>
                </ul>
                <p>
                    A current list is available on request from{' '}
                    <a href="mailto:privacy@nexus-ui.example">
                        privacy@nexus-ui.example
                    </a>
                    .
                </p>

                <h2>5. Retention</h2>
                <ul>
                    <li>
                        Snapshots: retained per your plan&apos;s history window,
                        then purged.
                    </li>
                    <li>
                        Request logs: 30 days by default, longer where required
                        for security.
                    </li>
                    <li>
                        Billing records: kept for the period required by
                        applicable tax law.
                    </li>
                </ul>

                <h2>6. International transfers</h2>
                <p>
                    Data may be processed in regions where our infrastructure
                    providers operate. Where required we rely on Standard
                    Contractual Clauses or equivalent safeguards.
                </p>

                <h2>7. Your rights</h2>
                <p>
                    Depending on where you live you may have the right to
                    access, correct, export, or delete your personal data, to
                    object to processing, or to lodge a complaint with a
                    supervisory authority. Email{' '}
                    <a href="mailto:privacy@nexus-ui.example">
                        privacy@nexus-ui.example
                    </a>{' '}
                    to exercise any of these rights.
                </p>

                <h2>8. Security</h2>
                <p>
                    We use TLS in transit, encryption at rest, scoped
                    authentication tokens, and least-privilege access for staff.
                    No system is perfectly secure, but we take this seriously
                    and have an internal incident-response process.
                </p>

                <h2>9. Children</h2>
                <p>
                    The Service is not intended for children under 16. We do not
                    knowingly collect personal data from children.
                </p>

                <h2>10. Changes</h2>
                <p>
                    We&apos;ll update this page when our practices change and
                    notify you of material changes in-product or by email.
                </p>

                <h2>11. Contact</h2>
                <p>
                    Privacy questions:{' '}
                    <a href="mailto:privacy@nexus-ui.example">
                        privacy@nexus-ui.example
                    </a>
                    .
                </p>
            </LegalLayout>
        </>
    );
}
