import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card } from '@/components/ui/card';

const labels: Record<string, string> = {
  'questions.manage': 'Kelola soal', 'exams.manage': 'Kelola ujian', 'exams.publish': 'Terbitkan ujian',
  'results.grade': 'Nilai jawaban', 'results.release': 'Rilis hasil', 'reports.view': 'Lihat laporan', 'monitor.view': 'Pantau ujian',
};

export function StaffPermissions({ userId, name, onClose }: { userId: number; name: string; onClose: () => void }) {
  const [rows, setRows] = useState<{ permission: string; allowed: boolean }[]>([]);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  useEffect(() => { api.userPermissions(userId).then(setRows).catch(reason => setError((reason as Error).message)); }, [userId]);
  async function change(permission: string, allowed: boolean) {
    setBusy(permission); setError(''); setNotice('');
    try {
      await api.saveUserPermission(userId, permission, allowed);
      setRows(current => current.map(row => row.permission === permission ? { ...row, allowed } : row));
      setNotice('Izin disimpan dan berlaku pada permintaan berikutnya.');
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(''); }
  }
  return <Card className="p-5"><div className="flex items-center justify-between gap-3"><h2 className="font-semibold">Izin {name}</h2><button type="button" className="min-h-11 rounded-md px-3 text-sm font-semibold text-[#155451] focus-visible:outline-2 focus-visible:outline-[#155451]" onClick={onClose}>Tutup</button></div>
    <p className="mt-1 text-sm text-[#536765]">Izin tetap dibatasi oleh mata pelajaran, rombel, dan sekolah yang ditugaskan.</p>
    {error && <p role="alert" className="mt-2 text-sm text-red-700">{error}</p>}{notice && <p role="status" className="mt-2 text-sm text-[#155451]">{notice}</p>}
    <div className="mt-3 grid gap-2 sm:grid-cols-2">{rows.map(row => <label key={row.permission} className="flex min-h-11 items-center gap-3 rounded-lg border border-[#D7E0DE] px-3 text-sm"><input type="checkbox" checked={row.allowed} disabled={busy !== ''} onChange={event => void change(row.permission, event.target.checked)} />{labels[row.permission] ?? row.permission}</label>)}</div>
  </Card>;
}
