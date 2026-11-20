import { useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Preview = { id: number; kind: string; mode: string; counts: Record<string, number>; rows: Record<string, unknown>[] };
type Commit = { id: number; status: string; created: number; skipped: number; credentials: { rowNumber: number; username: string; temporaryPassword: string }[] };

export default function ImportPage({ kind }: { kind: 'students' | 'teachers' | 'subjects' | 'questions' }) {
  const [file, setFile] = useState<File | null>(null);
  const [mode, setMode] = useState('reject');
  const [preview, setPreview] = useState<Preview | null>(null);
  const [result, setResult] = useState<Commit | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const label = { students: 'siswa', teachers: 'guru', subjects: 'mata pelajaran', questions: 'soal' }[kind];
  async function previewFile(event: React.FormEvent) {
    event.preventDefault(); if (!file) return;
    setBusy(true); setError(''); setPreview(null); setResult(null);
    try { setPreview(await api.previewImport(kind, mode, file)); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  async function commit() {
    if (!preview || preview.counts.error > 0 || !window.confirm('Impor ' + preview.counts.ready + ' baris siap?')) return;
    setBusy(true); setError('');
    try { setResult(await api.commitImport(kind, preview.id)); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <section className="space-y-5"><div><h1 className="text-3xl font-bold">Impor {label}</h1><p className="mt-2 text-[#536765]">Unduh template, unggah CSV atau XLSX maksimal 2 MB, periksa setiap baris, lalu konfirmasi. Maksimal 500 baris.</p></div>
    <a className="inline-block rounded-lg border border-[#155451] px-4 py-2 text-sm font-semibold text-[#155451]" href={'/api/v1/imports/' + kind + '/template'}>Unduh template CSV</a>
    <Card><CardContent className="p-5"><form className="grid gap-4 sm:grid-cols-[1fr_200px_auto] sm:items-end" onSubmit={event => void previewFile(event)}><label className="text-sm">Berkas<Input className="mt-1" type="file" accept=".csv,.xlsx" required onChange={event => setFile(event.target.files?.[0] ?? null)} /></label><label className="text-sm">Duplikat<select className="mt-1 block min-h-11 w-full rounded-lg border bg-white px-3" value={mode} onChange={event => setMode(event.target.value)}><option value="reject">Tolak</option><option value="skip">Lewati</option>{kind !== 'questions' && <option value="update">Perbarui data</option>}</select></label><Button disabled={busy || !file}>Periksa berkas</Button></form></CardContent></Card>
    {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-red-700">{error}</p>}
    {preview && <Card><CardContent className="space-y-4 p-5"><h2 className="text-xl font-semibold">Pratinjau #{preview.id}</h2><p className="text-sm">Siap {preview.counts.ready} · Lewati {preview.counts.skip} · Bermasalah {preview.counts.error}</p><div className="max-h-96 overflow-auto"><table className="w-full min-w-[540px] text-left text-sm"><thead><tr><th className="p-2">Baris</th><th className="p-2">Status</th><th className="p-2">Isi</th><th className="p-2">Masalah</th></tr></thead><tbody>{preview.rows.map(row => <tr className="border-t" key={Number(row.rowNumber)}><td className="p-2">{String(row.rowNumber)}</td><td className="p-2">{String(row.status)}</td><td className="p-2 break-words">{Object.values((row.value ?? {}) as Record<string, unknown>).filter(Boolean).slice(0, 3).join(' · ')}</td><td className="p-2 text-red-700">{String(row.error ?? '')}</td></tr>)}</tbody></table></div><Button disabled={busy || preview.counts.error > 0 || result !== null} onClick={() => void commit()}>Konfirmasi impor</Button>{preview.counts.error > 0 && <p className="text-sm text-red-700">Perbaiki berkas dan unggah ulang sebelum konfirmasi.</p>}</CardContent></Card>}
    {result && <Card><CardContent className="space-y-3 p-5"><h2 className="text-xl font-semibold">Impor selesai</h2><p>{result.created} baris diproses; {result.skipped} dilewati.</p>{result.credentials.length > 0 && <div className="rounded-lg bg-amber-50 p-4 text-sm"><strong>Kata sandi sementara ditampilkan sekali. Salin dan berikan langsung kepada pemilik akun.</strong><ul className="mt-2 space-y-1">{result.credentials.map(item => <li key={item.rowNumber}>Baris {item.rowNumber}: {item.username} · <code>{item.temporaryPassword}</code></li>)}</ul></div>}</CardContent></Card>}
  </section>;
}
