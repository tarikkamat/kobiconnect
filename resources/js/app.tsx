import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { initialiseTenantUrlDefaults } from '@/lib/tenant-url-defaults';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome' || name === 'error':
                return null;
            // Central onboarding sayfalarinda oturum yoktur; AppLayout'un
            // kenar cubugu ve auth.user beklentisi burada karsilanamaz.
            case name.startsWith('auth/'):
            case name.startsWith('onboarding/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app, { page }) {
        // Wayfinder'in tenant varsayilanini besle — bkz. lib/tenant-url-defaults.
        initialiseTenantUrlDefaults(page);

        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#18e299',
    },
});

// This will set light / dark mode on load...
initializeTheme();
