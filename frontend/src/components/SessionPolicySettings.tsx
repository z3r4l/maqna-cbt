import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

const names: Record<string, string> = { student: 'Siswa', teacher: 'Guru', proctor: 'Pengawas', school_admin: 'Admin sekolah', superadmin: 'Superadmin' };

export function SessionPolicySettings() {
  const [rows, setRows] = useState<{ role: string; maxSessions: number }[]>([]);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  useEffect(() => { api.sessionPolicies().then(setRows).catch(reason => setError((reason as Error).message)); }, []);
  async function save(role: string, maxSessions: number) {
    setBusy(role); setError(''); setNotice('');
    try { await api.saveSessionPolicy(role, maxSessions); setNotice('Batas sesi ' + (names[role] ?? role) + ' disimpan. Berlaku untuk login berikutnya.'); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(''); }
  }
  return <Card className="max-w-xl space-y-4 p-6"><div><h2 className="font-semibold">Batas sesi akun</h2><p className="mt-1 text-sm text-[#536765]">Nol berarti tanpa batas. Saat batas tercapai, sesi lama ditutup pada login berikutnya. Jika siswa sedang mengerjakan ujian, login baru ditolak agar percobaan tetap aman.</p></div>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}{rows.map(row => <div key={row.role} className="flex flex-wrap items-end gap-2"><label className="min-w-40 flex-1 text-sm">{names[row.role] ?? row.role}<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={row.maxSessions} onChange={event => setRows(current => current.map(item => item.role === row.role ? { ...item, maxSessions: Number(event.target.value) } : item))}>{[0, 1, 2, 3, 4, 5].map(value => <option key={value} value={value}>{value === 0 ? 'Tanpa batas' : value + ' sesi'}</option>)}</select></label><Button type="button" variant="outline" disabled={busy === row.role} onClick={() => void save(row.role, row.maxSessions)}>Simpan</Button></div>)}</Card>;
}
