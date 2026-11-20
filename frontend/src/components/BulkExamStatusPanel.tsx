import { useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Row = Record<string, unknown>;
type Preview = { id: number; target: string; rows: Row[]; blocked: { id: number; reason: string }[] };

export function BulkExamStatusPanel({ rows, onCommitted }: { rows: Row[]; onCommitted: () => void }) {
  const [selected, setSelected] = useState<number[]>([]);
  const [target, setTarget] = useState<'paused' | 'published'>('paused');
  const [preview, setPreview] = useState<Preview | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const eligible = rows.filter(row => row.status === (target === 'paused' ? 'published' : 'paused'));

  async function loadPreview() {
    setBusy(true); setError(''); setNotice(''); setPreview(null);
    try { setPreview(await api.previewBulkExamStatus(selected, target)); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }

  async function commit() {
    if (!preview || !window.confirm('Ubah status ' + preview.rows.length + ' ujian sesuai pratinjau?')) return;
    setBusy(true); setError('');
    try {
      const result = await api.commitBulkExamStatus(preview.id, preview.target as 'paused' | 'published');
      setNotice(result.updated + ' ujian diperbarui.'); setPreview(null); setSelected([]); onCommitted();
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }

  return <Card className="space-y-3 p-4">
    <h2 className="font-semibold">Status massal ujian</h2>
    <p className="text-sm text-[#536765]">Hanya ujian tanpa percobaan yang dapat dijeda atau diaktifkan kembali. Pilih dari halaman ini.</p>
    <label className="block text-sm">Tindakan<select className="mt-1 block min-h-11 rounded-md border border-[#B6C9C5] bg-white px-3" value={target} onChange={event => { setTarget(event.target.value as 'paused' | 'published'); setSelected([]); setPreview(null); }}><option value="paused">Jeda ujian terbit</option><option value="published">Aktifkan ujian jeda</option></select></label>
    <div className="max-h-56 overflow-y-auto rounded-md border border-[#D7E0DE]">{eligible.map(row => <label key={Number(row.id)} className="flex min-h-11 items-center gap-3 border-b px-3 py-2 text-sm last:border-0"><input type="checkbox" checked={selected.includes(Number(row.id))} onChange={event => { setPreview(null); setSelected(current => event.target.checked ? [...current, Number(row.id)] : current.filter(id => id !== Number(row.id))); }} />{String(row.title)}</label>)}{eligible.length === 0 && <p className="p-3 text-sm text-[#536765]">Tidak ada ujian sesuai status ini pada halaman sekarang.</p>}</div>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    <Button type="button" variant="outline" disabled={busy || selected.length === 0} onClick={() => void loadPreview()}>Pratinjau {selected.length} ujian</Button>
    {preview && <div className="space-y-2 rounded-md border border-[#B6C9C5] p-3 text-sm"><p>{preview.rows.length} ujian dipilih untuk {preview.target === 'paused' ? 'dijeda' : 'diaktifkan'}.</p>{preview.blocked.map(row => <p key={row.id} className="text-red-700">Ujian #{row.id}: {row.reason}</p>)}<Button type="button" disabled={busy || preview.blocked.length > 0} onClick={() => void commit()}>Konfirmasi perubahan status</Button></div>}
  </Card>;
}
