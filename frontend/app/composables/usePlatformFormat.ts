// Small formatting helpers shared by the super admin panels.
export function usePlatformFormat() {
  const { t, locale } = useTailorsI18n()
  const isBn = computed(() => locale.value === 'bn')


  const money = (minor: number | null | undefined, currency = 'BDT') => {
    const symbol = currency === 'BDT' ? '৳' : `${currency} `
    return `${symbol}${((minor ?? 0) / 100).toLocaleString(isBn.value ? 'bn-BD' : 'en-BD', { maximumFractionDigits: 2 })}`
  }

  const date = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleDateString(isBn.value ? 'bn-BD' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

  const dateTime = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleString(isBn.value ? 'bn-BD' : 'en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'

  const STATUSES = ['active', 'inactive', 'hold', 'suspended', 'none', 'trialing', 'past_due', 'cancelled', 'expired']
  const statusLabel = (status: string) => (STATUSES.includes(status) ? t(`app.platform.status_${status}`) : status)

  const METHODS = ['cash', 'bank_transfer', 'card', 'other']
  const methodLabel = (method: string) => (METHODS.includes(method) ? t(`app.platform.method_${method}`) : ({ bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket' } as Record<string, string>)[method] ?? method)

  /** CSS modifier used by the status chip: good | warn | bad | muted. */
  const tone = (status: string) =>
    ({ active: 'good', trialing: 'info', hold: 'warn', past_due: 'warn', inactive: 'muted', none: 'muted', cancelled: 'muted', suspended: 'bad', expired: 'bad' })[status] ?? 'muted'

  return { isBn, money, date, dateTime, statusLabel, methodLabel, tone }
}
