<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Element;

use CollectHouse\XML\XSDReader\Schema\SchemaItem;

interface ElementContainer extends SchemaItem
{
    public function addElement(ElementItem $element): void;

    /**
     * @return ElementItem[]
     */
    public function getElements(): array;
}
