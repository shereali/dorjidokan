import { describe, expect, it } from 'vitest'
import { canOpen, firstAllowedPath } from '../../app/composables/useRouteAccess'

describe('route access', () => {
  it('hides finance pages from a receptionist-style permission set', () => {
    const perms = ['dashboard.view', 'orders.view', 'customers.view']
    expect(canOpen('/orders', perms)).toBe(true)
    expect(canOpen('/reports', perms)).toBe(false)
    expect(canOpen('/accounting', perms)).toBe(false)
    expect(canOpen('/settings/anything', perms)).toBe(false)
  })

  it('always allows help and unknown pages', () => {
    expect(canOpen('/help', [])).toBe(true)
  })

  it('redirects to the first page the role can open', () => {
    expect(firstAllowedPath(['orders.view'])).toBe('/orders')
    expect(firstAllowedPath([])).toBe('/help')
  })
})
