<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

final class QuestionContent
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'sub', 'sup', 'code', 'blockquote', 'a', 'img', 'iframe', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'hr'];

    public static function sanitize(string $html, int $max = 20000): string
    {
        if (mb_strlen($html) > $max * 2) throw new \DomainException('Teks terlalu panjang.');
        if (! preg_match('/<\/?(?:p|br|strong|b|em|i|u|ul|ol|li|sub|sup|code|blockquote|a|img|iframe|table|thead|tbody|tfoot|tr|th|td|hr)\b/i', $html)) {
            $plain = trim(strip_tags($html));
            if ($plain === '' || mb_strlen($plain) > $max) throw new \DomainException('Teks kosong atau terlalu panjang.');
            return $plain;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $prior = libxml_use_internal_errors(true);
        $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body><div id="content">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
        $root = $document->getElementById('content');
        if ($root === null) throw new \DomainException('HTML tidak valid.');
        self::clean($root);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
        $result = trim($result);
        if (trim(strip_tags($result)) === '' || mb_strlen(strip_tags($result)) > $max) throw new \DomainException('Teks kosong atau terlalu panjang.');
        return $result;
    }

    private static function clean(DOMNode $parent): void
    {
        for ($child = $parent->firstChild; $child !== null;) {
            $next = $child->nextSibling;
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'svg', 'object', 'embed', 'form', 'input', 'video', 'audio'], true)) {
                    $parent->removeChild($child);
                } elseif (! in_array($tag, self::TAGS, true)) {
                    self::clean($child);
                    while ($child->firstChild !== null) $parent->insertBefore($child->firstChild, $child);
                    $parent->removeChild($child);
                } else {
                    $href = $child->getAttribute('href');
                    $src = $child->getAttribute('src');
                    $alt = $child->getAttribute('alt');
                    $title = $child->getAttribute('title');
                    $style = $child->getAttribute('style');
                    $colspan = $child->getAttribute('colspan');
                    $rowspan = $child->getAttribute('rowspan');
                    while ($child->attributes->length > 0) $child->removeAttributeNode($child->attributes->item(0));
                    if (in_array($tag, ['p', 'th', 'td'], true) && preg_match('/^text-align:\s*(left|center|right)\s*;?$/i', trim($style), $alignment)) $child->setAttribute('style', 'text-align:' . strtolower($alignment[1]));
                    if (in_array($tag, ['th', 'td'], true)) {
                        if (ctype_digit($colspan) && (int) $colspan >= 1 && (int) $colspan <= 12) $child->setAttribute('colspan', $colspan);
                        if (ctype_digit($rowspan) && (int) $rowspan >= 1 && (int) $rowspan <= 12) $child->setAttribute('rowspan', $rowspan);
                    }
                    if ($tag === 'a' && preg_match('~^https://[^\s]+$~i', $href)) { $child->setAttribute('href', $href); $child->setAttribute('rel', 'noopener noreferrer'); }
                    if ($tag === 'img' && (preg_match('~^https://[^\s]+$~i', $src) || preg_match('~^/api/v1/question-assets/[1-9][0-9]*$~', $src) || preg_match('~^/api/v1/question-media/temp/[A-Za-z0-9_-]{32,80}$~', $src))) { $child->setAttribute('src', $src); $child->setAttribute('alt', mb_substr($alt ?: 'Gambar soal', 0, 160)); }
                    if ($tag === 'iframe' && preg_match('~^https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}$~', $src)) {
                        $child->setAttribute('src', $src); $child->setAttribute('title', mb_substr($title ?: 'Video soal', 0, 160)); $child->setAttribute('loading', 'lazy');
                    }
                    if (($tag === 'img' || $tag === 'iframe') && ! $child->hasAttribute('src')) $parent->removeChild($child);
                    else self::clean($child);
                }
            } elseif ($child->nodeType !== XML_TEXT_NODE) $parent->removeChild($child);
            $child = $next;
        }
    }
}
