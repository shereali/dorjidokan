// Role and permission names live in the catalog (app.roles.*, app.permission_groups.*, app.permissions.*).
// Permission codes contain a dot ("orders.view"), which is the catalog separator, so keys use an underscore.
type Lang = 'en' | 'bn'

const lookup = (key: string, lang: Lang, fallback: string) => {
  const text = translate(key, undefined, lang)
  return text === key ? fallback : text
}

export const roleLabel = (role: string, lang: Lang) => lookup(`app.roles.${role}`, lang, role)
export const roleHint = (role: string, lang: Lang) => lookup(`app.roles.${role}_hint`, lang, '')
export const groupLabel = (group: string, lang: Lang) => lookup(`app.permission_groups.${group}`, lang, group)
export const permissionLabel = (permission: string, lang: Lang) => lookup(`app.permissions.${permission.replace('.', '_')}`, lang, permission)
