<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Documentation;

use DOMElement;

class StandardDocumentationReader implements DocumentationReader
{
    public function get(DOMElement $node): string
    {
        $doc = '';

        /**
         * @var \DOMNode $childNode
         */
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->localName == 'annotation') {
                /**
                 * @var \DOMNode $subChildNode
                 */
                foreach ($childNode->childNodes as $subChildNode) {
                    if ($subChildNode instanceof DOMElement && $subChildNode->localName == 'documentation') {
                        if (!empty($doc)) {
                            $doc .=  "\n" . ($subChildNode->nodeValue);
                        } else {
                            $doc .= ($subChildNode->nodeValue);
                        }
                    }
                }
            }
        }
        $doc = preg_replace('/[\t ]+/', ' ', $doc);

        return trim($doc);
    }
}
