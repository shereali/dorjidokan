export interface ToastMessage {
  id: string;
  type: "success" | "error" | "info" | "warning";
  title?: string;
  message: string;
  duration?: number;
}

const toasts = ref<ToastMessage[]>([]);

export function useToast() {
  function show(message: string, type: ToastMessage["type"] = "success", title?: string, duration = 4000) {
    const id = Math.random().toString(36).substring(2, 9);
    const toast: ToastMessage = { id, type, message, title, duration };
    toasts.value.push(toast);

    if (duration > 0) {
      setTimeout(() => {
        dismiss(id);
      }, duration);
    }
    return id;
  }

  function success(message: string, title?: string) {
    return show(message, "success", title);
  }

  function error(message: string, title?: string) {
    return show(message, "error", title, 6000);
  }

  function info(message: string, title?: string) {
    return show(message, "info", title);
  }

  function warning(message: string, title?: string) {
    return show(message, "warning", title);
  }

  function dismiss(id: string) {
    const index = toasts.value.findIndex((t) => t.id === id);
    if (index !== -1) {
      toasts.value.splice(index, 1);
    }
  }

  return {
    toasts: readonly(toasts),
    show,
    success,
    error,
    info,
    warning,
    dismiss,
  };
}
