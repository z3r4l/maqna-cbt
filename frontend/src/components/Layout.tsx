import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { Sheet, SheetClose, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { api, type Profile } from '@/lib/api';

type Destination = { to: string; label: string };
type Section = { id: string; label: string; items: Destination[] };

const academic: Section = { id: 'academic', label: 'Data akademik', items: [
  { to: '/academic/academic-years', label: 'Tahun ajaran' },
  { to: '/academic/semesters', label: 'Semester' },
  { to: '/academic/grade-levels', label: 'Tingkat' },
  { to: '/academic/class-groups', label: 'Rombel' },
  { to: '/academic/subjects', label: 'Mata pelajaran' },
  { to: '/academic/question-categories', label: 'Kategori soal' },
  { to: '/academic/question-topics', label: 'Topik soal' },
  { to: '/academic/exam-types', label: 'Jenis ujian' },
  { to: '/academic/exam-rooms', label: 'Ruang ujian' },
] };
const people: Section = { id: 'people', label: 'Warga sekolah', items: [
  { to: '/people/students', label: 'Siswa' },
  { to: '/imports/students', label: 'Impor siswa' },
  { to: '/people/teachers', label: 'Guru' },
  { to: '/proctors', label: 'Pengawas' },
  { to: '/imports/teachers', label: 'Impor guru' },
  { to: '/memberships', label: 'Rombel siswa' },
  { to: '/teaching', label: 'Penugasan guru' },
] };
const questions: Section = { id: 'questions', label: 'Soal', items: [
  { to: '/questions', label: 'Bank soal' },
  { to: '/question-versions', label: 'Revisi soal' },
  { to: '/imports/questions', label: 'Impor soal' },
] };
const exams: Section = { id: 'exams', label: 'Ujian', items: [
  { to: '/exams', label: 'Ujian' },
  { to: '/monitoring', label: 'Pemantauan ujian' },
] };
const results: Section = { id: 'results', label: 'Hasil & analisis', items: [
  { to: '/reports', label: 'Laporan hasil' },
  { to: '/analytics/questions', label: 'Analisis soal' },
] };
const administration: Section = { id: 'administration', label: 'Administrasi', items: [
  { to: '/school', label: 'Sekolah' },
  { to: '/password/admin', label: 'Bantu reset sandi' },
  { to: '/audit', label: 'Aktivitas' },
  { to: '/profile', label: 'Profil dan keamanan' },
] };
const account: Section = { id: 'account', label: 'Akun', items: [
  { to: '/audit', label: 'Aktivitas' },
  { to: '/profile', label: 'Profil dan keamanan' },
] };

function sectionsFor(profile: Profile): Section[] {
  if (profile.roles.some(role => role === 'school_admin' || role === 'superadmin')) return [academic, people, questions, exams, results, administration];
  if (profile.roles.includes('teacher')) return [questions, exams, results, account];
  if (profile.roles.includes('proctor')) return [{ id: 'monitor', label: 'Pemantauan', items: [{ to: '/monitoring', label: 'Ujian yang diawasi' }] }, { id: 'proctor-account', label: 'Akun', items: [{ to: '/profile', label: 'Profil dan keamanan' }] }];
  return [];
}

function matches(path: string, destination: string): boolean {
  return path === destination || path.startsWith(destination + '/');
}

function Navigation({ profile, pathname, expanded, setExpanded, onNavigate, signOut, scope }: {
  profile: Profile | null;
  pathname: string;
  expanded: string | null;
  setExpanded: (id: string | null) => void;
  onNavigate: () => void;
  signOut: () => void;
  scope: 'desktop' | 'mobile';
}) {
  const sections = profile ? sectionsFor(profile) : [];
  const student = profile?.roles.includes('student') && sections.length === 0;
  const linkClass = (active: boolean, child = false) =>
    `flex min-h-11 items-center rounded-lg px-4 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451] ${child ? 'ml-3' : ''} ${active ? 'bg-[#155451] font-semibold text-white' : 'font-medium text-[#29413E] hover:bg-[#E5F3EF]'}`;
  const directLink = (item: Destination) => <NavLink key={item.to} to={item.to} end={item.to === '/dashboard'} onClick={onNavigate} className={({ isActive }) => linkClass(isActive)}>{item.label}</NavLink>;

  return <div className="sidebar-panel flex min-h-0 flex-1 flex-col bg-white">
    <Link to="/dashboard" onClick={onNavigate} className="shrink-0 px-5 py-6 text-2xl font-bold text-[#155451] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]">maqna-cbt</Link>
    <nav aria-label="Navigasi utama" className="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-4">
      {profile && directLink({ to: '/dashboard', label: 'Beranda' })}
      {sections.map(section => {
        const active = section.items.some(item => matches(pathname, item.to));
        const isOpen = expanded === section.id;
        const panelId = `${scope}-nav-${section.id}`;
        return <div key={section.id} className="pt-1">
          <button type="button" aria-expanded={isOpen} aria-controls={panelId} onClick={() => setExpanded(isOpen ? null : section.id)}
            className={`flex min-h-11 w-full items-center justify-between rounded-lg border-l-4 px-3 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451] ${active ? 'border-[#155451] bg-[#E5F3EF] font-semibold text-[#155451]' : 'border-transparent font-medium text-[#29413E] hover:bg-[#E5F3EF]'}`}>
            <span>{section.label}</span><span aria-hidden="true" className="ml-2 text-lg leading-none">{isOpen ? '−' : '+'}</span>
          </button>
          <div id={panelId} hidden={!isOpen} className="mt-1 space-y-1 border-l border-[#B6C9C5] pl-1">
            {section.items.map(item => <NavLink key={item.to} to={item.to} onClick={onNavigate} className={({ isActive }) => linkClass(isActive, true)}>{item.label}</NavLink>)}
          </div>
        </div>;
      })}
      {student && directLink({ to: '/my-exams', label: 'Ujian saya' })}
      {student && directLink({ to: '/my-results', label: 'Hasil saya' })}
      {student && directLink({ to: '/profile', label: 'Profil' })}
    </nav>
    <div className="shrink-0 border-t border-[#D7E0DE] p-3">
      <button type="button" onClick={signOut} className="min-h-11 w-full rounded-lg px-4 py-2 text-left text-sm font-medium text-[#29413E] hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]">Keluar</button>
    </div>
  </div>;
}

export default function Layout() {
  const [open, setOpen] = useState(false);
  const [sidebarDark, setSidebarDark] = useState(() => window.localStorage.getItem('maqna-sidebar-theme') === 'dark');
  const [expanded, setExpanded] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [profileError, setProfileError] = useState('');
  const [profile, setProfile] = useState<Profile | null>(null);
  const location = useLocation();
  const navigate = useNavigate();

  function loadProfile() {
    setProfileError('');
    api.me().then(setProfile).catch(() => setProfileError('Navigasi gagal dimuat. Coba lagi.'));
  }
  useEffect(() => { loadProfile(); }, []);
  useEffect(() => {
    if (!profile) return;
    const current = sectionsFor(profile).find(section => section.items.some(item => matches(location.pathname, item.to)));
    setExpanded(current?.id ?? null);
  }, [location.pathname, profile]);

  async function signOut() {
    setError('');
    try { await api.logout(); setOpen(false); navigate('/login', { replace: true }); }
    catch (reason) { setError((reason as Error).message); }
  }

  const navigationProps = { profile, pathname: location.pathname, expanded, setExpanded, onNavigate: () => setOpen(false), signOut: () => void signOut() };
  return <div className={`${sidebarDark ? 'sidebar-dark ' : ''}min-h-screen bg-[#F5F6F7] lg:flex`}>
    <aside className="hidden border-r border-[#D7E0DE] bg-white lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-60 lg:shrink-0 lg:flex-col">
      <Navigation {...navigationProps} scope="desktop" />
    </aside>
    <div className="min-w-0 flex-1">
      <header className="flex min-h-16 items-center gap-4 border-b border-[#D7E0DE] bg-white px-4">
        <Sheet open={open} onOpenChange={next => {
          setOpen(next);
          if (next && profile) {
            const current = sectionsFor(profile).find(section => section.items.some(item => matches(location.pathname, item.to)));
            setExpanded(current?.id ?? null);
          }
        }}>
          <SheetTrigger asChild><button type="button" aria-label="Buka navigasi" className="min-h-11 rounded-lg px-2 font-medium text-[#155451] hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451] lg:hidden">Menu</button></SheetTrigger>
          <SheetContent side="left" showCloseButton={false} className="w-[min(85vw,280px)] gap-0 border-[#D7E0DE] bg-white p-0 sm:max-w-[280px]">
            <SheetTitle className="sr-only">Navigasi utama</SheetTitle>
            <SheetClose asChild><button type="button" aria-label="Tutup navigasi" className="absolute top-5 right-3 z-10 min-h-11 min-w-11 rounded-lg text-lg text-[#29413E] hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]">×</button></SheetClose>
            <Navigation {...navigationProps} scope="mobile" />
          </SheetContent>
        </Sheet>
        <span className="font-semibold">maqna-cbt</span><button type="button" aria-label={sidebarDark ? 'Gunakan sidebar terang' : 'Gunakan sidebar gelap'} aria-pressed={sidebarDark} onClick={() => { const next = !sidebarDark; setSidebarDark(next); window.localStorage.setItem('maqna-sidebar-theme', next ? 'dark' : 'light'); }} className="ml-auto min-h-11 rounded-md px-3 text-sm font-semibold text-[#155451] hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]">Tema</button><Link to="/help" className="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-[#155451] hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]">Bantuan</Link>
      </header>
      {profileError && <p role="alert" className="m-4 text-sm text-red-700">{profileError} <button type="button" className="underline" onClick={loadProfile}>Ulangi</button></p>}
      {error && <p role="alert" className="m-4 text-red-700">{error}</p>}
      <main className="w-full min-w-0 p-4"><Outlet /></main>
    </div>
  </div>;
}
