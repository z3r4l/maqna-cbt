import { useEffect, useMemo, useState } from 'react';
import { Dialog } from 'radix-ui';
import { Check, ChevronDown, Search, Shuffle, X } from 'lucide-react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DateTimePicker } from '@/components/DateTimePicker';

type Row = Record<string, unknown>;
type Masters = { subjects: Row[]; semesters: Row[]; classes: Row[]; examTypes: Row[] };
const label = (row: Row | null | undefined, key: string) => String(row?.[key] ?? '');
const number = (row: Row | null | undefined, key: string) => Number(row?.[key] ?? 0);
const localNow = (offsetHours: number) => {
  const date = new Date(Date.now() + offsetHours * 3600000);
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
const localDate = (value: string) => {
  if (!value) return '';
  const date = new Date(value.replace(' ', 'T') + 'Z');
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
function createToken() {
  const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  const values = crypto.getRandomValues(new Uint8Array(8));
  return Array.from(values, value => alphabet[value % alphabet.length]).join('');
}

export function ExamScheduleDialog({ open, examId, onOpenChange, onSaved }: {
  open: boolean; examId: number | null; onOpenChange: (open: boolean) => void; onSaved: () => void;
}) {
  const [masters, setMasters] = useState<Masters>({ subjects: [], semesters: [], classes: [], examTypes: [] });
  const [banks, setBanks] = useState<Row[]>([]);
  const [bankTotal, setBankTotal] = useState(0);
  const [bankPage, setBankPage] = useState(1);
  const [bankSearch, setBankSearch] = useState('');
  const [bankPickerOpen, setBankPickerOpen] = useState(false);
  const [bank, setBank] = useState<Row | null>(null);
  const [title, setTitle] = useState('');
  const [semesterId, setSemesterId] = useState('');
  const [classIds, setClassIds] = useState<number[]>([]);
  const [displayCount, setDisplayCount] = useState('');
  const [accessCode, setAccessCode] = useState('');
  const [status, setStatus] = useState<'draft' | 'published'>('published');
  const [startAt, setStartAt] = useState(localNow(1));
  const [endAt, setEndAt] = useState(localNow(3));
  const [duration, setDuration] = useState('90');
  const [shuffleQuestions, setShuffleQuestions] = useState(true);
  const [shuffleOptions, setShuffleOptions] = useState(true);
  const [requiresCode, setRequiresCode] = useState(false);
  const [attemptCount, setAttemptCount] = useState(0);
  const [published, setPublished] = useState(false);
  const [savedCode, setSavedCode] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const isEdit = examId !== null;
  const isPublishedEdit = published || (isEdit && status === 'published');

  useEffect(() => {
    if (!open) return;
    setError(''); setSavedCode(''); setBusy(false); setBankPickerOpen(false);
    setStartAt(localNow(1)); setEndAt(localNow(3)); setDuration('90'); setStatus('published'); setPublished(false);
    setBankSearch(''); setBankPage(1); setBank(null); setClassIds([]); setTitle(''); setDisplayCount(''); setAttemptCount(0);
    setAccessCode(examId === null ? createToken() : ''); setRequiresCode(false);
    api.examMasters().then(setMasters).catch(reason => setError((reason as Error).message));
    if (examId !== null) {
      api.exam(examId).then(async row => {
        setTitle(label(row, 'title')); setSemesterId(label(row, 'semester_id')); setClassIds((row.classIds as number[]) ?? []);
        setStartAt(localDate(label(row, 'start_at'))); setEndAt(localDate(label(row, 'end_at'))); setDuration(label(row, 'duration_minutes'));
        setStatus(label(row, 'status') === 'draft' ? 'draft' : 'published'); setPublished(label(row, 'status') !== 'draft');
        setDisplayCount(label(row, 'display_question_count')); setAttemptCount(number(row, 'attemptCount')); setRequiresCode(Boolean(row.requiresAccessCode));
        setShuffleQuestions(number(row, 'shuffle_questions') === 1); setShuffleOptions(number(row, 'shuffle_options') === 1);
        if (number(row, 'source_bank_id') > 0) {
          try { setBank(await api.questionBank(number(row, 'source_bank_id'))); }
          catch (reason) { setError((reason as Error).message); }
        }
      }).catch(reason => setError((reason as Error).message));
    } else setSemesterId('');
  }, [open, examId]);

  useEffect(() => {
    if (!open || isPublishedEdit) return;
    const timer = window.setTimeout(() => {
      api.questionBanks(bankPage, bankSearch, '', '', 20).then(result => { setBanks(result.rows); setBankTotal(result.total); }).catch(reason => setError((reason as Error).message));
    }, 180);
    return () => window.clearTimeout(timer);
  }, [open, bankPage, bankSearch, isPublishedEdit]);

  const semesters = useMemo(() => bank ? masters.semesters.filter(row => ((bank.classes as Row[]) ?? []).some(item => number(item, 'academic_year_id') === number(row, 'academic_year_id'))) : masters.semesters, [bank, masters.semesters]);
  const activeSemester = semesters.find(row => String(row.id) === semesterId) ?? null;
  const bankClasses = ((bank?.classes as Row[]) ?? []).filter(row => !activeSemester || number(row, 'academic_year_id') === number(activeSemester, 'academic_year_id'));
  const selectableClasses = bankClasses.filter(row => masters.classes.some(item => Number(item.id) === Number(row.id)));
  const readyCount = number(bank, 'published_question_count');

  function selectBank(row: Row) {
    setBank(row); setTitle(label(row, 'name')); setDisplayCount(String(number(row, 'published_question_count'))); setBankPickerOpen(false);
    const matchingSemesters = masters.semesters.filter(item => ((row.classes as Row[]) ?? []).some(group => number(group, 'academic_year_id') === number(item, 'academic_year_id')));
    const nextSemester = matchingSemesters.find(item => String(item.id) === semesterId) ?? matchingSemesters[0];
    setSemesterId(label(nextSemester, 'id'));
    const year = number(nextSemester, 'academic_year_id');
    setClassIds(((row.classes as Row[]) ?? []).filter(group => number(group, 'academic_year_id') === year && masters.classes.some(item => Number(item.id) === Number(group.id))).map(group => Number(group.id)));
  }

  function toggleClass(id: number) { setClassIds(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id]); }

  async function submit(event: React.FormEvent) {
    event.preventDefault(); setError(''); setSavedCode('');
    if (!startAt || !endAt || (!isPublishedEdit && (!bank || !semesterId))) { setError('Pilih bank, semester, serta waktu mulai dan selesai.'); return; }
    if (!isPublishedEdit && (!accessCode || accessCode.length < 6)) { setError('Token ujian harus berisi sedikitnya 6 karakter.'); return; }
    setBusy(true);
    try {
      if (isPublishedEdit) {
        if (!examId) throw new Error('Jadwal terbit tidak ditemukan.');
        await api.updateExamSchedule(examId, { startAt: new Date(startAt).toISOString(), endAt: new Date(endAt).toISOString(), durationMinutes: Number(duration) });
      } else {
        if (!bank) throw new Error('Pilih bank soal terlebih dahulu.');
        const payload: Record<string, unknown> = { bankId: Number(bank.id), title, semesterId: Number(semesterId), classIds, displayQuestionCount: Number(displayCount),
          startAt: new Date(startAt).toISOString(), endAt: new Date(endAt).toISOString(), durationMinutes: Number(duration),
          shuffleQuestions, shuffleOptions, status };
        if (accessCode) payload.accessCode = accessCode;
        if (examId) await api.updateExamFromBank(examId, payload);
        else await api.createExamFromBank(payload);
        setPublished(status === 'published'); setSavedCode(accessCode); setRequiresCode(true);
      }
      onSaved();
      if (isPublishedEdit || (!accessCode && examId !== null)) onOpenChange(false);
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }

  const titleText = examId ? (isPublishedEdit ? 'Jadwal Ujian' : 'Ubah Jadwal Ujian') : 'Tambah Jadwal Ujian';
  return <Dialog.Root open={open} onOpenChange={onOpenChange}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-[#102C2B]/60" />
      <Dialog.Content className="fixed inset-0 z-50 flex flex-col bg-white shadow-2xl sm:inset-x-1/2 sm:top-1/2 sm:h-[min(92dvh,900px)] sm:w-[min(820px,calc(100vw-32px))] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl" aria-describedby="exam-schedule-description">
        <div className="flex shrink-0 items-center justify-between bg-[#155451] px-5 py-4 text-white sm:rounded-t-xl"><div><Dialog.Title className="text-lg font-semibold">{titleText}</Dialog.Title><Dialog.Description id="exam-schedule-description" className="text-xs text-white/80">Atur bank soal, peserta, dan waktu ujian.</Dialog.Description></div><Dialog.Close aria-label="Tutup" className="flex size-11 items-center justify-center rounded-md hover:bg-white/15"><X size={20} /></Dialog.Close></div>
        {savedCode ? <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 overflow-y-auto p-6 text-center"><span className="flex size-12 items-center justify-center rounded-full bg-[#E5F3EF] text-[#155451]"><Check /></span><h2 className="text-xl font-semibold">{published ? 'Jadwal ujian diterbitkan' : 'Draf jadwal tersimpan'}</h2><p className="max-w-md text-sm text-[#536765]">Simpan token ini dan bagikan kepada peserta. Token tidak akan ditampilkan kembali.</p><code className="rounded-md border border-[#D7E0DE] bg-[#F5F6F7] px-5 py-3 text-xl font-bold tracking-[.2em]">{savedCode}</code><Button type="button" onClick={() => onOpenChange(false)}>Selesai</Button></div> : <form onSubmit={event => void submit(event)} className="flex min-h-0 flex-1 flex-col">
          <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
            {error && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}
            {isPublishedEdit ? <div className="rounded-md border border-[#D7E0DE] bg-[#F7FAF9] p-3 text-sm"><p className="font-semibold">{title}</p><p className="mt-1 text-[#536765]">{label(bank, 'name')} · {label(bank, 'subject_name')}</p>{attemptCount > 0 && <p className="mt-2 text-amber-800">Waktu dikunci karena sudah ada siswa yang mulai mengerjakan.</p>}</div> : <>
              <div className="relative"><label className="block text-sm font-medium">Pilih Bank Soal <span className="text-red-700">*</span><button type="button" aria-haspopup="listbox" aria-expanded={bankPickerOpen} disabled={busy} onClick={() => setBankPickerOpen(value => !value)} className="mt-1 flex min-h-11 w-full items-center justify-between rounded-md border border-[#B6C9C5] bg-white px-3 text-left focus-visible:outline-2 focus-visible:outline-[#155451]"><span className="truncate">{bank ? label(bank, 'name') : '-- Pilih Bank Soal --'}</span><ChevronDown className="size-4 shrink-0" /></button></label>
                {bankPickerOpen && <div className="absolute inset-x-0 top-full z-20 mt-1 rounded-lg border border-[#B6C9C5] bg-white p-2 shadow-xl"><label className="relative block"><Search className="absolute left-3 top-3 size-4 text-[#536765]" /><Input autoFocus aria-label="Cari bank soal" className="pl-9" placeholder="Cari nama bank soal..." value={bankSearch} onChange={event => { setBankSearch(event.target.value); setBankPage(1); }} /></label><div role="listbox" aria-label="Daftar bank soal" className="mt-2 max-h-64 overflow-y-auto">{banks.map(row => <button key={Number(row.id)} type="button" role="option" aria-selected={Number(bank?.id) === Number(row.id)} onClick={() => selectBank(row)} className="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-[#155451]"><span className="min-w-0"><span className="block truncate text-sm font-medium">{label(row, 'name')}</span><span className="mt-1 block truncate text-xs text-[#536765]">{label(row, 'author_name') || 'Bank soal'} · {((row.classes as Row[]) ?? []).map(item => label(item, 'name')).join(', ')}</span></span><span className="shrink-0 rounded bg-[#F0F5F4] px-2 py-1 text-xs">{number(row, 'published_question_count')} soal · {number(row, 'published_total_points')} poin</span></button>)}{banks.length === 0 && <p className="p-4 text-center text-sm text-[#536765]">Bank soal tidak ditemukan.</p>}</div><div className="flex items-center justify-between border-t px-2 pt-2 text-xs text-[#536765]"><span>{bankTotal} bank</span><div className="flex gap-2"><button type="button" disabled={bankPage <= 1} onClick={() => setBankPage(page => page - 1)}>Sebelumnya</button><button type="button" disabled={bankPage * 20 >= bankTotal} onClick={() => setBankPage(page => page + 1)}>Berikutnya</button></div></div></div>}
                <p className="mt-1 text-xs text-[#536765]">Nama ujian, mapel, kelas, dan jumlah soal akan mengikuti bank yang dipilih.</p>
              </div>
              <label className="block text-sm font-medium">Nama Ujian <span className="text-red-700">*</span><Input required minLength={3} maxLength={160} className="mt-1" value={title} onChange={event => setTitle(event.target.value)} placeholder="Contoh: PAS MATEMATIKA" /></label>
              {semesters.length > 1 && <label className="block text-sm font-medium">Semester <span className="text-red-700">*</span><select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={semesterId} onChange={event => { setSemesterId(event.target.value); const year = number(masters.semesters.find(item => String(item.id) === event.target.value), 'academic_year_id'); setClassIds(bankClasses.filter(item => number(item, 'academic_year_id') === year).map(item => Number(item.id))); }}><option value="">Pilih semester</option>{semesters.map(row => <option key={Number(row.id)} value={Number(row.id)}>{label(row, 'name')}</option>)}</select></label>}
              {bank && <fieldset><div className="flex flex-wrap items-center justify-between gap-2"><legend className="text-sm font-medium">Pilih Kelas</legend><div className="flex gap-3 text-xs"><button type="button" className="font-medium text-[#155451] underline" onClick={() => setClassIds(selectableClasses.map(row => Number(row.id)))}>Pilih Semua</button><button type="button" className="text-[#536765] underline" onClick={() => setClassIds([])}>Kosongkan</button></div></div><div className="mt-2 grid grid-cols-2 gap-2 rounded-md border border-[#D7E0DE] bg-[#F7FAF9] p-3 sm:grid-cols-4">{selectableClasses.map(row => <label key={Number(row.id)} className="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" checked={classIds.includes(Number(row.id))} onChange={() => toggleClass(Number(row.id))} className="size-4 accent-[#155451]" />{label(row, 'name')}</label>)}{selectableClasses.length === 0 && <p className="col-span-full text-sm text-[#536765]">Tidak ada kelas bank yang dapat dipilih untuk semester aktif.</p>}</div><p className="mt-1 text-xs text-[#536765]">Kelas peserta ujian. Guru hanya dapat menerbitkan untuk kelas dalam penugasannya.</p></fieldset>}
              <label className="block text-sm font-medium">Jumlah Soal Ditampilkan <span className="text-red-700">*</span><div className="mt-1 flex min-w-0"><Input required type="number" min="0" max={readyCount} className="rounded-r-none" disabled={!bank} value={displayCount} onChange={event => setDisplayCount(event.target.value)} placeholder="Jumlah soal yang dikerjakan siswa" /><span className="flex shrink-0 items-center rounded-r-md border border-l-0 border-[#B6C9C5] bg-[#F7FAF9] px-3 text-xs text-[#536765]">dari {readyCount} soal terbit</span></div><span className="mt-1 block text-xs text-[#536765]">Saat Acak Soal aktif, setiap siswa memperoleh pilihan berbeda dari soal dalam bank.</span></label>
              <div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-medium">Token Ujian <span className="text-red-700">*</span><div className="mt-1 flex gap-2"><Input required={!requiresCode} minLength={6} maxLength={32} autoComplete="off" value={accessCode} onChange={event => setAccessCode(event.target.value.toUpperCase())} placeholder={requiresCode ? 'Kosongkan untuk mempertahankan token' : 'Token akses'} /><Button type="button" variant="outline" onClick={() => setAccessCode(createToken())}>Acak</Button></div></label><label className="block text-sm font-medium">Status Ujian<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={status} onChange={event => setStatus(event.target.value as 'draft' | 'published')}><option value="published">Aktif (terbitkan saat simpan)</option><option value="draft">Draf</option></select></label></div>
            </>}
            <div className="grid gap-3 sm:grid-cols-2"><DateTimePicker label="Waktu Mulai" value={startAt} onChange={setStartAt} /><DateTimePicker label="Waktu Berakhir" value={endAt} onChange={setEndAt} /></div>
            <div className="grid gap-4 sm:grid-cols-[minmax(150px,1fr)_1fr]"><label className="block text-sm font-medium">Durasi (Menit) <span className="text-red-700">*</span><Input required type="number" min="1" max="720" disabled={isPublishedEdit && attemptCount > 0} className="mt-1" value={duration} onChange={event => setDuration(event.target.value)} /></label>{!isPublishedEdit && <div className="grid gap-2 self-end text-sm sm:pb-2"><label className="flex items-center gap-2"><input type="checkbox" checked={shuffleQuestions} onChange={event => setShuffleQuestions(event.target.checked)} className="size-4 accent-[#155451]" /><Shuffle className="size-4 text-[#155451]" />Acak Soal</label><label className="flex items-center gap-2"><input type="checkbox" checked={shuffleOptions} onChange={event => setShuffleOptions(event.target.checked)} className="size-4 accent-[#155451]" /><Shuffle className="size-4 text-[#155451]" />Acak Opsi</label></div>}</div>
          </div>
          <div className="flex shrink-0 justify-end gap-2 border-t border-[#D7E0DE] bg-white p-4 sm:rounded-b-xl"><Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Batal</Button><Button disabled={busy || (isPublishedEdit && attemptCount > 0) || (!isPublishedEdit && !bank)}>{busy ? 'Menyimpan…' : isPublishedEdit ? 'Simpan Perubahan Jadwal' : status === 'published' ? 'Simpan Jadwal Ujian' : 'Simpan Draf'}</Button></div>
        </form>}
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
