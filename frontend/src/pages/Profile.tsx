import { useEffect, useState } from 'react';
import { api, type Profile } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export default function ProfilePage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [displayName, setDisplayName] = useState('');
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  useEffect(() => { api.me().then(value => { setProfile(value); setDisplayName(value.name); }).catch(reason => setMessage(reason.message)); }, []);
  async function saveName(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setMessage('');
    try { const updated = await api.updateProfile(displayName); setProfile(updated); setMessage('Nama tampilan berhasil diubah.'); }
    catch (reason) { setMessage((reason as Error).message); }
    finally { setBusy(false); }
  }
  async function change(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setMessage('');
    try { await api.changePassword(current, next); setCurrent(''); setNext(''); setMessage('Kata sandi berhasil diubah.'); }
    catch (reason) { setMessage((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <div className="max-w-xl space-y-6"><h1 className="text-2xl font-bold">Profil dan keamanan</h1>
    {profile?.mustChangePassword && <p role="alert" className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">Anda harus mengganti kata sandi sementara sebelum melanjutkan.</p>}
    {profile && <Card className="p-6"><dl className="grid gap-3 text-sm"><div><dt className="text-[#536765]">Nama pengguna</dt><dd>{profile.username}</dd></div><div><dt className="text-[#536765]">Email</dt><dd>{profile.email || 'Tidak tercatat'}</dd></div></dl><form onSubmit={saveName} className="mt-5 space-y-3"><label className="block text-sm">Nama tampilan<input required minLength={2} maxLength={120} className="mt-1 block w-full rounded-lg border p-3" value={displayName} onChange={event => setDisplayName(event.target.value)} /></label><Button disabled={busy}>Simpan nama</Button></form></Card>}
    <Card className="p-6"><h2 className="text-lg font-semibold">Ubah kata sandi</h2><form onSubmit={change} className="mt-5 space-y-4"><label className="block text-sm">Kata sandi saat ini<input type="password" required autoComplete="current-password" className="mt-1 block w-full rounded-lg border p-3" value={current} onChange={event => setCurrent(event.target.value)} /></label><label className="block text-sm">Kata sandi baru (minimal 12 karakter)<input type="password" minLength={12} required autoComplete="new-password" className="mt-1 block w-full rounded-lg border p-3" value={next} onChange={event => setNext(event.target.value)} /></label>{message && <p role="status" className="text-sm">{message}</p>}<Button disabled={busy}>Simpan kata sandi</Button></form></Card>
  </div>;
}
