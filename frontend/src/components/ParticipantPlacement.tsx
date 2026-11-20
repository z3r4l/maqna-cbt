import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Row = Record<string, unknown>;

export function ParticipantPlacement({ examId, academicYearId }: { examId: number; academicYearId: number }) {
  const [allowed, setAllowed] = useState(false);
  const [rows, setRows] = useState<Row[]>([]);
  const [rooms, setRooms] = useState<Row[]>([]);
  const [drafts, setDrafts] = useState<Record<number, { room: string; number: string }>>({});
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    api.me().then(profile => {
      if (!profile.roles.some(role => role === 'school_admin' || role === 'superadmin')) return;
      setAllowed(true);
      return Promise.all([api.examAssignments(examId), api.catalog('exam-rooms', 1, '')]).then(([assignments, catalog]) => {
        setRows(assignments);
        setRooms(catalog.rows.filter(room => Number(room.academic_year_id) === academicYearId && Number(room.active) === 1));
        setDrafts(Object.fromEntries(assignments.map(row => [Number(row.student_id), { room: String(row.exam_room_id ?? ''), number: String(row.participant_number ?? '') }])));
      });
    }).catch(reason => setError((reason as Error).message));
  }, [examId, academicYearId]);

  if (!allowed) return null;

  async function save(studentId: number) {
    const draft = drafts[studentId];
    setBusy(studentId); setError(''); setNotice('');
    try {
      await api.assignExamParticipant(examId, studentId, draft.room ? Number(draft.room) : null, draft.number.trim() || null);
      setNotice('Penempatan peserta disimpan.');
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(null); }
  }

  return <Card className="p-5">
    <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-lg font-semibold">Ruang dan nomor peserta</h2><div className="flex flex-wrap gap-2">{[['cards.pdf', 'Kartu'], ['attendance.pdf', 'Daftar hadir'], ['attendance.pdf?blank=1', 'Daftar hadir kosong'], ['minutes.pdf', 'Berita acara'], ['minutes.pdf?blank=1', 'Berita acara kosong']].map(([path, label]) => <a key={path} className="inline-flex min-h-11 items-center rounded-lg border border-[#B6C9C5] px-4 text-sm font-semibold text-[#155451] underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]" href={'/api/v1/exams/' + examId + '/' + path}>Cetak {label}</a>)}</div></div>
    <p className="mt-1 text-sm text-[#536765]">Penempatan berlaku untuk ujian ini. Peserta yang sudah memulai ujian tidak dapat dipindahkan.</p>
    {error && <p role="alert" className="mt-3 text-sm text-red-700">{error}</p>}
    {notice && <p role="status" className="mt-3 text-sm text-[#155451]">{notice}</p>}
    <div className="mt-4 max-h-[34rem] space-y-3 overflow-y-auto">
      {rows.map(row => {
        const id = Number(row.student_id);
        const draft = drafts[id] ?? { room: '', number: '' };
        return <div key={id} className="grid items-end gap-3 rounded-lg border border-[#D7E0DE] p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
          <div className="min-w-0"><p className="truncate text-sm font-semibold">{String(row.display_name)}</p><p className="text-xs text-[#536765]">{String(row.class_name)}</p></div>
          <label className="text-sm">Ruang<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={draft.room} onChange={event => setDrafts({ ...drafts, [id]: { ...draft, room: event.target.value } })}><option value="">Belum ditempatkan</option>{rooms.map(room => <option key={Number(room.id)} value={Number(room.id)}>{String(room.name)} ({String(room.capacity)})</option>)}</select></label>
          <label className="text-sm">Nomor peserta<Input className="mt-1" maxLength={40} pattern="[A-Za-z0-9-]+" value={draft.number} onChange={event => setDrafts({ ...drafts, [id]: { ...draft, number: event.target.value } })} /></label>
          <Button type="button" disabled={busy === id} onClick={() => void save(id)}>Simpan</Button>
        </div>;
      })}
    </div>
  </Card>;
}
