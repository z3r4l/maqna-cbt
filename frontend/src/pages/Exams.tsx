import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DateTimePicker } from '@/components/DateTimePicker';
import { ParticipantPlacement } from '@/components/ParticipantPlacement';

import { ExamMinutesEditor } from '@/components/ExamMinutesEditor';
import { ExamStudentPreview } from '@/components/ExamStudentPreview';
import { ExamScheduleDialog } from '@/components/ExamScheduleDialog';

type Row = Record<string, unknown>;
type Masters = { subjects: Row[]; semesters: Row[]; classes: Row[]; examTypes: Row[] };
function value(row: Row, key: string) { return String(row[key] ?? ''); }
function localDate(utc: string) {
  if (!utc) return '';
  const date = new Date(utc.replace(' ', 'T') + 'Z');
  const shifted = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return shifted.toISOString().slice(0, 16);
}
function formatDate(utc: string) { return new Date(utc.replace(' ', 'T') + 'Z').toLocaleString('id-ID'); }

export function ExamEditor() {
  const navigate = useNavigate();
  const { id: idParam } = useParams();
  const id = idParam && idParam !== 'new' ? Number(idParam) : null;
  const [masters, setMasters] = useState<Masters>({ subjects: [], semesters: [], classes: [], examTypes: [] });
  const [questions, setQuestions] = useState<Row[]>([]);
  const [title, setTitle] = useState('');
  const [instructions, setInstructions] = useState('');
  const [subjectId, setSubjectId] = useState('');
  const [semesterId, setSemesterId] = useState('');
  const [examTypeId, setExamTypeId] = useState('');
  const [startAt, setStartAt] = useState('');
  const [endAt, setEndAt] = useState('');
  const [durationMinutes, setDurationMinutes] = useState('60');
  const [passingGrade, setPassingGrade] = useState('70');
  const [maxAttempts, setMaxAttempts] = useState('1');
  const [resultPolicy, setResultPolicy] = useState('first');
  const [shuffleQuestions, setShuffleQuestions] = useState(false);
  const [shuffleOptions, setShuffleOptions] = useState(false);
  const [accessCode, setAccessCode] = useState('');
  const [requiresAccessCode, setRequiresAccessCode] = useState(false);
  const [codeTouched, setCodeTouched] = useState(false);
  const [reviewEnabled, setReviewEnabled] = useState(false);
  const [packageMode, setPackageMode] = useState('single');
  const [requireAllAnswers, setRequireAllAnswers] = useState(false);
  const [classIds, setClassIds] = useState<number[]>([]);
  const [questionIds, setQuestionIds] = useState<number[]>([]);
  const [status, setStatus] = useState('draft');
  const [roster, setRoster] = useState<Row[]>([]);
  const [questionPreview, setQuestionPreview] = useState<Awaited<ReturnType<typeof api.examQuestionPreview>> | null>(null);
  const [examDetail, setExamDetail] = useState<Row | null>(null);
  const [scheduleOpen, setScheduleOpen] = useState(false);
  const bankExam = Boolean(examDetail && examDetail.source_bank_id);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  useEffect(() => { api.examMasters().then(setMasters).catch(reason => setError(reason.message)); }, []);
  useEffect(() => { if (subjectId && !bankExam) api.questions(1, '', subjectId, 100).then(result => setQuestions(result.rows.filter(row => row.status === 'published'))).catch(reason => setError(reason.message)); else setQuestions([]); }, [subjectId, bankExam]);
  useEffect(() => { if (!id) return; api.exam(id).then(row => {
    setExamDetail(row);
    setTitle(value(row, 'title')); setInstructions(value(row, 'instructions')); setSubjectId(value(row, 'subject_id')); setSemesterId(value(row, 'semester_id')); setExamTypeId(value(row, 'exam_type_id'));
    setStartAt(localDate(value(row, 'start_at'))); setEndAt(localDate(value(row, 'end_at'))); setDurationMinutes(value(row, 'duration_minutes')); setPassingGrade(value(row, 'passing_grade'));
    setMaxAttempts(value(row, 'max_attempts')); setResultPolicy(value(row, 'result_policy')); setShuffleQuestions(Number(row.shuffle_questions) === 1); setShuffleOptions(Number(row.shuffle_options) === 1);
    setClassIds((row.classIds as number[]) ?? []); setQuestionIds((row.questionIds as number[]) ?? []); setStatus(value(row, 'status')); setRequiresAccessCode(Boolean(row.requiresAccessCode)); setReviewEnabled(Number(row.review_enabled) === 1); setPackageMode(value(row, 'package_mode') || 'single'); setRequireAllAnswers(Number(row.require_all_answers) === 1);
  }).catch(reason => setError(reason.message)); }, [id]);
  const semester = masters.semesters.find(row => Number(row.id) === Number(semesterId));
  const classes = masters.classes.filter(row => Number(row.academic_year_id) === Number(semester?.academic_year_id));
  function toggle(current: number[], id: number, setter: (value: number[]) => void) { setter(current.includes(id) ? current.filter(item => item !== id) : [...current, id]); }
  function payload() {
    return { title, instructions, subjectId: Number(subjectId), semesterId: Number(semesterId), examTypeId: Number(examTypeId), startAt: new Date(startAt).toISOString(), endAt: new Date(endAt).toISOString(), durationMinutes: Number(durationMinutes), passingGrade: Number(passingGrade), maxAttempts: Number(maxAttempts), resultPolicy, shuffleQuestions, shuffleOptions, reviewEnabled, packageMode, requireAllAnswers, classIds, questionIds, ...(codeTouched ? { accessCode } : {}) };
  }
  async function save(event: React.FormEvent) {
      event.preventDefault(); setError(''); setNotice('');
      if (!startAt || !endAt) { setError('Pilih tanggal dan jam mulai serta selesai.'); return; }
      setBusy(true);
    try { const row = id ? await api.updateExam(id, payload()) : await api.createExam(payload()); setNotice('Draf ujian disimpan.'); if (!id) navigate('/exams/' + row.id, { replace: true }); }
    catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  async function previewRoster() { if (!id) return; try { setRoster(await api.examRoster(id)); setError(''); } catch (reason) { setError((reason as Error).message); } }
  async function previewQuestions() { if (!id) return; try { setQuestionPreview(await api.examQuestionPreview(id)); setError(''); } catch (reason) { setError((reason as Error).message); } }
  async function publish() {
    if (!id) return; setBusy(true); setError(''); setNotice('');
    try { await api.publishExam(id); setStatus('published'); setNotice('Ujian diterbitkan. Daftar peserta telah dibekukan.'); }
    catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  const locked = status !== 'draft';
  return <div className="space-y-5"><div><Link className="text-sm text-[#155451] underline" to="/exams">← Ujian</Link><h1 className="mt-3 text-2xl font-bold">{id ? 'Pengaturan ujian' : 'Buat ujian'}</h1><p className="mt-1 text-sm text-[#536765]">Semua waktu disimpan sebagai UTC dan ditampilkan menurut perangkat Anda. Hasil siswa dirilis manual.</p></div>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_330px]"><Card className="min-w-0 p-5"><form onSubmit={save} className="space-y-5"><fieldset disabled={busy || locked || bankExam} className="space-y-4"><label className="block text-sm">Judul ujian<Input required minLength={3} maxLength={160} className="mt-1" value={title} onChange={event => setTitle(event.target.value)} /></label><label className="block text-sm">Instruksi<textarea value={instructions} onChange={event => setInstructions(event.target.value)} className="mt-1 block w-full rounded-md border border-[#B6C9C5] p-3" rows={3} /></label><div className="grid gap-4 md:grid-cols-2"><label className="block text-sm">Mata pelajaran<select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={subjectId} onChange={event => { setSubjectId(event.target.value); setQuestionIds([]); }}><option value="">Pilih mapel</option>{masters.subjects.map(row => <option key={Number(row.id)} value={Number(row.id)}>{value(row, 'name')}</option>)}</select></label><label className="block text-sm">Semester<select aria-label="Semester" required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={semesterId} onChange={event => { setSemesterId(event.target.value); setClassIds([]); }}><option value="">Pilih semester</option>{masters.semesters.map(row => <option key={Number(row.id)} value={Number(row.id)}>{value(row, 'name')}</option>)}</select></label></div>
      <label className="block text-sm">Jenis ujian<select aria-label="Jenis ujian" className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={examTypeId} onChange={event => setExamTypeId(event.target.value)}><option value="">Tanpa jenis</option>{masters.examTypes.map(row => <option key={Number(row.id)} value={Number(row.id)}>{value(row, 'name')}</option>)}</select></label><div className="grid gap-4 md:grid-cols-2"><DateTimePicker label="Mulai" value={startAt} onChange={setStartAt} /><DateTimePicker label="Selesai" value={endAt} onChange={setEndAt} /></div><div className="grid gap-4 md:grid-cols-3"><label className="block text-sm">Durasi (menit)<Input required type="number" min="1" max="720" className="mt-1" value={durationMinutes} onChange={event => setDurationMinutes(event.target.value)} /></label><label className="block text-sm">Ambang lulus<Input required type="number" min="0" max="100" className="mt-1" value={passingGrade} onChange={event => setPassingGrade(event.target.value)} /></label><label className="block text-sm">Maks. percobaan<Input required type="number" min="1" max="5" className="mt-1" value={maxAttempts} onChange={event => { setMaxAttempts(event.target.value); if (event.target.value === '1') setResultPolicy('first'); }} /></label></div><label className="block text-sm">Hasil resmi<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={resultPolicy} onChange={event => setResultPolicy(event.target.value)}><option value="first">Percobaan pertama</option>{Number(maxAttempts) > 1 && <option value="highest">Nilai tertinggi</option>}</select></label>
      <label className="block text-sm">Kode akses ujian (opsional)<Input className="mt-1" type="text" autoComplete="off" minLength={6} maxLength={32} value={accessCode} onChange={event => { setAccessCode(event.target.value); setCodeTouched(true); }} placeholder={requiresAccessCode ? "Kode sudah diatur; kosongkan lalu simpan untuk menghapus" : "Kosong berarti tanpa kode"} /></label><label className="block text-sm">Paket soal<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={packageMode} onChange={event => { setPackageMode(event.target.value); if (event.target.value === 'ab') setShuffleQuestions(false); }}><option value="single">Satu paket</option><option value="ab">Paket A/B setara</option></select></label><fieldset className="space-y-2"><legend className="font-semibold">Rombel target</legend>{classes.length === 0 && <p className="text-sm text-[#536765]">Pilih semester dahulu.</p>}{classes.map(row => <label key={Number(row.id)} className="flex min-h-11 items-center gap-3 rounded-md border border-[#D7E0DE] px-3 text-sm"><input type="checkbox" checked={classIds.includes(Number(row.id))} onChange={() => toggle(classIds, Number(row.id), setClassIds)} />{value(row, 'name')}</label>)}</fieldset>
{bankExam ? <section className="space-y-3 rounded-lg border border-teal-200 bg-teal-50 p-4"><h2 className="font-semibold">Ringkasan bank soal</h2><dl className="grid gap-2 text-sm sm:grid-cols-2"><div><dt className="text-slate-600">Bank</dt><dd className="font-medium">{value(examDetail!, 'source_bank_name') || 'Bank soal #' + value(examDetail!, 'source_bank_id')}</dd></div><div><dt className="text-slate-600">Status</dt><dd className="font-medium">{status === 'published' ? 'Terbit' : 'Draf'}</dd></div><div><dt className="text-slate-600">Soal ditampilkan</dt><dd className="font-medium">{value(examDetail!, 'display_question_count') || 'Semua soal terbit'}</dd></div><div><dt className="text-slate-600">Pengacakan</dt><dd className="font-medium">{shuffleQuestions ? 'Soal ' : ''}{shuffleQuestions && shuffleOptions ? '? ' : ''}{shuffleOptions ? 'Opsi' : ''}{!shuffleQuestions && !shuffleOptions ? 'Tidak ada' : ''}</dd></div></dl><div className="flex flex-wrap items-center gap-3"><Button type="button" variant="outline" onClick={() => setScheduleOpen(true)}>Ubah jadwal dan bank</Button><p className="text-xs text-slate-600">Pemilihan soal dikelola oleh bank sumber.</p></div></section> :       <fieldset className="space-y-2"><legend className="font-semibold">Soal</legend>{questions.length === 0 && <p className="text-sm text-[#536765]">Pilih mapel dan siapkan soal terbit.</p>}{questions.map(row => <label key={Number(row.id)} className="flex min-h-11 items-start gap-3 rounded-md border border-[#D7E0DE] px-3 py-3 text-sm"><input type="checkbox" className="mt-1" checked={questionIds.includes(Number(row.id))} onChange={() => toggle(questionIds, Number(row.id), setQuestionIds)} /><span className="min-w-0 break-words">{value(row, 'body')} <small className="text-[#536765]">({value(row, 'status')})</small></span></label>)}</fieldset>}<div className="grid gap-2 text-sm"><label className="flex items-center gap-2"><input type="checkbox" checked={shuffleQuestions} disabled={packageMode === 'ab'} onChange={event => setShuffleQuestions(event.target.checked)} />Acak urutan soal</label><label className="flex items-center gap-2"><input type="checkbox" checked={shuffleOptions} onChange={event => setShuffleOptions(event.target.checked)} />Acak urutan opsi</label><label className="flex items-center gap-2"><input type="checkbox" checked={requireAllAnswers} onChange={event => setRequireAllAnswers(event.target.checked)} />Wajib jawab semua soal sebelum kumpulkan</label><label className="flex items-center gap-2"><input type="checkbox" checked={reviewEnabled} onChange={event => setReviewEnabled(event.target.checked)} />Izinkan siswa meninjau jawaban setelah hasil dirilis</label></div></fieldset><Button disabled={busy || locked || bankExam}>Simpan draf</Button></form></Card>
    <Card className="p-5"><h2 className="text-lg font-semibold">Peserta dan penerbitan</h2><p className="mt-2 text-sm text-[#536765]">Simpan draf untuk melihat peserta sesuai rombel saat ini. Saat terbit, daftar peserta dan isi soal dibekukan.</p>{id && <div className="mt-4 space-y-3"><div className="flex flex-wrap gap-2"><Button type="button" variant="outline" onClick={() => void previewRoster()}>Pratinjau peserta</Button><Button type="button" variant="outline" onClick={() => void previewQuestions()}>Pratinjau tampilan siswa</Button></div>{roster.length > 0 && <div><p className="text-sm font-semibold">{roster.length} siswa</p><ul className="mt-2 max-h-56 overflow-auto text-sm">{roster.map(row => <li key={Number(row.student_id)} className="border-b py-2">{value(row, 'display_name')} ? {value(row, 'class_name')}</li>)}</ul></div>}{!locked && <Button type="button" disabled={busy} onClick={() => void publish()}>Terbitkan ujian</Button>}{locked && <p className="text-sm font-semibold text-[#155451]">Ujian sudah terbit.</p>}</div>}</Card></div>
    {bankExam && id && <ExamScheduleDialog open={scheduleOpen} examId={id} onOpenChange={setScheduleOpen} onSaved={() => { void api.exam(id).then(setExamDetail); setNotice('Perubahan jadwal tersimpan.'); }} />}
    {questionPreview && <ExamStudentPreview examId={id!} initial={questionPreview} onClose={() => setQuestionPreview(null)} />}
    {id && status === 'published' && semester && <ParticipantPlacement examId={id} academicYearId={Number(semester.academic_year_id)} />}
    {id && status === 'published' && <ExamMinutesEditor examId={id} />}
  </div>;
}

export function StudentExams() {
  const navigate = useNavigate();
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState<number | null>(null);
  const [accessCodes, setAccessCodes] = useState<Record<number, string>>({});
  useEffect(() => { api.studentExams().then(setRows).catch(reason => setError(reason.message)); }, []);
  async function start(id: number) {
    setBusy(id); setError('');
    try { const attempt = await api.startAttempt(id, accessCodes[id] ?? ''); navigate('/attempts/' + attempt.id); }
    catch (reason) { setError((reason as Error).message); } finally { setBusy(null); }
  }
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Ujian saya</h1><p className="mt-1 text-sm text-[#536765]">Jadwal dan petunjuk ujian yang ditugaskan kepada Anda.</p></div>{error && <p role="alert" className="text-red-700">{error}</p>}<div className="grid gap-4">{rows.length === 0 && <Card className="p-5 text-sm text-[#536765]">Belum ada ujian yang ditugaskan.</Card>}{rows.map(row => <Card key={Number(row.id)} className="p-5"><p className="text-xs font-semibold uppercase text-[#536765]">{value(row, 'subject_name')}</p><h2 className="mt-2 text-lg font-semibold">{value(row, 'title')}</h2><p className="mt-2 whitespace-pre-wrap text-sm">{value(row, 'instructions')}</p><dl className="mt-4 grid gap-2 text-sm"><div><dt className="text-[#536765]">Mulai</dt><dd>{formatDate(value(row, 'start_at'))}</dd></div><div><dt className="text-[#536765]">Selesai</dt><dd>{formatDate(value(row, 'end_at'))}</dd></div><div><dt className="text-[#536765]">Durasi</dt><dd>{value(row, 'duration_minutes')} menit</dd></div></dl>{Number(row.requires_access_code) === 1 && <label className="mt-4 block text-sm">Kode akses<Input className="mt-1" type="text" autoComplete="off" value={accessCodes[Number(row.id)] ?? ""} onChange={event => setAccessCodes({ ...accessCodes, [Number(row.id)]: event.target.value })} /></label>}<Button type="button" className="mt-5" disabled={busy === Number(row.id)} onClick={() => void start(Number(row.id))}>Mulai atau lanjutkan</Button></Card>)}</div></div>;
}

