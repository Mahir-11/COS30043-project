import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],

  // For Mercury: set to the exact public path on Mercury where your dist/ files live
  base: '/cos30043/s104799137/project/',


  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:3001',
        changeOrigin: true,
        // 1. Strip /api prefix and .php suffix
        // 2. Convert ?id=X to /X so json-server returns a single object, not an array
        rewrite: (path) => {
          let p = path.replace(/^\/api/, '').replace(/\.php(?=[?#]|$)/, '')
          // ?id=X[&...] → /X[?...]  (primary-key lookup → REST path)
          p = p.replace(/^([^?#]+)\?id=(\d+)(&(.*))?$/, (_, base, id, _amp, rest) =>
            rest ? `${base}/${id}?${rest}` : `${base}/${id}`
          )
          return p
        }
      }
    }
  }
})
