export const useSessionStore = defineStore('session', () => {
  const authenticated = useCookie<boolean>('tailors_authenticated', { default: () => false, sameSite: 'strict' })
  const tenant = useCookie<string>('tailors_tenant', { default: () => '', sameSite: 'strict' })
  const tenantId = useCookie<string | null>('tailors_tenant_id', { sameSite: 'strict' })
  const isSuperAdmin = useCookie<boolean>('tailors_super_admin', { default: () => false, sameSite: 'strict' })
  const hasTwoFactor = useCookie<boolean>('tailors_two_factor', { default: () => false, sameSite: 'strict' })
  const role = useCookie<string>('tailors_role', { default: () => 'receptionist', sameSite: 'strict' })
  const permissions = useCookie<string[]>('tailors_permissions', { default: () => [], sameSite: 'strict' })
  const userEmail = useCookie<string>('tailors_user_email', { default: () => '', sameSite: 'strict' })

  function clear() {
    authenticated.value = false
    tenantId.value = null
    role.value = 'receptionist'
    permissions.value = []
    isSuperAdmin.value = false
    hasTwoFactor.value = false
    userEmail.value = ''
  }

  return { authenticated, tenant, tenantId, role, permissions, isSuperAdmin, hasTwoFactor, userEmail, clear }
})
