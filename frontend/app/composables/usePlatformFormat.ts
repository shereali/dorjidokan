// Small formatting helpers shared by the super admin panels.
export function usePlatformFormat() {
  const { locale } = useTailorsI18n()
  const isBn = computed(() => locale.value === 'bn')

  /** Pick the Bengali or English string for the active language. */
  const tr = (en: string, bn: string) => (isBn.value ? bn : en)

  const money = (minor: number | null | undefined, currency = 'BDT') => {
    const symbol = currency === 'BDT' ? '৳' : `${currency} `
    return `${symbol}${((minor ?? 0) / 100).toLocaleString(isBn.value ? 'bn-BD' : 'en-BD', { maximumFractionDigits: 2 })}`
  }

  const date = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleDateString(isBn.value ? 'bn-BD' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

  const dateTime = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleString(isBn.value ? 'bn-BD' : 'en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'

  const statusLabel = (status: string) =>
    ({
      active: tr('Active', 'সক্রিয়'),
      inactive: tr('Inactive', 'নিষ্ক্রিয়'),
      hold: tr('On hold', 'হোল্ড'),
      suspended: tr('Suspended', 'সাসপেন্ড'),
      none: tr('No plan', 'প্ল্যান নেই'),
      trialing: tr('Trial', 'ট্রায়াল'),
      past_due: tr('Payment due', 'বকেয়া'),
      cancelled: tr('Cancelled', 'বাতিল'),
      expired: tr('Expired', 'মেয়াদোত্তীর্ণ'),
    })[status] ?? status

  const methodLabel = (method: string) =>
    ({ cash: tr('Cash', 'নগদ'), bank_transfer: tr('Bank transfer', 'ব্যাংক ট্রান্সফার'), bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket', card: tr('Card', 'কার্ড'), other: tr('Other', 'অন্যান্য') })[method] ?? method

  /** CSS modifier used by the status chip: good | warn | bad | muted. */
  const tone = (status: string) =>
    ({ active: 'good', trialing: 'info', hold: 'warn', past_due: 'warn', inactive: 'muted', none: 'muted', cancelled: 'muted', suspended: 'bad', expired: 'bad' })[status] ?? 'muted'

  return { isBn, tr, money, date, dateTime, statusLabel, methodLabel, tone }
}
