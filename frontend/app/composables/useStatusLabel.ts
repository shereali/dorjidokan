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

export function statusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status.replaceAll('_', ' ')
}
