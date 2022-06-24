<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Attribute;

use CollectHouse\XML\XSDReader\Schema\SchemaItem;

interface AttributeItem extends SchemaItem
{
    public function getName(): string;
}
