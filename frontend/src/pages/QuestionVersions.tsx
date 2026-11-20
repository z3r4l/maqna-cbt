import { lazy, Suspense, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, type QuestionVersion } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import MathText from '@/components/MathText';
const RichQuestionEditor = lazy(() => import('@/components/RichQuestionEditor').then(module => ({ default: module.RichQuestionEditor })));

export function QuestionVersions() {
  const [rows, setRows] = useState<QuestionVersion[]>([]);
  const [error, setError] = useState('');
  useEffect(() => { api.questionVersions().then(setRows).catch(reason => setError(reason.message)); }, []);
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Revisi soal</h1><p className="mt-1 text-sm text-[#536765]">Riwayat draf, pengajuan, dan keputusan. Soal pada ujian yang telah terbit tetap memakai salinan semula.</p></div>
    {error && <p role="alert" className="text-red-700">{error}</p>}
    {rows.length === 0 && <Card className="p-5 text-sm">Belum ada revisi soal.</Card>}
    {rows.map(row => <Card key={row.id} className="p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs uppercase text-[#536765]">Soal #{row.question_id} Â· Versi {row.version_no} Â· {row.status}</p><div className="mt-2"><MathText text={row.current_body ?? ''} /></div>{row.review_note && <p className="mt-2 text-sm">Catatan peninjau: {row.review_note}</p>}</div><Link className="rounded-lg border border-[#B6C9C5] px-3 py-2 text-sm" to={'/question-versions/' + row.id}>Buka revisi</Link></div></Card>)}
  </div>;
}

export function QuestionVersionEditor() {
  const id = Number(useParams().id);
  const [row, setRow] = useState<QuestionVersion | null>(null);
  const [form, setForm] = useState<QuestionVersion['snapshot'] | null>(null);
  const [note, setNote] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  async function refresh() { const updated = await api.questionVersion(id); setRow(updated); setForm(updated.snapshot); }
  useEffect(() => { refresh().catch(reason => setError(reason.message)); }, [id]);
  async function action(operation: () => Promise<unknown>) { setBusy(true); setError(''); try { await operation(); await refresh(); } catch (reason) { setError((reason as Error).message); } finally { setBusy(false); } }
  const editable = row?.status === 'draft' || row?.status === 'rejected';
  return <div className="space-y-5"><div><Link className="text-sm text-[#155451] underline" to="/question-versions">â† Revisi soal</Link><h1 className="mt-3 text-2xl font-bold">{row ? `Revisi versi ${row.version_no}` : 'Revisi soal'}</h1>{row && <p className="mt-1 text-sm text-[#536765]">Status: {row.status}</p>}</div>
    {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-red-700">{error}</p>}
    {row?.review_note && <p className="rounded-lg bg-amber-50 p-3 text-sm">Catatan peninjau: {row.review_note}</p>}
    {form && <Card className="space-y-4 p-5"><label className="block text-sm">Pertanyaan</label><Suspense fallback={<div className="min-h-28" />}><RichQuestionEditor disabled={!editable} value={form.body} onChange={body => setForm({ ...form, body })} /></Suspense>
      <label className="block text-sm">Jenis<select disabled={!editable} className="mt-1 block min-h-11 w-full rounded-lg border px-3" value={form.type} onChange={event => setForm({ ...form, type: event.target.value as QuestionVersion['snapshot']['type'], options: event.target.value === 'essay' ? [] : [{ body: '', isCorrect: true }, { body: '', isCorrect: false }] })}><option value="mcq">Pilihan ganda</option><option value="multi_mcq">Pilihan jamak</option><option value="true_false">Benar atau salah</option><option value="matching">Menjodohkan</option><option value="essay">Esai</option></select></label>
      {form.type === 'matching' && <section className="space-y-2"><div className="flex items-center justify-between"><h2 className="font-medium">Pilihan kanan</h2><Button type="button" variant="outline" disabled={!editable || (form.matchingChoices ?? []).length >= 12} onClick={() => setForm({ ...form, matchingChoices: [...(form.matchingChoices ?? []), { body: '' }] })}>Tambah pilihan</Button></div>{(form.matchingChoices ?? []).map((choice, index) => <Suspense key={index} fallback={<div className="min-h-24" />}><RichQuestionEditor disabled={!editable} value={choice.body} onChange={body => setForm({ ...form, matchingChoices: (form.matchingChoices ?? []).map((item, at) => at === index ? { body } : item) })} ariaLabel={'Pilihan kanan ' + (index + 1)} compact /></Suspense>)}</section>}
      {form.type !== 'essay' && <fieldset className="space-y-2"><legend className="text-sm font-medium">{form.type === 'matching' ? 'Pernyataan kiri dan kunci' : 'Pilihan dan kunci'}</legend>{form.options.map((option, index) => <div key={index} className="flex flex-wrap gap-2">{form.type !== 'matching' && <input type={form.type === 'mcq' ? 'radio' : 'checkbox'} name={form.type === 'mcq' ? 'correct-option' : undefined} disabled={!editable} checked={option.isCorrect} onChange={event => setForm({ ...form, options: form.options.map((value, position) => ({ ...value, isCorrect: form.type === 'mcq' ? position === index : position === index ? event.target.checked : value.isCorrect })) })} aria-label={'Kunci pilihan ' + (index + 1)} />}<Suspense fallback={<div className="min-h-24 flex-1" />}><RichQuestionEditor disabled={!editable} value={option.body} onChange={body => setForm({ ...form, options: form.options.map((value, position) => position === index ? { ...value, body } : value) })} ariaLabel={'Pernyataan atau pilihan ' + (index + 1)} compact /></Suspense>{form.type === 'matching' && <select className="min-h-10 rounded-md border px-2" disabled={!editable} value={option.matchChoiceIndex ?? ''} onChange={event => setForm({ ...form, options: form.options.map((value, position) => position === index ? { ...value, matchChoiceIndex: event.target.value === '' ? null : Number(event.target.value) } : value) })}><option value="">Pilih pasangan</option>{(form.matchingChoices ?? []).map((_, target) => <option key={target} value={target}>Pilihan {target + 1}</option>)}</select>}<Button type="button" variant="outline" disabled={!editable || form.options.length <= 2} onClick={() => setForm({ ...form, options: form.options.filter((_, position) => position !== index) })}>Hapus</Button></div>)}<Button type="button" variant="outline" disabled={!editable || form.options.length >= 12} onClick={() => setForm({ ...form, options: [...form.options, { body: '', isCorrect: false, matchChoiceIndex: null }] })}>Tambah pilihan</Button></fieldset>}
      <label className="block text-sm">Rubrik<textarea disabled={!editable} className="mt-1 w-full rounded-lg border p-3" value={form.rubric} onChange={event => setForm({ ...form, rubric: event.target.value })} /></label>
      <label className="block text-sm">Pembahasan<textarea disabled={!editable} className="mt-1 w-full rounded-lg border p-3" value={form.explanation} onChange={event => setForm({ ...form, explanation: event.target.value })} /></label>
      <label className="block text-sm">Bobot<Input disabled={!editable} type="number" min="0.01" max="1000" step="0.01" value={form.weight} onChange={event => setForm({ ...form, weight: Number(event.target.value) })} /></label>
      {editable && <div className="flex flex-wrap gap-2"><Button disabled={busy} onClick={() => void action(() => api.editQuestionVersion(id, form))}>Simpan draf</Button><Button disabled={busy} variant="outline" onClick={() => void action(async () => { await api.editQuestionVersion(id, form); await api.submitQuestionVersion(id); })}>Ajukan tinjauan</Button><Button disabled={busy} variant="outline" onClick={() => void action(() => api.archiveQuestionVersion(id))}>Arsipkan draf</Button></div>}
      {row?.status === 'review' && <div className="space-y-3 border-t pt-4"><label className="block text-sm">Catatan keputusan<textarea className="mt-1 w-full rounded-lg border p-3" value={note} onChange={event => setNote(event.target.value)} /></label><div className="flex gap-2"><Button disabled={busy} onClick={() => void action(() => api.decideQuestionVersion(id, true, note))}>Setujui</Button><Button disabled={busy} variant="outline" onClick={() => void action(() => api.decideQuestionVersion(id, false, note))}>Tolak</Button></div><p className="text-xs text-[#536765]">Keputusan hanya dapat dibuat peninjau sekolah yang bukan penulis revisi.</p></div>}
    </Card>}
  </div>;
}

