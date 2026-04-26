import * as React from 'react';
import { cn } from '@/lib/utils';
import { Input } from './input';
import { Label } from './label';

/**
 * REQ-M9-008 — Form-row primitive used across `pages/auth/*` and
 * `pages/settings/*`. Composes a shadcn Label + Input + optional helper text
 * + optional error message in one consistent `grid gap-2` row.
 *
 * The `control` slot lets callers swap the default `<Input>` for a
 * `PasswordInput` or any other input element while keeping the row chrome
 * (label, helper, error) identical.
 */

type FormFieldOwnProps = {
    /** The unique id used to associate the label with the control. */
    id: string;
    /** The visible label text. Always rendered above the control. */
    label: React.ReactNode;
    /** Optional helper text rendered between the control and the error. */
    helper?: React.ReactNode;
    /** Validation error message; if non-empty the field is treated as invalid. */
    error?: string;
    /** Optional content rendered to the right of the label (e.g. forgot-password link). */
    labelAction?: React.ReactNode;
    /**
     * Optional override control; receives `id` automatically when rendered.
     * When omitted we render the default shadcn `<Input>` and forward
     * input-level props (type, name, placeholder, …) to it.
     */
    control?: React.ReactElement<{ id?: string; 'aria-invalid'?: boolean }>;
    /** Wrapper class — rare; prefer relying on the default `grid gap-2`. */
    className?: string;
};

type FormFieldProps = FormFieldOwnProps &
    Omit<React.ComponentProps<'input'>, 'id'>;

function FormField({
    id,
    label,
    helper,
    error,
    labelAction,
    control,
    className,
    ...inputProps
}: FormFieldProps) {
    const hasError = typeof error === 'string' && error.length > 0;
    const errorId = hasError ? `${id}-error` : undefined;
    const helperId = helper ? `${id}-helper` : undefined;
    const describedBy =
        [helperId, errorId].filter(Boolean).join(' ') || undefined;

    const labelRow = labelAction ? (
        <div className="flex items-center justify-between gap-2">
            <Label htmlFor={id}>{label}</Label>
            {labelAction}
        </div>
    ) : (
        <Label htmlFor={id}>{label}</Label>
    );

    const renderedControl = control ? (
        React.cloneElement(control, {
            id,
            'aria-invalid': hasError ? true : undefined,
        })
    ) : (
        <Input
            id={id}
            aria-invalid={hasError || undefined}
            aria-describedby={describedBy}
            {...inputProps}
        />
    );

    return (
        <div className={cn('grid gap-2', className)}>
            {labelRow}
            {renderedControl}
            {helper && (
                <p id={helperId} className="text-xs text-muted-foreground">
                    {helper}
                </p>
            )}
            {hasError && (
                <p
                    id={errorId}
                    className="text-sm text-red-600 dark:text-red-400"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

export { FormField };
export type { FormFieldProps };
