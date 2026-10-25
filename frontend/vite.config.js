import { defineConfig } from 'vite';
import { createVuePlugin } from 'vite-plugin-vue2';
import fs from 'fs';
import path from 'path';

const certPath = path.resolve(__dirname, 'cert.pem');
const keyPath = path.resolve(__dirname, 'key.pem');

const httpsConfig = fs.existsSync(certPath) && fs.existsSync(keyPath)
  ? {
      cert: fs.readFileSync(certPath),
      key: fs.readFileSync(keyPath)
    }
  : false;

export default defineConfig({
  plugins: [
    createVuePlugin()
  ],
  server: {
    host: '0.0.0.0',
    port: 3000,
    https: httpsConfig,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true
      }
    }
  }
});
