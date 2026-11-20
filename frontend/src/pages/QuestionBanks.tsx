import { Suspense, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Dialog } from 'radix-ui';
import { ArrowLeft, Archive, BookOpenText, Download, FilePlus2, FolderPlus, ListFilter, Pencil, Plus, Search, X } from 'lucide-react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import MathText from '@/components/MathText';
import { QuestionEditor } from '@/pages/Questions';

type Row = Record<string, unknown>;
type Masters = { subjects: Row[]; classes: Row[]; examTypes: Row[] };
const emptyMasters: Masters = { subjects: [], classes: [], examTypes: [] };
const label = (row: Row, key: string) => String(row[key] ?? '');
const count = (row: Row) => Number(row.question_count ?? 0);
const typeLabels: Record<string, string> = { mcq: 'Pilihan ganda', multi_mcq: 'Pilihan kompleks', true_false: 'Benar / salah', matching: 'Menjodohkan', essay: 'Esai' };

function BankForm({ bank, masters, assignments, onClose, onSaved }: { bank: Row | null; masters: Masters; assignments: Row[] | null; onClose: () => void; onSaved: () => void }) {
  const [examTypeId, setExamTypeId] = useState(label(bank ?? {}, 'exam_type_id'));
  const [subjectId, setSubjectId] = useState(label(bank ?? {}, 'subject_id'));
  const [classIds, setClassIds] = useState<number[]>(((bank?.classes as Row[]) ?? []).map(row => Number(row.id)));
  const [name, setName] = useState(label(bank ?? {}, 'name'));
  const [code, setCode] = useState(() => Math.random().toString(36).slice(2, 6).toUpperCase());
  const [nameTouched, setNameTouched] = useState(Boolean(bank));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const examType = masters.examTypes.find(row => String(row.id) === examTypeId);
  const subject = masters.subjects.find(row => String(row.id) === subjectId);
  const availableClasses = assignments === null ? masters.classes : masters.classes.filter(row => assignments.some(item => Number(item.subject_id) === Number(subjectId) && Number(item.class_group_id) === Number(row.id)));
  const selectedClasses = masters.classes.filter(row => classIds.includes(Number(row.id)));
  const suggested = examTypeId && subjectId && classIds.length ? [label(examType ?? {}, 'code'), selectedClasses.map(row => label(row, 'code')).join('.'), label(subject ?? {}, 'code'), code].filter(Boolean).join('-') : '';
  useEffect(() => { if (!nameTouched && !bank) setName(suggested); }, [suggested, nameTouched, bank]);
  function toggle(id: number) { setClassIds(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id]); }
  async function submit(event: React.FormEvent) {
    event.preventDefault(); setError(''); setBusy(true);
    try {
      const payload = { name: name.trim(), examTypeId: Number(examTypeId), subjectId: Number(subjectId), classGroupIds: classIds };
      if (bank) await api.updateQuestionBank(Number(bank.id), payload);
      else await api.createQuestionBank(payload);
      onSaved();
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-50 bg-[#102C2B]/65" /><Dialog.Content className="fixed inset-0 z-50 flex flex-col bg-white shadow-2xl sm:inset-auto sm:left-1/2 sm:top-1/2 sm:max-h-[min(90dvh,780px)] sm:w-[min(820px,calc(100vw-32px))] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl">
    <div className="flex items-center justify-between border-b border-[#D7E0DE] bg-[#155451] px-5 py-4 text-white sm:rounded-t-xl"><Dialog.Title className="text-lg font-semibold">{bank ? 'Ubah bank soal' : 'Buat bank soal baru'}</Dialog.Title><Dialog.Close aria-label="Tutup" className="flex size-11 items-center justify-center rounded-md hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><X size={20} /></Dialog.Close></div>
    <form onSubmit={event => void submit(event)} className="flex min-h-0 flex-1 flex-col"><div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">
      {error && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}
      <div className="grid gap-4 sm:grid-cols-2"><label className="block text-sm font-medium">Jenis ujian <span aria-hidden="true" className="text-red-600">*</span><select required value={examTypeId} onChange={event => setExamTypeId(event.target.value)} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3"><option value="">Pilih jenis ujian</option>{masters.examTypes.map(row => <option key={Number(row.id)} value={Number(row.id)}>{label(row, 'name')}</option>)}</select></label><label className="block text-sm font-medium">Mata pelajaran <span aria-hidden="true" className="text-red-600">*</span><select required value={subjectId} onChange={event => { setSubjectId(event.target.value); setClassIds([]); }} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3"><option value="">Pilih mata pelajaran</option>{masters.subjects.map(row => <option key={Number(row.id)} value={Number(row.id)}>{label(row, 'name')}</option>)}</select></label></div>
      <fieldset><legend className="text-sm font-medium">Pilih kelas <span aria-hidden="true" className="text-red-600">*</span></legend><div className="mt-2 rounded-md border border-[#D7E0DE] bg-[#F7FAF9] p-3"><div className="mb-2 flex flex-wrap justify-end gap-3 text-sm"><button type="button" className="font-medium text-[#155451] underline" onClick={() => setClassIds(availableClasses.map(row => Number(row.id)))}>Pilih semua</button><button type="button" className="text-[#536765] underline" onClick={() => setClassIds([])}>Kosongkan</button></div><div className="grid gap-2 sm:grid-cols-3 md:grid-cols-4">{availableClasses.map(row => <label key={Number(row.id)} className="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-2 text-sm hover:bg-[#E5F3EF]"><input type="checkbox" checked={classIds.includes(Number(row.id))} onChange={() => toggle(Number(row.id))} className="size-4 accent-[#155451]" />{label(row, 'name')}</label>)}</div>{availableClasses.length === 0 && <p className="text-sm text-[#536765]">Belum ada kelas aktif yang dapat dipilih.</p>}</div><p className="mt-1 text-xs text-[#536765]">Guru hanya dapat memilih kelas dalam penugasan mapel aktif.</p></fieldset>
      <label className="block text-sm font-medium">Nama bank soal <span aria-hidden="true" className="text-red-600">*</span><div className="mt-1 flex gap-2"><Input required maxLength={160} value={name} onChange={event => { setNameTouched(true); setName(event.target.value); }} placeholder="Contoh: PAS-X.1-MATEMATIKA-A7Q2" /><Button type="button" variant="outline" onClick={() => { const next = Math.random().toString(36).slice(2, 6).toUpperCase(); setCode(next); setNameTouched(false); }} className="shrink-0">Acak kode</Button></div></label><p className="text-xs text-[#536765]">Saran nama mengikuti jenis ujian, kelas, mapel, dan kode unik. Nama dapat diubah.</p>
    </div><div className="flex justify-end gap-2 border-t border-[#D7E0DE] bg-white p-4 sm:rounded-b-xl"><Button type="button" variant="outline" onClick={onClose}>Batal</Button><Button disabled={busy || classIds.length === 0}>{busy ? 'Menyimpan…' : 'Simpan bank soal'}</Button></div></form>
  </Dialog.Content></Dialog.Portal>;
}

export function QuestionBankList() {
  const [rows, setRows] = useState<Row[]>([]);
  const [masters, setMasters] = useState<Masters>(emptyMasters);
  const [assignments, setAssignments] = useState<Row[] | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [subject, setSubject] = useState('');
  const [classId, setClassId] = useState('');
  const [editing, setEditing] = useState<Row | null | 'new'>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [revision, setRevision] = useState(0);
  useEffect(() => { api.questionBankMasters().then(setMasters).catch(reason => setError((reason as Error).message)); api.me().then(profile => { if (profile.roles.includes("teacher") && !profile.roles.some(role => role === "school_admin" || role === "superadmin")) api.myTeaching().then(result => setAssignments(result.rows)).catch(reason => setError((reason as Error).message)); }).catch(reason => setError((reason as Error).message)); }, []);
  useEffect(() => { setLoading(true); api.questionBanks(page, search, subject, classId).then(result => { setRows(result.rows); setTotal(result.total); setError(''); }).catch(reason => setError((reason as Error).message)).finally(() => setLoading(false)); }, [page, search, subject, classId, revision]);
  async function archive(row: Row) {
    if (!window.confirm('Arsipkan bank soal ini? Soal di dalamnya tetap tersimpan untuk ujian yang telah dibuat.')) return;
    try { await api.archiveQuestionBank(Number(row.id)); setRevision(value => value + 1); }
    catch (reason) { setError((reason as Error).message); }
  }
  return <section className="min-w-0 space-y-5"><header className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-[.16em] text-[#38746F]">Ruang guru / administrasi</p><h1 className="mt-1 text-2xl font-bold">Bank soal</h1><p className="mt-1 text-sm text-[#536765]">Susun soal per jenis ujian, mata pelajaran, dan kelas.</p></div><div className="flex flex-wrap gap-2"><Link className="inline-flex min-h-11 items-center gap-2 rounded-md border border-[#B6C9C5] bg-white px-3 text-sm font-semibold text-[#155451] hover:bg-[#E5F3EF]" to="/questions/tools">Impor soal</Link><a className="inline-flex min-h-11 items-center gap-2 rounded-md border border-[#B6C9C5] bg-white px-3 text-sm font-semibold text-[#155451] hover:bg-[#E5F3EF]" href="/api/v1/questions/package.zip"><Download size={16} />Backup ZIP</a><Button onClick={() => setEditing('new')}><FolderPlus size={17} />Buat bank soal</Button></div></header>
    {error && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    <Card className="overflow-hidden"><div className="flex flex-wrap items-center justify-between gap-3 border-b p-4 sm:p-5"><div className="flex items-center gap-2"><BookOpenText className="text-[#155451]" size={20} /><h2 className="font-semibold">Daftar bank soal</h2></div><Link className="text-sm font-semibold text-[#155451] underline" to="/questions/new">Tulis soal</Link></div>
      <div className="grid gap-3 border-b bg-[#F7FAF9] p-4 sm:grid-cols-[minmax(150px,220px)_minmax(150px,220px)_minmax(180px,1fr)] sm:p-5"><label className="text-xs font-semibold text-[#536765]">Kelas<select value={classId} onChange={event => { setClassId(event.target.value); setPage(1); }} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3 text-sm text-[#171C1D]"><option value="">Semua kelas</option>{masters.classes.map(row => <option key={Number(row.id)} value={Number(row.id)}>{label(row, 'name')}</option>)}</select></label><label className="text-xs font-semibold text-[#536765]">Mata pelajaran<select value={subject} onChange={event => { setSubject(event.target.value); setPage(1); }} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3 text-sm text-[#171C1D]"><option value="">Semua mata pelajaran</option>{masters.subjects.map(row => <option key={Number(row.id)} value={Number(row.id)}>{label(row, 'name')}</option>)}</select></label><label className="text-xs font-semibold text-[#536765]">Cari nama bank<div className="relative mt-1"><Search size={17} className="pointer-events-none absolute left-3 top-3 text-[#536765]" /><Input className="pl-9" value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} placeholder="Nama bank soal…" /></div></label></div>
      <div className="border-b px-4 py-2 text-right text-xs text-[#536765]">Menampilkan {rows.length} dari {total} bank soal</div>
      {loading ? <p role="status" className="p-6 text-sm text-[#536765]">Memuat bank soal…</p> : rows.length === 0 ? <div className="p-8 text-center"><ListFilter className="mx-auto text-[#38746F]" size={32} /><p className="mt-3 font-semibold">Belum ada bank soal pada pilihan ini.</p><p className="mt-1 text-sm text-[#536765]">Buat bank baru atau ubah filter untuk melihat bank lain.</p></div> : <><div className="hidden overflow-x-auto lg:block"><table className="w-full min-w-[900px] border-collapse text-left text-sm"><thead className="bg-[#F0F5F4] text-[#29413F]"><tr><th className="w-14 px-4 py-3">No</th><th className="px-4 py-3">Nama bank soal</th><th className="px-4 py-3">Jenis ujian</th><th className="px-4 py-3">Mata pelajaran</th><th className="px-4 py-3">Kelas</th><th className="px-4 py-3">Tipe soal</th><th className="px-4 py-3">Jumlah soal</th><th className="px-4 py-3">Aksi</th></tr></thead><tbody>{rows.map((row, index) => <tr key={Number(row.id)} className="border-t align-top"><td className="px-4 py-4 text-[#536765]">{(page - 1) * 20 + index + 1}</td><td className="px-4 py-4 font-semibold"><Link className="break-all text-[#155451] underline-offset-2 hover:underline" to={'/question-banks/' + row.id}>{label(row, 'name')}</Link></td><td className="px-4 py-4">{label(row, 'exam_type_name') || '—'}</td><td className="px-4 py-4">{label(row, 'subject_name')}</td><td className="px-4 py-4"><div className="flex max-w-48 flex-wrap gap-1">{((row.classes as Row[]) ?? []).map(item => <span key={Number(item.id)} className="rounded border border-[#C9E0D8] bg-[#E5F3EF] px-1.5 py-0.5 text-xs text-[#155451]">{label(item, 'name')}</span>)}{((row.classes as Row[]) ?? []).length === 0 && '—'}</div></td><td className="px-4 py-4"><TypeCounts row={row} /></td><td className="px-4 py-4 whitespace-nowrap font-semibold">{count(row)} soal</td><td className="px-4 py-4"><div className="flex gap-1"><Link aria-label={'Kelola ' + label(row, 'name')} title="Kelola soal" className="flex size-11 items-center justify-center rounded-md bg-[#155451] text-white hover:bg-[#0D403D]" to={'/question-banks/' + row.id}><BookOpenText size={17} /></Link><button type="button" aria-label={'Ubah ' + label(row, 'name')} title="Ubah bank" className="flex size-11 items-center justify-center rounded-md border border-[#B6C9C5] text-[#155451] hover:bg-[#E5F3EF]" onClick={() => setEditing(row)}><Pencil size={17} /></button>{!row.default_key && <button type="button" aria-label={'Arsipkan ' + label(row, 'name')} title="Arsipkan bank" className="flex size-11 items-center justify-center rounded-md border border-[#E8B9B4] text-red-700 hover:bg-red-50" onClick={() => void archive(row)}><Archive size={17} /></button>}</div></td></tr>)}</tbody></table></div><div className="divide-y lg:hidden">{rows.map(row => <div key={Number(row.id)} className="space-y-3 p-4"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="text-xs font-bold uppercase tracking-wide text-[#38746F]">{label(row, 'exam_type_name') || 'Koleksi soal'} · {label(row, 'subject_name')}</p><Link className="mt-1 block break-all font-semibold text-[#155451]" to={'/question-banks/' + row.id}>{label(row, 'name')}</Link></div><span className="shrink-0 rounded-md bg-[#E5F3EF] px-2 py-1 text-xs font-semibold text-[#155451]">{count(row)} soal</span></div><div className="flex flex-wrap gap-1">{((row.classes as Row[]) ?? []).map(item => <span key={Number(item.id)} className="rounded border border-[#C9E0D8] px-2 py-1 text-xs">{label(item, 'name')}</span>)}</div><TypeCounts row={row} /><div className="flex flex-wrap gap-2"><Button asChild size="sm"><Link to={'/question-banks/' + row.id}>Kelola soal</Link></Button><Button type="button" size="sm" variant="outline" onClick={() => setEditing(row)}>Ubah</Button>{!row.default_key && <Button type="button" size="sm" variant="outline" onClick={() => void archive(row)}>Arsipkan</Button>}</div></div>)}</div></>}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4 text-sm"><span>Halaman {page}</span><div className="flex flex-wrap gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" disabled={page * 20 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div>
    </Card>
    <Dialog.Root open={editing !== null} onOpenChange={open => { if (!open) setEditing(null); }}>{editing !== null && <BankForm key={editing === 'new' ? 'new' : Number(editing.id)} bank={editing === 'new' ? null : editing} masters={masters} assignments={assignments} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); setRevision(value => value + 1); }} />}</Dialog.Root>
  </section>;
}

function TypeCounts({ row }: { row: Row }) {
  const counts = (row.type_counts as Record<string, number>) ?? {};
  const entries = Object.entries(counts).filter(([, value]) => Number(value) > 0);
  return entries.length ? <div className="flex flex-wrap gap-1">{entries.map(([key, value]) => <span key={key} className="rounded border border-[#D7E0DE] bg-white px-1.5 py-0.5 text-xs text-[#29413F]">{typeLabels[key] ?? key} {value}</span>)}</div> : <span className="text-[#536765]">—</span>;
}

export function QuestionBankManage() {
  const { id } = useParams();
  const bankId = Number(id);
  const [bank, setBank] = useState<Row | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const [revision, setRevision] = useState(0);
  const [editor, setEditor] = useState<number | 'new' | null>(null);
  useEffect(() => { api.questionBank(bankId).then(setBank).catch(reason => setError((reason as Error).message)); }, [bankId, revision]);
  useEffect(() => { api.questions(page, search, '', 20, String(bankId)).then(result => { setRows(result.rows); setTotal(result.total); }).catch(reason => setError((reason as Error).message)); }, [bankId, page, search, revision]);
  async function archiveQuestion(row: Row) {
    if (!window.confirm('Arsipkan soal ini?')) return;
    try { await api.archiveQuestion(Number(row.id)); setRevision(value => value + 1); }
    catch (reason) { setError((reason as Error).message); }
  }
  return <section className="min-w-0 space-y-5"><header><Link to="/questions" className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[#155451] underline"><ArrowLeft size={16} />Daftar bank soal</Link><p className="mt-2 text-xs font-bold uppercase tracking-[.16em] text-[#38746F]">Kelola soal</p><h1 className="mt-1 break-all text-2xl font-bold">{bank ? label(bank, 'name') : 'Bank soal'}</h1>{bank && <p className="mt-1 text-sm text-[#536765]">{label(bank, 'exam_type_name') || 'Koleksi soal'} · {label(bank, 'subject_name')} · {count(bank)} soal</p>}</header>
    {error && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    {bank && <Card className="overflow-hidden"><div className="flex flex-wrap items-center justify-between gap-3 border-b p-4 sm:p-5"><div><h2 className="font-semibold">Soal dalam bank</h2><p className="mt-1 text-sm text-[#536765]">Simpan draf, periksa jawaban, lalu terbitkan soal.</p></div><div className="flex flex-wrap gap-2"><Link className="inline-flex min-h-11 items-center rounded-md border border-[#B6C9C5] bg-white px-3 text-sm font-semibold text-[#155451] hover:bg-[#E5F3EF]" to="/questions/tools">Impor Word / ZIP</Link><Button onClick={() => setEditor('new')}><Plus size={17} />Buat soal baru</Button></div></div><div className="grid gap-3 bg-[#F7FAF9] p-4 sm:grid-cols-[minmax(180px,1fr)_auto] sm:items-end"><label className="text-xs font-semibold text-[#536765]">Cari soal<Input className="mt-1" value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} placeholder="Kata pada soal…" /></label><span className="text-sm text-[#536765]">Menampilkan {rows.length} dari {total} soal</span></div><div className="divide-y">{rows.length === 0 && <div className="p-8 text-center"><FilePlus2 className="mx-auto text-[#38746F]" size={32} /><p className="mt-3 font-semibold">Belum ada soal dalam bank ini.</p><p className="mt-1 text-sm text-[#536765]">Pilih “Buat soal baru” untuk mulai menulis.</p></div>}{rows.map((row, index) => <article key={Number(row.id)} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:gap-4 sm:p-5"><span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-[#E5F3EF] text-sm font-bold text-[#155451]">{(page - 1) * 20 + index + 1}</span><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2 text-xs font-semibold"><span className="rounded bg-[#E5F3EF] px-2 py-1 text-[#155451]">{typeLabels[label(row, 'question_type')] ?? label(row, 'question_type')}</span><span className="text-[#536765]">{label(row, 'status') === 'published' ? 'Terbit' : 'Draf'}</span><span className="text-[#536765]">Bobot {label(row, 'weight')}</span></div><div className="mt-3 break-words text-sm"><MathText text={label(row, 'body')} /></div></div><div className="flex shrink-0 gap-2"><Button size="sm" variant="outline" onClick={() => setEditor(Number(row.id))}>Buka / ubah</Button><Button size="sm" variant="outline" onClick={() => void archiveQuestion(row)}>Arsipkan</Button></div></article>)}</div><div className="flex flex-wrap items-center justify-between gap-3 border-t p-4 text-sm"><span>Halaman {page}</span><div className="flex flex-wrap gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" disabled={page * 20 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div></Card>}
    <Dialog.Root open={editor !== null} onOpenChange={open => { if (!open) setEditor(null); }}>{editor !== null && bank && <Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-50 bg-[#102C2B]/65" /><Dialog.Content className="fixed inset-0 z-50 overflow-y-auto bg-[#F5F6F7] p-3 sm:inset-4 sm:rounded-xl sm:p-5 lg:inset-x-[max(24px,calc((100vw-1000px)/2))] lg:inset-y-6"><Dialog.Title className="sr-only">{editor === 'new' ? 'Buat soal baru' : 'Ubah soal'}</Dialog.Title><Dialog.Close aria-label="Tutup editor" className="absolute right-3 top-3 z-10 flex size-11 items-center justify-center rounded-md border bg-white shadow-sm sm:right-5 sm:top-5"><X size={20} /></Dialog.Close><Suspense fallback={<div className="flex min-h-80 items-center justify-center" role="status">Memuat editor soal…</div>}><QuestionEditor key={editor} bankId={bankId} subjectId={Number(bank.subject_id)} questionId={editor === 'new' ? null : editor} onClose={() => setEditor(null)} onSaved={() => { setEditor(null); setRevision(value => value + 1); }} /></Suspense></Dialog.Content></Dialog.Portal>}</Dialog.Root>
  </section>;
}
