import { fileURLToPath, URL } from "node:url";
import process from "node:process";
import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import { visualizer } from "rollup-plugin-visualizer";

export default defineConfig(({ mode }) => {
    process.env = { ...process.env, ...loadEnv(mode, process.cwd(), '') };
    const myIp = '192.168.112.197'; 

    return {
        // server: {
        //     host: true,
        //     port: 5173,
        //     strictPort: true,
        //     cors: true,
        //     origin: `http://${myIp}:5173`,
        //     hmr: { host: myIp },
        // },
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/main.ts"],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            visualizer({
                template: "treemap",
                open: false,
                gzipSize: true,
                brotliSize: true,
                filename: "analyse.html",
            }),
        ],
        resolve: {
            alias: {
                "vue-i18n": "vue-i18n/dist/vue-i18n.cjs.js",
                "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
            },
        },
        optimizeDeps: {
            esbuildOptions: { target: ["es2020", "safari14"] },
            include: ['vue3-toastify']
        },
        build: {
            chunkSizeWarningLimit: 1600, 
            target: ["es2020", "safari14"],
            rollupOptions: {
                output: {
                    globals: { jquery: "jQuery" },
                    manualChunks(id) {
                        if (id.includes('node_modules')) {
                            return id.toString().split('node_modules/')[1].split('/')[0].toString();
                        }
                    },
                },
            },
        },
    };
});