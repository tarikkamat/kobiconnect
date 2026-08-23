import { useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PermissionCheck } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

type Props = {
    /** Ham deger — duzenleme kutusuna bu girer. */
    value: number | null;
    /** Sunucuda bicimlendirilmis gosterim — FRONTEND-PLAN.md §7. */
    display: string;
    check: PermissionCheck;
    step?: string;
    label: string;
    onCommit: (value: number) => void;
};

/**
 * Tikla-duzenle hucresi. Kaydetme cagirana aittir; cagiran istegi `optimistic`
 * visit ile yollar, basarisizlikta Inertia degeri kendisi geri alir.
 */
export function InlineNumberCell({
    value,
    display,
    check,
    step = '1',
    label,
    onCommit,
}: Props) {
    const [editing, setEditing] = useState(false);

    const commit = (raw: string): void => {
        setEditing(false);
        const parsed = Number(raw);

        if (raw.trim() === '' || Number.isNaN(parsed) || parsed === value) {
            return;
        }

        onCommit(parsed);
    };

    if (!editing) {
        const trigger = (
            <button
                type="button"
                aria-label={label}
                disabled={!check.allowed}
                onClick={() => setEditing(true)}
                className={cn(
                    'w-full rounded-md px-2 py-1 text-left font-mono tabular-nums transition-colors',
                    check.allowed
                        ? 'hover:bg-secondary'
                        : 'cursor-not-allowed text-foreground/25',
                )}
            >
                {display}
            </button>
        );

        if (check.reason === null) {
            return trigger;
        }

        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex w-full">{trigger}</span>
                </TooltipTrigger>
                <TooltipContent>{check.reason}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <Input
            type="number"
            min="0"
            step={step}
            aria-label={label}
            autoFocus
            defaultValue={value ?? ''}
            className="h-8 w-28 rounded-md font-mono tabular-nums"
            onBlur={(event) => commit(event.currentTarget.value)}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }

                if (event.key === 'Escape') {
                    setEditing(false);
                }
            }}
        />
    );
}
