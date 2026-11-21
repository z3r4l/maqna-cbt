import { useEffect, useMemo, useRef, useState } from 'react';
import { Dialog } from 'radix-ui';
import katex from 'katex';
import { Check, Code2, Eye, Eraser, FunctionSquare, X } from 'lucide-react';
import { safeKatexOptions } from '@/lib/math';

const palettes = [
  { name: 'Pecahan & Akar', entries: [['Pecahan', '\\frac{a}{b}'], ['Akar kuadrat', '\\sqrt{x}'], ['Akar pangkat n', '\\sqrt[n]{x}'], ['Pangkat dua', 'x^{2}'], ['Pangkat dan indeks', 'x^{2}_{i}'], ['Nilai mutlak', '\\left|x\\right|']] },
  { name: 'Operasi & Simbol', entries: [['Tambah kurang', 'a \\pm b'], ['Kali bagi', 'a \\times b'], ['Kurang lebih', '\\approx'], ['Tidak sama dengan', '\\neq'], ['Lebih besar sama dengan', '\\geq'], ['Himpunan bilangan', '\\in \\mathbb{R}']] },
  { name: 'Huruf Yunani', entries: [['Alfa', '\\alpha'], ['Beta', '\\beta'], ['Gamma', '\\gamma'], ['Delta', '\\Delta'], ['Theta', '\\theta'], ['Pi', '\\pi'], ['Sigma', '\\sigma'], ['Omega', '\\omega']] },
  { name: 'Kalkulus & Vektor', entries: [['Jumlah', '\\sum_{i=1}^{n} x_i'], ['Integral', '\\int_{a}^{b} f(x)\\,dx'], ['Limit', '\\lim_{x \\to a} f(x)'], ['Turunan', '\\frac{dy}{dx}'], ['Vektor', '\\vec{v}'], ['Gradien', '\\nabla f']] },
  { name: 'Matriks & Sistem', entries: [['Matriks 2×2', '\\begin{bmatrix}a & b\\\\c & d\\end{bmatrix}'], ['Determinan', '\\begin{vmatrix}a & b\\\\c & d\\end{vmatrix}'], ['Sistem persamaan', '\\begin{cases}x+y=2\\\\x-y=0\\end{cases}']] },
] as const;

type Props = {
  open: boolean;
  initialLatex: string;
  initialDisplay: boolean;
  onOpenChange: (open: boolean) => void;
  onInsert: (latex: string, display: boolean) => void;
};

export function MathFormulaDialog({ open, initialLatex, initialDisplay, onOpenChange, onInsert }: Props) {
  const [category, setCategory] = useState(0);
  const [latex, setLatex] = useState(initialLatex);
  const [display, setDisplay] = useState(initialDisplay);
  const codeRef = useRef<HTMLTextAreaElement>(null);
  const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const selection = useRef({ start: initialLatex.length, end: initialLatex.length });
  useEffect(() => {
    if (!open) return;
    setLatex(initialLatex);
    setDisplay(initialDisplay);
    setCategory(0);
    selection.current = { start: initialLatex.length, end: initialLatex.length };
  }, [open, initialLatex, initialDisplay]);
  const rendered = useMemo(() => {
    if (!latex.trim()) return { html: '', error: 'Masukkan kode LaTeX untuk melihat pratinjau.' };
    try {
      return { html: katex.renderToString(latex, { ...safeKatexOptions, displayMode: display }), error: '' };
    } catch (reason) {
      return { html: '', error: reason instanceof Error ? reason.message : 'Kode LaTeX tidak dapat dirender.' };
    }
  }, [latex, display]);

  function insertTemplate(template: string) {
    const start = selection.current.start;
    const end = selection.current.end;
    const next = latex.slice(0, start) + template + latex.slice(end);
    const caret = start + template.length;
    setLatex(next);
    selection.current = { start: caret, end: caret };
    requestAnimationFrame(() => {
      codeRef.current?.focus();
      codeRef.current?.setSelectionRange(caret, caret);
    });
  }
  function moveCategory(event: React.KeyboardEvent<HTMLButtonElement>, current: number) {
    const next = event.key === 'ArrowRight' ? (current + 1) % palettes.length
      : event.key === 'ArrowLeft' ? (current + palettes.length - 1) % palettes.length
        : event.key === 'Home' ? 0 : event.key === 'End' ? palettes.length - 1 : null;
    if (next === null) return;
    event.preventDefault();
    setCategory(next);
    tabRefs.current[next]?.focus();
  }

  return <Dialog.Root open={open} onOpenChange={onOpenChange}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-[70] bg-[#102C2B]/65" />
      <Dialog.Content aria-describedby="math-formula-description" onOpenAutoFocus={event => { event.preventDefault(); codeRef.current?.focus(); }} className="fixed inset-0 z-[70] flex min-h-0 flex-col overflow-hidden bg-white shadow-2xl sm:inset-x-1/2 sm:top-1/2 sm:h-[min(90dvh,720px)] sm:w-[min(760px,calc(100vw-32px))] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl">
        <header className="flex shrink-0 items-center justify-between gap-3 bg-[#155451] px-4 py-3 text-white sm:px-5">
          <div className="flex min-w-0 items-center gap-2"><FunctionSquare size={20} aria-hidden="true" /><div><Dialog.Title className="text-base font-semibold">Editor Rumus Matematika (LaTeX &amp; KaTeX)</Dialog.Title><Dialog.Description id="math-formula-description" className="text-xs text-white/80">Tulis LaTeX dan periksa hasilnya sebelum disisipkan.</Dialog.Description></div></div>
          <Dialog.Close aria-label="Tutup editor rumus" className="flex size-10 shrink-0 items-center justify-center rounded-md hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><X size={20} /></Dialog.Close>
        </header>
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-3 sm:p-5">
          <section aria-label="Palet simbol dan template">
            <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-[#344246]"><Code2 size={16} />Palet Simbol &amp; Template Rumus Cepat</h3>
            <div className="flex gap-1 overflow-x-auto rounded-md border bg-[#F6F8F9] p-1" role="tablist" aria-label="Kategori rumus">
              {palettes.map((palette, index) => <button key={palette.name} ref={element => { tabRefs.current[index] = element; }} id={'math-palette-tab-' + index} type="button" role="tab" tabIndex={category === index ? 0 : -1} aria-selected={category === index} aria-controls="math-palette-content" onClick={() => setCategory(index)} onKeyDown={event => moveCategory(event, index)} className={'min-h-10 shrink-0 rounded px-3 text-xs font-semibold ' + (category === index ? 'bg-[#155451] text-white' : 'text-[#536765] hover:bg-[#E5F3EF]')}>{palette.name}</button>)}
            </div>
            <div id="math-palette-content" role="tabpanel" aria-labelledby={'math-palette-tab-' + category} className="mt-2 flex min-h-16 flex-wrap content-start gap-2 rounded-md border p-2">
              {palettes[category].entries.map(([label, template]) => <button key={label} type="button" aria-label={label} title={label} onClick={() => insertTemplate(template)} className="min-h-10 min-w-11 rounded-md border border-[#C9D5E0] bg-white px-3 text-sm text-[#155451] hover:border-[#155451] hover:bg-[#F3F8F7] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]"><span aria-hidden="true" dangerouslySetInnerHTML={{ __html: katex.renderToString(template, { ...safeKatexOptions, displayMode: false }) }} /></button>)}
            </div>
          </section>
          <section>
            <div className="mb-2 flex items-center justify-between gap-2"><label htmlFor="math-latex-code" className="flex items-center gap-2 text-sm font-semibold"><Code2 size={16} className="text-[#087E8B]" />Kode Rumus LaTeX</label><button type="button" onClick={() => { setLatex(''); selection.current = { start: 0, end: 0 }; codeRef.current?.focus(); }} className="inline-flex min-h-9 items-center gap-1 rounded px-2 text-xs font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-[#155451]"><Eraser size={14} />Bersihkan</button></div>
            <textarea ref={codeRef} id="math-latex-code" aria-label="Kode Rumus LaTeX" value={latex} onChange={event => { setLatex(event.target.value); selection.current = { start: event.target.selectionStart, end: event.target.selectionEnd }; }} onSelect={event => { selection.current = { start: event.currentTarget.selectionStart, end: event.currentTarget.selectionEnd }; }} rows={3} spellCheck={false} className="block min-h-24 w-full resize-y rounded-md border border-[#B6C9C5] bg-white p-3 font-mono text-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#155451]" />
          </section>
          <fieldset className="flex flex-wrap gap-x-5 gap-y-2 rounded-md border bg-[#F6F8F9] p-3 text-sm">
            <legend className="px-1 text-xs font-semibold text-[#536765]">Format tampilan</legend>
            <label className="flex min-h-9 items-center gap-2"><input type="radio" name="math-display-mode" checked={!display} onChange={() => setDisplay(false)} className="accent-[#087E8B]" />Inline <code className="text-[#087E8B]">$...$</code></label>
            <label className="flex min-h-9 items-center gap-2"><input type="radio" name="math-display-mode" checked={display} onChange={() => setDisplay(true)} className="accent-[#087E8B]" />Baris Baru / Display <code className="text-[#087E8B]">$$...$$</code></label>
          </fieldset>
          <section aria-label="Pratinjau rumus" className="overflow-hidden rounded-lg border border-[#D7E0DE]">
            <div className="flex items-center gap-2 border-b px-3 py-2 text-sm font-semibold text-[#536765]"><Eye size={16} />Pratinjau Real-Time (KaTeX)</div>
            <div className="flex min-h-24 items-center justify-center overflow-x-auto bg-[#F7FAFB] p-4" aria-live="polite">
              {rendered.html ? <div className="max-w-full overflow-x-auto" dangerouslySetInnerHTML={{ __html: rendered.html }} /> : <p role="status" className="text-sm text-red-700">{rendered.error}</p>}
            </div>
            {rendered.html && <p role="status" className="border-t px-3 py-1 text-center text-xs font-semibold text-emerald-700">Valid LaTeX</p>}
          </section>
        </div>
        <footer className="flex shrink-0 flex-wrap justify-between gap-2 border-t bg-[#F6F8F9] px-3 py-3 sm:px-5">
          <Dialog.Close className="inline-flex min-h-10 items-center gap-2 rounded-md border border-[#B6C9C5] bg-white px-3 text-sm font-semibold text-[#155451] hover:bg-[#E5F3EF] focus-visible:outline-2 focus-visible:outline-[#155451]">Batal</Dialog.Close>
          <button type="button" disabled={!latex.trim() || !rendered.html} onClick={() => onInsert(latex.trim(), display)} className="inline-flex min-h-10 items-center gap-2 rounded-md bg-[#087E8B] px-4 text-sm font-semibold text-white hover:bg-[#066C75] disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#155451]"><Check size={16} />Sisipkan Rumus ke Soal</button>
        </footer>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
