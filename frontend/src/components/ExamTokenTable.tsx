import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Row = Record<string, unknown>;

export function ExamTokenTable() {
  const [rows, setRows] = useState<Row[]>([]);
  const [subjects, setSubjects] = useState<Row[]>([]);
  const [classes, setClasses] = useState<Row[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [status, setStatus] = useState('all');
  const [subjectId, setSubjectId] = useState('');
  const [classGroupId, setClassGroupId] = useState('');
  const [revealed, setRevealed] = useState<{ examId: number; code: string; expiresAt?: string } | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => { api.examMasters().then(result => { setSubjects(result.subjects); setClasses(result.classes); }).catch(reason => setError((reason as Error).message)); }, []);
  useEffect(() => {
    let active = true;
    api.examTokens(page, status, subjectId, classGroupId).then(result => { if (active) { setRows(result.rows); setTotal(result.total); } }).catch(reason => { if (active) setError((reason as Error).message); });
    return () => { active = false; };
  }, [page, status, subjectId, classGroupId]);

  async function rotate(id: number) {
    if (!window.confirm('Buat token baru untuk ujian ini? Token lama tidak dapat dipakai untuk mulai ujian baru.')) return;
    setBusy(id); setError(''); setNotice(''); setRevealed(null);
    try {
      const result = await api.rotateExamToken(id);
      setRevealed({ examId: id, code: result.accessCode });
      setRows(current => current.map(row => Number(row.id) === id ? { ...row, token_enabled: 1 } : row));
      try { await navigator.clipboard.writeText(result.accessCode); setNotice('Token baru disalin. Simpan dan bagikan secara aman.'); }
      catch { setNotice('Token baru ditampilkan di bawah. Salin sebelum meninggalkan halaman.'); }
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }

  async function setPolicy(id: number, mode: 'none' | 'static' | 'dynamic') {
    if (!window.confirm('Ubah mode token? Peserta yang belum memulai harus memakai token baru.')) return;
    setBusy(id); setError(''); setNotice(''); setRevealed(null);
    try {
      const result = await api.setExamTokenPolicy(id, mode);
      setRows(current => current.map(row => Number(row.id) === id ? { ...row, token_mode: mode, token_enabled: mode === 'none' ? 0 : 1 } : row));
      if (result.accessCode) setRevealed({ examId: id, code: result.accessCode, expiresAt: result.expiresAt });
      setNotice('Mode token diperbarui.');
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }

  async function showCurrent(id: number) {
    setBusy(id); setError('');
    try { const result = await api.currentExamToken(id); setRevealed({ examId: id, code: result.accessCode, expiresAt: result.expiresAt }); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }

  const selectClass = 'mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]';
  return <Card className="p-5">
    <div><h2 className="text-lg font-semibold">Token ujian</h2><p className="mt-1 text-sm text-[#536765]">Token tersimpan sebagai hash. Token baru hanya tampil sekali saat dibuat.</p></div>
    <div className="mt-4 grid gap-3 sm:grid-cols-3">
      <label className="text-sm">Status<select aria-label="Status" className={selectClass} value={status} onChange={event => { setStatus(event.target.value); setPage(1); }}><option value="all">Semua</option><option value="draft">Draf</option><option value="published">Terbit</option></select></label>
      <label className="text-sm">Mata pelajaran<select className={selectClass} value={subjectId} onChange={event => { setSubjectId(event.target.value); setPage(1); }}><option value="">Semua</option>{subjects.map(row => <option key={Number(row.id)} value={Number(row.id)}>{String(row.name)}</option>)}</select></label>
      <label className="text-sm">Rombel<select className={selectClass} value={classGroupId} onChange={event => { setClassGroupId(event.target.value); setPage(1); }}><option value="">Semua</option>{classes.map(row => <option key={Number(row.id)} value={Number(row.id)}>{String(row.name)}</option>)}</select></label>
    </div>
    {error && <p role="alert" className="mt-3 text-sm text-red-700">{error}</p>}
    {notice && <p role="status" className="mt-3 text-sm text-[#155451]">{notice}</p>}
    {revealed && <div className="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm"><strong>Token ujian #{revealed.examId}</strong><code className="mt-1 block select-all text-lg font-bold tracking-widest">{revealed.code}</code>{revealed.expiresAt && <p>Berlaku sampai {new Date(revealed.expiresAt.replace(' ', 'T') + 'Z').toLocaleString('id-ID')}.</p>}</div>}
    <div className="mt-4 divide-y divide-[#D7E0DE]">{rows.length === 0 && <p className="py-4 text-sm text-[#536765]">Tidak ada ujian sesuai filter.</p>}{rows.map(row => <div key={Number(row.id)} className="flex flex-wrap items-center justify-between gap-3 py-3"><div className="min-w-0"><Link to={'/exams/' + row.id} className="font-semibold text-[#155451] underline underline-offset-2">{String(row.title)}</Link><p className="mt-1 text-xs text-[#536765]">{String(row.subject_name)} Â· {String(row.class_names ?? 'Belum ada rombel')} Â· {String(row.status)} Â· {Number(row.token_enabled) === 1 ? (row.token_mode === 'dynamic' ? 'Token dinamis' : 'Token tetap') : 'Tanpa token'}</p></div>{row.status === 'published' && <div className="flex flex-wrap gap-2"><label className="text-xs">Mode token<select aria-label={"Mode token ujian " + row.title} className={selectClass} disabled={busy === Number(row.id)} value={Number(row.token_enabled) === 0 ? "none" : String(row.token_mode ?? "static")} onChange={event => void setPolicy(Number(row.id), event.target.value as "none" | "static" | "dynamic")}><option value="none">Tanpa token</option><option value="static">Tetap</option><option value="dynamic">Dinamis</option></select></label>{row.token_mode === "dynamic" && Number(row.token_enabled) === 1 && <Button type="button" variant="outline" disabled={busy === Number(row.id)} onClick={() => void showCurrent(Number(row.id))}>Lihat token saat ini</Button>}<Button type="button" variant="outline" disabled={busy === Number(row.id)} onClick={() => void rotate(Number(row.id))}>Buat token baru</Button></div>}</div>)}</div>
    <div className="mt-3 flex items-center justify-between text-sm"><span>{total} ujian</span><div className="flex gap-2"><Button type="button" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button type="button" variant="outline" disabled={page * 20 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div>
  </Card>;
}

