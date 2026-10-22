// Sends uncaught browser errors to the API log (POST /client-errors) so they show up next to the server logs.
// Replace `report()` with Sentry.captureException(...) if a hosted tracker is added; see docs/operations.md.
type ErrorKind = 'vue' | 'unhandled' | 'promise' | 'resource'

const SEEN_LIMIT = 20

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const seen = new Set<string>()
  let sending = false

  function report(kind: ErrorKind, error: unknown, component?: string) {
    const err = error instanceof Error ? error : new Error(typeof error === 'string' ? error : 'Unknown error')
    const key = `${kind}:${err.message}`
    // One report per distinct error per page load keeps a render loop from flooding the endpoint.
    if (sending || seen.has(key) || seen.size >= SEEN_LIMIT) return
    seen.add(key)
    sending = true
    $fetch(`${config.public.apiBase}/client-errors`, {
      method: 'POST',
      body: { message: err.message || 'Error', stack: err.stack, url: window.location.href, component, kind },
    })
      .catch(() => {})
      .finally(() => { sending = false })
  }

  nuxtApp.vueApp.config.errorHandler = (error, instance, info) => {
    report('vue', error, instance?.$options?.name || info)
    console.error(error)
  }
  window.addEventListener('error', (event) => {
    if (event.target && event.target !== window) {
      const target = event.target as HTMLElement
      report('resource', `Failed to load ${target.tagName.toLowerCase()}: ${(target as HTMLImageElement).src || ''}`)
      return
    }
    report('unhandled', event.error ?? event.message)
  }, true)
  window.addEventListener('unhandledrejection', (event) => report('promise', event.reason))
})
