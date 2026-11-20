type LoadingState = { count: number; label: string };

let state: LoadingState = { count: 0, label: 'Memuat data' };
const listeners = new Set<() => void>();

function update(count: number, label = state.label) {
  state = { count, label };
  listeners.forEach(listener => listener());
}

export function subscribeLoading(listener: () => void) {
  listeners.add(listener);
  return () => { listeners.delete(listener); };
}

export function loadingSnapshot() {
  return state;
}

export function trackLoading<T>(pending: Promise<T>, label: string): Promise<T> {
  let shown = false;
  const timer = window.setTimeout(() => {
    shown = true;
    update(state.count + 1, label);
  }, 250);
  return pending.finally(() => {
    window.clearTimeout(timer);
    if (shown) update(Math.max(0, state.count - 1));
  });
}
