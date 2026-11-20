import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export default function AdminReset() {
  const [searchParams] = useSearchParams();
  const [username, setUsername] = useState(() => searchParams.get('username') ?? '');
  const [secret, setSecret] = useState('');
  const [kind, setKind] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  async function issue(action: 'temporary' | 'token') {
    setBusy(true); setError(''); setSecret('');
    try {
      if (action === 'temporary') { const result = await api.adminReset(username); setSecret(result.temporaryPassword); setKind('Kata sandi sementara'); }
      else { const result = await api.issueResetToken(username); setSecret(result.token); setKind('Kode reset (15 menit)'); }
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <div className="max-w-xl space-y-5"><h1 className="text-2xl font-bold">Bantu reset kata sandi siswa</h1><Card className="space-y-4 p-6"><label className="block text-sm">Nama pengguna siswa<input className="mt-1 block w-full rounded-lg border p-3" value={username} onChange={event => setUsername(event.target.value)} /></label><div className="flex flex-wrap gap-3"><Button disabled={busy || !username} onClick={() => void issue('temporary')}>Buat sandi sementara</Button><Button variant="outline" disabled={busy || !username} onClick={() => void issue('token')}>Buat kode reset</Button></div>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}{secret && <div className="rounded-lg border border-amber-300 bg-amber-50 p-4"><strong className="block text-sm">{kind}</strong><code className="mt-2 block break-all select-all">{secret}</code><p className="mt-2 text-xs">Salin sekarang dan berikan langsung kepada siswa. Kode ini tidak ditampilkan lagi setelah keluar dari halaman.</p></div>}</Card></div>;
}
