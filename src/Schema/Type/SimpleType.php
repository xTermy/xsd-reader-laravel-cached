<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Type;

use CollectHouse\XML\XSDReader\Schema\Inheritance\Restriction;

class SimpleType extends Type
{
    /**
     * @var Restriction|null
     */
    protected $restriction;

    /**
     * @var SimpleType[]
     */
    protected $unions = [];

    /**
     * @var SimpleType|null
     */
    protected $list;

    public function getRestriction(): ?Restriction
    {
        return $this->restriction;
    }

    public function setRestriction(Restriction $restriction): void
    {
        $this->restriction = $restriction;
    }

    public function addUnion(self $type): void
    {
        $this->unions[] = $type;
    }

    /**
     * @return SimpleType[]
     */
    public function getUnions(): array
    {
        return $this->unions;
    }

    public function getList(): ?self
    {
        return $this->list;
    }

    public function setList(self $list): void
    {
        $this->list = $list;
    }
}
