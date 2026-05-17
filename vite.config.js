import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/blueprint-floor-map.js',
                'resources/js/seating-layout.js',
                'resources/js/dashboard-seat-map-waitlist.js',
            ],
            refresh: true,
        }),
    ],
    optimizeDeps: {
        exclude: ['livewire', 'alpinejs', '@livewire/livewire'],
    },
    build: {
        rollupOptions: {
            external: ['livewire', 'alpinejs', '@livewire/livewire'],
        },
    },
});
