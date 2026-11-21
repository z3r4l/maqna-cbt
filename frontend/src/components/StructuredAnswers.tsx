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

  const renderLeft = (option: AttemptItem['options'][number], index: number) => {
    const selectedId = answer?.[String(option.id)];
    const chosen = item.choices.find(choice => choice.id === selectedId);
    const selected = selectedLeft === option.id;
    return <div key={option.id} className="relative z-[1] flex min-w-0 items-stretch">
      <div ref={node => { if (node) leftRefs.current.set(option.id, node); else leftRefs.current.delete(option.id); }} role="button" tabIndex={0}
        aria-label={`Pernyataan ${index + 1}: ${plainText(option.body)}`} aria-pressed={selected}
        onClick={() => { if (suppressPointerClick.current) { suppressPointerClick.current = false; return; } selectLeft(option.id); }}
        onKeyDown={event => onKeyActivate(event, () => selectLeft(option.id))}
        onPointerDown={event => { if (event.button === 0 && (event.pointerType === 'mouse' || (event.target as HTMLElement).closest('[data-matching-dot]'))) { dragSource.current = option.id; setDragPoint({ x: event.clientX, y: event.clientY }); } }}
        className={`relative flex min-h-12 min-w-0 flex-1 cursor-pointer select-none items-center gap-1.5 rounded-[11px] border px-2 py-2 text-left text-xs leading-snug transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B] min-[640px]:gap-3 min-[640px]:px-3 min-[640px]:py-2.5 min-[640px]:text-sm ${selected ? 'border-[#08777B] bg-[#E6F4F2] ring-1 ring-[#08777B]' : chosen ? 'border-[#08777B] bg-[#F0F8F7]' : 'border-[#D7E3EA] bg-white hover:border-[#70AEB0]'}`}>
        <span className={`flex size-6 shrink-0 items-center justify-center rounded-[7px] text-xs font-bold min-[640px]:size-7 min-[640px]:rounded-[8px] ${selected || chosen ? 'bg-[#08777B] text-white' : 'bg-[#EFF4F7] text-[#174B56]'}`}>{index + 1}</span>
        <span className="min-w-0 flex-1 break-words [overflow-wrap:anywhere]"><MathText text={option.body} attemptId={attemptId} /></span>
        <span data-matching-dot aria-hidden="true" className="absolute -right-4 top-1/2 z-[2] flex size-8 -translate-y-1/2 items-center justify-center touch-none"><span className={`size-[14px] rounded-full border-2 ${chosen ? 'border-[#08777B] bg-[#08777B]' : selected ? 'border-[#08777B] bg-white' : 'border-[#AFC5D2] bg-white'}`} /></span>
      </div>
      {chosen && <button type="button" aria-label={`Lepas pasangan untuk Pernyataan ${index + 1}`} onClick={() => removePair(option.id)}
        className="absolute -left-5 -top-3 z-10 flex size-11 items-center justify-center rounded-full text-[#38576A] hover:bg-[#EEF5F6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B]"><span aria-hidden="true" className="flex size-5 items-center justify-center rounded-full border border-[#C9D8E0] bg-white">×</span></button>}
      {chosen && <span className="sr-only">Pasangan: {plainText(chosen.body)}</span>}
    </div>;
  };

  const renderRight = (choice: AttemptItem['choices'][number], index: number) => {
    const usedBy = Object.entries(answer ?? {}).find(([, selected]) => selected === choice.id)?.[0];
    const active = selectedLeft !== null;
    const selected = selectedLeft !== null && answer?.[String(selectedLeft)] === choice.id;
    return <div key={choice.id} ref={node => { if (node) rightRefs.current.set(choice.id, node); else rightRefs.current.delete(choice.id); }}
      role="button" tabIndex={0} data-matching-choice={choice.id} aria-label={`Jawaban ${String.fromCharCode(65 + index)}: ${plainText(choice.body)}`}
      aria-disabled={Boolean(usedBy && !selected)} aria-pressed={selected}
      onClick={() => { if (selectedLeft !== null) selectRight(choice.id); }}
      onKeyDown={event => onKeyActivate(event, () => selectRight(choice.id))}
      className={`relative z-[1] flex min-h-12 min-w-0 cursor-pointer select-none items-center gap-1.5 rounded-[11px] border px-2 py-2 text-xs leading-snug transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00666B] min-[640px]:gap-3 min-[640px]:px-3 min-[640px]:py-2.5 min-[640px]:text-sm ${selected || usedBy ? 'border-[#08777B] bg-[#E6F4F2]' : active ? 'border-[#70AEB0] bg-white hover:bg-[#F0F8F7]' : 'border-[#D7E3EA] bg-white'}`}>
      <span aria-hidden="true" className={`absolute -left-[7px] top-1/2 size-[14px] -translate-y-1/2 rounded-full border-2 ${usedBy ? 'border-[#08777B] bg-[#08777B]' : active ? 'border-[#08777B] bg-white' : 'border-[#C8D7E0] bg-white'}`} />
      <span className={`flex size-6 shrink-0 items-center justify-center rounded-[7px] text-xs font-bold min-[640px]:size-7 min-[640px]:rounded-[8px] ${usedBy ? 'bg-[#08777B] text-white' : 'bg-[#EFF4F7] text-[#174B56]'}`}>{String.fromCharCode(65 + index)}</span>
      <span className="min-w-0 flex-1 break-words [overflow-wrap:anywhere]"><MathText text={choice.body} attemptId={attemptId} /></span>
    </div>;
  };

  return <div className="mt-4">
    <p className="mb-3 rounded-lg border border-[#D7E3EA] bg-[#F5F9FA] px-3 py-2.5 text-sm text-[#243957]">Tarik titik dari pernyataan ke jawaban yang tepat, atau pilih kedua sisi.</p>
    <div ref={rootRef} className="relative min-w-0">
        <svg aria-hidden="true" className="matching-answer-lines pointer-events-none absolute inset-0 z-0 size-full overflow-visible">
          {connections.map((line, index) => {
            const distance = Math.max(8, (line.target.x - line.source.x) * 0.4);
            return <path key={index} d={`M ${line.source.x} ${line.source.y} C ${line.source.x + distance} ${line.source.y}, ${line.target.x - distance} ${line.target.y}, ${line.target.x} ${line.target.y}`}
              fill="none" stroke="#08777B" strokeWidth="3" strokeDasharray={line.preview ? '5 4' : undefined} strokeLinecap="round" />;
          })}
        </svg>
        <div data-matching-columns className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-x-7 min-[640px]:gap-x-16">
          <section aria-label="Pernyataan" className="min-w-0 space-y-2.5">{item.options.map((option, index) => renderLeft(option, index))}</section>
          <section aria-label="Pilihan jawaban" className="min-w-0 space-y-2.5">{item.choices.map((choice, index) => renderRight(choice, index))}</section>
        </div>
    </div>
  </div>;
}
