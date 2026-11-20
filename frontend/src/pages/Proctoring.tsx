import { useEffect, useState } from 'react';
import { api, type Profile } from '@/lib/api';
import { Card } from '@/components/ui/card';
import MathText from '@/components/MathText';
import { AnswerMatrix } from '@/components/AnswerMatrix';
import { Link } from 'react-router-dom';

type Row = Record<string, unknown>;
const labels: Record<string, string> = { not_started: 'Belum mulai', active: 'Aktif', disconnected: 'Koneksi terputus', expired: 'Waktu habis', submitted: 'Terkumpul' };
function timer(seconds: number | null) { if (seconds === null) return '—'; return `${Math.floor(seconds / 60)}m ${seconds % 60}d`; }

function ExamPicker({ exams, examId, setExamId }: { exams: Row[]; examId: number; setExamId: (id: number) => void }) {
  return <label className="block text-sm">Ujian<select className="mt-1 block min-h-11 w-full max-w-xl rounded-lg border border-[#B6C9C5] bg-white px-3" value={examId} onChange={event => setExamId(Number(event.target.value))}><option value={0}>Pilih ujian</option>{exams.map(exam => <option key={Number(exam.id)} value={Number(exam.id)}>{String(exam.title)}</option>)}</select></label>;
}

export function ProctorDashboard() {
  const [exams, setExams] = useState<Row[]>([]); const [examId, setExamId] = useState(0);
  const [data, setData] = useState<{ serverNow: string; rows: Row[] } | null>(null); const [error, setError] = useState('');
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loginStatus, setLoginStatus] = useState('all');
  const [busy, setBusy] = useState(false);
  useEffect(() => { api.me().then(user => { setProfile(user); return user.roles.includes('proctor') ? api.proctorExams().then(setExams) : api.exams(1).then(result => setExams(result.rows)); }).catch(reason => setError(reason.message)); }, []);
  useEffect(() => { if (!examId) return; let live = true; let first = true; const poll = () => { const silent = !first; first = false; api.proctor(examId, silent, loginStatus).then(result => { if (live) { setData(result); setError(''); } }).catch(reason => { if (live) setError(reason.message); }); }; void poll(); const interval = setInterval(poll, 15000); return () => { live = false; clearInterval(interval); }; }, [examId, loginStatus]);
  async function revoke(studentId: number, sessionId: number) {
    if (!examId) return;
    const reason = window.prompt('Alasan mencabut sesi siswa (minimal 10 karakter):');
    if (reason === null) return;
    if (reason.trim().length < 10) { setError('Alasan minimal 10 karakter.'); return; }
    if (!window.confirm(`Cabut sesi #${sessionId} untuk peserta ini? Jawaban ujian tetap tersimpan.`)) return;
    setBusy(true); setError('');
    try { await api.proctorRevokeSession(examId, studentId, sessionId, reason.trim()); setData(await api.proctor(examId, false, loginStatus)); }
    catch (cause) { setError((cause as Error).message); }
    finally { setBusy(false); }
  }
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Pemantauan ujian</h1><p className="mt-1 text-sm text-[#536765]">Status diperbarui setiap 15 detik. Peristiwa pindah tab hanya sinyal koneksi dan perhatian; perlu pemeriksaan manusia sebelum mengambil tindakan.</p></div><ExamPicker exams={exams} examId={examId} setExamId={setExamId} />{examId > 0 && profile?.roles.some(role => role === 'teacher' || role === 'school_admin' || role === 'superadmin') && <Link className="inline-flex min-h-11 items-center rounded-md border border-[#B6C9C5] px-4 text-sm font-semibold text-[#155451] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]" to={'/reports/' + examId + '/essays'}>Nilai jawaban esai</Link>}<label className="block max-w-xs text-sm">Riwayat login<select className="mt-1 block min-h-11 w-full rounded-lg border border-[#B6C9C5] bg-white px-3" value={loginStatus} onChange={event => setLoginStatus(event.target.value)}><option value="all">Semua</option><option value="yes">Pernah login</option><option value="no">Belum login</option></select></label>{error && <p role="alert" className="text-red-700">{error}</p>}
    {examId > 0 && data && <Card className="overflow-x-auto p-4"><p className="mb-3 text-xs text-[#536765]">Waktu server: {data.serverNow} UTC</p><table className="w-full min-w-[600px] text-left text-sm"><thead><tr className="border-b"><th className="p-2">Siswa</th><th className="p-2">Rombel</th><th className="p-2">Status</th><th className="p-2">Login</th><th className="p-2">Sisa waktu</th><th className="p-2">Aktivitas tab</th><th className="p-2">Sesi</th></tr></thead><tbody>{data.rows.map(row => <tr key={Number(row.student_id)} className="border-b"><td className="p-2">{String(row.display_name)}</td><td className="p-2">{String(row.class_name)}</td><td className="p-2">{labels[String(row.status)] ?? String(row.status)}</td><td className="p-2">{Number(row.has_logged_in) === 1 ? "Pernah login" : "Belum login"}</td><td className="p-2">{timer(row.remainingSeconds === null ? null : Number(row.remainingSeconds))}</td><td className="p-2">{Number(row.eventCount)}</td><td className="p-2">{profile?.roles.some(role => role === "proctor" || role === "school_admin" || role === "superadmin") && ((row.sessions as { id: number; last_seen_at: string }[] | undefined) ?? []).map(session => <button key={session.id} type="button" disabled={busy} className="min-h-11 whitespace-nowrap text-[#155451] underline focus-visible:outline-2" onClick={() => void revoke(Number(row.student_id), session.id)}>Cabut #{session.id}</button>)}</td></tr>)}</tbody></table>{data.rows.length === 0 && <p className="p-3 text-sm">Belum ada siswa yang ditugaskan.</p>}</Card>}
  </div>;
}

export function QuestionAnalytics() {
  const [exams, setExams] = useState<Row[]>([]); const [examId, setExamId] = useState(0);
  const [data, setData] = useState<Awaited<ReturnType<typeof api.questionAnalytics>> | null>(null); const [error, setError] = useState('');
  const [matrix, setMatrix] = useState<Awaited<ReturnType<typeof api.answerMatrix>> | null>(null);
  useEffect(() => { api.exams(1).then(result => setExams(result.rows)).catch(reason => setError(reason.message)); }, []);
  useEffect(() => { if (!examId) { setData(null); return; } api.questionAnalytics(examId).then(result => { setData(result); setError(''); }).catch(reason => setError(reason.message)); }, [examId]);
  useEffect(() => { if (!examId) { setMatrix(null); return; } api.answerMatrix(examId).then(setMatrix).catch(reason => setError((reason as Error).message)); }, [examId]);
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Analisis soal</h1><p className="mt-1 text-sm text-[#536765]">Kesukaran = proporsi jawaban benar. Daya pembeda membandingkan kelompok nilai tertinggi dan terendah. Hanya percobaan selesai yang telah dinilai.</p></div><ExamPicker exams={exams} examId={examId} setExamId={setExamId} />{error && <p role="alert" className="text-red-700">{error}</p>}
    {data?.suppressed && <Card className="p-5 text-sm">Statistik disembunyikan karena baru {data.sampleSize} siswa yang selesai dinilai. Minimal {data.minSample} siswa diperlukan untuk menjaga privasi.</Card>}
    {data && !data.suppressed && data.items.length === 0 && <Card className="p-5 text-sm">Tidak ada soal pilihan ganda untuk dianalisis.</Card>}
    {data?.items.map(item => <Card key={item.itemId} className="p-5"><p className="text-xs font-semibold uppercase text-[#536765]">Soal {item.order} · {item.sampleSize} siswa</p><h2 className="mt-2 font-medium"><MathText text={item.body} /></h2><div className="mt-4 grid gap-3 sm:grid-cols-2"><p className="rounded-lg bg-[#E5F3EF] p-3 text-sm">Kesukaran: {(item.difficulty * 100).toFixed(1)}% benar</p><p className="rounded-lg bg-[#E5F3EF] p-3 text-sm">Daya pembeda: {item.discrimination.toFixed(2)}</p></div><h3 className="mt-4 text-sm font-semibold">Distribusi pilihan</h3><ul className="mt-2 space-y-1 text-sm">{item.distractors.map(option => <li key={option.optionId} className="flex justify-between gap-3 border-b py-2"><span><MathText text={option.body} />{option.correct ? ' ✓' : ''}</span><span>{option.count}</span></li>)}</ul><p className="mt-2 text-xs text-[#536765]">Tidak dijawab: {item.unanswered}</p></Card>)}
    {matrix && <AnswerMatrix data={matrix} />}
  </div>;
}
