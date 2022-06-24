<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Attribute;

use CollectHouse\XML\XSDReader\Schema\AbstractNamedGroupItem;

class Group extends AbstractNamedGroupItem implements AttributeItem, AttributeContainer
{
    use AttributeContainerTrait;
}
