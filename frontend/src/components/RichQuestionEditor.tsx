import { useEffect, useRef, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import HorizontalRule from '@tiptap/extension-horizontal-rule';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { Mathematics } from '@tiptap/extension-mathematics';
import { AlignCenter, AlignLeft, AlignRight, Bold, Code2, FileCode2, ImagePlus, Italic, Link2, List, ListOrdered, Maximize2, Minus, Plus, Strikethrough, Table2, Underline as UnderlineIcon, WandSparkles } from 'lucide-react';
import { api } from '@/lib/api';
import { MathFormulaDialog } from '@/components/MathFormulaDialog';

function initialHtml(value: string) {
  if (/<\/?(?:p|br|strong|b|em|i|u|ul|ol|li|sub|sup|code|blockquote|a|img|iframe|table|hr)\b/i.test(value)) return value;
  const escaped = value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
  return `<p>${escaped.replaceAll('\n', '<br>')}</p>`;
}
function hydrateMath(value: string) {
  const document = new DOMParser().parseFromString(initialHtml(value), 'text/html');
  document.querySelectorAll('p').forEach(paragraph => {
    const match = paragraph.textContent?.trim().match(/^\$\$([\s\S]+)\$\$$/);
    if (!match) return;
    const node = document.createElement('div');
    node.setAttribute('data-type', 'block-math');
    node.setAttribute('data-latex', match[1]);
    paragraph.replaceWith(node);
  });
  const displayWalker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const displayNodes: Text[] = [];
  while (displayWalker.nextNode()) {
    const node = displayWalker.currentNode as Text;
    if (!node.parentElement?.closest('td, th') || node.parentElement.closest('code, pre')) continue;
    if (/^\s*\$\$[\s\S]+\$\$\s*$/.test(node.data)) displayNodes.push(node);
  }
  displayNodes.forEach(textNode => {
    const match = textNode.data.trim().match(/^\$\$([\s\S]+)\$\$$/);
    if (!match) return;
    const node = document.createElement('div');
    node.setAttribute('data-type', 'block-math');
    node.setAttribute('data-latex', match[1]);
    textNode.replaceWith(node);
  });
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const textNodes: Text[] = [];
  while (walker.nextNode()) {
    const node = walker.currentNode as Text;
    if (node.parentElement?.closest('code, pre, [data-type="inline-math"], [data-type="block-math"]')) continue;
    if (/\$(?!\$)[^$\n]+\$(?!\$)/.test(node.data)) textNodes.push(node);
  }
  textNodes.forEach(textNode => {
    const expression = /\$(?!\$)([^$\n]+)\$(?!\$)/g;
    const fragment = document.createDocumentFragment();
    let cursor = 0;
    for (const match of textNode.data.matchAll(expression)) {
      const start = match.index ?? 0;
      if (start > cursor) fragment.append(document.createTextNode(textNode.data.slice(cursor, start)));
      const math = document.createElement('span');
      math.setAttribute('data-type', 'inline-math');
      math.setAttribute('data-latex', match[1]);
      fragment.append(math);
      cursor = start + match[0].length;
    }
    if (cursor < textNode.data.length) fragment.append(document.createTextNode(textNode.data.slice(cursor)));
    textNode.replaceWith(fragment);
  });
  return document.body.innerHTML;
}
function serializeMath(value: string) {
  const document = new DOMParser().parseFromString(value, 'text/html');
  document.querySelectorAll('[data-type="inline-math"], [data-type="block-math"]').forEach(node => {
    const latex = node.getAttribute('data-latex') ?? node.textContent ?? '';
    const display = node.getAttribute('data-type') === 'block-math';
    node.replaceWith(document.createTextNode(display ? `$$${latex}$$` : `$${latex}$`));
  });
  return document.body.innerHTML;
}

type Props = { value: string; onChange: (value: string) => void; disabled: boolean; ariaLabel?: string; compact?: boolean };
type FormulaState = { latex: string; display: boolean; range: { from: number; to: number }; nodeType?: 'inlineMath' | 'blockMath'; sourceRange?: { from: number; to: number } };
export function RichQuestionEditor({ value, onChange, disabled, ariaLabel = 'Pertanyaan', compact = false }: Props) {
  const [source, setSource] = useState(false);
  const [html, setHtml] = useState(value);
  const [formula, setFormula] = useState<FormulaState | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const sourceRef = useRef<HTMLTextAreaElement>(null);
  const rootRef = useRef<HTMLDivElement>(null);
  const editor = useEditor({
    editable: !disabled,
    editorProps: { attributes: { 'aria-label': ariaLabel, class: 'tiptap' } },
    extensions: [StarterKit.configure({ horizontalRule: false, link: false }), Underline, HorizontalRule, Link.configure({ openOnClick: false, protocols: ['https'] }), Image.configure({ inline: true, allowBase64: false }), TextAlign.configure({ types: ['heading', 'paragraph'] }), Table.configure({ resizable: true }), TableRow, TableHeader, TableCell, Mathematics.configure({
      inlineOptions: { onClick: (node, pos) => setFormula({ latex: node.attrs.latex ?? '', display: false, nodeType: 'inlineMath', range: { from: pos, to: pos + node.nodeSize } }) },
      blockOptions: { onClick: (node, pos) => setFormula({ latex: node.attrs.latex ?? '', display: true, nodeType: 'blockMath', range: { from: pos, to: pos + node.nodeSize } }) },
    })],
    content: hydrateMath(value),
    onUpdate: ({ editor: current }) => { const next = serializeMath(current.getHTML()); setHtml(next); onChange(next); },
  });
  useEffect(() => { if (editor?.isInitialized && editor.view && editor.getHTML() !== hydrateMath(value)) editor.commands.setContent(hydrateMath(value), { emitUpdate: false }); setHtml(value); }, [value, editor]);
  useEffect(() => { if (editor?.isInitialized && editor.view) editor.setEditable(!disabled); }, [disabled, editor]);
  function command(action: () => void) { if (!disabled && editor) action(); }
  function openMath() {
    if (disabled) return;
    if (source) {
      const textarea = sourceRef.current;
      const from = textarea?.selectionStart ?? html.length;
      const to = textarea?.selectionEnd ?? from;
      const selected = html.slice(from, to).match(/^\$\$([\s\S]+)\$\$$|^\$([^$\n]+)\$$/);
      setFormula({ latex: selected?.[1] ?? selected?.[2] ?? '', display: Boolean(selected?.[1]), range: { from: 0, to: 0 }, sourceRange: { from, to } });
      return;
    }
    const selection = editor?.state.selection;
    if (!selection || !editor) return;
    const from = selection.from;
    const to = selection.to;
    const selected = editor.state.doc.textBetween(from, to, '\n').match(/^\$\$([\s\S]+)\$\$$|^\$([^$\n]+)\$$/);
    setFormula({ latex: selected?.[1] ?? selected?.[2] ?? '', display: Boolean(selected?.[1]), range: { from, to } });
  }
  function insertMath(latex: string, display: boolean) {
    if (!formula || disabled) return;
    if (formula.sourceRange) {
      const { from, to } = formula.sourceRange;
      const token = display ? `$$${latex}$$` : `$${latex}$`;
      const next = html.slice(0, from) + token + html.slice(to);
      setHtml(next);
      onChange(serializeMath(next));
      setFormula(null);
      requestAnimationFrame(() => { sourceRef.current?.focus(); sourceRef.current?.setSelectionRange(from + token.length, from + token.length); });
      return;
    }
    command(() => {
      if (!editor) return;
      if (formula.nodeType === 'inlineMath') editor.commands.updateInlineMath({ latex, pos: formula.range.from });
      else if (formula.nodeType === 'blockMath') editor.commands.updateBlockMath({ latex, pos: formula.range.from });
      else editor.chain().focus().insertContentAt(formula.range, { type: display ? 'blockMath' : 'inlineMath', attrs: { latex } }).run();
    });
    setFormula(null);
  }
  function insertLink() { const url = window.prompt('Tautan HTTPS'); if (url && /^https:\/\//i.test(url)) command(() => editor?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()); }
  async function upload(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]; event.target.value = '';
    if (!file || !editor) return;
    try { const result = await api.uploadQuestionMedia(file); editor.chain().focus().setImage({ src: result.src, alt: file.name }).run(); }
    catch (reason) { window.alert((reason as Error).message); }
  }
  const button = (label: string, Icon: typeof Bold, run: () => void, active = false) => <button key={label} type="button" aria-label={label} title={label} disabled={disabled} onClick={run} className={'question-editor-tool' + (active ? ' is-active' : '')}><Icon size={16} /></button>;
  const toolbar = <div className="question-editor-toolbar" role="toolbar" aria-label="Format teks">
    {button('Format teks', WandSparkles, () => command(() => editor?.chain().focus().unsetAllMarks().clearNodes().run()))}
    {button('Tebal', Bold, () => command(() => editor?.chain().focus().toggleBold().run()), !!editor?.isActive('bold'))}{button('Miring', Italic, () => command(() => editor?.chain().focus().toggleItalic().run()), !!editor?.isActive('italic'))}{button('Garis bawah', UnderlineIcon, () => command(() => editor?.chain().focus().toggleUnderline().run()), !!editor?.isActive('underline'))}{button('Coret', Strikethrough, () => command(() => editor?.chain().focus().toggleStrike().run()), !!editor?.isActive('strike'))}
    {button('Rumus', Code2, openMath)}{button('Daftar berpoin', List, () => command(() => editor?.chain().focus().toggleBulletList().run()), !!editor?.isActive('bulletList'))}{button('Daftar bernomor', ListOrdered, () => command(() => editor?.chain().focus().toggleOrderedList().run()), !!editor?.isActive('orderedList'))}
    {button('Rata kiri', AlignLeft, () => command(() => editor?.chain().focus().setTextAlign('left').run()))}{button('Rata tengah', AlignCenter, () => command(() => editor?.chain().focus().setTextAlign('center').run()))}{button('Rata kanan', AlignRight, () => command(() => editor?.chain().focus().setTextAlign('right').run()))}
    {button('Sisipkan tabel', Table2, () => command(() => editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()))}{button('Unggah gambar', ImagePlus, () => fileRef.current?.click())}{button('Tautan', Link2, insertLink)}{button('Garis pemisah', Minus, () => command(() => editor?.chain().focus().setHorizontalRule().run()))}
    {button(source ? 'Tutup HTML' : 'Sunting HTML', source ? FileCode2 : Code2, () => { setSource(!source); if (source && editor) editor.commands.setContent(hydrateMath(html)); })}{button('Layar penuh', Maximize2, () => rootRef.current?.classList.toggle('question-editor--fullscreen'))}
    <input ref={fileRef} className="hidden" type="file" accept="image/png,image/jpeg,image/webp" onChange={event => void upload(event)} />
  </div>;
  return <div ref={rootRef} className={'question-editor' + (compact ? ' question-editor--compact' : '')}>
    {toolbar}
    {source ? <textarea ref={sourceRef} aria-label={ariaLabel + ' HTML'} disabled={disabled} className="question-editor-source" value={html} onChange={event => { setHtml(event.target.value); onChange(serializeMath(event.target.value)); }} /> : <EditorContent editor={editor} aria-label={ariaLabel} className="question-editor-content" />}
    <div className="question-editor-grip" aria-hidden="true"><Plus size={13} /></div>
    <MathFormulaDialog open={formula !== null} initialLatex={formula?.latex ?? ''} initialDisplay={formula?.display ?? false} onOpenChange={open => { if (!open) setFormula(null); }} onInsert={insertMath} />
  </div>;
}
