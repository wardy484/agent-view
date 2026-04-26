import { Head } from '@inertiajs/react';
import LegalLayout from '@/layouts/legal-layout';

export default function AcceptableUse() {
    return (
        <>
            <Head title="Acceptable Use Policy" />
            <LegalLayout
                title="Acceptable Use Policy"
                lastUpdated="19 April 2026"
                intro="Nexus-UI is a tool for work. This policy lists the things you agree not to do while using it."
            >
                <h2>1. Don&apos;t break the law</h2>
                <p>
                    You won&apos;t use Nexus-UI for anything illegal under the
                    laws that apply to you or to us, including export controls,
                    sanctions, or consumer-protection law.
                </p>

                <h2>2. Don&apos;t abuse the infrastructure</h2>
                <ul>
                    <li>
                        No credential-stuffing, scraping, or circumventing rate
                        limits.
                    </li>
                    <li>
                        No denial-of-service attacks or traffic amplification
                        through our endpoints.
                    </li>
                    <li>
                        No attempts to access accounts, data, or systems you
                        don&apos;t own.
                    </li>
                    <li>
                        No deploying malware, backdoors, or crypto-miners via
                        our Service.
                    </li>
                </ul>

                <h2>3. Don&apos;t harm others</h2>
                <ul>
                    <li>
                        No harassing, doxxing, or targeting individuals or
                        groups.
                    </li>
                    <li>
                        No content that sexualises minors, incites violence, or
                        promotes terrorism.
                    </li>
                    <li>
                        No content that infringes intellectual property you
                        don&apos;t have the right to use.
                    </li>
                </ul>

                <h2>4. Sensitive data</h2>
                <p>
                    Don&apos;t push regulated data (PCI cardholder data, PHI,
                    government-classified data) into a workbench unless you have
                    a written agreement with us that allows it.
                </p>

                <h2>5. AI-specific rules</h2>
                <ul>
                    <li>
                        Don&apos;t use Nexus-UI to generate content designed to
                        deceive (deepfakes of real people without consent,
                        disinformation campaigns, etc).
                    </li>
                    <li>
                        Don&apos;t use Nexus-UI to build systems that make
                        consequential automated decisions about people (hiring,
                        credit, housing, medical) without qualified human
                        review.
                    </li>
                </ul>

                <h2>6. Reporting abuse</h2>
                <p>
                    If you see something that violates this policy email{' '}
                    <a href="mailto:abuse@nexus-ui.example">
                        abuse@nexus-ui.example
                    </a>{' '}
                    with as much detail as possible.
                </p>

                <h2>7. Consequences</h2>
                <p>
                    We may suspend or terminate access for violations of this
                    policy, immediately where necessary to protect the Service
                    or other users, and we may cooperate with law enforcement
                    where required.
                </p>
            </LegalLayout>
        </>
    );
}
