import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type Profile } from '@/lib/api';
import { Card } from '@/components/ui/card';

function MoreLinks() {
  return <nav aria-label="Informasi aplikasi" className="flex flex-wrap gap-3 text-sm"><Link className="text-[#155451] underline underline-offset-2" to="/help">Bantuan</Link><Link className="text-[#155451] underline underline-offset-2" to="/updates">Pembaruan</Link><Link className="text-[#155451] underline underline-offset-2" to="/about">Tentang</Link></nav>;
}

export function HelpPage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  useEffect(() => { api.me().then(setProfile).catch(() => {}); }, []);
  const roles = profile?.roles ?? [];
  const student = roles.includes('student');
  const teacher = roles.includes('teacher');
  const admin = roles.some(role => role === 'school_admin' || role === 'superadmin');
  const proctor = roles.includes('proctor');
  return <div className="max-w-3xl space-y-5"><div><h1 className="text-2xl font-bold">Bantuan</h1><p className="mt-1 text-sm text-[#536765]">Panduan ringkas untuk tugas yang tersedia di akun Anda.</p></div><MoreLinks />
    {student && <Card className="space-y-2 p-5"><h2 className="font-semibold">Mengerjakan ujian</h2><p className="text-sm">Buka Ujian saya, baca jadwal dan petunjuk, lalu masukkan token jika diminta. Jawaban disimpan otomatis saat Anda berpindah soal. Periksa status tersimpan sebelum mengumpulkan.</p><p className="text-sm">Jika koneksi terputus, buka kembali percobaan pada perangkat yang sama. Waktu ujian mengikuti server. Aplikasi yang terpasang tetap memerlukan koneksi untuk login, menyimpan jawaban, dan mengumpulkan ujian.</p><p className="text-sm">Hasil baru terlihat setelah guru atau admin merilisnya. Hubungi pengawas bila sesi atau perangkat perlu dibantu.</p></Card>}
    {teacher && <Card className="space-y-2 p-5"><h2 className="font-semibold">Soal dan ujian</h2><p className="text-sm">Siapkan soal di Bank soal, lalu buat draf ujian dengan mapel, semester, rombel, jadwal, dan soal terbit. Pratinjau peserta dan tampilan siswa sebelum menerbitkan.</p><p className="text-sm">Pantau ujian melalui Pemantauan. Nilai esai di Laporan hasil sebelum merilis nilai manual. Ekspor jawaban dan matriks hanya tersedia untuk ujian dalam penugasan Anda.</p></Card>}
    {admin && <Card className="space-y-2 p-5"><h2 className="font-semibold">Administrasi sekolah</h2><p className="text-sm">Atur tahun ajaran, rombel, guru, siswa, jenis ujian, dan ruang sebelum membuat ujian. Unggah logo sekolah pada halaman Sekolah agar tercetak pada kartu peserta.</p><p className="text-sm">Perubahan status massal perlu pratinjau; ujian yang sudah memiliki percobaan tidak dapat dijeda. Token dapat dibuat ulang dari Beranda. Bagikan token melalui saluran sekolah yang aman.</p><div className="flex flex-wrap gap-3 text-sm"><a className="text-[#155451] underline" href="/api/v1/imports/students/template">Template siswa</a><a className="text-[#155451] underline" href="/api/v1/imports/teachers/template">Template guru</a><a className="text-[#155451] underline" href="/api/v1/imports/subjects/template">Template mapel</a><a className="text-[#155451] underline" href="/api/v1/imports/questions/template">Template soal</a></div></Card>}
    {proctor && <Card className="space-y-2 p-5"><h2 className="font-semibold">Pengawasan</h2><p className="text-sm">Buka ujian yang ditugaskan di Pemantauan. Status dan koneksi diperbarui berkala. Peristiwa pindah tab adalah sinyal untuk diperiksa, bukan bukti kecurangan. Pencabutan sesi memerlukan alasan dan tidak menghapus jawaban.</p></Card>}
    <Card className="space-y-2 p-5"><h2 className="font-semibold">Pertanyaan umum</h2><details><summary className="cursor-pointer font-medium">Mengapa hasil belum terlihat?</summary><p className="mt-2 text-sm">Hasil dirilis manual setelah penilaian selesai.</p></details><details><summary className="cursor-pointer font-medium">Apa yang dilakukan jika koneksi terputus?</summary><p className="mt-2 text-sm">Hubungkan kembali perangkat yang sama, buka Ujian saya, dan lanjutkan percobaan aktif. Jika waktu habis, hubungi pengawas.</p></details><details><summary className="cursor-pointer font-medium">Apakah aplikasi bisa mengerjakan ujian tanpa internet?</summary><p className="mt-2 text-sm">Tidak. Koneksi diperlukan untuk autentikasi, penyimpanan jawaban, dan pengumpulan.</p></details></Card>
  </div>;
}

const updates = [
  { title: 'Pelaksanaan dan administrasi ujian', date: '15 November 2026', detail: 'Token terjadwal, cetak kartu dan daftar hadir, berita acara, jawaban wajib, serta ekspor jawaban.' },
  { title: 'Bank soal', date: '15 November 2026', detail: 'Tipe soal baru, impor dan ekspor paket soal, serta pratinjau tampilan siswa.' },
  { title: 'Data sekolah', date: '15 November 2026', detail: 'Ruang ujian, nomor peserta, impor guru dan mapel, serta pengawasan sesi.' },
];

export function UpdatesPage() {
  return <div className="max-w-3xl space-y-5"><div><h1 className="text-2xl font-bold">Pembaruan</h1><p className="mt-1 text-sm text-[#536765]">Riwayat perubahan yang terlihat oleh pengguna.</p></div><MoreLinks />{updates.map(update => <Card key={update.title} className="p-5"><p className="text-xs text-[#536765]">{update.date}</p><h2 className="mt-1 font-semibold">{update.title}</h2><p className="mt-2 text-sm">{update.detail}</p></Card>)}</div>;
}

export function AboutPage() {
  return <div className="max-w-3xl space-y-5"><div><h1 className="text-2xl font-bold">Tentang maqna-cbt</h1><p className="mt-1 text-sm text-[#536765]">Aplikasi ujian untuk sekolah dan madrasah.</p></div><MoreLinks /><Card className="space-y-3 p-5 text-sm"><p><strong>Nama:</strong> maqna-cbt</p><p><strong>Versi aplikasi:</strong> 0.1.0</p><p>Jadwal dan batas waktu ditentukan server. Jawaban disimpan pada akun peserta dan hasil dibuka setelah rilis manual.</p><p>Aplikasi memerlukan koneksi untuk mengikuti ujian. Panduan penggunaan tersedia di halaman Bantuan.</p></Card></div>;
}
