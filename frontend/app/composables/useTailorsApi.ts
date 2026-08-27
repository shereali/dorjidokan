interface Envelope<T> { data: T; meta: Record<string, unknown>; errors: Array<{ message: string }> }

export const useTailorsApi = () => {
  const config = useRuntimeConfig()
  const session = useSessionStore()
  const { authenticated, tenant, tenantId, isSuperAdmin, hasTwoFactor, role } = storeToRefs(session)
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
    return $fetch<Envelope<T>>(`${config.public.apiBase}${path}`, {
      ...options,
      credentials: 'include',
      headers: { Accept: 'application/json', 'Accept-Language': locale.value, 'X-Tenant': tenant.value, ...(xsrf.value ? { 'X-XSRF-TOKEN': xsrf.value } : {}), ...(options.headers || {}) },
    })
  }
  const login = async (email: string, password: string, twoFactorCode = '') => {
    await csrf()
    const response = await request<{ authenticated: boolean; tenant: { id: string; slug: string }; user: { role: string; is_super_admin: boolean; two_factor_confirmed: boolean } }>('/auth/login', { method: 'POST', body: { tenant: tenant.value, email, password, two_factor_code: twoFactorCode || null } })
    authenticated.value = response.data.authenticated
    tenant.value = response.data.tenant.slug
    tenantId.value = response.data.tenant.id
    role.value = response.data.user.role
    isSuperAdmin.value = response.data.user.is_super_admin
    hasTwoFactor.value = response.data.user.two_factor_confirmed
    return response.data
  }
  const register = async (body: { business_name: string; slug: string; name: string; email: string; password: string; password_confirmation: string; locale: string }) => {
    await csrf()
    const response = await request<{ authenticated: boolean; tenant: { id: string; slug: string }; user: { name: string } }>('/onboarding', { method: 'POST', body })
    authenticated.value = response.data.authenticated
    tenant.value = response.data.tenant.slug
    tenantId.value = response.data.tenant.id
    role.value = 'admin'
    isSuperAdmin.value = false
    hasTwoFactor.value = false
    return response.data
  }
  const logout = async () => {
    if (authenticated.value) await request('/auth/logout', { method: 'POST' })
    session.clear()
  }

  return { request, login, register, logout, token: authenticated, authenticated, tenant, tenantId, role, isSuperAdmin, hasTwoFactor }
}
