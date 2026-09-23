import { reactive, readonly } from 'vue';

export interface ConfirmDialogOptions {
  title: string;
  message: string;
  highlight?: string;
  confirmText?: string;
  cancelText?: string;
  type?: "danger" | "warning" | "info";
}

interface ConfirmDialogState extends ConfirmDialogOptions {
  isOpen: boolean;
  resolve?: (value: boolean) => void;
}

const state = reactive<ConfirmDialogState>({
  isOpen: false,
  title: "",
  message: "",
  highlight: "",
  confirmText: "হ্যাঁ, নিশ্চিত",
  cancelText: "বাতিল",
  type: "danger",
});

export function useConfirmDialog() {
  function confirm(options: ConfirmDialogOptions): Promise<boolean> {
    state.title = options.title;
    state.message = options.message;
    state.highlight = options.highlight || "";
    state.confirmText = options.confirmText || "হ্যাঁ, নিশ্চিত";
    state.cancelText = options.cancelText || "বাতিল";
    state.type = options.type || "danger";
    state.isOpen = true;

    return new Promise<boolean>((resolve) => {
      state.resolve = resolve;
    });
  }

  function handleConfirm() {
    state.isOpen = false;
    state.resolve?.(true);
    state.resolve = undefined;
  }

  function handleCancel() {
    state.isOpen = false;
    state.resolve?.(false);
    state.resolve = undefined;
  }

  return {
    state: readonly(state),
    confirm,
    handleConfirm,
    handleCancel,
  };
}
