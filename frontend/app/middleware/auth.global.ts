export default defineNuxtRouteMiddleware((to) => {
  const api = useTailorsApi();

  // Public routes that don't require authentication
  const isAuthRoute = to.path === '/login' || to.path.startsWith('/auth');

  // If user is not authenticated and trying to access a protected route
  if (!api.token.value && !isAuthRoute) {
    return navigateTo('/login');
  }

  const permissions = api.permissions.value;

  // If user is already authenticated and on login page, send them to the first page their role can open
  if (api.token.value && (isAuthRoute || to.path === '/')) {
    return navigateTo(firstAllowedPath(permissions));
  }

  if (to.path.startsWith('/super-admin')) {
    return api.isSuperAdmin.value ? undefined : navigateTo(firstAllowedPath(permissions));
  }

  // Permission-based restrictions (the API enforces the same rules)
  if (api.token.value && !canOpen(to.path, permissions)) {
    return navigateTo(firstAllowedPath(permissions));
  }
});
