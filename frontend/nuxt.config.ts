// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: ['@nuxtjs/i18n', '@pinia/nuxt', '@nuxt/image'],
  srcDir: 'app/',
  serverDir: 'server',
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  experimental: {
    appManifest: false,
  },
  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      title: 'Dorjidokan — Bespoke Tailoring & Workshop ERP',
      meta: [{ name: 'description', content: 'Dorjidokan — Bespoke tailoring atelier operations, measurements, kanban pipeline and POS.' }],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap' },
      ],
    },
  },
  css: ['../assets/css/settings/_semantic.scss', '../assets/css/main.scss', '../assets/css/components/_login.scss', '../assets/css/components/_workspace.scss', '../assets/css/components/_operations.scss', '../assets/css/components/_responsive.scss'],
  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'en',
    locales: [
      { code: 'bn', name: 'বাংলা', file: 'bn.json', lazy: false },
      { code: 'en', name: 'English', file: 'en.json', lazy: false },
    ],
    langDir: '../i18n/locales',
    experimental: { optimizeMessageBundling: false },
    detectBrowserLanguage: { useCookie: true, cookieKey: 'tailors_locale', redirectOn: 'root', fallbackLocale: 'en' },
  },
  runtimeConfig: { public: {
    apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
    reverbKey: process.env.NUXT_PUBLIC_REVERB_APP_KEY || '',
    reverbHost: process.env.NUXT_PUBLIC_REVERB_HOST || 'localhost',
    reverbPort: Number(process.env.NUXT_PUBLIC_REVERB_PORT || 8080),
    reverbScheme: process.env.NUXT_PUBLIC_REVERB_SCHEME || 'http',
  } },
  typescript: { strict: true }
})
