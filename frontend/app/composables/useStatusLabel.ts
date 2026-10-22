// Human-friendly labels for the raw status codes the API returns.
// Non-technical operators should never see snake_case.
const STATUS_LABELS: Record<string, string> = {
  measuring: 'Measuring',
  pending_assignment: 'Waiting for assignment',
  in_progress: 'At the workshop',
  ready: 'Ready for pickup',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  queued: 'Queued',
  scheduled: 'Scheduled',
  sent: 'Sent',
  failed: 'Failed',
  partial: 'Partly sent',
  active: 'Active',
  inactive: 'Inactive',
  trialing: 'Trial',
  past_due: 'Payment overdue',
}

const STATUS_LABELS_BN: Record<string, string> = {
  measuring: 'মাপ নেওয়া হচ্ছে',
  pending_assignment: 'কারিগরের অপেক্ষায়',
  in_progress: 'কাজ চলছে',
  ready: 'ডেলিভারির জন্য প্রস্তুত',
  delivered: 'ডেলিভারি হয়েছে',
  cancelled: 'বাতিল',
  queued: 'অপেক্ষায়',
  scheduled: 'নির্ধারিত',
  sent: 'পাঠানো হয়েছে',
  failed: 'ব্যর্থ',
  partial: 'আংশিক পাঠানো',
  skipped: 'বাদ দেওয়া',
  active: 'সক্রিয়',
  inactive: 'নিষ্ক্রিয়',
  trialing: 'ট্রায়াল',
  past_due: 'বকেয়া',
}

export function statusLabel(status: string): string {
  const bn = useCookie<string>('tailors_locale').value === 'bn'
  const label = (bn ? STATUS_LABELS_BN : STATUS_LABELS)[status]
  return label ?? status.replaceAll('_', ' ')
}
