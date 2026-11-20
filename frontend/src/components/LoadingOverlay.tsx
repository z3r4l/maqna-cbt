import { useSyncExternalStore } from 'react';
import { loadingSnapshot, subscribeLoading } from '@/lib/loading';
import BarWave from '@/components/BarWave';

export default function LoadingOverlay() {
  const { count, label } = useSyncExternalStore(subscribeLoading, loadingSnapshot);
  if (count === 0) return null;

  return <div className="fixed inset-0 z-[100] grid place-items-center bg-white/90 px-4" aria-busy="true">
    <div role="status" aria-label={label} className="rounded-xl bg-white px-7 py-6 shadow-[0_8px_30px_rgba(24,53,49,0.12)]">
      <BarWave />
    </div>
  </div>;
}
