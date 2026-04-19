import { Head, Link } from '@inertiajs/react';
import LegalLayout from '@/layouts/legal-layout';

export default function Terms() {
    return (
        <>
            <Head title="Terms of Service" />
            <LegalLayout
                title="Terms of Service"
                lastUpdated="19 April 2026"
                intro="These terms govern your use of Nexus-UI. By creating an account or sending data to our MCP endpoints you agree to be bound by them."
            >
                <h2>1. Who we are</h2>
                <p>
                    Nexus-UI (&quot;we&quot;, &quot;us&quot;) is operated by
                    the legal entity behind the service. References to the
                    &quot;Service&quot; mean the Nexus-UI website, MCP tool,
                    workbench UI, APIs, and any related software we make
                    available.
                </p>

                <h2>2. Your account</h2>
                <p>
                    You are responsible for keeping your credentials and
                    Sanctum tokens confidential. You must be at least 16
                    years old (or the digital-consent age in your country) to
                    use the Service. You may not share an account between
                    humans.
                </p>

                <h2>3. Acceptable use</h2>
                <p>
                    You agree to our{' '}
                    <Link href="/acceptable-use">Acceptable Use Policy</Link>. In
                    short: don&apos;t use the Service to break the law, harm
                    others, or abuse our infrastructure.
                </p>

                <h2>4. Your content</h2>
                <p>
                    You retain all rights to the data your agents push into a
                    workbench. You grant us a limited licence to process,
                    store, cache and render that content only to the extent
                    needed to operate the Service for you.
                </p>

                <h2>5. Our software</h2>
                <p>
                    The Nexus-UI code, brand, and UI are licensed — not sold.
                    The open source portions are covered by their respective
                    open source licence; the hosted platform is proprietary.
                </p>

                <h2>6. Plans, billing, and trials</h2>
                <ul>
                    <li>
                        Paid plans are billed in advance on a monthly or
                        annual basis via our payment processor.
                    </li>
                    <li>
                        Trials end automatically; no charges occur without a
                        paid subscription.
                    </li>
                    <li>
                        Taxes (VAT/GST/sales tax) are added where required by
                        law.
                    </li>
                    <li>
                        Fees are non-refundable except where required by law
                        or our written policy.
                    </li>
                </ul>

                <h2>7. Service levels</h2>
                <p>
                    We aim for high availability and publish a status page,
                    but the Service is provided &quot;as is&quot; and
                    &quot;as available&quot; without warranties of any kind
                    except those that cannot be disclaimed by law.
                </p>

                <h2>8. Limitation of liability</h2>
                <p>
                    To the maximum extent permitted by law, our total
                    liability to you for any claim arising out of the Service
                    is limited to the fees you paid us in the 12 months
                    before the event giving rise to the claim. We are not
                    liable for indirect, consequential, or lost-profit
                    damages.
                </p>

                <h2>9. Termination</h2>
                <p>
                    You can cancel at any time from your billing settings.
                    We can suspend or terminate your account for violations
                    of these terms or the Acceptable Use Policy, with notice
                    where reasonable.
                </p>

                <h2>10. Changes</h2>
                <p>
                    We may update these terms from time to time. If the
                    changes are material we&apos;ll notify you by email or
                    in-product at least 14 days before they take effect.
                </p>

                <h2>11. Governing law</h2>
                <p>
                    These terms are governed by the laws of the jurisdiction
                    in which the operator of the Service is established.
                    Disputes will be resolved in the competent courts of
                    that jurisdiction.
                </p>

                <h2>12. Contact</h2>
                <p>
                    Questions about these terms? Email{' '}
                    <a href="mailto:legal@nexus-ui.example">
                        legal@nexus-ui.example
                    </a>{' '}
                    or use our <Link href="/contact">contact form</Link>.
                </p>
            </LegalLayout>
        </>
    );
}
