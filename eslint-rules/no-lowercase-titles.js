/**
 * Custom ESLint rule: no-lowercase-titles
 *
 * Spec REQ-M9-006 — Warns when JSX `<h1>`–`<h3>`, `<Button>`, or sidebar nav-text
 * containers (`<SidebarMenuButton>`, `<SidebarGroupLabel>`, `<NavigationMenuLink>`,
 * `<BreadcrumbPage>`) contain literal text whose first non-whitespace character is
 * lowercase a-z.
 *
 * Body text, helper text, and table-cell strings are not targeted by this rule
 * — only headings, primary action buttons, and nav-text containers.
 *
 * The rule walks JSX children and inspects:
 *   - JSXText nodes
 *   - JSXExpressionContainer wrapping a string Literal
 * It ignores expression containers wrapping identifiers / member expressions
 * (those are dynamic, e.g. `{item.title}`).
 */

const TARGET_ELEMENTS = new Set([
    'h1',
    'h2',
    'h3',
    'Button',
    'SidebarMenuButton',
    'SidebarGroupLabel',
    'NavigationMenuLink',
    'BreadcrumbPage',
    'SheetTitle',
    'DialogTitle',
    'CardTitle',
]);

/** @type {import('eslint').Rule.RuleModule} */
const rule = {
    meta: {
        type: 'suggestion',
        docs: {
            description:
                'Disallow lowercase first letter in JSX heading, button, and nav text.',
        },
        messages: {
            lowercase:
                'Heading/button/nav text should start with an uppercase letter (Title Case or sentence-case noun phrase). Found: {{ snippet }}',
        },
        schema: [],
    },
    create(context) {
        function checkText(node, raw) {
            if (typeof raw !== 'string') {
                return;
            }

            const trimmed = raw.trim();

            if (trimmed.length === 0) {
                return;
            }

            const first = trimmed.charAt(0);

            // Only complain about lowercase ASCII a-z. Symbols, digits,
            // emoji, accented uppercase, etc. are all fine.
            if (first >= 'a' && first <= 'z') {
                context.report({
                    node,
                    messageId: 'lowercase',
                    data: {
                        snippet: trimmed.slice(0, 32),
                    },
                });
            }
        }

        function elementName(openingElement) {
            const name = openingElement.name;

            if (!name) {
                return null;
            }

            if (name.type === 'JSXIdentifier') {
                return name.name;
            }

            if (name.type === 'JSXMemberExpression' && name.property) {
                return name.property.name;
            }

            return null;
        }

        return {
            JSXElement(node) {
                const tag = elementName(node.openingElement);

                if (!tag || !TARGET_ELEMENTS.has(tag)) {
                    return;
                }

                for (const child of node.children) {
                    if (child.type === 'JSXText') {
                        checkText(child, child.value);
                        continue;
                    }

                    if (
                        child.type === 'JSXExpressionContainer' &&
                        child.expression &&
                        child.expression.type === 'Literal' &&
                        typeof child.expression.value === 'string'
                    ) {
                        checkText(child.expression, child.expression.value);
                    }
                }
            },
        };
    },
};

export default {
    rules: {
        'no-lowercase-titles': rule,
    },
};
