import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { StaffPermissions } from '@/components/StaffPermissions';

type Row = Record<string, unknown>;

export default function ProctorAdmin() {
  const [users, setUsers] = useState<Row[]>([]);
  const [exams, setExams] = useState<Row[]>([]);
  const [search, setSearch] = useState('');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [examId, setExamId] = useState('');
  const [userId, setUserId] = useState('');
  const [secret, setSecret] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [permissionTarget, setPermissionTarget] = useState<{ id: number; name: string } | null>(null);

  useEffect(() => { api.proctors().then(setUsers).catch(reason => setError((reason as Error).message)); }, []);
  useEffect(() => { api.exams(1, search).then(result => setExams(result.rows.filter(row => row.status === 'published'))).catch(reason => setError((reason as Error).message)); }, [search]);

  async function create(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice(''); setSecret('');
    try {
      const result = await api.createProctor({ username, email, displayName });
      setSecret(result.temporaryPassword); setNotice('Pengawas dibuat. Salin sandi sementara sekarang.');
      setUsers(await api.proctors()); setUsername(''); setEmail(''); setDisplayName('');
    } catch (cause) { setError((cause as Error).message); }
    finally { setBusy(false); }
  }

  async function assign(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    try { await api.assignProctor(Number(examId), Number(userId)); setNotice('Pengawas ditugaskan ke ujian.'); }
    catch (cause) { setError((cause as Error).message); }
    finally { setBusy(false); }
  }

  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Pengawas ujian</h1><p className="mt-1 text-sm text-[#536765]">Akun pengawas hanya dapat melihat ujian yang ditugaskan dan status peserta.</p></div>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    {secret && <Card className="border-amber-300 bg-amber-50 p-4"><strong>Sandi sementara, ditampilkan sekali</strong><code className="mt-2 block select-all break-all">{secret}</code></Card>}
    <div className="grid items-start gap-5 lg:grid-cols-2"><Card className="p-5"><h2 className="font-semibold">Tambah pengawas</h2><form onSubmit={create} className="mt-4 space-y-3"><label className="block text-sm">Username<Input required className="mt-1" value={username} onChange={event => setUsername(event.target.value)} /></label><label className="block text-sm">Email<Input required type="email" className="mt-1" value={email} onChange={event => setEmail(event.target.value)} /></label><label className="block text-sm">Nama<Input required className="mt-1" value={displayName} onChange={event => setDisplayName(event.target.value)} /></label><Button disabled={busy}>Simpan pengawas</Button></form></Card>
      <Card className="p-5"><h2 className="font-semibold">Tugaskan ke ujian</h2><label className="mt-4 block text-sm">Cari ujian<Input className="mt-1" value={search} onChange={event => setSearch(event.target.value)} /></label><form onSubmit={assign} className="mt-3 space-y-3"><label className="block text-sm">Ujian terbit<select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={examId} onChange={event => setExamId(event.target.value)}><option value="">Pilih ujian</option>{exams.map(row => <option key={Number(row.id)} value={Number(row.id)}>{String(row.title)}</option>)}</select></label><label className="block text-sm">Pengawas<select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={userId} onChange={event => setUserId(event.target.value)}><option value="">Pilih pengawas</option>{users.filter(row => Number(row.active) === 1).map(row => <option key={Number(row.user_id)} value={Number(row.user_id)}>{String(row.display_name)} ({String(row.username)})</option>)}</select></label><Button disabled={busy}>Tugaskan</Button></form></Card></div>
    <Card className="p-5"><h2 className="font-semibold">Akun pengawas</h2><ul className="mt-3 divide-y text-sm">{users.map(row => <li className="flex min-h-11 items-center justify-between gap-3 py-2" key={Number(row.user_id)}><span>{String(row.display_name)} · {String(row.username)} · {Number(row.active) === 1 ? 'Aktif' : 'Tidak aktif'}</span><Button type="button" variant="outline" onClick={() => setPermissionTarget({ id: Number(row.user_id), name: String(row.display_name) })}>Izin</Button></li>)}</ul></Card>
    {permissionTarget && <StaffPermissions userId={permissionTarget.id} name={permissionTarget.name} onClose={() => setPermissionTarget(null)} />}
  </div>;
}
