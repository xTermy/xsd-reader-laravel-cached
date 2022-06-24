<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Documentation;

use DOMElement;

interface DocumentationReader
{
    public function get(DOMElement $node): string;
}
