import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// El SPA llama a rutas relativas /api/..., el proxy de Vite las reenvia al
// backend PHP en desarrollo para evitar lidiar con CORS/puertos distintos.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
});
