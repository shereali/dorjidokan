import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { translate } from '../../app/composables/useTailorsI18n'

// Every permission the API can return must have a readable label in both languages.
const config = readFileSync(resolve(process.cwd(), '../backend/config/permissions.php'), 'utf8')
const groupsBlock = config.slice(config.indexOf('$all = ['), config.indexOf('$flat'))
const permissions = [...groupsBlock.matchAll(/'([a-z]+\.[a-z]+)'/g)].map(m => m[1]!)
const groups = [...groupsBlock.matchAll(/^\s+'([a-z]+)' => \[/gm)].map(m => m[1]!)
const roles = [...config.matchAll(/^\s+'(\w+)' => \['name' =>/gm)].map(m => m[1]!)

describe('role and permission labels', () => {
  it('finds the backend catalog', () => {
    expect(permissions.length).toBeGreaterThan(40)
    expect(groups.length).toBeGreaterThanOrEqual(9)
    expect(roles).toContain('accountant')
  })

  it.each(['en', 'bn'])('labels every permission, group and role in %s', (lang) => {
    for (const permission of permissions) {
      const key = `app.permissions.${permission.replace('.', '_')}`
      expect(translate(key, undefined, lang), key).not.toBe(key)
    }
    for (const group of groups) expect(translate(`app.permission_groups.${group}`, undefined, lang)).not.toBe(`app.permission_groups.${group}`)
    for (const role of roles) expect(translate(`app.roles.${role}`, undefined, lang)).not.toBe(`app.roles.${role}`)
  })

  it('translates order statuses per language', () => {
    expect(translate('app.order_status.in_progress', undefined, 'en')).toBe('At the workshop')
    expect(translate('app.order_status.in_progress', undefined, 'bn')).toBe('কাজ চলছে')
  })
})
