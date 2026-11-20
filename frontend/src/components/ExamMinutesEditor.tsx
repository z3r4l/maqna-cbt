import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export function ExamMinutesEditor({ examId }: { examId: number }) {
  const [notes, setNotes] = useState('');
  const [signerOne, setSignerOne] = useState('');
  const [signerTwo, setSignerTwo] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  useEffect(() => { api.examMinutes(examId).then(row => { if (row) { setNotes(String(row.notes ?? '')); setSignerOne(String(row.signer_one ?? '')); setSignerTwo(String(row.signer_two ?? '')); } }).catch(reason => setError((reason as Error).message)); }, [examId]);

  async function save(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    try { await api.saveExamMinutes(examId, notes, signerOne, signerTwo); setNotice('Berita acara disimpan. PDF terisi memakai catatan dan nama pengawas ini.'); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }

  return <Card className="p-5"><h2 className="text-lg font-semibold">Catatan berita acara</h2><p className="mt-1 text-sm text-[#536765]">Jumlah hadir dihitung dari peserta yang sudah memulai ujian.</p><form className="mt-4 grid gap-3" onSubmit={event => void save(event)}><label className="text-sm">Catatan pelaksanaan<textarea className="mt-1 block min-h-28 w-full rounded-md border border-[#B6C9C5] bg-white p-3" maxLength={5000} value={notes} onChange={event => setNotes(event.target.value)} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="text-sm">Pengawas 1<Input className="mt-1" maxLength={160} value={signerOne} onChange={event => setSignerOne(event.target.value)} /></label><label className="text-sm">Pengawas 2<Input className="mt-1" maxLength={160} value={signerTwo} onChange={event => setSignerTwo(event.target.value)} /></label></div>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}<Button className="justify-self-start" disabled={busy}>Simpan berita acara</Button></form></Card>;
}
