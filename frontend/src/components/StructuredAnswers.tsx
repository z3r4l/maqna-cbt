import { useLayoutEffect, useRef, useState } from 'react';
import type { AttemptItem } from '@/lib/api';
import MathText from '@/components/MathText';

type TrueFalseAnswer = Record<string, boolean>;
type MatchingAnswer = Record<string, number>;

function plainText(value: string) {
  return new DOMParser().parseFromString(value, 'text/html').body.textContent ?? '';
}

function nextAnswer<T extends Record<string, boolean | number>>(answer: T | null, key: number, value: boolean | number): T {
  return { ...(answer ?? {}), [String(key)]: value } as T;
}

export function TrueFalseAnswerView({ item, attemptId, onChange }: {
  item: AttemptItem;
  attemptId: number;
  onChange: (answer: TrueFalseAnswer | null) => void;
}) {
  const answer = item.answer as TrueFalseAnswer | null;
  const choose = (optionId: number, value: boolean) => onChange(nextAnswer(answer, optionId, value));

  return <div className="mt-4">
    <p className="mb-3 rounded-lg border border-[#B8E8D0] bg-[#EDFBF4] px-3 py-2.5 text-sm font-medium text-[#075D50]">Tentukan Benar atau Salah untuk setiap pernyataan berikut:</p>
    <table className="hidden w-full table-fixed border-collapse text-sm min-[640px]:table">
      <thead><tr className="bg-[#F1F5F8] text-[#13294B]"><th scope="col" className="w-12 border border-[#CEDAE3] px-2 py-2 text-center">No</th><th scope="col" className="border border-[#CEDAE3] px-3 py-2 text-center">Pernyataan</th><th scope="col" className="w-20 border border-[#CEDAE3] px-2 py-2 text-center">Benar</th><th scope="col" className="w-20 border border-[#CEDAE3] px-2 py-2 text-center">Salah</th></tr></thead>
      <tbody>{item.options.map((option, index) => <tr key={option.id} className={index % 2 ? 'bg-[#F8FAFC]' : 'bg-white'}>
        <th scope="row" className="border border-[#CEDAE3] px-2 py-2.5 text-center font-normal">{index + 1}</th>
        <td className="break-words border border-[#CEDAE3] px-3 py-2.5"><MathText text={option.body} attemptId={attemptId} /></td>
        {[true, false].map(value => <td key={String(value)} className="border border-[#CEDAE3] px-2 py-2 text-center">
          <input type="radio" name={`tf-table-${item.id}-${option.id}`} aria-label={`${value ? 'Benar' : 'Salah'} untuk pernyataan ${index + 1}`}
            checked={answer?.[String(option.id)] === value} onChange={() => choose(option.id, value)}
            className="size-5 cursor-pointer accent-[#08777B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B]" />
        </td>)}
      </tr>)}</tbody>
    </table>
    <fieldset className="space-y-2.5 min-[640px]:hidden">
      <legend className="sr-only">Tentukan Benar atau Salah untuk setiap pernyataan</legend>
      {item.options.map((option, index) => <div key={option.id} className="rounded-lg border border-[#CEDAE3] bg-white p-3">
        <div className="text-sm"><span className="mr-1 font-semibold">{index + 1}.</span><MathText text={option.body} attemptId={attemptId} /></div>
        <div className="mt-2 grid grid-cols-2 gap-2">
          {[true, false].map(value => <label key={String(value)} className={`flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border px-2 text-sm ${answer?.[String(option.id)] === value ? 'border-[#08777B] bg-[#E6F4F2] font-semibold text-[#075D50]' : 'border-[#CEDAE3] bg-white hover:bg-[#F5F9FA]'}`}>
            <input type="radio" name={`tf-mobile-${item.id}-${option.id}`} aria-label={`${value ? 'Benar' : 'Salah'} untuk pernyataan ${index + 1}`}
              checked={answer?.[String(option.id)] === value} onChange={() => choose(option.id, value)}
              className="size-4 cursor-pointer accent-[#08777B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B]" />
            {value ? 'Benar' : 'Salah'}
          </label>)}
        </div>
      </div>)}
    </fieldset>
  </div>;
}

type Point = { x: number; y: number };
type Connection = { source: Point; target: Point; preview?: boolean };

export function MatchingAnswerView({ item, attemptId, onChange }: {
  item: AttemptItem;
  attemptId: number;
  onChange: (answer: MatchingAnswer | null) => void;
}) {
  const answer = item.answer as MatchingAnswer | null;
  const rootRef = useRef<HTMLDivElement>(null);
  const leftRefs = useRef(new Map<number, HTMLElement>());
  const rightRefs = useRef(new Map<number, HTMLElement>());
  const dragSource = useRef<number | null>(null);
  const suppressPointerClick = useRef(false);
  const [selectedLeft, setSelectedLeft] = useState<number | null>(null);
  const [dragPoint, setDragPoint] = useState<Point | null>(null);
  const [connections, setConnections] = useState<Connection[]>([]);

  const clearSelection = () => setSelectedLeft(null);
  const setPair = (leftId: number, rightId: number) => {
    const occupiedByAnother = Object.entries(answer ?? {}).some(([key, value]) => Number(key) !== leftId && value === rightId);
    if (occupiedByAnother) return;
    const next = { ...(answer ?? {}), [String(leftId)]: rightId };
    onChange(next);
    clearSelection();
  };
  const removePair = (leftId: number) => {
    const next = { ...(answer ?? {}) };
    delete next[String(leftId)];
    onChange(Object.keys(next).length ? next : null);
    clearSelection();
  };
  const selectLeft = (leftId: number) => setSelectedLeft(current => current === leftId ? null : leftId);
  const selectRight = (rightId: number) => {
    if (selectedLeft === null) return;
    setPair(selectedLeft, rightId);
  };

  useLayoutEffect(() => {
    const root = rootRef.current;
    if (!root) return;
    const measure = () => {
      if (!window.matchMedia('(min-width: 640px)').matches) { setConnections([]); return; }
      const bounds = root.getBoundingClientRect();
      const rows: Connection[] = [];
      Object.entries(answer ?? {}).forEach(([leftId, rightId]) => {
        const left = leftRefs.current.get(Number(leftId))?.getBoundingClientRect();
        const right = rightRefs.current.get(rightId)?.getBoundingClientRect();
        if (left && right) rows.push({ source: { x: left.right - bounds.left, y: left.top + left.height / 2 - bounds.top }, target: { x: right.left - bounds.left, y: right.top + right.height / 2 - bounds.top } });
      });
      if (dragSource.current !== null && dragPoint) {
        const left = leftRefs.current.get(dragSource.current)?.getBoundingClientRect();
        if (left) rows.push({ source: { x: left.right - bounds.left, y: left.top + left.height / 2 - bounds.top }, target: { x: dragPoint.x - bounds.left, y: dragPoint.y - bounds.top }, preview: true });
      }
      setConnections(rows);
    };
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(root);
    leftRefs.current.forEach(node => observer.observe(node));
    rightRefs.current.forEach(node => observer.observe(node));
    window.addEventListener('resize', measure);
    return () => { observer.disconnect(); window.removeEventListener('resize', measure); };
  }, [answer, dragPoint, item.id]);

  useLayoutEffect(() => {
    if (dragPoint === null) return;
    const move = (event: PointerEvent) => setDragPoint({ x: event.clientX, y: event.clientY });
    const up = (event: PointerEvent) => {
      const leftId = dragSource.current;
      if (leftId === null) return;
      const target = document.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>('[data-matching-choice]');
      const rightId = Number(target?.dataset.matchingChoice);
      if (target && Number.isFinite(rightId)) setPair(leftId, rightId);
      else setSelectedLeft(leftId);
      suppressPointerClick.current = true;
      window.setTimeout(() => { suppressPointerClick.current = false; }, 0);
      dragSource.current = null;
      setDragPoint(null);
    };
    const cancel = () => {
      dragSource.current = null;
      setDragPoint(null);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    window.addEventListener('pointercancel', cancel);
    return () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      window.removeEventListener('pointercancel', cancel);
    };
  }, [dragPoint]);
  const onKeyActivate = (event: React.KeyboardEvent<HTMLElement>, action: () => void) => {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); action(); }
  };

  const renderLeft = (option: AttemptItem['options'][number], index: number, mobile = false) => {
    const selectedId = answer?.[String(option.id)];
    const chosen = item.choices.find(choice => choice.id === selectedId);
    const selected = selectedLeft === option.id;
    return <div key={option.id} className={`relative flex min-w-0 items-stretch gap-1.5 ${mobile ? '' : 'z-[1]'}`}>
      <div ref={node => { if (!mobile) { if (node) leftRefs.current.set(option.id, node); else leftRefs.current.delete(option.id); } }} role="button" tabIndex={0}
        aria-label={`Pernyataan ${index + 1}: ${plainText(option.body)}`} aria-pressed={selected}
        onClick={() => { if (suppressPointerClick.current) { suppressPointerClick.current = false; return; } selectLeft(option.id); }}
        onKeyDown={event => onKeyActivate(event, () => selectLeft(option.id))}
        onPointerDown={event => { if (!mobile && event.button === 0) { dragSource.current = option.id; setDragPoint({ x: event.clientX, y: event.clientY }); } }}
        className={`relative flex min-h-12 min-w-0 flex-1 cursor-pointer select-none items-center gap-3 rounded-[11px] border px-3 py-2.5 text-left text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B] ${selected ? 'border-[#08777B] bg-[#E6F4F2] ring-1 ring-[#08777B]' : chosen ? 'border-[#08777B] bg-[#F0F8F7]' : 'border-[#D7E3EA] bg-white hover:border-[#70AEB0]'}`}>
        <span className={`flex size-7 shrink-0 items-center justify-center rounded-[8px] text-xs font-bold ${selected || chosen ? 'bg-[#08777B] text-white' : 'bg-[#EFF4F7] text-[#174B56]'}`}>{index + 1}</span>
        <span className="min-w-0 flex-1 break-words"><MathText text={option.body} attemptId={attemptId} /></span>
        <span aria-hidden="true" className={`absolute -right-[7px] top-1/2 size-[14px] -translate-y-1/2 rounded-full border-2 ${chosen ? 'border-[#08777B] bg-[#08777B]' : selected ? 'border-[#08777B] bg-white' : 'border-[#AFC5D2] bg-white'}`} />
      </div>
      {chosen && <button type="button" aria-label={`Lepas pasangan untuk Pernyataan ${index + 1}`} onClick={() => removePair(option.id)}
        className="flex size-11 shrink-0 items-center justify-center rounded-lg text-[#38576A] hover:bg-[#EEF5F6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B] min-[640px]:absolute min-[640px]:-left-5 min-[640px]:-top-3 min-[640px]:z-10 min-[640px]:rounded-full"><span aria-hidden="true" className="min-[640px]:flex min-[640px]:size-5 min-[640px]:items-center min-[640px]:justify-center min-[640px]:rounded-full min-[640px]:border min-[640px]:border-[#C9D8E0] min-[640px]:bg-white">×</span></button>}
      {mobile && chosen && <span className="sr-only">Pasangan: {plainText(chosen.body)}</span>}
    </div>;
  };

  const renderRight = (choice: AttemptItem['choices'][number], index: number, mobile = false) => {
    const usedBy = Object.entries(answer ?? {}).find(([, selected]) => selected === choice.id)?.[0];
    const active = selectedLeft !== null;
    const selected = selectedLeft !== null && answer?.[String(selectedLeft)] === choice.id;
    return <div key={choice.id} ref={node => { if (!mobile) { if (node) rightRefs.current.set(choice.id, node); else rightRefs.current.delete(choice.id); } }}
      role="button" tabIndex={0} data-matching-choice={choice.id} aria-label={`Jawaban ${String.fromCharCode(65 + index)}: ${plainText(choice.body)}`}
      aria-disabled={Boolean(usedBy && !selected)} aria-pressed={selected}
      onClick={() => { if (selectedLeft !== null) selectRight(choice.id); }}
      onKeyDown={event => onKeyActivate(event, () => selectRight(choice.id))}
      className={`relative z-[1] flex min-h-12 min-w-0 cursor-pointer select-none items-center gap-3 rounded-[11px] border px-3 py-2.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B] ${selected || usedBy ? 'border-[#08777B] bg-[#E6F4F2]' : active ? 'border-[#70AEB0] bg-white hover:bg-[#F0F8F7]' : 'border-[#D7E3EA] bg-white'}`}>
      <span aria-hidden="true" className={`absolute -left-[7px] top-1/2 size-[14px] -translate-y-1/2 rounded-full border-2 ${usedBy ? 'border-[#08777B] bg-[#08777B]' : active ? 'border-[#08777B] bg-white' : 'border-[#C8D7E0] bg-white'}`} />
      <span className={`flex size-7 shrink-0 items-center justify-center rounded-[8px] text-xs font-bold ${usedBy ? 'bg-[#08777B] text-white' : 'bg-[#EFF4F7] text-[#174B56]'}`}>{String.fromCharCode(65 + index)}</span>
      <span className="min-w-0 flex-1 break-words"><MathText text={choice.body} attemptId={attemptId} /></span>
    </div>;
  };

  return <div className="mt-4">
    <p className="mb-3 rounded-lg border border-[#D7E3EA] bg-[#F5F9FA] px-3 py-2.5 text-sm text-[#243957]">Tarik titik dari pernyataan ke jawaban yang tepat, atau pilih kedua sisi.</p>
    <div className="hidden min-[640px]:block">
      <div ref={rootRef} className="relative">
        <svg aria-hidden="true" className="matching-answer-lines pointer-events-none absolute inset-0 z-0 size-full overflow-visible">
          {connections.map((line, index) => {
            const distance = Math.max(24, (line.target.x - line.source.x) * 0.45);
            return <path key={index} d={`M ${line.source.x} ${line.source.y} C ${line.source.x + distance} ${line.source.y}, ${line.target.x - distance} ${line.target.y}, ${line.target.x} ${line.target.y}`}
              fill="none" stroke="#08777B" strokeWidth="3" strokeDasharray={line.preview ? '5 4' : undefined} strokeLinecap="round" />;
          })}
        </svg>
        <div className="grid grid-cols-2 gap-x-16 gap-y-2.5">
          <div className="space-y-2.5">{item.options.map((option, index) => renderLeft(option, index))}</div>
          <div className="space-y-2.5">{item.choices.map((choice, index) => renderRight(choice, index))}</div>
        </div>
      </div>
    </div>
    <div className="space-y-4 min-[640px]:hidden">
      <section aria-label="Pernyataan" className="space-y-2.5">
        <h3 className="text-sm font-semibold text-[#174B56]">Pernyataan</h3>
        {item.options.map((option, index) => <div key={option.id} className="space-y-1.5">
          {renderLeft(option, index, true)}
          {answer?.[String(option.id)] && <p className="pl-3 text-xs text-[#37586A]">Pasangan: {plainText(item.choices.find(choice => choice.id === answer[String(option.id)])?.body ?? '')}</p>}
        </div>)}
      </section>
      <section aria-label="Pilihan jawaban" className="space-y-2.5">
        <h3 className="text-sm font-semibold text-[#174B56]">Pilihan jawaban</h3>
        {item.choices.map((choice, index) => renderRight(choice, index, true))}
      </section>
    </div>
  </div>;
}
