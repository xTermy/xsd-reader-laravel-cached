<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Attribute;

use CollectHouse\XML\XSDReader\Schema\Item;

abstract class AbstractAttributeItem extends Item implements AttributeItem
{
    /**
     * @var string|null
     */
    protected $fixed;

    /**
     * @var string|null
     */
    protected $default;

    public function getFixed(): ?string
    {
        return $this->fixed;
    }

    public function setFixed(string $fixed): void
    {
        $this->fixed = $fixed;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setDefault(string $default): void
    {
        $this->default = $default;
    }
}
