import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export default function ResetPassword() {
  const [token, setToken] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  async function submit(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setMessage('');
    try { await api.resetPassword(token, password); setToken(''); setPassword(''); setMessage('Kata sandi sudah diubah. Silakan masuk.'); }
    catch (reason) { setMessage((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <main className="grid min-h-screen place-items-center bg-[#F5F6F7] p-4"><Card className="w-full max-w-md p-7"><h1 className="text-2xl font-bold">Reset kata sandi</h1><p className="mt-2 text-sm text-[#536765]">Masukkan kode sekali pakai yang diberikan admin sekolah. Kode berlaku 15 menit.</p><form onSubmit={submit} className="mt-6 space-y-4"><label className="block text-sm">Kode reset<input required autoComplete="off" className="mt-1 block w-full rounded-lg border p-3" value={token} onChange={event => setToken(event.target.value)} /></label><label className="block text-sm">Kata sandi baru<input required minLength={12} type="password" autoComplete="new-password" className="mt-1 block w-full rounded-lg border p-3" value={password} onChange={event => setPassword(event.target.value)} /></label>{message && <p role="status" className="text-sm">{message}</p>}<Button disabled={busy}>Reset kata sandi</Button></form><Link to="/login" className="mt-5 inline-block text-sm text-[#155451] underline">Kembali ke halaman masuk</Link></Card></main>;
}
