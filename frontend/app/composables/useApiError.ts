// The API answers errors in two shapes: our envelope (`errors: [{ message }]`) and Laravel validation
// (`message` + `errors: { field: ['…'] }`). Show the first useful sentence instead of a generic fallback.
export function apiErrorMessage(error: unknown, fallback: string): string {
  const data = (error as { data?: { message?: string; errors?: unknown } })?.data
  const errors = data?.errors
  if (Array.isArray(errors) && errors[0]?.message) return String(errors[0].message)
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors as Record<string, unknown>)[0]
    if (Array.isArray(first) && first[0]) return String(first[0])
  }
  return data?.message || fallback
}
