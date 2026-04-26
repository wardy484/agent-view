import type { SVGAttributes } from 'react';

/**
 * Nexus-UI mark — a workbench frame tapped by a single MCP connection.
 *
 * Reads as: three stacked rows inside a rounded pane (tables, kanban
 * columns, slides — the "structured output sink"), with a small node
 * and short leader line in the upper-right quadrant representing the
 * agent's MCP hand-off into the workbench.
 *
 * Uses `currentColor` throughout so it inherits text colour in both
 * light and dark surfaces.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {/* Workbench frame */}
            <rect x="3" y="7" width="22" height="18" rx="3" strokeWidth="2" />

            {/* Three structured rows inside the frame */}
            <line x1="7" y1="12" x2="21" y2="12" strokeWidth="2" />
            <line x1="7" y1="16" x2="17" y2="16" strokeWidth="2" />
            <line x1="7" y1="20" x2="19" y2="20" strokeWidth="2" />

            {/* MCP agent node + leader line tapping the top-right corner */}
            <line x1="23.5" y1="8.5" x2="27" y2="5" strokeWidth="2" />
            <circle
                cx="28"
                cy="4"
                r="2.5"
                strokeWidth="2"
                fill="currentColor"
            />
        </svg>
    );
}
