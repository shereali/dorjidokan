// Reliable translation helper.
//
// vue-i18n's message loading is unreliable in this app's client bundle
// (lazy locale chunks don't always register, so `t()` returns literal keys
// and `tm()` only sees the default locale). To guarantee the bilingual UI
// works, both locale files are imported directly and resolved by the locale
// ref — fully reactive, no dependency on the i18n message registry.
import en from '../../i18n/locales/en.json'
import bn from '../../i18n/locales/bn.json'

// `useI18n` is auto-imported by Nuxt and carries the module's locale wiring.

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

// Vite compiles the imported JSON into intlify message AST nodes. The plain
// text of a node lives in its `static`/`source` fields; extract recursively.
function toText(value: unknown): string | undefined {
  if (typeof value === 'string') return value
  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>
    for (const key of ['static', 'source', 'value'] as const) {
      if (typeof record[key] === 'string') return record[key]
    }
    const body = record.body
    if (body && typeof body === 'object') {
      const bodyRecord = body as Record<string, unknown>
      for (const key of ['static', 'source', 'value'] as const) {
        if (typeof bodyRecord[key] === 'string') return bodyRecord[key]
      }
      if (Array.isArray(bodyRecord.items)) {
        const joined = bodyRecord.items
          .map(item => toText(item))
          .filter((part): part is string => part !== undefined)
          .join('')
        if (joined) return joined
      }
    }
    if (Array.isArray(record.items)) {
      const joined = record.items
        .map(item => toText(item))
        .filter((part): part is string => part !== undefined)
        .join('')
      if (joined) return joined
    }
  }
  return undefined
}

export function useTailorsI18n() {
  const { locale } = useI18n()

  const t = (key: string, params?: Record<string, string | number>): string => {
    const segments = key.split('.')
    const catalog = catalogs[locale.value] ?? catalogs.en
    const value = toText(getPath(catalog, segments))
    if (value !== undefined) {
      if (!params) return value
      return value.replace(/\{(\w+)\}/g, (_, name) => String(params[name] ?? `{${name}}`))
    }
    return key
  }

  return { t, locale }
}
