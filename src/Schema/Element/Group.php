<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Element;

use CollectHouse\XML\XSDReader\Schema\AbstractNamedGroupItem;

class Group extends AbstractNamedGroupItem implements ElementItem, ElementContainer
{
    use ElementContainerTrait;
}
