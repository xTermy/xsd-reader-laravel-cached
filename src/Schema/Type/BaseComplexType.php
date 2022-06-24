<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Type;

use CollectHouse\XML\XSDReader\Schema\Attribute\AttributeContainer;
use CollectHouse\XML\XSDReader\Schema\Attribute\AttributeContainerTrait;

abstract class BaseComplexType extends Type implements AttributeContainer
{
    use AttributeContainerTrait;
}
