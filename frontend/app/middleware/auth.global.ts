export default defineNuxtRouteMiddleware((to) => {
  const api = useTailorsApi();

  // Public routes that don't require authentication
  const isAuthRoute = to.path === '/login' || to.path.startsWith('/auth');

  // If user is not authenticated and trying to access a protected route
  if (!api.token.value && !isAuthRoute) {
    return navigateTo('/login');
  }

  // If user is already authenticated and on login page, redirect to dashboard
  if (api.token.value && (isAuthRoute || to.path === '/')) {
    return navigateTo('/dashboard');
  }

  // Role-based restrictions
  if (to.path.startsWith('/accounting') || to.path.startsWith('/settings')) {
    if (!['admin', 'manager'].includes(api.role.value)) {
      return navigateTo('/dashboard');
    }
  }

  if (to.path.startsWith('/super-admin') && !api.isSuperAdmin.value) {
    return navigateTo('/dashboard');
  }
});
