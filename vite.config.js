import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";

export default defineConfig({
  server: {
    host: "localhost",
    port: 5173,
    cors: true,
  },

  plugins: [
    laravel({
      input: "resources/js/app.jsx",
      refresh: true,
    }),
    react(),
  ],

  resolve: {
    alias: {
      "@": "/resources/js",
    },
  },

  build: {
    sourcemap: false,      // 🚫 não expõe NADA
    minify: "esbuild",     // 🔥 mais performance
    cssMinify: true,
  },

  define: {
    "process.env.NODE_ENV": '"production"',
  },
});
