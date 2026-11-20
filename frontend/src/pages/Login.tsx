import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export default function Login() {
  const [identity, setIdentity] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const navigate = useNavigate();

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try { const profile = await api.login(identity, password); navigate(profile.mustChangePassword ? '/profile' : '/dashboard', { replace: true }); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }

  return <main className="min-h-screen grid lg:grid-cols-2">
    <section className="hidden lg:flex bg-[#155451] text-white flex-col justify-between p-12">
      <strong className="text-3xl">maqna-cbt</strong>
      <div><h1 className="text-5xl font-semibold leading-tight">Ruang ujian sekolah yang tenang dan jelas.</h1><p className="mt-6 text-white/80">Masuk untuk melihat kegiatan sesuai peran Anda.</p></div>
      <p className="text-sm text-white/70">Sekolah dan madrasah</p>
    </section>
    <section className="grid place-items-center p-5"><Card className="w-full max-w-md p-7 sm:p-10">
      <h2 className="text-2xl font-bold">Masuk ke maqna-cbt</h2>
      <p className="mt-2 text-sm text-[#727B80]">Gunakan nama pengguna atau email sekolah.</p>
      <form onSubmit={submit} className="mt-8 space-y-5">
        <label className="block text-sm font-medium">Nama pengguna atau email<input required autoComplete="username" className="mt-2 block w-full rounded-lg border border-[#C6D3D1] p-3 focus-visible:outline-[#155451]" value={identity} onChange={event => setIdentity(event.target.value)} /></label>
        <label className="block text-sm font-medium">Kata sandi<input required type="password" autoComplete="current-password" className="mt-2 block w-full rounded-lg border border-[#C6D3D1] p-3 focus-visible:outline-[#155451]" value={password} onChange={event => setPassword(event.target.value)} /></label>
        {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
        <Button className="w-full" disabled={busy}>Masuk</Button>
        <Link to="/password/reset" className="block text-sm font-medium text-[#155451] underline">Punya kode reset dari admin?</Link>
      </form>
    </Card></section>
  </main>;
}
