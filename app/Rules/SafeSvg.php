<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use DOMAttr;
use DOMDocument;
use DOMElement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Allow-list check for SVG uploads: rejects scripts, event handlers, external references and unknown elements.
 */
class SafeSvg implements ValidationRule
{
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'defs', 'title', 'desc',
        'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'use', 'symbol', 'text', 'tspan',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || strtolower($value->getClientOriginalExtension()) !== 'svg') {
            return;
        }

        if (! $this->isSafe((string) file_get_contents($value->getRealPath()))) {
            $fail(__('The SVG contains disallowed content such as scripts or external references.'));
        }
    }

    public function isSafe(string $svg): bool
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg) === 1) {
            return false;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $document->documentElement === null || strtolower($document->documentElement->localName) !== 'svg') {
            return false;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! in_array(strtolower($element->localName), self::ELEMENTS, true) || ! $this->attributesAreSafe($element)) {
                return false;
            }
        }

        return true;
    }

    private function attributesAreSafe(DOMElement $element): bool
    {
        /** @var DOMAttr $attribute */
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = strtolower(trim($attribute->value));

            if (str_starts_with($name, 'on')) {
                return false;
            }

            if (preg_match('/javascript:|data:|expression\(|@import|url\(\s*[\'"]?(?!#)/', $value) === 1) {
                return false;
            }

            if (in_array($name, ['href', 'xlink:href'], true) && ! str_starts_with($value, '#')) {
                return false;
            }
        }

        return true;
    }
}
