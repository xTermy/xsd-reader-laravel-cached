<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Type;

use CollectHouse\XML\XSDReader\Schema\Element\ElementContainer;
use CollectHouse\XML\XSDReader\Schema\Element\ElementContainerTrait;

class ComplexType extends BaseComplexType implements ElementContainer
{
    use ElementContainerTrait;
}
