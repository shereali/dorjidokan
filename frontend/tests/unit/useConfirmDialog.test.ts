import { describe, expect, it } from 'vitest';
import { useConfirmDialog } from '../../app/composables/useConfirmDialog';

describe('useConfirmDialog', () => {
  it('resolves true when handleConfirm is called', async () => {
    const { confirm, state, handleConfirm } = useConfirmDialog();

    const promise = confirm({
      title: 'Delete item',
      message: 'Are you sure?',
      highlight: 'Panjabi',
      type: 'danger',
    });

    expect(state.isOpen).toBe(true);
    expect(state.title).toBe('Delete item');
    expect(state.highlight).toBe('Panjabi');
    expect(state.type).toBe('danger');

    handleConfirm();

    const result = await promise;
    expect(result).toBe(true);
    expect(state.isOpen).toBe(false);
  });

  it('resolves false when handleCancel is called', async () => {
    const { confirm, state, handleCancel } = useConfirmDialog();

    const promise = confirm({
      title: 'Cancel action',
      message: 'Dismiss changes?',
      type: 'warning',
    });

    expect(state.isOpen).toBe(true);

    handleCancel();

    const result = await promise;
    expect(result).toBe(false);
    expect(state.isOpen).toBe(false);
  });
});
