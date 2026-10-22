// Order pipeline stages, in board order. Labels live in the catalog as app.orders.stage_<key>.
export const kanbanColumns = [
  { key: "measuring", color: "var(--status-measuring)" },
  { key: "pending_assignment", color: "var(--status-pending)" },
  { key: "in_progress", color: "var(--status-inprogress)" },
  { key: "ready", color: "var(--status-ready)" },
  { key: "delivered", color: "var(--status-delivered)" },
];
