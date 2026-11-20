import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, ArrowRight, Check, ClipboardCheck, Clock3, Flag, Grid2X2, Info, X } from 'lucide-react';
import { Dialog } from 'radix-ui';
import { api, type Attempt, type AttemptItem } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import MathText from '@/components/MathText';
import BarWave from '@/components/BarWave';
import { MatchingAnswerView, TrueFalseAnswerView } from '@/components/StructuredAnswers';

function parseUtc(value: string) { return new Date(value.replace(' ', 'T') + 'Z').getTime(); }
function clock(seconds: number) { return Math.floor(seconds / 3600).toString().padStart(2, '0') + ':' + Math.floor((seconds % 3600) / 60).toString().padStart(2, '0') + ':' + (seconds % 60).toString().padStart(2, '0'); }
function isAnswered(item: AttemptItem) { return item.type === 'essay' ? Boolean(item.essayText?.trim()) : item.type === 'mcq' ? item.selectedOptionId !== null : item.answer !== null && Object.keys(item.answer).length > 0; }
function questionLabel(item: AttemptItem, position: number) {
  const state = position === -1 ? '' : isAnswered(item) ? ', sudah dijawab' : ', belum dijawab';
  return `Soal ${position + 1}${state}${item.marked ? ', ragu-ragu' : ''}`;
}

function Palette({ attempt, index, onSelect }: { attempt: Attempt; index: number; onSelect: (position: number) => void }) {
  return <div className="grid grid-cols-5 gap-2">
    {attempt.items.map((row, position) => {
      const current = index === position;
      const answered = isAnswered(row);
      const status = row.marked ? 'Ragu-ragu' : answered ? 'Sudah dijawab' : 'Belum dijawab';
      return <button key={row.id} type="button" aria-label={`${questionLabel(row, position)}, ${status}`} aria-current={current ? 'step' : undefined}
        onClick={() => onSelect(position)}
        className={`relative flex min-h-11 items-center justify-center rounded-[12px] border text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#006C70] ${current ? 'border-[#00666B] bg-[#00666B] text-white' : 'border-[#D1E1E9] bg-white text-[#13294B] hover:border-[#78AEB2] hover:bg-[#F2F8F9]'}`}>
        {position + 1}
        {!current && (row.marked || answered) && <span aria-hidden="true" className={`absolute -right-0.5 -bottom-0.5 size-3 rounded-full border-2 border-white ${row.marked ? 'bg-[#FFAB25]' : 'bg-[#11A96B]'}`} />}
      </button>;
    })}
  </div>;
}

function PaletteLegend() {
  return <div className="flex flex-wrap gap-x-3 gap-y-2 text-[11px] text-[#253858]" aria-label="Keterangan status soal">
    <span className="inline-flex items-center gap-1"><span className="size-2.5 rounded-full bg-[#00777B]" />Saat ini</span>
    <span className="inline-flex items-center gap-1"><span className="size-2.5 rounded-full bg-[#11A96B]" />Sudah</span>
    <span className="inline-flex items-center gap-1"><span className="size-2.5 rounded-full bg-[#FFAB25]" />Ragu-ragu</span>
    <span className="inline-flex items-center gap-1"><span className="size-2.5 rounded-full border border-[#AFC2D2] bg-white" />Belum</span>
  </div>;
}

export default function AttemptRunner() {
  const { id: idParam } = useParams();
  const id = Number(idParam);
  const [attempt, setAttempt] = useState<Attempt | null>(null);
  const [index, setIndex] = useState(0);
  const [remaining, setRemaining] = useState(0);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState<Record<number, 'saving' | 'saved' | 'error'>>({});
  const [busy, setBusy] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [submitOpen, setSubmitOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadRevision, setLoadRevision] = useState(0);
  const draft = useRef<Attempt | null>(null);
  const revisions = useRef(new Map<number, number>());
  const changes = useRef(new Map<number, number>());
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());
  const chains = useRef(new Map<number, Promise<void>>());
  const serverOffset = useRef(0);
  const autoSubmitted = useRef(false);

  useEffect(() => {
    let alive = true;
    api.attempt(id).then(row => {
      if (!alive) return;
      serverOffset.current = parseUtc(row.serverNow) - Date.now();
      setRemaining(Math.max(0, Math.floor((parseUtc(row.deadlineAt) - Date.now() - serverOffset.current) / 1000)));
      draft.current = row; setAttempt(row);
      row.items.forEach(item => revisions.current.set(item.id, item.revision));
    }).catch(reason => { if (alive) setError(reason.message); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; timers.current.forEach(timer => clearTimeout(timer)); };
  }, [id, loadRevision]);

  useEffect(() => {
    if (!attempt || attempt.status !== 'active') return;
    function tick() { setRemaining(Math.max(0, Math.floor((parseUtc(attempt!.deadlineAt) - Date.now() - serverOffset.current) / 1000))); }
    tick(); const interval = setInterval(tick, 1000); return () => clearInterval(interval);
  }, [attempt?.deadlineAt, attempt?.status]);

  useEffect(() => {
    if (attempt?.status !== 'active') return;
    const beat = (event?: string) => { void api.heartbeat(id, event).then(result => { serverOffset.current = parseUtc(result.serverNow) - Date.now(); }).catch(() => undefined); };
    const visibility = () => beat(document.hidden ? 'visibility_hidden' : 'visibility_visible');
    const focus = () => beat('focus');
    const blur = () => beat('blur');
    beat();
    const interval = setInterval(() => beat(), 20000);
    document.addEventListener('visibilitychange', visibility);
    window.addEventListener('focus', focus); window.addEventListener('blur', blur);
    return () => { clearInterval(interval); document.removeEventListener('visibilitychange', visibility); window.removeEventListener('focus', focus); window.removeEventListener('blur', blur); };
  }, [attempt?.status, id]);

  useEffect(() => {
    if (!attempt || attempt.status !== 'active' || remaining > 0 || autoSubmitted.current) return;
    autoSubmitted.current = true;
    void api.submitAttempt(id).then(result => {
      const current = draft.current;
      if (current) { const next = { ...current, status: result.status, submittedAt: result.submittedAt }; draft.current = next; setAttempt(next); }
    }).catch(reason => setError('Waktu habis. Sinkronisasi ke server gagal: ' + reason.message));
  }, [remaining, attempt?.status, id]);

  function queue(itemId: number): Promise<void> {
    const prior = chains.current.get(itemId) ?? Promise.resolve();
    const next = prior.catch(() => undefined).then(async () => {
      const current = draft.current;
      const item = current?.items.find(row => row.id === itemId);
      if (!current || !item || current.status !== 'active') return;
      const sequence = changes.current.get(itemId) ?? 0;
      const response = await api.saveAttemptAnswer(id, itemId, {
        expectedRevision: revisions.current.get(itemId) ?? item.revision,
        selectedOptionId: item.selectedOptionId,
        essayText: item.type === 'essay' ? item.essayText : undefined,
        answer: ['multi_mcq', 'true_false', 'matching'].includes(item.type) ? item.answer : undefined,
        marked: item.marked,
        mutationId: crypto.randomUUID(),
      });
      revisions.current.set(itemId, response.revision);
      const updated = draft.current;
      if (updated) {
        const copy = { ...updated, items: updated.items.map(row => row.id === itemId ? { ...row, revision: response.revision } : row) };
        draft.current = copy; setAttempt(copy);
      }
      setSaving(state => ({ ...state, [itemId]: (changes.current.get(itemId) ?? 0) === sequence ? 'saved' : 'saving' }));
    });
    chains.current.set(itemId, next);
    void next.catch(reason => { setSaving(state => ({ ...state, [itemId]: 'error' })); setError('Jawaban belum tersimpan: ' + reason.message); });
    return next;
  }

  function schedule(itemId: number) {
    const old = timers.current.get(itemId);
    if (old) clearTimeout(old);
    setSaving(state => ({ ...state, [itemId]: 'saving' }));
    timers.current.set(itemId, setTimeout(() => { timers.current.delete(itemId); void queue(itemId); }, 500));
  }

  function change(itemId: number, patch: Partial<AttemptItem>) {
    const current = draft.current;
    if (!current || current.status !== 'active') return;
    const updated = { ...current, items: current.items.map(item => item.id === itemId ? { ...item, ...patch } : item) };
    draft.current = updated; setAttempt(updated);
    changes.current.set(itemId, (changes.current.get(itemId) ?? 0) + 1);
    schedule(itemId);
  }

  async function flush() {
    const retryIds = Object.entries(saving).filter(([, state]) => state === 'error').map(([itemId]) => Number(itemId));
    const pending = new Set([...timers.current.keys(), ...retryIds]);
    pending.forEach(itemId => {
      const timer = timers.current.get(itemId);
      if (timer) clearTimeout(timer);
      timers.current.delete(itemId);
      void queue(itemId);
    });
    await Promise.all([...chains.current.values()]);
    if (Object.values(saving).includes('error') && retryIds.length === 0) throw new Error('Ada jawaban yang gagal disimpan. Coba lagi sebelum mengumpulkan.');
  }

  async function submit() {
    if (!attempt || busy) return;
    setBusy(true); setError('');
    try {
      await flush();
      const result = await api.submitAttempt(id);
      const current = draft.current;
      if (current) { const next = { ...current, status: result.status, submittedAt: result.submittedAt }; draft.current = next; setAttempt(next); }
      setSubmitOpen(false);
    } catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }

  const item = attempt?.items[index];
  const answered = attempt?.items.filter(isAnswered).length ?? 0;
  const unanswered = (attempt?.items.length ?? 0) - answered;
  const progress = attempt?.items.length ? Math.round((index + 1) / attempt.items.length * 100) : 0;
  const saveLabel = item ? saving[item.id] === 'saving' ? 'Menyimpan jawaban' : saving[item.id] === 'saved' ? 'Jawaban tersimpan' : saving[item.id] === 'error' ? 'Gagal menyimpan' : 'Jawaban tersimpan' : '';
  const selectQuestion = (position: number) => { setIndex(position); setPaletteOpen(false); };
  const finish = () => { setError(''); setSubmitOpen(true); };

  if (loading) return <main className="flex min-h-dvh items-center justify-center bg-[#F3F8F9]" aria-label="Memuat ujian"><div role="status" aria-label="Memuat ujian"><BarWave /></div></main>;
  if (!attempt) return <main className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-[#F3F8F9] p-5"><p role="alert" className="mx-auto max-w-xl rounded-xl border border-red-200 bg-white p-5 text-sm text-red-800">{error || 'Ujian tidak dapat dimuat.'}</p><Button type="button" variant="outline" onClick={() => { setError(''); setLoading(true); setLoadRevision(value => value + 1); }}>Coba lagi</Button></main>;
  if (attempt.status !== 'active') return <main className="flex min-h-dvh items-center justify-center bg-[#F3F8F9] p-5"><section className="w-full max-w-lg rounded-2xl border border-[#D4E2E8] bg-white p-7 text-center shadow-[0_5px_20px_rgba(16,54,68,.07)]"><div className="mx-auto flex size-14 items-center justify-center rounded-full bg-[#E5F3EF] text-[#00666B]"><Check size={28} aria-hidden="true" /></div><h1 className="mt-4 text-2xl font-bold text-[#13294B]">{attempt.status === 'expired' ? 'Waktu ujian habis' : 'Jawaban terkumpul'}</h1><p className="mt-3 text-sm leading-6 text-[#43566E]">Hasil menunggu pemeriksaan dan rilis resmi. Nilai belum tersedia.</p><Link to="/my-exams" className="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-[#075B60] px-5 text-sm font-semibold text-white hover:bg-[#044D52] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B]">Kembali ke ujian saya</Link></section></main>;

  return <main className="min-h-dvh bg-[#F3F8F9] p-2 text-[#13294B] sm:p-3">
    <header className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 rounded-[10px] bg-[#075B60] px-4 py-3 text-white shadow-[0_3px_10px_rgba(16,54,68,.12)] sm:px-5 min-[900px]:min-h-[66px] min-[900px]:py-2">
      <div className="hidden min-w-0 items-center gap-4 min-[900px]:flex">
        <div className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-white/10"><ClipboardCheck size={30} aria-hidden="true" /></div>
        <div className="min-w-0"><h1 className="truncate text-lg font-bold">{attempt.examTitle || 'Simulasi Ujian Kompetensi'}</h1><p className="mt-0.5 text-xs text-white/90">Kerjakan soal dengan teliti dan pilih jawaban yang paling tepat.</p></div>
      </div>
      <h1 className="sr-only min-[900px]:hidden">{attempt.examTitle || 'Simulasi Ujian Kompetensi'}</h1>
      <div className="flex min-w-0 flex-1 items-center justify-around gap-2 min-[900px]:flex-none min-[900px]:justify-end">
        <div className="flex min-w-0 items-center gap-2 min-[900px]:border-l min-[900px]:border-white/50 min-[900px]:pl-6 xl:pl-7">
          <Clock3 size={30} className="shrink-0 min-[900px]:size-8" aria-hidden="true" />
          <div><span className="block text-[10px] text-white/90 sm:text-xs">Sisa Waktu</span><span role="timer" aria-live="off" className={`block text-lg font-bold tabular-nums sm:text-xl ${remaining < 300 ? 'text-[#FFE2AF]' : ''}`}>{clock(remaining)}</span></div>
        </div>
        <div className="h-10 w-px bg-white/50 min-[900px]:mx-2" />
        <div className="flex min-w-0 items-center gap-2 min-[900px]:border-l min-[900px]:border-white/50 min-[900px]:pl-6 xl:pl-7">
          <div><span className="block text-[10px] text-white/90 sm:text-xs">Nomor Soal</span><span className="block whitespace-nowrap text-lg font-bold tabular-nums sm:text-xl">{index + 1} / {attempt.items.length}</span></div>
          <button type="button" aria-label="Daftar soal" onClick={() => setPaletteOpen(true)} className="ml-1 flex size-10 shrink-0 items-center justify-center rounded-lg text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white min-[900px]:hidden"><Grid2X2 size={20} aria-hidden="true" /></button>
        </div>
      </div>
    </header>

    <div className="mx-auto mt-2 flex max-w-[1440px] items-center gap-3 rounded-lg border border-[#DFEAF0] bg-white px-3 py-2 min-[900px]:hidden"><div className="h-2.5 flex-1 overflow-hidden rounded-full bg-[#DEE4EE]"><div className="h-full rounded-full bg-[#08777B] transition-[width]" style={{ width: progress + '%' }} /></div><span className="min-w-8 text-right text-xs font-semibold text-[#253858]">{progress}%</span></div>

    <div className="mx-auto mt-2 grid max-w-[1440px] gap-2 pb-[calc(72px+env(safe-area-inset-bottom))] min-[900px]:mt-3 min-[900px]:min-h-[calc(100dvh-98px)] min-[900px]:grid-cols-[minmax(0,1fr)_276px] min-[900px]:pb-0">
      <section className="flex min-w-0 flex-col rounded-[12px] border border-[#DFEAF0] bg-white p-3 shadow-[0_4px_16px_rgba(16,54,68,.07)] sm:p-5 min-[900px]:p-[18px]" aria-label="Soal ujian">
        <div className="mb-2 flex items-center justify-between gap-3 min-[900px]:hidden">
          <p className="text-xs font-semibold text-[#174B56]">{attempt.subjectName || 'Ujian'} · Paket {attempt.packageCode}</p>
          <span role="status" className="text-right text-[10px] text-[#426579]">{saveLabel}</span>
        </div>
        <div className="mb-3 flex min-h-7 items-center justify-between gap-2 min-[900px]:mb-2">
          <span className="rounded-[8px] bg-[#DCEFF2] px-3 py-1.5 text-xs font-semibold text-[#075D67]">Kategori: {item?.categoryName || attempt.subjectName || 'Ujian'}</span>
          <span role="status" className="hidden text-xs text-[#426579] min-[900px]:inline">{saveLabel}</span>
        </div>
        {error && <p role="alert" className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p>}
        <div className="min-h-0 flex-1 min-[900px]:overflow-y-auto min-[900px]:pr-1">
          <h2 className="flex items-start gap-3 text-lg font-bold leading-snug sm:text-xl"><span>{index + 1}.</span><span className="break-words"><MathText text={item?.body ?? ''} attemptId={id} /></span></h2>
          {item?.assets.map(asset => <div key={asset.id} className="mt-2.5 aspect-[3/1] max-h-[190px] overflow-hidden rounded-[8px] bg-[#EEF5F7] sm:mt-3"><img src={'/api/v1/student/attempts/' + id + '/assets/' + asset.id} alt={asset.original_name || 'Gambar soal'} width="900" height="300" className="h-full w-full object-contain" /></div>)}

          {item?.type === 'essay' ? <label className="mt-4 block text-sm font-medium">Jawaban esai<textarea className="mt-2 min-h-36 w-full rounded-lg border border-[#B6C9D7] bg-white p-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B]" maxLength={20000} value={item.essayText ?? ''} onChange={event => change(item.id, { essayText: event.target.value })} /></label>
            : item?.type === 'matching' ? <MatchingAnswerView item={item} attemptId={id} onChange={answer => change(item.id, { answer })} />
            : item?.type === 'true_false' ? <TrueFalseAnswerView item={item} attemptId={id} onChange={answer => change(item.id, { answer })} />
              : <fieldset className="mt-3 space-y-1.5 sm:mt-4 sm:space-y-2"><legend className="sr-only">{item?.type === 'multi_mcq' ? 'Pilih semua jawaban benar' : 'Pilih jawaban'}</legend>{item?.options.map((option, optionIndex) => <label key={option.id} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-[9px] border border-[#DFE8EF] px-3 py-2 text-sm transition-colors hover:border-[#80B5B8] has-[:checked]:border-[#13858A] has-[:checked]:bg-[#E4F2F3] sm:px-4"><input type={item.type === 'multi_mcq' ? 'checkbox' : 'radio'} name={'answer-' + item.id} checked={item.type === 'multi_mcq' ? Array.isArray(item.answer) && item.answer.includes(option.id) : item.selectedOptionId === option.id} onChange={() => { if (item.type === 'multi_mcq') { const current = Array.isArray(item.answer) ? item.answer : []; const next = current.includes(option.id) ? current.filter(optionId => optionId !== option.id) : [...current, option.id]; change(item.id, { answer: next.length ? next : null }); } else change(item.id, { selectedOptionId: option.id }); }} className="size-[18px] shrink-0 accent-[#00777B]" /><span className="min-w-0 break-words"><b className="mr-1">{String.fromCharCode(65 + optionIndex)}.</b><MathText text={option.body} attemptId={id} /></span></label>)}</fieldset>}

          <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-[#536B7D] min-[900px]:hidden">
            <span className="inline-flex items-center gap-1.5"><span className={`size-2.5 rounded-full ${saving[item?.id ?? 0] === 'error' ? 'bg-red-600' : saving[item?.id ?? 0] === 'saving' ? 'animate-pulse bg-amber-500' : 'bg-[#11A96B]'}`} />{saveLabel}</span>
          </div>
        </div>

        <div className="mt-4 hidden items-center justify-between gap-3 border-t border-[#E4EDF1] pt-3 min-[900px]:flex">
          <Button type="button" variant="outline" disabled={index === 0} onClick={() => setIndex(index - 1)} className="min-h-11 rounded-full border-[#C4D8E1] px-5 text-[#075D67]"><ArrowLeft size={17} aria-hidden="true" />Sebelumnya</Button>
          <Button type="button" variant="outline" onClick={() => item && change(item.id, { marked: !item.marked })} className={`min-h-11 rounded-full border-[#16868A] px-6 ${item?.marked ? 'bg-[#FFF4DE] text-[#7D4C00]' : 'text-[#075D67]'}`}><Flag size={16} aria-hidden="true" />{item?.marked ? 'Hapus Ragu-ragu' : 'Ragu-ragu'}</Button>
          <Button type="button" onClick={() => index >= attempt.items.length - 1 ? finish() : setIndex(index + 1)} className="min-h-11 rounded-full bg-[#08777B] px-6 text-white hover:bg-[#05676B]">{index >= attempt.items.length - 1 ? 'Akhiri Ujian' : 'Selanjutnya'}{index >= attempt.items.length - 1 ? <Check size={17} aria-hidden="true" /> : <ArrowRight size={17} aria-hidden="true" />}</Button>
        </div>
      </section>

      <aside className="hidden min-h-0 flex-col overflow-hidden rounded-[12px] border border-[#DCE8EE] bg-white shadow-[0_4px_16px_rgba(16,54,68,.06)] min-[900px]:flex">
        <div className="flex items-center gap-3 border-b border-[#DCE8EE] bg-[#F7FAFB] px-4 py-3"><Grid2X2 size={22} className="text-[#08777B]" aria-hidden="true" /><h2 className="text-base font-bold">Daftar Soal</h2></div>
        <div className="border-b border-[#E5EDF1] px-3 py-3"><PaletteLegend /></div>
        <div className="p-3"><Palette attempt={attempt} index={index} onSelect={setIndex} /></div>
        <section className="mx-3 mb-3 rounded-[10px] bg-[#EAF4F6] p-3.5 text-sm">
          <h3 className="flex items-center gap-2 font-semibold"><Info size={19} className="fill-[#08777B] text-white" aria-hidden="true" />Petunjuk</h3>
          <ul className="mt-2 list-disc space-y-1.5 pl-5 text-xs leading-[1.45] text-[#243957]"><li>Pilih jawaban yang paling tepat.</li><li>Anda dapat menandai soal dengan status Ragu-ragu.</li><li>Klik nomor soal untuk langsung berpindah ke soal tersebut.</li></ul>
          <p className="mt-2 border-t border-[#D2E5E9] pt-2 text-[10px] leading-4 text-[#486174]">Perpindahan tab dan fokus dicatat untuk ditinjau pengawas, bukan penentu pelanggaran otomatis.</p>
        </section>
        <div className="mt-auto border-t border-[#E5EDF1] p-3"><Button type="button" disabled={busy} onClick={finish} className="min-h-11 w-full rounded-full bg-[#08777B] text-white hover:bg-[#05676B]">Akhiri Ujian</Button><p className="mt-2 text-center text-[10px] text-[#536B7D]">{answered} dijawab · {unanswered} belum dijawab</p></div>
      </aside>
    </div>

    <div className="fixed inset-x-0 bottom-0 z-30 border-t border-[#D8E5EA] bg-white/95 px-3 pt-2 pb-[calc(8px+env(safe-area-inset-bottom))] backdrop-blur min-[900px]:hidden">
      <div className="mx-auto flex max-w-lg items-center justify-between gap-2">
        <Button type="button" variant="outline" disabled={index === 0} onClick={() => setIndex(index - 1)} aria-label="Sebelumnya" className="min-h-11 flex-1 rounded-lg border-[#C4D8E1] px-2 text-xs text-[#075D67]"><ArrowLeft size={16} aria-hidden="true" />Sebelumnya</Button>
        <Button type="button" variant="outline" onClick={() => item && change(item.id, { marked: !item.marked })} className={`min-h-11 flex-1 rounded-lg border-[#16868A] px-2 text-xs ${item?.marked ? 'bg-[#FFF4DE] text-[#7D4C00]' : 'text-[#075D67]'}`}><Flag size={15} aria-hidden="true" />{item?.marked ? 'Ditandai' : 'Ragu-ragu'}</Button>
        <Button type="button" onClick={() => index >= attempt.items.length - 1 ? finish() : setIndex(index + 1)} className="min-h-11 flex-1 rounded-lg bg-[#08777B] px-2 text-xs text-white hover:bg-[#05676B]">{index >= attempt.items.length - 1 ? 'Akhiri' : 'Selanjutnya'}{index >= attempt.items.length - 1 ? <Check size={15} aria-hidden="true" /> : <ArrowRight size={15} aria-hidden="true" />}</Button>
      </div>
    </div>

    <Sheet open={paletteOpen} onOpenChange={setPaletteOpen}>
      <SheetContent side="bottom" showCloseButton={false} className="max-h-[84dvh] overflow-y-auto rounded-t-[20px] border-[#D5E3E8] bg-white p-4 sm:p-5 min-[900px]:hidden">
        <div className="mx-auto mb-3 h-1 w-10 rounded-full bg-[#AEBBC5]" />
        <SheetHeader className="flex flex-row items-center justify-between p-0"><SheetTitle className="flex items-center gap-2 text-lg text-[#13294B]"><Grid2X2 size={21} className="text-[#08777B]" aria-hidden="true" />Daftar Soal</SheetTitle><button type="button" aria-label="Tutup daftar soal" onClick={() => setPaletteOpen(false)} className="flex size-11 items-center justify-center rounded-lg text-[#324D5D] hover:bg-[#EDF5F6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B]"><X size={22} aria-hidden="true" /></button></SheetHeader>
        <div className="mt-3 border-y border-[#E5EDF1] py-3"><PaletteLegend /></div>
        <div className="py-3"><Palette attempt={attempt} index={index} onSelect={selectQuestion} /></div>
        <section className="rounded-[10px] bg-[#EAF4F6] p-3.5 text-sm"><h3 className="flex items-center gap-2 font-semibold"><Info size={19} className="fill-[#08777B] text-white" aria-hidden="true" />Petunjuk</h3><ul className="mt-2 list-disc space-y-1 pl-5 text-xs leading-[1.4] text-[#243957]"><li>Pilih nomor untuk langsung berpindah ke soal.</li><li>Titik hijau berarti sudah dijawab, oranye berarti Ragu-ragu.</li></ul><p className="mt-2 border-t border-[#D2E5E9] pt-2 text-[10px] leading-4 text-[#486174]">Perpindahan tab dan fokus dicatat untuk ditinjau pengawas, bukan penentu pelanggaran otomatis.</p></section>
        <Button type="button" disabled={busy} onClick={() => { setPaletteOpen(false); finish(); }} className="mt-3 min-h-11 w-full rounded-lg border border-[#08777B] bg-white text-[#075D67] hover:bg-[#EDF7F7]">Akhiri Ujian</Button>
      </SheetContent>
    </Sheet>

    <Dialog.Root open={submitOpen} onOpenChange={open => { if (!busy) setSubmitOpen(open); }}>
      <Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-50 bg-[#0C1725]/60" />
        <Dialog.Content aria-describedby="attempt-submit-description" className="fixed left-1/2 top-1/2 z-50 w-[min(420px,calc(100vw-32px))] -translate-x-1/2 -translate-y-1/2 rounded-[18px] border border-[#E0E8ED] bg-white p-6 text-center shadow-[0_14px_44px_rgba(8,34,44,.25)] focus:outline-none">
          <div className="mx-auto flex size-12 items-center justify-center rounded-full border-[3px] border-[#08777B] text-[#08777B]"><span className="text-3xl font-medium leading-none">!</span></div>
          <Dialog.Title className="mt-3 text-xl font-bold text-[#13294B]">Akhiri Ujian?</Dialog.Title>
          <Dialog.Description id="attempt-submit-description" className="mt-2 text-sm leading-5 text-[#172740]">Apakah Anda yakin ingin mengakhiri ujian ini? Pastikan semua jawaban sudah disimpan.</Dialog.Description>
          <p className="mt-2 text-xs font-medium text-[#536B7D]">{unanswered} soal belum dijawab</p>
          {error && <p role="alert" className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-left text-xs text-red-800">{error}</p>}
          <div className="mt-4 grid grid-cols-2 gap-2.5"><Dialog.Close asChild><Button type="button" variant="outline" disabled={busy} className="min-h-11 rounded-[10px] border-[#08777B] text-[#075D67]">Batal</Button></Dialog.Close><Button type="button" disabled={busy} onClick={() => void submit()} className="min-h-11 rounded-[10px] bg-[#075B60] text-white hover:bg-[#044D52]">{busy ? 'Menyimpan…' : 'Ya, Akhiri'}</Button></div>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>

    <span className="sr-only" aria-live="polite">{progress}% soal dijawab</span>
  </main>;
}
