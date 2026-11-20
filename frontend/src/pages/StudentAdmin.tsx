import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { BulkPeopleAction } from '@/components/BulkPeopleAction';

type Student = {
  id: number;
  nis: string;
  nisn: string | null;
  username: string;
  email: string | null;
  displayName: string;
  className: string | null;
  active: boolean;
  hasLoggedIn: boolean;
  lastActive: string | null;
};
type StudentForm = { username: string; email: string; displayName: string; nis: string; nisn: string; status: 'active' | 'inactive' };
const emptyForm: StudentForm = { username: '', email: '', displayName: '', nis: '', nisn: '', status: 'active' };

function studentFrom(row: Record<string, unknown>): Student {
  return {
    id: Number(row.id),
    nis: String(row.nis ?? ''),
    nisn: row.nisn == null ? null : String(row.nisn),
    username: String(row.username ?? ''),
    email: row.email == null ? null : String(row.email),
    displayName: String(row.display_name ?? ''),
    className: row.class_name == null ? null : String(row.class_name),
    active: row.status === 'active' && Number(row.account_active) === 1,
    hasLoggedIn: Number(row.has_logged_in) === 1,
    lastActive: row.last_active == null ? null : String(row.last_active),
  };
}

function StudentStatus({ active, hasLoggedIn, lastActive }: { active: boolean; hasLoggedIn: boolean; lastActive: string | null }) {
  return <div className="flex flex-col items-start gap-1"><Badge className={active ? '' : 'bg-[#EEF0F1] text-[#344441]'}>{active ? 'Aktif' : 'Tidak aktif'}</Badge><span className="text-xs text-[#536765]" title={lastActive ? `Aktivitas terakhir: ${lastActive} UTC` : undefined}>{hasLoggedIn ? 'Pernah login' : 'Belum login'}</span></div>;
}

export default function StudentAdmin() {
  const [rows, setRows] = useState<Student[]>([]);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [total, setTotal] = useState(0);
  const [searchDraft, setSearchDraft] = useState('');
  const [search, setSearch] = useState('');
  const [loginStatus, setLoginStatus] = useState('all');
  const [refreshKey, setRefreshKey] = useState(0);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [sheetOpen, setSheetOpen] = useState(false);
  const [editing, setEditing] = useState<Student | null>(null);
  const [form, setForm] = useState<StudentForm>({ ...emptyForm });
  const [formError, setFormError] = useState('');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const [secret, setSecret] = useState('');
  const [selected, setSelected] = useState<number[]>([]);
  const [sessionStudent, setSessionStudent] = useState<Student | null>(null);
  const [sessions, setSessions] = useState<Record<string, unknown>[]>([]);
  const [sessionReason, setSessionReason] = useState('');
  const [selectedSession, setSelectedSession] = useState<number | null>(null);
  const [confirmRevoke, setConfirmRevoke] = useState(false);
  const [sessionError, setSessionError] = useState('');

  useEffect(() => {
    let active = true;
    setLoading(true);
    setListError('');
    api.people('students', page, search, perPage, loginStatus).then(result => {
      if (!active) return;
      setRows(result.rows.map(studentFrom));
      setTotal(result.total);
    }).catch(reason => {
      if (active) setListError((reason as Error).message);
    }).finally(() => {
      if (active) setLoading(false);
    });
    return () => { active = false; };
  }, [page, perPage, search, loginStatus, refreshKey]);

  function openCreate() {
    setEditing(null);
    setForm({ ...emptyForm });
    setFormError('');
    setSheetOpen(true);
  }

  function openEdit(student: Student) {
    setEditing(student);
    setForm({ username: student.username, email: student.email ?? '', displayName: student.displayName, nis: student.nis, nisn: student.nisn ?? '', status: student.active ? 'active' : 'inactive' });
    setFormError('');
    setSheetOpen(true);
  }

  async function openSessions(student: Student) {
    setSessionStudent(student); setSessionError(''); setSessionReason(''); setSelectedSession(null); setConfirmRevoke(false);
    try { setSessions(await api.studentSessions(student.id)); }
    catch (reason) { setSessionError((reason as Error).message); }
  }

  async function revokeSession() {
    if (!sessionStudent || selectedSession === null || !confirmRevoke || sessionReason.trim().length < 10) return;
    setBusy(true); setSessionError('');
    try {
      await api.revokeStudentSession(sessionStudent.id, selectedSession, sessionReason.trim());
      setSessions(await api.studentSessions(sessionStudent.id));
      setSelectedSession(null); setSessionReason(''); setConfirmRevoke(false);
      setNotice('Sesi siswa dicabut. Siswa perlu masuk kembali pada perangkat tersebut.');
    } catch (reason) { setSessionError((reason as Error).message); }
    finally { setBusy(false); }
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setFormError('');
    try {
      if (editing === null) {
        const created = await api.createPerson('students', { username: form.username, email: form.email, displayName: form.displayName, nis: form.nis, nisn: form.nisn });
        setSecret(created.temporaryPassword);
        setNotice('Siswa ditambahkan. Salin sandi sementara sekarang.');
      } else {
        await api.updatePerson('students', editing.id, { displayName: form.displayName, nis: form.nis, nisn: form.nisn, status: form.status });
        setNotice('Data siswa diperbarui.');
      }
      setSheetOpen(false);
      setSearchDraft('');
      setSearch('');
      setPage(1);
      setRefreshKey(key => key + 1);
    } catch (reason) {
      setFormError((reason as Error).message);
    } finally {
      setBusy(false);
    }
  }

  const first = total === 0 ? 0 : (page - 1) * perPage + 1;
  const last = Math.min(page * perPage, total);
  const canNext = page * perPage < total;
  function toggleSelected(id: number) { setSelected(current => current.includes(id) ? current.filter(value => value !== id) : [...current, id]); }
  const actions = (student: Student) => <div className="flex flex-nowrap items-center gap-1 whitespace-nowrap">
    <Button type="button" variant="outline" onClick={() => openEdit(student)} aria-label={'Ubah ' + student.username}>Ubah</Button>
    <Button type="button" variant="ghost" onClick={() => void openSessions(student)} aria-label={'Kelola sesi ' + student.username}>Sesi</Button>
    <Button asChild variant="ghost" className="px-2"><Link to={'/password/admin?username=' + encodeURIComponent(student.username)} aria-label={'Bantu reset sandi ' + student.username}>Reset sandi</Link></Button>
  </div>;

  return <section className="space-y-5">
    <div><h1 className="text-2xl font-bold">Data Siswa</h1><p className="mt-1 text-sm text-[#536765]">Kelola identitas, kelas, dan status akun siswa.</p></div>
    {listError && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{listError} <button type="button" className="underline" onClick={() => setRefreshKey(key => key + 1)}>Coba lagi</button></p>}
    {notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    {secret && <Card className="gap-2 border-amber-300 bg-amber-50 p-4"><strong className="text-sm">Sandi sementara (ditampilkan sekali)</strong><code className="block break-all select-all">{secret}</code><p className="text-xs">Berikan langsung kepada siswa. Mereka wajib menggantinya setelah masuk.</p></Card>}
    <BulkPeopleAction kind="students" ids={selected} onDone={() => { setSelected([]); setRefreshKey(key => key + 1); }} />
    <Card className="gap-0 overflow-hidden border-[#D7E0DE] p-0">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#D7E0DE] p-4 md:px-5">
        <h2 className="text-lg font-semibold">Daftar Siswa</h2>
        <div className="flex flex-wrap gap-2"><Button asChild variant="outline"><a href="/api/v1/exports/students">Ekspor CSV</a></Button><Button asChild variant="outline"><Link to="/imports/students">Impor</Link></Button><Button type="button" onClick={openCreate}>Tambah Siswa</Button></div>
      </div>
      <div className="flex flex-col gap-3 border-b border-[#D7E0DE] bg-[#F8FAF9] p-4 md:flex-row md:items-end md:justify-between md:px-5">
        <div className="flex items-end gap-2"><div><span id="student-page-size-label" className="mb-1 block text-sm font-medium">Baris per halaman</span><Select value={String(perPage)} onValueChange={value => { setPerPage(Number(value)); setPage(1); }}><SelectTrigger aria-labelledby="student-page-size-label" className="w-22"><SelectValue /></SelectTrigger><SelectContent>{[10, 20, 50].map(size => <SelectItem key={size} value={String(size)}>{size}</SelectItem>)}</SelectContent></Select></div><span className="pb-3 text-sm text-[#536765]">data</span></div>
        <form className="flex w-full gap-2 md:max-w-sm" onSubmit={event => { event.preventDefault(); setPage(1); setSearch(searchDraft.trim()); }}><label className="min-w-0 flex-1 text-sm font-medium">Cari siswa<Input className="mt-1 bg-white" value={searchDraft} onChange={event => setSearchDraft(event.target.value)} placeholder="Nama, username, atau NIS" /></label><Button className="self-end" type="submit" variant="outline">Cari</Button></form>
      </div>
      <div className="border-b border-[#D7E0DE] px-4 py-3 md:px-5"><label className="block max-w-56 text-sm font-medium">Riwayat login<select value={loginStatus} onChange={event => { setLoginStatus(event.target.value); setPage(1); }} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3"><option value="all">Semua siswa</option><option value="yes">Sudah pernah login</option><option value="no">Belum pernah login</option></select></label></div>
      <div aria-busy={loading} className="hidden md:block">
        <Table aria-label="Daftar siswa" className="min-w-[780px] [&_td]:px-3 [&_th]:px-3"><TableHeader className="bg-[#EFF4F2]"><TableRow><TableHead className="w-12">No</TableHead><TableHead>NIS</TableHead><TableHead>Username</TableHead><TableHead>Nama Siswa</TableHead><TableHead>Kelas</TableHead><TableHead>Status</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader><TableBody>
          {!loading && rows.length === 0 && <TableRow><TableCell colSpan={7} className="py-8 text-center text-[#536765]">{search ? 'Tidak ada siswa yang cocok dengan pencarian.' : 'Belum ada siswa.'}</TableCell></TableRow>}
          {rows.map((student, index) => <TableRow key={student.id}><TableCell className="text-[#536765]">{(page - 1) * perPage + index + 1}</TableCell><TableCell className="font-medium"><label className="flex min-h-11 items-center gap-2"><input type="checkbox" aria-label={"Pilih " + student.username} checked={selected.includes(student.id)} onChange={() => toggleSelected(student.id)} />{student.nis}</label></TableCell><TableCell>{student.username}</TableCell><TableCell>{student.displayName}</TableCell><TableCell>{student.className ?? 'Belum ada kelas'}</TableCell><TableCell><StudentStatus active={student.active} hasLoggedIn={student.hasLoggedIn} lastActive={student.lastActive} /></TableCell><TableCell>{actions(student)}</TableCell></TableRow>)}
        </TableBody></Table>
      </div>
      <div aria-busy={loading} className="divide-y divide-[#D7E0DE] md:hidden">
        {!loading && rows.length === 0 && <p className="p-5 text-sm text-[#536765]">{search ? 'Tidak ada siswa yang cocok dengan pencarian.' : 'Belum ada siswa.'}</p>}
        {rows.map(student => <article key={student.id} className="space-y-3 p-4"><label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" checked={selected.includes(student.id)} onChange={() => toggleSelected(student.id)} />Pilih siswa</label><div className="flex items-start justify-between gap-3"><div className="min-w-0"><h3 className="break-words font-semibold">{student.displayName}</h3><p className="mt-1 break-all text-sm text-[#536765]">{student.username}</p></div><StudentStatus active={student.active} hasLoggedIn={student.hasLoggedIn} lastActive={student.lastActive} /></div><dl className="grid grid-cols-[72px_1fr] gap-x-2 gap-y-1 text-sm"><dt className="text-[#536765]">NIS</dt><dd className="break-all">{student.nis}</dd><dt className="text-[#536765]">Kelas</dt><dd>{student.className ?? 'Belum ada kelas'}</dd></dl>{actions(student)}</article>)}
      </div>
      <div className="flex flex-col gap-3 border-t border-[#D7E0DE] p-4 text-sm text-[#536765] sm:flex-row sm:items-center sm:justify-between md:px-5"><span>Menampilkan {first}–{last} dari {total} siswa</span><div className="flex gap-2"><Button type="button" variant="outline" disabled={page <= 1 || loading} onClick={() => setPage(value => value - 1)}>Sebelumnya</Button><Button type="button" variant="outline" disabled={!canNext || loading} onClick={() => setPage(value => value + 1)}>Berikutnya</Button></div></div>
    </Card>
    <Sheet open={sheetOpen} onOpenChange={setSheetOpen}><SheetContent side="right" showCloseButton={false} className="w-full max-w-none overflow-y-auto border-[#D7E0DE] p-0 sm:max-w-md"><SheetHeader className="border-b border-[#D7E0DE] px-5 py-5"><SheetTitle>{editing ? 'Ubah siswa' : 'Tambah siswa'}</SheetTitle><SheetDescription>Isi identitas siswa. Sandi sementara diberikan setelah akun dibuat.</SheetDescription></SheetHeader><form onSubmit={event => void save(event)} className="space-y-4 px-5 pb-6">
      {editing === null && <><label className="block text-sm font-medium">Nama pengguna<Input required minLength={3} maxLength={30} className="mt-1" value={form.username} onChange={event => setForm({ ...form, username: event.target.value })} /></label><label className="block text-sm font-medium">Email (opsional)<Input type="email" className="mt-1" value={form.email} onChange={event => setForm({ ...form, email: event.target.value })} /></label></>}
      <label className="block text-sm font-medium">Nama tampilan<Input required minLength={2} maxLength={120} className="mt-1" value={form.displayName} onChange={event => setForm({ ...form, displayName: event.target.value })} /></label>
      <label className="block text-sm font-medium">NIS<Input required minLength={2} maxLength={40} className="mt-1" value={form.nis} onChange={event => setForm({ ...form, nis: event.target.value })} /></label>
      <label className="block text-sm font-medium">NISN (opsional)<Input maxLength={40} className="mt-1" value={form.nisn} onChange={event => setForm({ ...form, nisn: event.target.value })} /></label>
      {editing && <label className="block text-sm font-medium">Status<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={form.status} onChange={event => setForm({ ...form, status: event.target.value as StudentForm['status'] })}><option value="active">Aktif</option><option value="inactive">Tidak aktif</option></select></label>}
      {formError && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{formError}</p>}
      <div className="flex gap-2 pt-2"><Button disabled={busy} type="submit">Simpan</Button><Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>Batal</Button></div>
    </form></SheetContent></Sheet>
    <Sheet open={sessionStudent !== null} onOpenChange={open => { if (!open) setSessionStudent(null); }}><SheetContent side="right" className="w-full max-w-none overflow-y-auto p-5 sm:max-w-md"><SheetHeader><SheetTitle>Sesi {sessionStudent?.displayName}</SheetTitle><SheetDescription>Cabut hanya sesi yang dipilih. Jawaban dan hasil ujian tetap tersimpan.</SheetDescription></SheetHeader>
      {sessionError && <p role="alert" className="mt-4 text-sm text-red-700">{sessionError}</p>}
      <div className="mt-5 space-y-2">{sessions.length === 0 && <p className="text-sm text-[#536765]">Belum ada sesi tercatat.</p>}{sessions.map(session => <label key={Number(session.id)} className="flex min-h-11 items-center gap-3 rounded-lg border border-[#D7E0DE] p-3 text-sm"><input type="radio" name="student-session" disabled={session.revoked_at !== null} checked={selectedSession === Number(session.id)} onChange={() => setSelectedSession(Number(session.id))} /><span><strong>Sesi #{String(session.id)}</strong><span className="block text-xs text-[#536765]">Terakhir aktif: {String(session.last_seen_at)} UTC{session.revoked_at !== null ? ' · Dicabut' : ''}</span></span></label>)}</div>
      <label className="mt-5 block text-sm font-medium">Alasan pencabutan<textarea required minLength={10} maxLength={500} value={sessionReason} onChange={event => setSessionReason(event.target.value)} className="mt-1 block min-h-24 w-full rounded-md border border-[#B6C9C5] p-3" /></label>
      <label className="mt-4 flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" checked={confirmRevoke} onChange={event => setConfirmRevoke(event.target.checked)} />Saya yakin ingin mencabut sesi terpilih.</label>
      <Button type="button" variant="destructive" disabled={busy || selectedSession === null || !confirmRevoke || sessionReason.trim().length < 10} onClick={() => void revokeSession()}>Cabut sesi</Button>
    </SheetContent></Sheet>
  </section>;
}
