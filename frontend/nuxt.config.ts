// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: ['@nuxtjs/i18n', '@pinia/nuxt', '@nuxt/image'],
  srcDir: 'app/',
  serverDir: 'server',
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      title: 'Suto Tailor OS',
      meta: [{ name: 'description', content: 'Tailoring workshop operations and measurement management.' }],
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
    langDir: 'locales',
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
