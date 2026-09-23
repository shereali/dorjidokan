// Reliable translation helper.
//
// vue-i18n's message loading is unreliable in this app's client bundle
// (lazy locale chunks don't always register, so `t()` returns literal keys
// and `tm()` only sees the default locale). To guarantee the bilingual UI
// works, both locale files are imported directly and resolved by the locale
// ref — fully reactive, no dependency on the i18n message registry.
import en from '../../i18n/locales/en.json'
import bn from '../../i18n/locales/bn.json'

type Messages = typeof en
const rawEn = en as unknown as { default?: Messages }
const rawBn = bn as unknown as { default?: Messages }

// Vite may wrap the JSON import in one or two `default` layers depending on
// the query suffix — unwrap until we reach a plain messages object.
function unwrap<T>(candidate: unknown): T {
  let current = candidate
  while (current && typeof current === 'object' && 'default' in (current as Record<string, unknown>) && Object.keys(current as Record<string, unknown>).length === 1) {
    current = (current as Record<string, unknown>).default
  }
  return current as T
}

const catalogs: Record<string, Messages> = { en: unwrap<Messages>(rawEn), bn: unwrap<Messages>(rawBn) }

function getPath(obj: unknown, segments: string[]): unknown {
  return segments.reduce<unknown>((acc, segment) => (acc && typeof acc === 'object' ? (acc as Record<string, unknown>)[segment] : undefined), obj)
}

// Vite and @intlify/unplugin-vue-i18n compile imported JSON into intlify message AST nodes.
// In dev they contain `static`/`source`/`body`/`items`; in production builds they are
// minified to `s` (static/source), `v` (value), `b` (body), `i` (items), `k` (param key).
function toText(value: unknown): string | undefined {
  if (typeof value === 'string') return value
  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>

    for (const key of ['s', 'static', 'source', 'v', 'value', 'text'] as const) {
      if (typeof record[key] === 'string') return record[key] as string
    }

    const body = (record.b ?? record.body) as Record<string, unknown> | undefined
    if (body && typeof body === 'object') {
      for (const key of ['s', 'static', 'source', 'v', 'value', 'text'] as const) {
        if (typeof body[key] === 'string') return body[key] as string
      }
      const items = (body.i ?? body.items) as unknown[] | undefined
      if (Array.isArray(items)) {
        const parts = items.map(item => {
          if (item && typeof item === 'object') {
            const r = item as Record<string, unknown>
            const k = r.k ?? r.key
            if (typeof k === 'string') return `{${k}}`
          }
          return toText(item) ?? ''
        })
        const joined = parts.join('')
        if (joined) return joined
      }
    }

    const items = (record.i ?? record.items) as unknown[] | undefined
    if (Array.isArray(items)) {
      const parts = items.map(item => {
        if (item && typeof item === 'object') {
          const r = item as Record<string, unknown>
          const k = r.k ?? r.key
          if (typeof k === 'string') return `{${k}}`
        }
        return toText(item) ?? ''
      })
      const joined = parts.join('')
      if (joined) return joined
    }
  }
  return undefined
}

export function useTailorsI18n() {
  const i18n = useI18n()
  const locale = i18n.locale

  const t = (key: string, params?: Record<string, string | number>): string => {
    const segments = key.split('.')
    const currentCode = (locale && unref(locale)) || 'bn'
    const catalog = catalogs[currentCode] ?? catalogs.bn ?? catalogs.en
    const candidate = getPath(catalog, segments)
    let value = toText(candidate)

    // Fallback to English catalog if key not found in current locale
    if (value === undefined && currentCode !== 'en') {
      value = toText(getPath(catalogs.en, segments))
    }
    // Fallback to Bengali catalog if still not found
    if (value === undefined && currentCode !== 'bn') {
      value = toText(getPath(catalogs.bn, segments))
    }

    if (value !== undefined) {
      if (!params) return value
      return value.replace(/\{(\w+)\}/g, (_, name) => String(params[name] ?? `{${name}}`))
    }
    return key
  }

  return { t, locale }
}

