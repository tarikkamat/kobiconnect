import { usePage } from '@inertiajs/react';

export type PermissionCheck = {
    allowed: boolean;
    /** Devre disi birakma sebebi; izin varsa null. Tooltip'te gosterilir. */
    reason: string | null;
};

export type PermissionChecker = (permission: string) => PermissionCheck;

const ALLOWED: PermissionCheck = { allowed: true, reason: null };

/**
 * Paylasilan `permissions` prop'unu okur — FRONTEND-PLAN.md §6.
 *
 * Aksiyonlar GIZLENMEZ, devre disi birakilir ve sebebi tooltip'e girer.
 * Buradaki kontrol yalnizca UX icindir; gercek yaptirim Policy'lerdedir.
 */
export function usePermission(): PermissionChecker {
    const { permissions } = usePage().props;

    return (permission) => {
        if (!permissions.includes(permission)) {
            return {
                allowed: false,
                reason: 'Bu işlem için yetkiniz yok.',
            };
        }

        return ALLOWED;
    };
}
