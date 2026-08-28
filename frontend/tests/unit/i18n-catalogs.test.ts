import { describe, expect, it } from 'vitest'
import en from '../../i18n/locales/en.json'
import bn from '../../i18n/locales/bn.json'

// The app must be genuinely bilingual: every key in the English catalog must
// exist in the Bengali catalog (and vice versa), so the language switcher can
// never fall back to a literal key.
function flatten(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return value && typeof value === 'object' ? flatten(value as Record<string, unknown>, path) : [path]
  })
}

describe('i18n locale catalogs', () => {
  it('keeps English and Bengali catalogs in key parity', () => {
    const enKeys = new Set(flatten(en))
    const bnKeys = new Set(flatten(bn))
    const missingInBn = [...enKeys].filter(key => !bnKeys.has(key))
    const missingInEn = [...bnKeys].filter(key => !enKeys.has(key))
    expect(missingInBn).toEqual([])
    expect(missingInEn).toEqual([])
  })

  it('translates the navigation and core workspace headings', () => {
    expect(bn.nav.Dashboard).toBe('ড্যাশবোর্ড')
    expect(bn.nav.Orders).toBe('অর্ডার')
    expect(bn.workspace.customers.title).toBe('গ্রাহক')
    expect(en.workspace.dashboard.title).toBe('Dashboard')
    expect(en.status.ready).toBe('Ready')
    expect(bn.status.ready).toBe('প্রস্তুত')
  })
})
