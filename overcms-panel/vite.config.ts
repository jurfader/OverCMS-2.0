import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwind from '@tailwindcss/vite';
import path from 'node:path';

// Bedrock domyślnie udostępnia mu-plugins pod /app/mu-plugins/.
// Dla niestandardowych instalacji można nadpisać przez OVERCMS_BASE.
const base = process.env.OVERCMS_BASE ?? '/app/mu-plugins/overcms-core/panel/dist/';

export default defineConfig({
  base,
  plugins: [react(), tailwind()],
  build: {
    outDir: path.resolve(__dirname, '../web/app/mu-plugins/overcms-core/panel/dist'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/main.tsx'),
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
});
