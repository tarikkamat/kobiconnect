import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Aksiyonlari gizlemek icin degil, devre disi birakmak icin. */
            permissions: string[];
            roles: string[];
            /** Central domain'de null. */
            tenant: { id: string; host: string } | null;
            /** Lisans yoksa veya central domain'de null. */
            license: {
                status:
                    'active' | 'grace' | 'expired' | 'suspended' | 'cancelled';
                endsAt: string | null;
                graceDaysLeft: number | null;
                readOnly: boolean;
            } | null;
            [key: string]: unknown;
        };
    }
}
