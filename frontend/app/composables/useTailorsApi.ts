interface Envelope<T> { data: T; meta: Record<string, unknown>; errors: Array<{ message: string }> }

export const useTailorsApi = () => {
  const config = useRuntimeConfig()
  const session = useSessionStore()
  const { authenticated, tenant, tenantId, isSuperAdmin, hasTwoFactor, role, permissions, userEmail } = storeToRefs(session)
  const locale = useCookie<string>('tailors_locale', { default: () => 'en', sameSite: 'lax' })
  const xsrf = useCookie<string | null>('XSRF-TOKEN')
  const apiOrigin = new URL(config.public.apiBase as string).origin

  const csrf = async () => {
    await $fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: 'include' })
    refreshCookie('XSRF-TOKEN')
  }
  const request = async <T>(path: string, options: Record<string, any> = {}) => {
    const method = String(options.method || 'GET').toUpperCase()
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !xsrf.value) await csrf()
    refreshCookie('XSRF-TOKEN')
    try {
      return await $fetch<Envelope<T>>(`${config.public.apiBase}${path}`, {
      ...options,
      credentials: 'include',
      headers: { Accept: 'application/json', 'Accept-Language': locale.value, 'X-Tenant': tenant.value, ...(xsrf.value ? { 'X-XSRF-TOKEN': xsrf.value } : {}), ...(options.headers || {}) },
      })
    } catch (error: any) {
      // Platform-level locks (hold / expired plan) explain themselves once, whichever page triggered them.
      const code = error?.data?.errors?.[0]?.code
      if (['subscription_expired', 'tenant_on_hold', 'tenant_suspended', 'tenant_inactive'].includes(code)) {
        useToast().warning(error.data.errors[0].message)
      }
      throw error
    }
  }
  // Authenticated file download (CSV exports); the browser cannot send the session cookie via a plain link to the API origin.
  const download = async (path: string, filename: string) => {
    const blob = await $fetch<Blob>(`${config.public.apiBase}${path}`, { credentials: 'include', responseType: 'blob', headers: { Accept: 'text/csv', 'X-Tenant': tenant.value } })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  }
  const login = async (email: string, password: string, twoFactorCode = '') => {
    await csrf()
    const response = await request<{ authenticated: boolean; tenant: { id: string; slug: string }; user: { role: string; permissions: string[]; is_super_admin: boolean; two_factor_confirmed: boolean } }>('/auth/login', { method: 'POST', body: { tenant: tenant.value, email, password, two_factor_code: twoFactorCode || null } })
    authenticated.value = response.data.authenticated
    tenant.value = response.data.tenant.slug
    tenantId.value = response.data.tenant.id
    role.value = response.data.user.role
    permissions.value = response.data.user.permissions || []
    isSuperAdmin.value = response.data.user.is_super_admin
    hasTwoFactor.value = response.data.user.two_factor_confirmed
    userEmail.value = email
    return response.data
  }
  const register = async (body: { business_name: string; slug: string; name: string; email: string; password: string; password_confirmation: string; locale: string }) => {
    await csrf()
    const response = await request<{ authenticated: boolean; tenant: { id: string; slug: string }; user: { name: string } }>('/onboarding', { method: 'POST', body })
    authenticated.value = response.data.authenticated
    tenant.value = response.data.tenant.slug
    tenantId.value = response.data.tenant.id
    role.value = 'owner'
    isSuperAdmin.value = false
    hasTwoFactor.value = false
    userEmail.value = body.email
    await refreshMe()
    return response.data
  }
  // Permissions are enforced by the API; this only keeps the menu and buttons in sync with it.
  const refreshMe = async () => {
    try {
      const res = await request<{ role: string; permissions: string[]; is_super_admin: boolean }>('/auth/me')
      role.value = res.data.role
      permissions.value = res.data.permissions
      isSuperAdmin.value = res.data.is_super_admin
    } catch {
      // Leave the cached values; a 401 is handled by the login redirect.
    }
  }
  const can = (...needed: string[]) => needed.some((p) => permissions.value.includes(p))
  const logout = async () => {
    try {
      if (authenticated.value) {
        await request('/auth/logout', { method: 'POST' })
      }
    } catch {
      // Ignore 401 or network errors on logout
    } finally {
      session.clear()
    }
  }

  return { request, download, login, register, logout, refreshMe, can, token: authenticated, authenticated, tenant, tenantId, role, permissions, isSuperAdmin, hasTwoFactor, userEmail }
}
