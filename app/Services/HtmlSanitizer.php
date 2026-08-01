<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

class HtmlSanitizer
{
    /**
     * Allowlisted tags. Everything else is unwrapped (children preserved) or dropped.
     */
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup',
        'div', 'em', 'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'i', 'img', 'li', 'mark', 'ol', 'p', 'pre', 's', 'small', 'span',
        'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th',
        'thead', 'tr', 'u', 'ul',
    ];

    /**
     * Tags removed entirely, together with their content.
     */
    private const DANGEROUS_TAGS = [
        'audio', 'base', 'body', 'button', 'canvas', 'embed', 'form', 'head',
        'html', 'iframe', 'input', 'link', 'math', 'meta', 'noscript', 'object',
        'optgroup', 'option', 'script', 'select', 'source', 'style', 'svg',
        'template', 'textarea', 'title', 'track', 'video',
    ];

    /**
     * Attributes allowed on every tag.
     */
    private const GLOBAL_ATTRIBUTES = ['class', 'dir', 'id', 'lang', 'style', 'title'];

    /**
     * Tag-specific attributes merged with the global allowlist.
     */
    private const ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'blockquote' => ['cite'],
        'code' => [],
        'col' => ['span'],
        'colgroup' => ['span'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'li' => ['value'],
        'ol' => ['start', 'type'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    /**
     * URLs allowed as link destinations.
     */
    private const SAFE_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitize an HTML fragment, keeping only allowlisted tags and attributes.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        self::prune($dom);

        $output = '';
        foreach (iterator_to_array($dom->documentElement->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    /**
     * Sanitize HTML and reduce it to plain text (used for excerpts).
     */
    public static function sanitizeText(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        return trim(strip_tags(self::sanitize($value) ?? ''));
    }

    /**
     * Validate a standalone URL (e.g. embed or media source) against the safe schemes.
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = self::stripControlCharacters(trim($url));

        if (self::hasTraversal($url)) {
            return null;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, self::SAFE_LINK_SCHEMES, true)) {
            return $url;
        }

        return null;
    }

    /**
     * Validate an image source URL (relative, http(s), or an inline image).
     */
    public static function sanitizeImageUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = self::stripControlCharacters(trim($url));

        if (self::hasTraversal($url)) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (str_starts_with(strtolower($url), 'data:image/')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    private static function prune(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($element->tagName);

            if (in_array($tag, self::DANGEROUS_TAGS, true)) {
                $element->parentNode->removeChild($element);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($element);

                continue;
            }

            $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::ATTRIBUTES[$tag] ?? []);

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);

                if (! in_array($name, $allowed, true)) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }

            if ($tag === 'a' && $element->hasAttribute('href') && self::sanitizeUrl($element->getAttribute('href')) === null) {
                $element->removeAttribute('href');
            }

            if ($tag === 'img' && $element->hasAttribute('src') && self::sanitizeImageUrl($element->getAttribute('src')) === null) {
                $element->removeAttribute('src');
            }
        }
    }

    private static function unwrap(DOMElement $element): void
    {
        while ($element->firstChild) {
            $element->parentNode->insertBefore($element->firstChild, $element);
        }

        $element->parentNode->removeChild($element);
    }

    private static function stripControlCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? $value;
    }

    private static function hasTraversal(string $value): bool
    {
        $normalized = str_replace('\\', '/', $value);

        return preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1;
    }
}
