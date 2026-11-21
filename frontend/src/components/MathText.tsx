import { createElement, type ReactNode } from 'react';
import katex from 'katex';
import 'katex/dist/katex.min.css';
import { safeKatexOptions } from '@/lib/math';

function mathParts(text: string): ReactNode[] {
  return text.split(/(\$\$[\s\S]+?\$\$|\$[^$\n]+\$)/g).map((part, index) => {
    const display = part.startsWith('$$') && part.endsWith('$$');
    const inline = !display && part.startsWith('$') && part.endsWith('$');
    if (!display && !inline) return <span key={index}>{part}</span>;
    try {
      const html = katex.renderToString(part.slice(display ? 2 : 1, display ? -2 : -1),
        { ...safeKatexOptions, displayMode: display });
      return <span key={index} className={display ? 'my-2 block max-w-full overflow-x-auto' : 'inline-block max-w-full overflow-x-auto align-middle'} dangerouslySetInnerHTML={{ __html: html }} />;
    } catch {
      return <span key={index} className="font-mono">{part}</span>;
    }
  });
}

const allowed = new Set(['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'sub', 'sup', 'code', 'blockquote', 'a', 'img', 'iframe', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'hr']);
function richNode(node: Node, key: number, attemptId?: number, assetPrefix?: string): ReactNode {
  if (node.nodeType === Node.TEXT_NODE) return <span key={key}>{mathParts(node.textContent ?? '')}</span>;
  if (node.nodeType !== Node.ELEMENT_NODE) return null;
  const element = node as Element;
  const tag = element.tagName.toLowerCase();
  if (!allowed.has(tag)) return null;
  const children = Array.from(element.childNodes).map(child => richNode(child, key + 1, attemptId, assetPrefix));
  if (tag === 'br') return <br key={key} />;
  if (tag === 'a') {
    const href = element.getAttribute('href') ?? '';
    return /^https:\/\/[^\s]+$/i.test(href) ? <a key={key} href={href} rel="noopener noreferrer" className="text-[#155451] underline">{children}</a> : <span key={key}>{children}</span>;
  }
  if (tag === 'img') {
    const src = element.getAttribute('src') ?? '';
    const local = /^\/api\/v1\/question-assets\/([1-9][0-9]*)$/.exec(src);
    const resolved = local && attemptId ? `/api/v1/student/attempts/${attemptId}/assets/${local[1]}` : local && assetPrefix ? `${assetPrefix}${local[1]}` : src;
    return /^https:\/\/[^\s]+$/i.test(resolved) || local ? <img key={key} src={resolved} alt={element.getAttribute('alt') ?? 'Gambar soal'} loading="lazy" className="my-2 max-h-72 max-w-full rounded-lg border object-contain" /> : null;
  }
  if (tag === 'iframe') {
    const src = element.getAttribute('src') ?? '';
    return /^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}$/.test(src) ? <iframe key={key} src={src} title={element.getAttribute('title') ?? 'Video soal'} loading="lazy" className="my-3 aspect-video w-full max-w-xl rounded-lg border" allowFullScreen /> : null;
  }
  if (tag === 'hr') return <hr key={key} className="my-3 border-t" />;
  if (['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'].includes(tag)) {
    const span = ['colspan', 'rowspan'].map(name => [name, element.getAttribute(name)] as const).find(([, value]) => value && /^[1-9][0-9]?$/.test(value));
    const style = element.getAttribute('style') === 'text-align:left' || element.getAttribute('style') === 'text-align:center' || element.getAttribute('style') === 'text-align:right' ? { textAlign: element.getAttribute('style')!.slice(11) as 'left' | 'center' | 'right' } : undefined;
    return createElement(tag, { key, className: tag === 'table' ? 'my-3 w-full border-collapse' : 'border border-slate-300 px-2 py-1 align-top', ...(tag === 'td' || tag === 'th' ? { [span?.[0] === 'rowspan' ? 'rowSpan' : 'colSpan']: span ? Number(span[1]) : undefined } : {}), style }, children);
  }
  const block = ['p', 'ul', 'ol', 'li', 'blockquote'].includes(tag);
  const className = tag === 'blockquote' ? 'my-2 block border-l-2 pl-3 italic' : tag === 'li' ? 'ml-5 block list-item' : block ? 'my-1 block' : undefined;
  return createElement(block ? 'span' : tag, { key, className, role: tag === 'ul' || tag === 'ol' ? 'list' : tag === 'li' ? 'listitem' : undefined }, children);
}

export default function MathText({ text, className = '', attemptId, assetPrefix }: { text: string; className?: string; attemptId?: number; assetPrefix?: string }) {
  const rich = /<\/?(?:p|br|strong|b|em|i|u|ul|ol|li|sub|sup|code|blockquote|a|img|iframe|table|thead|tbody|tfoot|tr|th|td|hr)\b/i.test(text);
  if (!rich) return <span className={'whitespace-pre-wrap break-words ' + className}>{mathParts(text)}</span>;
  const fragment = new DOMParser().parseFromString(text, 'text/html').body;
  return <div className={'break-words ' + className}>{Array.from(fragment.childNodes).map((node, index) => richNode(node, index, attemptId, assetPrefix))}</div>;
}
