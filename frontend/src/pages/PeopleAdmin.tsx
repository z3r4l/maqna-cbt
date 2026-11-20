import { useCallback, useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { BulkPeopleAction } from '@/components/BulkPeopleAction';
import { StaffPermissions } from '@/components/StaffPermissions';

type Kind = 'students' | 'teachers';
type Person = Record<string, unknown>;
const empty = { username: '', email: '', displayName: '', nis: '', nisn: '', employeeNo: '', status: 'active' };

export default function PeopleAdmin({ kind }: { kind: Kind }) {
  const [rows, setRows] = useState<Person[]>([]);
  const [form, setForm] = useState({ ...empty });
  const [editing, setEditing] = useState<number | null>(null);
  const [secret, setSecret] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchDraft, setSearchDraft] = useState('');
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [selected, setSelected] = useState<number[]>([]);
  const [permissionTarget, setPermissionTarget] = useState<{ id: number; name: string } | null>(null);
  const title = kind === 'students' ? 'Siswa' : 'Guru';
  const refresh = useCallback(async () => {
    const result = await api.people(kind, page, search);
    setRows(result.rows); setTotal(result.total);
  }, [kind, page, search]);
  useEffect(() => { setForm({ ...empty }); setEditing(null); setPage(1); setSearch(''); setSelected([]); }, [kind]);
  useEffect(() => { refresh().catch(reason => setError(reason.message)); }, [refresh]);

  function edit(row: Person) {
    setEditing(Number(row.id)); setSecret(''); setNotice('');
    setForm({
      username: String(row.username ?? ''), email: String(row.email ?? ''), displayName: String(row.display_name ?? ''),
      nis: String(row.nis ?? ''), nisn: String(row.nisn ?? ''), employeeNo: String(row.employee_no ?? ''), status: String(row.status ?? 'active'),
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  async function save(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setSecret(''); setNotice('');
    try {
      if (editing === null) {
        const payload: Record<string, unknown> = { username: form.username, displayName: form.displayName, email: form.email };
        if (kind === 'students') { payload.nis = form.nis; payload.nisn = form.nisn; }
        else payload.employeeNo = form.employeeNo;
        const created = await api.createPerson(kind, payload);
        setSecret(created.temporaryPassword);
        setNotice(title + ' ditambahkan. Salin sandi sementara sekarang.');
      } else {
        const payload: Record<string, unknown> = { displayName: form.displayName, status: form.status };
        if (kind === 'students') { payload.nis = form.nis; payload.nisn = form.nisn; }
        else payload.employeeNo = form.employeeNo;
        await api.updatePerson(kind, editing, payload);
        setNotice('Data diperbarui.');
      }
      setEditing(null); setForm({ ...empty }); await refresh();
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <div className="space-y-5">
    <div><h1 className="text-2xl font-bold">{title}</h1><p className="mt-1 text-sm text-[#536765]">Akun, identitas sekolah, dan status tetap menyimpan riwayat.</p></div>
    <div className="flex flex-wrap gap-2"><a className="inline-flex min-h-11 items-center rounded-lg border border-[#B6C9C5] px-4 text-sm font-semibold text-[#155451]" href={'/api/v1/exports/' + kind}>Ekspor CSV</a><a className="inline-flex min-h-11 items-center rounded-lg border border-[#B6C9C5] px-4 text-sm font-semibold text-[#155451]" href={'/imports/' + kind}>Impor {title.toLowerCase()}</a></div>
    {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
    {notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    {secret && <Card className="border-amber-300 bg-amber-50 p-4"><strong className="text-sm">Sandi sementara (ditampilkan sekali)</strong><code className="mt-2 block break-all select-all">{secret}</code><p className="mt-2 text-xs">Berikan langsung kepada pengguna. Mereka wajib menggantinya setelah masuk.</p></Card>}
    <BulkPeopleAction kind={kind} ids={selected} onDone={() => { setSelected([]); void refresh(); }} />
    {kind === 'teachers' && permissionTarget && <StaffPermissions userId={permissionTarget.id} name={permissionTarget.name} onClose={() => setPermissionTarget(null)} />}
    <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
      <Card className="order-2 min-w-0 p-5 xl:order-1">
        <form onSubmit={event => { event.preventDefault(); setPage(1); setSearch(searchDraft); }} className="flex gap-2"><label className="sr-only" htmlFor="person-search">Cari {title}</label><Input id="person-search" value={searchDraft} onChange={event => setSearchDraft(event.target.value)} placeholder={'Cari ' + title.toLowerCase()} /><Button variant="outline">Cari</Button></form>
        <div className="mt-4 divide-y divide-[#E1E7E5]">{rows.length === 0 && <p className="py-5 text-sm text-[#536765]">Belum ada {title.toLowerCase()}.</p>}
          {rows.map(row => <div key={String(row.id)} className="flex items-start justify-between gap-3 py-3"><label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" checked={selected.includes(Number(row.id))} onChange={() => setSelected(current => current.includes(Number(row.id)) ? current.filter(id => id !== Number(row.id)) : [...current, Number(row.id)])} />Pilih</label><div className="min-w-0"><p className="break-words font-medium">{String(row.display_name)}</p><p className="mt-1 text-xs text-[#536765]">{String(row.username)} · {String(row.nis ?? row.employee_no ?? '—')} · {String(row.status)}</p></div><div className="flex flex-wrap gap-1">{kind === 'teachers' && <Button type="button" variant="outline" onClick={() => setPermissionTarget({ id: Number(row.user_id), name: String(row.display_name) })}>Izin</Button>}<Button type="button" variant="outline" onClick={() => edit(row)}>Ubah</Button></div></div>)}
        </div>
        <div className="mt-4 flex items-center justify-between text-sm"><span>{total} data</span><div className="flex gap-2"><Button variant="outline" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" type="button" disabled={page * 20 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div>
      </Card>
      <Card className="order-1 p-5 xl:order-2"><h2 className="text-lg font-semibold">{editing === null ? 'Tambah ' + title.toLowerCase() : 'Ubah ' + title.toLowerCase()}</h2>
        <form onSubmit={save} className="mt-4 space-y-4">
          {editing === null && <><label className="block text-sm">Nama pengguna<Input required minLength={3} maxLength={30} className="mt-1" value={form.username} onChange={event => setForm({ ...form, username: event.target.value })} /></label><label className="block text-sm">Email {kind === 'students' ? '(opsional)' : ''}<Input type="email" required={kind === 'teachers'} className="mt-1" value={form.email} onChange={event => setForm({ ...form, email: event.target.value })} /></label></>}
          <label className="block text-sm">Nama tampilan<Input required minLength={2} maxLength={120} className="mt-1" value={form.displayName} onChange={event => setForm({ ...form, displayName: event.target.value })} /></label>
          {kind === 'students' ? <><label className="block text-sm">NIS<Input required maxLength={40} className="mt-1" value={form.nis} onChange={event => setForm({ ...form, nis: event.target.value })} /></label><label className="block text-sm">NISN (opsional)<Input maxLength={40} className="mt-1" value={form.nisn} onChange={event => setForm({ ...form, nisn: event.target.value })} /></label></> : <label className="block text-sm">Nomor pegawai (opsional)<Input maxLength={40} className="mt-1" value={form.employeeNo} onChange={event => setForm({ ...form, employeeNo: event.target.value })} /></label>}
          {editing !== null && <label className="block text-sm">Status<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={form.status} onChange={event => setForm({ ...form, status: event.target.value })}><option value="active">Aktif</option><option value="inactive">Tidak aktif</option></select></label>}
          <div className="flex flex-wrap gap-2"><Button disabled={busy}>Simpan</Button>{editing !== null && <Button type="button" variant="outline" onClick={() => { setEditing(null); setForm({ ...empty }); }}>Batal</Button>}</div>
        </form>
      </Card>
    </div>
  </div>;
}
