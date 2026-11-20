import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Dialog } from 'radix-ui';
import { Eye, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { BulkExamStatusPanel } from '@/components/BulkExamStatusPanel';
import { ExamScheduleDialog } from '@/components/ExamScheduleDialog';

type Row = Record<string, unknown>;
type Tab = 'all' | 'active' | 'finished' | 'draft' | 'paused';
const tabs: { id: Tab; label: string; tone: string }[] = [
  { id: 'all', label: 'Semua Ujian', tone: 'bg-[#155451]' },
  { id: 'active', label: 'Aktif', tone: 'bg-emerald-500' },
  { id: 'finished', label: 'Selesai', tone: 'bg-sky-500' },
  { id: 'draft', label: 'Draf', tone: 'bg-slate-400' },
  { id: 'paused', label: 'Dijeda', tone: 'bg-amber-500' },
];
const value = (row: Row, key: string) => String(row[key] ?? '');
const int = (row: Row, key: string) => Number(row[key] ?? 0);
const dateTime = (date: string, timeZone: string) => date ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'short', timeStyle: 'short', timeZone }).format(new Date(date.replace(' ', 'T') + 'Z')) : '—';

function StatusBadge({ status }: { status: string }) {
  const color = status === 'Aktif' ? 'bg-emerald-100 text-emerald-800' : status === 'Selesai' ? 'bg-sky-100 text-sky-800' : status === 'Dijeda' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700';
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${color}`}>{status}</span>;
}

export function ExamList() {
  const navigate = useNavigate();
  const [rows, setRows] = useState<Row[]>([]);
  const [masters, setMasters] = useState<{ subjects: Row[]; classes: Row[] }>({ subjects: [], classes: [] });
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [total, setTotal] = useState(0);
  const [serverNow, setServerNow] = useState('');
  const [search, setSearch] = useState('');
  const [subject, setSubject] = useState('');
  const [classId, setClassId] = useState('');
  const [tab, setTab] = useState<Tab>('all');
  const [timeZone, setTimeZone] = useState('Asia/Jakarta');
  const [admin, setAdmin] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refresh, setRefresh] = useState(0);
  const [examDialog, setExamDialog] = useState<number | null | false>(false);
  const [tokenReceipt, setTokenReceipt] = useState<{ title: string; code: string } | null>(null);
  const [busy, setBusy] = useState<number | null>(null);

  useEffect(() => {
    api.examMasters().then(data => setMasters({ subjects: data.subjects, classes: data.classes })).catch(reason => setError((reason as Error).message));
    api.me().then(profile => setAdmin(profile.roles.some(role => role === 'school_admin' || role === 'superadmin'))).catch(() => {});
    api.school().then(row => { if (typeof row.timezone === 'string' && row.timezone) setTimeZone(row.timezone); }).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    api.exams(page, search, tab, subject, classId, perPage).then(result => {
      setRows(result.rows); setTotal(result.total); setCounts(result.counts); setServerNow(result.serverNow ?? ''); setError('');
    }).catch(reason => setError((reason as Error).message)).finally(() => setLoading(false));
  }, [page, perPage, search, tab, subject, classId, refresh]);

  const dialogOpen = examDialog !== false;
  const editId = typeof examDialog === 'number' ? examDialog : null;
  function refreshRows() { setRefresh(current => current + 1); }
  function statusOf(row: Row) {
    if (value(row, 'status') === 'draft') return 'Draf';
    if (value(row, 'status') === 'paused') return 'Dijeda';
    const end = new Date(value(row, 'end_at').replace(' ', 'T') + 'Z').getTime();
    return end < (serverNow ? new Date(serverNow.replace(' ', 'T') + 'Z').getTime() : Date.now()) ? 'Selesai' : 'Aktif';
  }
  async function archive(row: Row) {
    if (!window.confirm(`Arsipkan draf “${value(row, 'title')}”? Data tetap disimpan untuk jejak audit.`)) return;
    setBusy(int(row, 'id')); setError('');
    try { await api.archiveExam(int(row, 'id')); refreshRows(); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }
  async function rotateToken(row: Row) {
    setBusy(int(row, 'id')); setError('');
    try {
      const result = await api.setExamTokenPolicy(int(row, 'id'), 'static');
      if (!result.accessCode) throw new Error('Token baru tidak diterima dari server.');
      setTokenReceipt({ title: value(row, 'title'), code: result.accessCode }); refreshRows();
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }
  function edit(row: Row) {
    if (int(row, 'source_bank_id') > 0 && (value(row, 'status') === 'draft' || value(row, 'status') === 'published')) setExamDialog(int(row, 'id'));
    else navigate('/exams/' + row.id);
  }

  return <section className="min-w-0 space-y-5">
    <header className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-[.16em] text-[#38746F]">Ruang ujian</p><h1 className="mt-1 text-2xl font-bold">Jadwal Ujian</h1><p className="mt-1 text-sm text-[#536765]">Atur jadwal dari bank soal dan pantau status ujian.</p></div><Button onClick={() => setExamDialog(null)}><Plus size={17} />Tambah Jadwal</Button></header>
    {error && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    <Card className="min-w-0 overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4 sm:p-5"><h2 className="font-semibold">Daftar Jadwal Ujian</h2><span className="text-xs text-[#536765]">Draf disimpan; jadwal Aktif langsung diterbitkan.</span></div>
      <div role="tablist" aria-label="Filter status ujian" className="flex gap-2 overflow-x-auto border-b px-4 py-3 sm:px-5">{tabs.map(item => <button key={item.id} type="button" role="tab" aria-selected={tab === item.id} onClick={() => { setTab(item.id); setPage(1); }} className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-3 text-sm ${tab === item.id ? 'border-[#B6C9C5] bg-white font-semibold shadow-sm' : 'border-transparent text-[#536765] hover:bg-[#F0F5F4]'}`}><span className={`size-2 rounded-full ${item.tone}`} />{item.label}<span className="text-xs">{counts[item.id] ?? 0}</span></button>)}</div>
      <div className="grid gap-3 border-b bg-[#F7FAF9] p-4 sm:grid-cols-2 lg:grid-cols-[minmax(200px,1fr)_minmax(160px,220px)_minmax(150px,200px)_auto] lg:items-end sm:p-5">
        <label className="block text-xs font-semibold text-[#536765]">Cari ujian / bank soal<Input className="mt-1" value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} placeholder="Cari nama ujian atau bank soal..." /></label>
        <label className="block text-xs font-semibold text-[#536765]">Mata pelajaran<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3 text-sm text-[#171C1D]" value={subject} onChange={event => { setSubject(event.target.value); setPage(1); }}><option value="">Semua mapel</option>{masters.subjects.map(row => <option key={int(row, 'id')} value={int(row, 'id')}>{value(row, 'name')}</option>)}</select></label>
        <label className="block text-xs font-semibold text-[#536765]">Kelas<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3 text-sm text-[#171C1D]" value={classId} onChange={event => { setClassId(event.target.value); setPage(1); }}><option value="">Semua kelas</option>{masters.classes.map(row => <option key={int(row, 'id')} value={int(row, 'id')}>{value(row, 'name')}</option>)}</select></label>
        <label className="flex items-center gap-2 text-xs text-[#536765]">Tampilkan<select className="min-h-11 rounded-md border border-[#B6C9C5] bg-white px-2 text-sm text-[#171C1D]" value={perPage} onChange={event => { setPerPage(Number(event.target.value)); setPage(1); }}><option value={10}>10</option><option value={20}>20</option><option value={50}>50</option></select></label>
      </div>
      {admin && <div className="border-b p-4"><BulkExamStatusPanel rows={rows} onCommitted={refreshRows} /></div>}
      {loading ? <p role="status" className="p-8 text-center text-sm text-[#536765]">Memuat jadwal ujian…</p> : rows.length === 0 ? <div className="p-10 text-center"><p className="font-semibold">Belum ada jadwal ujian untuk filter ini.</p><p className="mt-1 text-sm text-[#536765]">Pilih bank soal untuk membuat jadwal baru.</p></div> : <>
        <div className="hidden overflow-x-auto lg:block"><table className="w-full min-w-[1120px] border-collapse text-sm"><thead className="bg-[#F0F5F4] text-[#29413F]"><tr><th className="p-3 text-left">No.</th><th className="p-3 text-left">Nama Ujian</th><th className="p-3 text-left">Kelas</th><th className="p-3 text-left">Bank Soal</th><th className="p-3 text-left">Tanggal &amp; Waktu</th><th className="p-3 text-center">Jml Soal</th><th className="p-3 text-center">Acak Soal / Opsi</th><th className="p-3 text-center">Token</th><th className="p-3 text-center">Status</th><th className="p-3 text-center">Aksi</th></tr></thead><tbody>{rows.map((row, index) => <tr key={int(row, 'id')} className="border-t align-middle odd:bg-white even:bg-[#FAFCFC]"><td className="p-3 text-[#536765]">{(page - 1) * perPage + index + 1}</td><td className="max-w-56 p-3 font-semibold"><Link className="text-[#155451] hover:underline" to={'/exams/' + row.id}>{value(row, 'title')}</Link><span className="mt-1 block text-xs font-normal text-[#536765]">{value(row, 'subject_name')}</span></td><td className="max-w-40 p-3 text-xs">{value(row, 'class_names') || '—'}</td><td className="max-w-64 p-3 text-xs">{int(row, 'source_bank_id') > 0 ? <Link className="text-[#155451] hover:underline" to={'/question-banks/' + row.source_bank_id}>{value(row, 'bank_name')}</Link> : 'Bank lama'}<span className="mt-1 block text-[#536765]">Poin bank: {Number(row.total_points ?? 0)}</span></td><td className="p-3 text-xs"><span className="whitespace-nowrap">{dateTime(value(row, 'start_at'), timeZone)}</span><span className="block text-[#536765]">s/d {dateTime(value(row, 'end_at'), timeZone)}</span></td><td className="p-3 text-center">{int(row, 'question_count')}</td><td className="p-3 text-center text-xs"><span className="block">Soal: {int(row, 'shuffle_questions') === 1 ? '✓' : '—'}</span><span className="block">Opsi: {int(row, 'shuffle_options') === 1 ? '✓' : '—'}</span></td><td className="p-3 text-center">{int(row, 'token_enabled') === 1 ? <span className="rounded border bg-[#F7FAF9] px-2 py-1 font-mono text-xs">Tersedia</span> : '—'}</td><td className="p-3 text-center"><StatusBadge status={statusOf(row)} /></td><td className="p-3"><ActionButtons row={row} busy={busy === int(row, 'id')} onView={() => navigate('/exams/' + row.id)} onEdit={() => edit(row)} onArchive={() => void archive(row)} onRotate={() => void rotateToken(row)} /></td></tr>)}</tbody></table></div>
        <div className="divide-y lg:hidden">{rows.map((row, index) => <article key={int(row, 'id')} className="space-y-3 p-4"><div className="flex items-start gap-3"><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center justify-between gap-2"><p className="text-xs font-semibold uppercase text-[#38746F]">{value(row, 'subject_name')} · {value(row, 'class_names') || 'Tanpa kelas'}</p><StatusBadge status={statusOf(row)} /></div><Link className="mt-1 block break-words font-semibold text-[#155451]" to={'/exams/' + row.id}>{value(row, 'title')}</Link></div></div><dl className="grid grid-cols-2 gap-x-4 gap-y-2 pl-0 text-xs"><div><dt className="text-[#536765]">Bank soal</dt><dd className="mt-0.5 break-words">{value(row, 'bank_name') || 'Bank lama'}</dd></div><div><dt className="text-[#536765]">Jumlah soal</dt><dd className="mt-0.5">{int(row, 'question_count')} · Poin {Number(row.total_points ?? 0)}</dd></div><div><dt className="text-[#536765]">Mulai</dt><dd className="mt-0.5">{dateTime(value(row, 'start_at'), timeZone)}</dd></div><div><dt className="text-[#536765]">Berakhir</dt><dd className="mt-0.5">{dateTime(value(row, 'end_at'), timeZone)}</dd></div><div><dt className="text-[#536765]">Acak</dt><dd className="mt-0.5">Soal {int(row, 'shuffle_questions') ? '✓' : '—'} · Opsi {int(row, 'shuffle_options') ? '✓' : '—'}</dd></div><div><dt className="text-[#536765]">Token</dt><dd className="mt-0.5">{int(row, 'token_enabled') ? 'Tersedia' : '—'}</dd></div></dl><div className="flex flex-wrap gap-2 pl-0"><ActionButtons row={row} busy={busy === int(row, 'id')} onView={() => navigate('/exams/' + row.id)} onEdit={() => edit(row)} onArchive={() => void archive(row)} onRotate={() => void rotateToken(row)} /></div><span className="sr-only">Nomor {index + 1}</span></article>)}</div>
      </>}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4 text-sm"><span>{total} jadwal · Halaman {page}</span><div className="flex gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" disabled={page * perPage >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div>
    </Card>
    <ExamScheduleDialog open={dialogOpen} examId={editId} onOpenChange={open => setExamDialog(open ? editId : false)} onSaved={refreshRows} />
    <Dialog.Root open={tokenReceipt !== null} onOpenChange={open => { if (!open) setTokenReceipt(null); }}><Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-[60] bg-[#102C2B]/60" /><Dialog.Content className="fixed left-1/2 top-1/2 z-[60] w-[min(420px,calc(100vw-24px))] -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white p-6 shadow-xl"><Dialog.Title className="text-lg font-semibold">Token baru dibuat</Dialog.Title><p className="mt-2 text-sm text-[#536765]">Simpan dan bagikan token untuk “{tokenReceipt?.title}”. Token tidak dapat ditampilkan kembali.</p><code className="mt-4 block rounded-md border bg-[#F7FAF9] p-3 text-center font-mono text-xl font-bold tracking-[.2em]">{tokenReceipt?.code}</code><Button className="mt-5 w-full" onClick={() => setTokenReceipt(null)}>Selesai</Button></Dialog.Content></Dialog.Portal></Dialog.Root>
  </section>;
}

function ActionButtons({ row, busy, onView, onEdit, onArchive, onRotate }: { row: Row; busy: boolean; onView: () => void; onEdit: () => void; onArchive: () => void; onRotate: () => void }) {
  const isDraft = value(row, 'status') === 'draft';
  const isPublished = value(row, 'status') === 'published';
  return <div className="flex flex-wrap gap-1"><button type="button" aria-label={`Lihat ${value(row, 'title')}`} title="Lihat detail" onClick={onView} className="flex size-10 items-center justify-center rounded-md bg-[#155451] text-white hover:bg-[#0D403D]"><Eye size={17} /></button><button type="button" aria-label={`Ubah ${value(row, 'title')}`} title="Ubah jadwal" disabled={busy || (isPublished && int(row, 'attempt_count') > 0)} onClick={onEdit} className="flex size-10 items-center justify-center rounded-md border border-[#B6C9C5] text-[#155451] hover:bg-[#E5F3EF] disabled:cursor-not-allowed disabled:opacity-40"><Pencil size={17} /></button>{isPublished && <button type="button" aria-label={`Ganti token ${value(row, 'title')}`} title="Buat token baru" disabled={busy} onClick={onRotate} className="flex size-10 items-center justify-center rounded-md border border-[#B6C9C5] text-[#155451] hover:bg-[#E5F3EF] disabled:opacity-40"><RefreshCw size={16} /></button>}{isDraft && <button type="button" aria-label={`Arsipkan ${value(row, 'title')}`} title="Arsipkan draf" disabled={busy} onClick={onArchive} className="flex size-10 items-center justify-center rounded-md border border-red-200 text-red-700 hover:bg-red-50 disabled:opacity-40"><Trash2 size={16} /></button>}</div>;
}
