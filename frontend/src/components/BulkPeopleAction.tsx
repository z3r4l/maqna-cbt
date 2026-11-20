import { useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Preview = Awaited<ReturnType<typeof api.previewBulkPeople>>;

export function BulkPeopleAction({ kind, ids, onDone }: { kind: 'students' | 'teachers'; ids: number[]; onDone: () => void }) {
  const [preview, setPreview] = useState<Preview | null>(null);
  const [reason, setReason] = useState('');
  const [confirmationText, setConfirmationText] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function inspect() {
    setBusy(true); setError(''); setPreview(null); setReason(''); setConfirmationText(''); setConfirmed(false);
    try { setPreview(await api.previewBulkPeople(kind, ids)); }
    catch (cause) { setError((cause as Error).message); }
    finally { setBusy(false); }
  }

  async function commit() {
    if (!preview || !confirmed || confirmationText !== 'NONAKTIFKAN ' + preview.count || reason.trim().length < 10) return;
    setBusy(true); setError('');
    try {
      await api.commitBulkPeople(kind, preview.id, reason.trim(), preview.count);
      setPreview(null); onDone();
    } catch (cause) { setError((cause as Error).message); }
    finally { setBusy(false); }
  }

  return <div className="space-y-3">
    <Button type="button" variant="outline" disabled={busy || ids.length === 0} onClick={() => void inspect()}>Periksa {ids.length} pilihan untuk dinonaktifkan</Button>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
    {preview && <Card className="space-y-3 border-amber-300 p-4"><h3 className="font-semibold">Pratinjau nonaktif massal</h3><p className="text-sm">{preview.count} akun dipilih. Tidak ada data yang dihapus.</p>
      <ul className="max-h-36 overflow-y-auto text-sm">{preview.rows.map(row => <li key={row.id}>{row.name} (ID {row.id})</li>)}</ul>
      {preview.blocked.length > 0 && <p role="alert" className="text-sm text-red-700">{preview.blocked.map(row => `ID ${row.id}: ${row.reason}`).join('; ')}. Selesaikan kondisi ini lalu periksa ulang.</p>}
      <label className="block text-sm">Alasan tindakan<Input minLength={10} maxLength={500} className="mt-1" value={reason} onChange={event => setReason(event.target.value)} /></label>
      <label className="block text-sm">Ketik NONAKTIFKAN {preview.count}<Input className="mt-1" value={confirmationText} onChange={event => setConfirmationText(event.target.value)} /></label>
      <label className="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" checked={confirmed} onChange={event => setConfirmed(event.target.checked)} />Saya menyetujui nonaktif massal ini.</label>
      <Button type="button" variant="destructive" disabled={busy || preview.blocked.length > 0 || !confirmed || confirmationText !== 'NONAKTIFKAN ' + preview.count || reason.trim().length < 10} onClick={() => void commit()}>Nonaktifkan {preview.count} akun</Button>
    </Card>}
  </div>;
}
