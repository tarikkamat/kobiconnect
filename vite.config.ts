import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // latin-ext ZORUNLU: Turkce ğ/Ğ ve ş/Ş yalniz latin altkumesinde yok,
            // eksik olunca o iki harf sistem fontuna duser ve satir zipliyor.
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600],
                    subsets: ['latin', 'latin-ext'],
                    preload: [{ weight: 400 }, { weight: 500 }],
                }),
                // Sadece bos durum/onboarding basliklarinda — on yukleme israfi.
                bunny('Petrona', {
                    weights: [400],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
                bunny('Geist Mono', {
                    weights: [400, 500],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
