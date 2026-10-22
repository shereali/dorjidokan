import { describe, expect, it } from 'vitest'
import { apiErrorMessage } from '../../app/composables/useApiError'

describe('apiErrorMessage', () => {
  it('prefers the envelope message', () => {
    expect(apiErrorMessage({ data: { errors: [{ message: 'Plan limit reached' }] } }, 'fallback')).toBe('Plan limit reached')
  })

  it('reads the first Laravel validation message', () => {
    expect(apiErrorMessage({ data: { message: 'The given data was invalid.', errors: { email: ['Email is taken.'] } } }, 'fallback')).toBe('Email is taken.')
  })

  it('falls back to the top-level message, then the supplied text', () => {
    expect(apiErrorMessage({ data: { message: 'Too many attempts.' } }, 'fallback')).toBe('Too many attempts.')
    expect(apiErrorMessage(new Error('network down'), 'fallback')).toBe('fallback')
    expect(apiErrorMessage(undefined, 'fallback')).toBe('fallback')
  })
})
