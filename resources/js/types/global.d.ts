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
            /** Kisiye ozel tablo kolon gorunurlugu: {"orders.index": {hidden: [...]}} */
            tablePreferences: Record<string, { hidden: string[] } | undefined>;
            [key: string]: unknown;
        };
    }
}
