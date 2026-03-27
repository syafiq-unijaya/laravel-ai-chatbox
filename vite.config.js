import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue()],
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
    build: {
        lib: {
            entry: resolve(__dirname, 'src/resources/js/app.js'),
            name: 'AiChatbox',
            formats: ['iife'],
            fileName: () => 'js/chatbox.js',
        },
        outDir: 'src/resources/assets',
        emptyOutDir: false,
        cssCodeSplit: false,
        rollupOptions: {
            output: {
                assetFileNames: ({ name }) =>
                    name?.endsWith('.css') ? 'css/chatbox.css' : '[name][extname]',
            },
        },
    },
})
