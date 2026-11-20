import { useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { Popover } from 'radix-ui';
import { Input } from '@/components/ui/input';

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const pad = (number: number) => String(number).padStart(2, '0');

export function DateTimePicker({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  const date = value.slice(0, 10);
  const time = value.slice(11, 16);
  const selected = date ? new Date(`${date}T12:00:00`) : null;
  const [open, setOpen] = useState(false);
  const [view, setView] = useState(() => selected ?? new Date());
  const year = view.getFullYear();
  const month = view.getMonth();
  const leading = (new Date(year, month, 1).getDay() + 6) % 7;
  const days = new Date(year, month + 1, 0).getDate();
  const changeMonth = (offset: number) => setView(new Date(year, month + offset, 1));
  return <div className="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_120px]">
    <div className="min-w-0 text-sm"><span>{label}</span>
      <Popover.Root open={open} onOpenChange={setOpen}>
        <Popover.Trigger asChild><button type="button" aria-label={`Pilih tanggal ${label.toLowerCase()}`} className="mt-1 flex min-h-11 w-full items-center justify-between rounded-md border border-[#B6C9C5] bg-white px-3 text-left text-sm text-[#183936] outline-none focus-visible:ring-2 focus-visible:ring-[#155451] focus-visible:ring-offset-2">
          <span>{selected ? `${selected.getDate()} ${months[selected.getMonth()]} ${selected.getFullYear()}` : 'Pilih tanggal'}</span><CalendarDays className="size-4 shrink-0" aria-hidden="true" />
        </button></Popover.Trigger>
        <Popover.Portal><Popover.Content align="start" sideOffset={4} className="z-50 w-[min(20rem,calc(100vw-1rem))] rounded-lg border border-[#B6C9C5] bg-white p-3 shadow-lg outline-none" aria-label={`Kalender ${label.toLowerCase()}`}>
          <div className="mb-2 flex items-center justify-between"><button type="button" aria-label="Bulan sebelumnya" onClick={() => changeMonth(-1)} className="rounded p-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]"><ChevronLeft className="size-4" /></button><strong className="text-sm">{months[month]} {year}</strong><button type="button" aria-label="Bulan berikutnya" onClick={() => changeMonth(1)} className="rounded p-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]"><ChevronRight className="size-4" /></button></div>
          <div className="grid grid-cols-7 gap-1 text-center text-xs">{weekdays.map(day => <span key={day} className="py-2 font-semibold text-[#536765]">{day}</span>)}{Array.from({ length: leading }, (_, index) => <span key={`empty-${index}`} />)}{Array.from({ length: days }, (_, index) => { const day = index + 1; const iso = `${year}-${pad(month + 1)}-${pad(day)}`; const active = iso === date; return <button key={day} type="button" aria-label={`${day} ${months[month]} ${year}`} aria-pressed={active} onClick={() => { onChange(`${iso}T${time || '08:00'}`); setOpen(false); }} className={`min-h-9 rounded-md font-medium outline-none focus-visible:ring-2 focus-visible:ring-[#155451] focus-visible:ring-offset-1 ${active ? 'bg-[#155451] text-white' : 'text-[#183936] hover:bg-[#E5F1EE]'}`}>{day}</button>; })}</div>
          <Popover.Arrow className="fill-white" />
        </Popover.Content></Popover.Portal>
      </Popover.Root>
    </div>
    <label className="block min-w-0 text-sm">Jam {label.toLowerCase()}<Input required type="time" className="mt-1 min-h-11 w-full" value={time} onChange={event => onChange(date ? `${date}T${event.target.value}` : '')} /></label>
  </div>;
}
