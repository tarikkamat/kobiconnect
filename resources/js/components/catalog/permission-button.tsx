import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PermissionCheck } from '@/hooks/use-permission';

type Props = ComponentProps<typeof Button> & {
    check: PermissionCheck;
};

/**
 * Yetkisiz aksiyon gizlenmez, devre disi birakilir ve sebebi tooltip'te durur
 * — FRONTEND-PLAN.md §6. Devre disi buton pointer olayi uretmedigi icin
 * tetikleyici sarmalayici bir span'dir.
 */
export function PermissionButton({
    check,
    disabled,
    children,
    ...props
}: Props) {
    const button = (
        <Button {...props} disabled={disabled || !check.allowed}>
            {children}
        </Button>
    );

    if (check.reason === null) {
        return button;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span tabIndex={0} className="inline-flex">
                    {button}
                </span>
            </TooltipTrigger>
            <TooltipContent>{check.reason}</TooltipContent>
        </Tooltip>
    );
}
