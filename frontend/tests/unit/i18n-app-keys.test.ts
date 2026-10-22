import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import en from '../../i18n/locales/en.json'
import bn from '../../i18n/locales/bn.json'

function leaves(obj: Record<string, unknown>, prefix = ''): Record<string, string> {
  return Object.entries(obj).reduce<Record<string, string>>((acc, [key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return value && typeof value === 'object' ? { ...acc, ...leaves(value as Record<string, unknown>, path) } : { ...acc, [path]: String(value) }
  }, {})
}

function sources(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const full = join(dir, name)
    return statSync(full).isDirectory() ? sources(full) : /\.(vue|ts)$/.test(name) ? [full] : []
  })
}

const enLeaves = leaves(en as Record<string, unknown>)
const bnLeaves = leaves(bn as Record<string, unknown>)
const params = (text: string) => [...text.matchAll(/\{(\w+)\}/g)].map(m => m[1]).sort()

describe('app.* translation catalog', () => {
  it('has non-empty text and identical placeholders in both languages', () => {
    const appKeys = Object.keys(enLeaves).filter(k => k.startsWith('app.'))
    expect(appKeys.length).toBeGreaterThan(500)
    for (const key of appKeys) {
      expect(enLeaves[key]!.trim(), key).not.toBe('')
      expect(bnLeaves[key]?.trim(), key).toBeTruthy()
      expect(params(bnLeaves[key]!), key).toEqual(params(enLeaves[key]!))
    }
  })

  it('avoids characters that vue-i18n treats as message syntax', () => {
    for (const [key, text] of Object.entries(enLeaves).filter(([k]) => k.startsWith('app.'))) {
      expect(text.replace(/\{p\d+\}/g, ''), key).not.toMatch(/[|@${}]/)
      expect(bnLeaves[key]!.replace(/\{p\d+\}/g, ''), key).not.toMatch(/[|@${}]/)
    }
  })

  it('references only keys that exist', () => {
    const missing: string[] = []
    for (const file of sources(join(__dirname, '../../app'))) {
      const text = readFileSync(file, 'utf8')
      for (const match of text.matchAll(/\bt\(\s*'(app\.[\w.]+)'/g)) {
        if (!(match[1]! in enLeaves)) missing.push(`${file}: ${match[1]}`)
      }
    }
    expect(missing).toEqual([])
  })

  it('no longer carries inline tr(en, bn) pairs', () => {
    const offenders = sources(join(__dirname, '../../app')).filter(file => /(?<![\w$.])tr\(/.test(readFileSync(file, 'utf8')))
    expect(offenders).toEqual([])
  })
})
