import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SessionPolicySettings } from '@/components/SessionPolicySettings';

export default function SchoolSettings() {
  const [name, setName] = useState('');
  const [timezone, setTimezone] = useState('Asia/Jakarta');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [logo, setLogo] = useState<File | null>(null);
  const [logoVersion, setLogoVersion] = useState(0);
  useEffect(() => { api.school().then(school => { setName(String(school.name ?? '')); setTimezone(String(school.timezone ?? 'Asia/Jakarta')); }).catch(reason => setError(reason.message)); }, []);
  async function save(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    try { await api.updateSchool({ name, timezone }); setNotice('Identitas sekolah disimpan.'); }
    catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  async function saveLogo(event: React.FormEvent) {
    event.preventDefault(); if (!logo) return;
    setBusy(true); setError(''); setNotice('');
    try { await api.uploadSchoolLogo(logo); setLogoVersion(Date.now()); setNotice('Logo sekolah disimpan untuk kartu peserta.'); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <div className="space-y-5"><div><h1 className="text-2xl font-bold">Sekolah</h1><p className="mt-1 text-sm text-[#536765]">Identitas dan zona waktu untuk jadwal ujian.</p></div><Card className="max-w-xl p-6"><form onSubmit={save} className="space-y-4"><label className="block text-sm">Nama sekolah<Input required minLength={3} maxLength={160} className="mt-1" value={name} onChange={event => setName(event.target.value)} /></label><label className="block text-sm">Zona waktu<select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={timezone} onChange={event => setTimezone(event.target.value)}><option value="Asia/Jakarta">Asia/Jakarta (WIB)</option><option value="Asia/Makassar">Asia/Makassar (WITA)</option><option value="Asia/Jayapura">Asia/Jayapura (WIT)</option></select></label>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}<Button disabled={busy}>Simpan sekolah</Button></form></Card><Card className="max-w-xl p-6"><h2 className="font-semibold">Logo sekolah</h2><p className="mt-1 text-sm text-[#536765]">PNG atau JPEG, maksimal 2 MB. Logo muncul pada kartu peserta.</p><img key={logoVersion} src={'/api/v1/school/logo?v=' + logoVersion} alt="Pratinjau logo sekolah" className="mt-3 h-20 w-20 object-contain" onError={event => { event.currentTarget.style.display = 'none'; }} /><form onSubmit={event => void saveLogo(event)} className="mt-3 flex flex-wrap items-end gap-3"><label className="text-sm">Pilih logo<Input className="mt-1" type="file" accept="image/png,image/jpeg" onChange={event => setLogo(event.target.files?.[0] ?? null)} /></label><Button disabled={busy || !logo}>Unggah logo</Button></form></Card><SessionPolicySettings /></div>;
}

