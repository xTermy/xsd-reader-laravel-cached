<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Attribute;

use CollectHouse\XML\XSDReader\Schema\SchemaItem;

interface AttributeContainer extends SchemaItem
{
    public function addAttribute(AttributeItem $attribute): void;

    /**
     * @return AttributeItem[]
     */
    public function getAttributes(): array;
}
