<?php

namespace App\Libraries;

use App\Libraries\Html\ImageSourceSanitizer;
use App\Libraries\Html\LinkGuardSanitizer;
use App\Libraries\Html\MeasureSanitizer;
use App\Libraries\Html\TextAlignSanitizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Turns editor HTML into a stored, renderable fragment.
 * The allowlist is applied again at render time.
 */
class BlockHtml
{
    public const MAX_LENGTH = 200000;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $styled = ['style'];
        $config = (new HtmlSanitizerConfig())
            ->withMaxInputLength(self::MAX_LENGTH)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(true)
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias(true)
            ->allowElement('p', $styled)
            ->allowElement('br')
            ->allowElement('h2', $styled)
            ->allowElement('h3', $styled)
            ->allowElement('h4', $styled)
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li', $styled)
            ->allowElement('blockquote', $styled)
            ->allowElement('div', $styled)
            ->allowElement('span', $styled)
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tfoot')
            ->allowElement('tr')
            ->allowElement('th', ['style', 'colspan', 'rowspan'])
            ->allowElement('td', ['style', 'colspan', 'rowspan'])
            ->allowElement('caption')
            ->blockElement('h1')
            ->dropElement('script')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('svg')
            ->dropElement('math')
            ->dropElement('form')
            ->dropElement('input')
            ->dropElement('textarea')
            ->dropElement('button')
            ->dropElement('link')
            ->dropElement('meta')
            ->dropElement('style')
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withAttributeSanitizer(new TextAlignSanitizer())
            ->withAttributeSanitizer(new MeasureSanitizer())
            ->withAttributeSanitizer(new LinkGuardSanitizer())
            ->withAttributeSanitizer(new ImageSourceSanitizer());

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function render(?string $html): string
    {
        $clean = $this->sanitize($html ?? '');

        return $this->isBlank($clean) ? '' : $clean;
    }

    public function normalize(?string $html): ?string
    {
        $rendered = $this->render($html);

        return $rendered === '' ? null : $rendered;
    }

    public static function imageUrlAllowed(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\s\x00-\x1f\\\\]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme  = strtolower((string) ($parts['scheme'] ?? ''));
        $host    = strtolower((string) ($parts['host'] ?? ''));
        $appHost = parse_url((string) base_url(), PHP_URL_HOST);
        $appHost = is_string($appHost) ? strtolower($appHost) : '';

        if ($scheme === '') {
            if ($host !== '') {
                return false;
            }
        } elseif (! in_array($scheme, ['http', 'https'], true) || $host === '' || $appHost === '' || $host !== $appHost) {
            return false;
        }

        $path = rawurldecode((string) ($parts['path'] ?? ''));
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        if ($scheme === '' && preg_match('#^/img/[A-Za-z0-9][A-Za-z0-9._-]*$#', $path) === 1) {
            return true;
        }

        return preg_match('#/uploads/blocks/[a-f0-9]{32}\.(?:jpe?g|png|gif|webp)$#', $path) === 1;
    }

    private function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return $this->prune($this->sanitizer->sanitize($html));
    }

    private function isBlank(string $html): bool
    {
        if (str_contains($html, '<img')) {
            return false;
        }

        $text = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", '', $text);

        return trim($text) === '';
    }

    private function prune(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="block-root">' . $html . '</div></body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('block-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->pruneNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function pruneNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'iframe', 'object', 'embed', 'svg', 'link', 'meta', 'style', 'form'], true)) {
                $node->parentNode?->removeChild($node);

                return;
            }

            if ($tag === 'img' && ! self::imageUrlAllowed($node->getAttribute('src'))) {
                $node->parentNode?->removeChild($node);

                return;
            }

            $this->pruneAttributes($node);
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $this->pruneNode($child);
        }
    }

    private function pruneAttributes(DOMElement $node): void
    {
        $remove = [];
        if ($node->attributes === null) {
            return;
        }

        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction'], true)) {
                $remove[] = $attribute->name;
                continue;
            }

            if ($name !== 'style') {
                continue;
            }

            if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify)\b/i', $attribute->value, $matches) !== 1) {
                $remove[] = $attribute->name;
                continue;
            }

            $attribute->value = 'text-align: ' . strtolower($matches[1]);
        }

        foreach ($remove as $name) {
            $node->removeAttribute($name);
        }
    }
}
