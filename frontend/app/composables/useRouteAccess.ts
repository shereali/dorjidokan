// Single source of truth for which permission unlocks which page. The API enforces
// the same permissions; this only decides what the menu shows and where to redirect.
export interface RouteAccess {
  path: string
  /** Any one of these permissions unlocks the page. Empty = every signed-in user. */
  any: string[]
}

export const ROUTE_ACCESS: RouteAccess[] = [
  { path: '/dashboard', any: ['dashboard.view'] },
  { path: '/orders', any: ['orders.view'] },
  { path: '/customers', any: ['customers.view'] },
  { path: '/garments', any: ['garments.view', 'garments.manage'] },
  { path: '/karigars', any: ['employees.view', 'employees.manage'] },
  { path: '/inventory', any: ['inventory.view', 'inventory.manage'] },
  { path: '/operations', any: ['sales.manage', 'rentals.manage', 'expenses.view', 'expenses.manage', 'attendance.manage', 'work.manage', 'payouts.view', 'purchases.manage'] },
  { path: '/notifications', any: ['notifications.view', 'notifications.manage'] },
  { path: '/reports', any: ['reports.view'] },
  { path: '/accounting', any: ['accounting.view'] },
  { path: '/billing', any: ['billing.view', 'billing.manage'] },
  { path: '/settings/users', any: ['members.manage'] },
  { path: '/settings/roles', any: ['roles.manage', 'members.manage'] },
  { path: '/settings', any: ['settings.view', 'settings.manage'] },
  { path: '/help', any: [] },
]

export function routeAccessFor(path: string): RouteAccess | undefined {
  const exact = ROUTE_ACCESS.find((r) => path === r.path)
  if (exact) return exact
  const prefixes = ROUTE_ACCESS.filter((r) => path.startsWith(`${r.path}/`))
  if (prefixes.length) {
    return prefixes.sort((a, b) => b.path.length - a.path.length)[0]
  }
  return undefined
}

export function canOpen(path: string, permissions: string[]): boolean {
  const rule = routeAccessFor(path)
  if (!rule || !rule.any.length) return true
  return rule.any.some((p) => permissions.includes(p))
}

export function firstAllowedPath(permissions: string[]): string {
  return ROUTE_ACCESS.find((r) => r.any.length && canOpen(r.path, permissions))?.path ?? '/help'
}
