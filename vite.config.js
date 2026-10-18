import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],

    build: {
        // Minifikasi menggunakan esbuild (built-in, lebih cepat dari terser)
        // Ganti ke 'terser' jika perlu minifikasi lebih agresif (perlu: npm i terser)
        minify: 'esbuild',

        // Target browser modern (esnext) agar esbuild tidak perlu transform destructuring kompleks
        target: 'esnext',

        // Ukuran warning chunk (600KB sebelum warning muncul)
        chunkSizeWarningLimit: 600,

        rollupOptions: {
            output: {
                // Pisahkan vendor libraries ke chunk terpisah
                // agar browser bisa cache lebih efisien
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },

                // Naming dengan hash untuk cache busting
                entryFileNames:  'assets/[name]-[hash].js',
                chunkFileNames:  'assets/[name]-[hash].js',
                assetFileNames:  'assets/[name]-[hash][extname]',
            },
        },

        // Tampilkan ukuran file setelah kompresi gzip di terminal
        reportCompressedSize: true,

        // Source maps hanya untuk production debugging (bisa di-set false untuk keamanan)
        sourcemap: false,

        // CSS code splitting: CSS di-inject per chunk
        cssCodeSplit: true,
    },

    // Optimasi dev server (tidak berpengaruh saat build)
    server: {
        hmr: {
            overlay: true,
        },
    },
});
