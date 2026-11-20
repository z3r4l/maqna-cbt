import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import MathText from '@/components/MathText';

type Row = Record<string, unknown>;
const value = (row: Row, key: string) => String(row[key] ?? '');
const formatScore = (score: unknown) => Number(score).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const statusText: Record<string, string> = { not_started: 'Belum dikerjakan', in_progress: 'Sedang dikerjakan', awaiting_release: 'Menunggu rilis hasil', released: 'Hasil dirilis' };

export function StudentResults() {
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState('');
  useEffect(() => { api.resultHistory().then(setRows).catch(reason => setError((reason as Error).message)); }, []);
  return <section className="space-y-5"><div><h1 className="text-3xl font-bold">Hasil saya</h1><p className="mt-2 text-[#536765]">Nilai tampil setelah sekolah merilis hasil ujian.</p></div>
    {error && <p role="alert" className="text-red-700">{error}</p>}
    {rows.length === 0 && !error && <p>Belum ada ujian yang ditugaskan.</p>}
    <div className="grid gap-3">{rows.map(row => <Card key={value(row, 'examId')}><CardContent className="flex flex-wrap items-center justify-between gap-4 p-5"><div><h2 className="font-semibold">{value(row, 'title')}</h2><p className="text-sm text-[#536765]">{value(row, 'subjectName')} Â· {statusText[value(row, 'status')] ?? value(row, 'status')}</p></div><div className="flex items-center gap-4">{row.status === 'released' && <strong className="text-2xl text-[#155451]">{formatScore(row.score)}</strong>}{Boolean(row.attemptId) && row.status !== 'in_progress' && <Button asChild variant="outline"><Link to={'/my-results/' + row.attemptId}>Detail</Link></Button>}</div></CardContent></Card>)}</div>
  </section>;
}

export function StudentResultDetail() {
  const id = Number(useParams().id);
  const [row, setRow] = useState<Row | null>(null);
  const [error, setError] = useState('');
  useEffect(() => { api.studentResult(id).then(setRow).catch(reason => setError((reason as Error).message)); }, [id]);
  return <section className="max-w-xl space-y-5"><Link className="text-[#155451] underline" to="/my-results">â† Hasil saya</Link><h1 className="text-3xl font-bold">Detail hasil</h1>{error && <p role="alert" className="text-red-700">{error}</p>}{row && <Card><CardContent className="space-y-3 p-6">{row.status === 'released' ? <><p className="text-sm text-[#536765]">Nilai akhir</p><p className="text-5xl font-bold text-[#155451]">{formatScore(row.score)}</p><p>{row.passed ? 'Lulus' : 'Belum lulus'} Â· {value(row, 'correctCount')} dari {value(row, 'questionCount')} soal benar</p><p className="text-sm text-[#536765]">Dirilis {value(row, 'releasedAt')}</p>{Boolean(row.reviewAvailable) && <Button asChild variant="outline"><Link to={'/my-results/' + id + '/review'}>Tinjau jawaban</Link></Button>}</> : <p>{statusText[value(row, 'status')] ?? 'Hasil belum tersedia.'}</p>}</CardContent></Card>}</section>;
}

export function AnswerReview() {
  const id = Number(useParams().id);
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState('');
  useEffect(() => { api.answerReview(id).then(setRows).catch(reason => setError((reason as Error).message)); }, [id]);
  return <section className="space-y-5"><Link className="text-[#155451] underline" to={'/my-results/' + id}>â† Detail hasil</Link><h1 className="text-3xl font-bold">Tinjauan jawaban</h1>{error && <p role="alert" className="text-red-700">{error}</p>}{rows.map(row => <Card key={Number(row.order)}><CardContent className="space-y-3 p-5"><h2 className="font-semibold">{value(row, 'order')}.</h2><div><MathText text={value(row, 'body')} /></div>{row.type === 'essay' ? <><p className="whitespace-pre-wrap text-sm">Jawaban: {value(row, 'essayText') || 'Kosong'}</p><p className="text-sm">Poin: {value(row, 'essayPoints')} / {value(row, 'maxPoints')}</p><p className="text-sm">Umpan balik: {value(row, 'essayFeedback') || 'â€”'}</p></> : row.type === 'matching' ? <div className="space-y-2">{((row.options ?? []) as Row[]).map(option => { const picked = (row.answer ?? {}) as Record<string, number>; const choice = ((row.choices ?? []) as Row[]).find(item => Number(item.id) === Number(picked[String(option.id)])); const key = ((row.choices ?? []) as Row[]).find(item => Number(item.id) === Number(option.match_choice_id)); return <div key={Number(option.id)} className="grid gap-2 rounded-lg border p-3 text-sm sm:grid-cols-2"><div><strong>Pernyataan</strong><MathText text={value(option, 'body')} /></div><div><div><strong>Jawaban Anda:</strong> {choice ? <MathText text={value(choice, 'body')} /> : 'Kosong'}</div><div className="mt-2 text-[#155451]"><strong>Kunci:</strong> {key ? <MathText text={value(key, 'body')} /> : '—'}</div></div></div>; })}</div> : <ul className="space-y-1 text-sm">{((row.options ?? []) as Row[]).map(option => <li key={Number(option.id)} className={Number(option.is_correct) === 1 ? 'font-semibold text-[#155451]' : ''}><MathText text={value(option, 'body')} />{Number(option.id) === Number(row.selectedOptionId) ? ' · Jawaban Anda' : ''}{Number(option.is_correct) === 1 ? ' · Kunci' : ''}</li>)}</ul>}{Boolean(row.explanation) && <p className="rounded-lg bg-[#E9F2EF] p-3 text-sm">Pembahasan: {value(row, 'explanation')}</p>}</CardContent></Card>)}</section>;
}

export function ReportList() {
  const [filters, setFilters] = useState<Record<string, string>>({ academicYearId: '', semesterId: '', classGroupId: '', subjectId: '', examId: '' });
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState('');
  const load = () => api.reports(filters).then(page => { setRows(page.rows); setError(''); }).catch(reason => setError((reason as Error).message));
  useEffect(() => { void load(); }, []);
  return <section className="space-y-5"><div><h1 className="text-3xl font-bold">Laporan hasil</h1><p className="mt-2 text-[#536765]">Hasil dapat ditinjau oleh staf sebelum rilis. Siswa baru melihat nilai setelah rilis manual.</p></div>
    <form className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" onSubmit={event => { event.preventDefault(); void load(); }}>{Object.entries({ academicYearId: 'ID tahun ajaran', semesterId: 'ID semester', classGroupId: 'ID rombel', subjectId: 'ID mata pelajaran', examId: 'ID ujian' }).map(([key, label]) => <label key={key} className="text-sm">{label}<Input className="mt-1" type="number" min="1" value={filters[key]} onChange={event => setFilters({ ...filters, [key]: event.target.value })} /></label>)}<Button className="self-end">Terapkan</Button></form>
    {error && <p role="alert" className="text-red-700">{error}</p>}
    <div className="grid gap-3">{rows.map(row => <Card key={value(row, 'id')}><CardContent className="flex flex-wrap items-center justify-between gap-3 p-5"><div><h2 className="font-semibold">{value(row, 'title')}</h2><p className="text-sm text-[#536765]">{value(row, 'subject_name')} Â· {value(row, 'semester_name')}</p></div><Button asChild variant="outline"><Link to={'/reports/' + row.id}>Buka laporan</Link></Button></CardContent></Card>)}</div>
    {rows.length === 0 && !error && <p>Belum ada laporan sesuai filter.</p>}
  </section>;
}

export function ReportDetail() {
  const id = Number(useParams().id);
  const [classGroupId, setClassGroupId] = useState('');
  const [detail, setDetail] = useState<Row | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const load = () => api.report(id, classGroupId).then(row => { setDetail(row); setError(''); }).catch(reason => setError((reason as Error).message));
  useEffect(() => { void load(); }, [id]);
  const exam = (detail?.exam ?? {}) as Row;
  const stats = (detail?.statistics ?? {}) as Row;
  const rows = (detail?.rows ?? []) as Row[];
  async function release() {
    if (!window.confirm('Rilis nilai untuk semua siswa pada ujian ini? Tindakan ini akan membuka hasil bagi siswa.')) return;
    setBusy(true);
    try { await api.releaseResults(id); await load(); } catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  return <section className="space-y-5"><Link className="text-[#155451] underline" to="/reports">â† Semua laporan</Link><div className="flex flex-wrap items-end justify-between gap-4"><div><h1 className="text-3xl font-bold">{value(exam, 'title') || 'Laporan ujian'}</h1><p className="mt-2 text-[#536765]">Lihat cakupan peserta dan nilai sebelum merilis hasil.</p></div><div className="flex flex-wrap gap-2"><Button asChild variant="outline"><Link to={'/reports/' + id + '/essays'}>Nilai esai</Link></Button><Button asChild variant="outline"><a href={'/api/v1/reports/exams/' + id + '/answers.xlsx' + (classGroupId ? '?classGroupId=' + encodeURIComponent(classGroupId) : '')}>Unduh jawaban XLSX</a></Button>{['xlsx', 'pdf'].map(format => <Button key={format} asChild variant="outline"><a href={'/api/v1/reports/exams/' + id + '/export/' + format + (classGroupId ? '?classGroupId=' + encodeURIComponent(classGroupId) : '')}>Unduh {format.toUpperCase()}</a></Button>)}<Button disabled={busy || !detail || Number(stats.graded) === 0 || rows.some(row => row.pending_review)} onClick={() => void release()}>Rilis hasil manual</Button></div></div>
    {error && <p role="alert" className="text-red-700">{error}</p>}
    <form className="flex max-w-sm items-end gap-2" onSubmit={event => { event.preventDefault(); void load(); }}><label className="flex-1 text-sm">Filter ID rombel<Input className="mt-1" type="number" min="1" value={classGroupId} onChange={event => setClassGroupId(event.target.value)} /></label><Button variant="outline">Terapkan</Button></form>
    {detail && <><div className="grid gap-3 sm:grid-cols-4">{[['Ditugaskan', stats.assigned], ['Dinilai', stats.graded], ['Rata-rata', stats.average == null ? 'â€”' : formatScore(stats.average)], ['Lulus', stats.passed]].map(([label, number]) => <Card key={String(label)}><CardContent className="p-5"><p className="text-sm text-[#536765]">{String(label)}</p><p className="mt-1 text-2xl font-bold">{number == null ? 'â€”' : String(number)}</p></CardContent></Card>)}</div><div className="overflow-x-auto rounded-xl border bg-white"><table className="w-full min-w-[650px] text-left text-sm"><thead className="bg-[#E9F2EF]"><tr>{['Siswa', 'Rombel', 'Status', 'Nilai', 'Kelulusan', 'Rilis'].map(label => <th className="p-3" key={label}>{label}</th>)}</tr></thead><tbody>{rows.map((row, index) => <tr className="border-t" key={value(row, 'student_id') + '-' + index}><td className="p-3">{value(row, 'display_name')}<span className="block text-xs text-[#536765]">{value(row, 'username')}</span></td><td className="p-3">{value(row, 'class_name')}</td><td className="p-3">{Boolean(row.pending_review) ? 'Perlu penilaian' : value(row, 'attempt_status') || 'Belum mulai'}</td><td className="p-3">{row.score == null ? 'â€”' : formatScore(row.score)}</td><td className="p-3">{row.passed == null ? 'â€”' : row.passed ? 'Lulus' : 'Belum lulus'}</td><td className="p-3">{row.released_at ? 'Sudah dirilis' : 'Belum dirilis'}</td></tr>)}</tbody></table></div></>}
  </section>;
}

export function EssayReview() {
  const id = Number(useParams().id);
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState<number | null>(null);
  const load = () => api.essays(id).then(setRows).catch(reason => setError((reason as Error).message));
  useEffect(() => { void load(); }, [id]);
  async function score(row: Row, points: number, feedback: string) {
    setBusy(Number(row.id)); setError('');
    try { await api.scoreEssay(id, Number(row.id), points, feedback); await load(); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }
  return <section className="space-y-5"><Link className="text-[#155451] underline" to={'/reports/' + id}>â† Laporan ujian</Link><div><h1 className="text-3xl font-bold">Penilaian esai</h1><p className="mt-2 text-[#536765]">Nilai setiap jawaban dengan rubrik sebelum hasil dapat dirilis.</p></div>{error && <p role="alert" className="text-red-700">{error}</p>}{rows.length === 0 && !error && <p>Tidak ada jawaban esai yang perlu diperiksa.</p>}<div className="grid gap-4">{rows.map(row => <EssayCard key={Number(row.id)} row={row} busy={busy === Number(row.id)} onScore={score} />)}</div></section>;
}

function EssayCard({ row, busy, onScore }: { row: Row; busy: boolean; onScore: (row: Row, points: number, feedback: string) => Promise<void> }) {
  const [points, setPoints] = useState(String(row.points ?? ''));
  const [feedback, setFeedback] = useState(value(row, 'feedback'));
  useEffect(() => { setPoints(String(row.points ?? '')); setFeedback(value(row, 'feedback')); }, [row.points, row.feedback]);
  return <Card><CardContent className="space-y-3 p-5"><p className="text-sm font-semibold text-[#536765]">{value(row, 'studentName')} Â· Percobaan #{value(row, 'attemptId')}</p><h2 className="font-semibold whitespace-pre-wrap">{value(row, 'question')}</h2><div className="rounded-lg bg-[#E9F2EF] p-3 text-sm"><strong>Rubrik</strong><p className="mt-1 whitespace-pre-wrap">{value(row, 'rubric')}</p></div><div className="rounded-lg border p-3 text-sm"><strong>Jawaban siswa</strong><p className="mt-1 whitespace-pre-wrap">{value(row, 'answer') || 'Tidak dijawab'}</p></div><form className="grid gap-3 sm:grid-cols-[120px_1fr_auto] sm:items-end" onSubmit={event => { event.preventDefault(); void onScore(row, Number(points), feedback); }}><label className="text-sm">Poin (maks. {value(row, 'maxPoints')})<Input className="mt-1" type="number" min="0" max={Number(row.maxPoints)} step="0.01" required value={points} onChange={event => setPoints(event.target.value)} /></label><label className="text-sm">Umpan balik<textarea className="mt-1 min-h-11 w-full rounded-lg border p-2" maxLength={5000} value={feedback} onChange={event => setFeedback(event.target.value)} /></label><Button disabled={busy}>Simpan nilai</Button></form></CardContent></Card>;
}


