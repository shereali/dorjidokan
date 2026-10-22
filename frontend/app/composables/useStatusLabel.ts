// Human-friendly labels for the raw status codes the API returns (catalog: app.order_status.*).
// Non-technical operators should never see snake_case.
export function statusLabel(status: string): string {
  const code = useCookie<string>('tailors_locale').value === 'bn' ? 'bn' : 'en'
  const key = `app.order_status.${status}`
  const label = translate(key, undefined, code)
  return label === key ? status.replaceAll('_', ' ') : label
}
