import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Row = Record<string, unknown>;
function id(row: Row) { return Number(row.id); }
function label(row: Row) { return String(row.name ?? row.display_name ?? row.label ?? row.code ?? row.id); }
function Select({ title, rows, value, onChange }: { title: string; rows: Row[]; value: string; onChange: (value: string) => void }) {
  return <label className="block text-sm">{title}<select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={value} onChange={event => onChange(event.target.value)}><option value="">Pilih {title.toLowerCase()}</option>{rows.map(row => <option key={id(row)} value={id(row)}>{label(row)}</option>)}</select></label>;
}

export function TeachingAdmin() {
  const [rows, setRows] = useState<Row[]>([]);
  const [teachers, setTeachers] = useState<Row[]>([]);
  const [subjects, setSubjects] = useState<Row[]>([]);
  const [classes, setClasses] = useState<Row[]>([]);
  const [semesters, setSemesters] = useState<Row[]>([]);
  const [form, setForm] = useState({ teacherId: '', subjectId: '', classGroupId: '', semesterId: '' });
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  async function refresh() { const result = await api.teachingAssignments(page); setRows(result.rows); setTotal(result.total); }
  useEffect(() => { Promise.all([api.people('teachers'), api.catalog('subjects'), api.catalog('class-groups'), api.catalog('semesters')]).then(([t, s, c, se]) => { setTeachers(t.rows); setSubjects(s.rows); setClasses(c.rows); setSemesters(se.rows); }).catch(reason => setError(reason.message)); }, []);
  useEffect(() => { refresh().catch(reason => setError(reason.message)); }, [page]);
  async function create(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    try { await api.createTeachingAssignment(Object.fromEntries(Object.entries(form).map(([key, value]) => [key, Number(value)]))); setNotice('Penugasan guru ditambahkan.'); setForm({ teacherId: '', subjectId: '', classGroupId: '', semesterId: '' }); await refresh(); }
    catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  async function toggle(row: Row) {
    setError(''); setNotice('');
    try { await api.setTeachingAssignmentActive(id(row), Number(row.active) !== 1); await refresh(); setNotice('Status penugasan diperbarui.'); }
    catch (reason) { setError((reason as Error).message); }
  }
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Penugasan guru</h1><p className="mt-1 text-sm text-[#536765]">Hubungkan guru, mata pelajaran, rombel, dan semester yang sama.</p></div>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
      <Card className="order-2 min-w-0 p-5 xl:order-1"><h2 className="text-lg font-semibold">Penugasan aktif dan riwayat</h2><div className="mt-4 divide-y divide-[#E1E7E5]">{rows.length === 0 && <p className="py-4 text-sm">Belum ada penugasan.</p>}{rows.map(row => <div key={id(row)} className="flex items-start justify-between gap-3 py-3"><div><p className="font-medium">{String(row.teacher_name)}</p><p className="mt-1 text-xs text-[#536765]">{String(row.subject_name)} · {String(row.class_name)} · {String(row.semester_name)} · {Number(row.active) === 1 ? 'Aktif' : 'Tidak aktif'}</p></div><Button type="button" variant="outline" onClick={() => void toggle(row)}>{Number(row.active) === 1 ? 'Nonaktifkan' : 'Aktifkan'}</Button></div>)}</div><div className="mt-4 flex items-center justify-between text-sm"><span>{total} data</span><div className="flex gap-2"><Button variant="outline" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" type="button" disabled={page * 20 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div></Card>
      <Card className="order-1 p-5 xl:order-2"><h2 className="text-lg font-semibold">Tambah penugasan</h2><form onSubmit={create} className="mt-4 space-y-4"><Select title="Guru" rows={teachers} value={form.teacherId} onChange={value => setForm({ ...form, teacherId: value })} /><Select title="Mata pelajaran" rows={subjects} value={form.subjectId} onChange={value => setForm({ ...form, subjectId: value })} /><Select title="Rombel" rows={classes} value={form.classGroupId} onChange={value => setForm({ ...form, classGroupId: value })} /><Select title="Semester" rows={semesters} value={form.semesterId} onChange={value => setForm({ ...form, semesterId: value })} /><Button disabled={busy}>Simpan penugasan</Button></form></Card>
    </div>
  </div>;
}

export function MembershipsAdmin() {
  const [students, setStudents] = useState<Row[]>([]);
  const [classes, setClasses] = useState<Row[]>([]);
  const [history, setHistory] = useState<Row[]>([]);
  const [studentSearch, setStudentSearch] = useState('');
  const [studentId, setStudentId] = useState('');
  const [classId, setClassId] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  useEffect(() => { api.catalog('class-groups').then(result => setClasses(result.rows)).catch(reason => setError(reason.message)); }, []);
  useEffect(() => { api.people('students', 1, studentSearch).then(result => setStudents(result.rows)).catch(reason => setError(reason.message)); }, [studentSearch]);
  useEffect(() => { if (studentId) api.memberships(Number(studentId)).then(result => setHistory(result.rows)).catch(reason => setError(reason.message)); else setHistory([]); }, [studentId]);
  async function move(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    try { await api.moveStudent(Number(studentId), Number(classId)); setHistory((await api.memberships(Number(studentId))).rows); setNotice('Rombel siswa diperbarui. Riwayat sebelumnya tetap tersimpan.'); }
    catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Rombel siswa</h1><p className="mt-1 text-sm text-[#536765]">Satu rombel utama per siswa pada tiap tahun ajaran. Perpindahan tetap tercatat.</p></div>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
      <Card className="order-2 p-5 xl:order-1"><h2 className="text-lg font-semibold">Riwayat rombel</h2><div className="mt-4 divide-y divide-[#E1E7E5]">{history.length === 0 && <p className="py-4 text-sm text-[#536765]">Pilih siswa untuk melihat riwayat.</p>}{history.map(row => <div key={id(row)} className="py-3"><p className="font-medium">{String(row.class_name)}</p><p className="mt-1 text-xs text-[#536765]">{String(row.academic_year)} · {Number(row.active) === 1 ? 'Aktif' : 'Riwayat'}</p></div>)}</div></Card>
      <Card className="order-1 p-5 xl:order-2"><h2 className="text-lg font-semibold">Pindahkan siswa</h2><form onSubmit={move} className="mt-4 space-y-4"><label className="block text-sm">Cari siswa<Input className="mt-1" value={studentSearch} onChange={event => setStudentSearch(event.target.value)} placeholder="Nama atau pengguna" /></label><Select title="Siswa" rows={students} value={studentId} onChange={setStudentId} /><Select title="Rombel tujuan" rows={classes.filter(row => Number(row.active) === 1)} value={classId} onChange={setClassId} /><Button disabled={busy}>Pindahkan rombel</Button></form></Card>
    </div>
  </div>;
}
