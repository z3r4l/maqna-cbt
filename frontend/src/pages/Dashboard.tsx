import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type Profile } from '@/lib/api';
import { Card } from '@/components/ui/card';
import { ExamTokenTable } from '@/components/ExamTokenTable';

export default function Dashboard() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [error, setError] = useState('');
  const [teaching, setTeaching] = useState<Record<string, unknown>[]>([]);
  const [myClass, setMyClass] = useState<Record<string, unknown> | null>(null);
  useEffect(() => { api.me().then(setProfile).catch(reason => setError(reason.message)); }, []);
  useEffect(() => {
    if (profile?.roles.includes('teacher')) api.myTeaching().then(result => setTeaching(result.rows)).catch(reason => setError(reason.message));
    if (profile?.roles.includes('student')) api.myClass().then(setMyClass).catch(reason => setError(reason.message));
  }, [profile]);
  return <div className="space-y-6">
    <div><h1 className="text-2xl font-bold">{profile ? `Selamat datang, ${profile.name}` : 'Beranda'}</h1><p className="mt-2 text-[#536765]">Informasi akun dan kegiatan sekolah tersedia sesuai izin Anda.</p></div>
    {error && <p role="alert" className="text-red-700">{error}</p>}
    {profile && <Card className="max-w-xl p-6"><h2 className="text-lg font-semibold">Akun Anda</h2><dl className="mt-4 grid gap-3 text-sm"><div><dt className="text-[#536765]">Nama pengguna</dt><dd className="font-medium">{profile.username}</dd></div><div><dt className="text-[#536765]">Peran</dt><dd className="font-medium">{profile.roles.join(', ')}</dd></div></dl><Link to="/profile" className="mt-6 inline-block rounded-lg bg-[#155451] px-4 py-3 text-white">Kelola profil</Link></Card>}
    {profile?.roles.includes('teacher') && <Card className="max-w-xl p-6"><h2 className="text-lg font-semibold">Kelas dan mata pelajaran saya</h2>{teaching.length === 0 ? <p className="mt-3 text-sm text-[#536765]">Belum ada penugasan aktif.</p> : <ul className="mt-3 divide-y">{teaching.filter(row => Number(row.active) === 1).map(row => <li key={Number(row.id)} className="py-3 text-sm"><span className="font-medium">{String(row.subject_name)}</span><span className="block text-[#536765]">{String(row.class_name)} · {String(row.semester_name)}</span></li>)}</ul>}</Card>}
    {profile?.roles.includes('student') && <Card className="max-w-xl p-6"><h2 className="text-lg font-semibold">Rombel saya</h2><p className="mt-3 text-sm">{myClass ? String(myClass.class_name) + ' · ' + String(myClass.academic_year) : 'Belum ada rombel aktif.'}</p></Card>}
    {profile && profile.roles.some(role => role === 'school_admin' || role === 'superadmin' || role === 'teacher') && <ExamTokenTable />}
  </div>;
}
