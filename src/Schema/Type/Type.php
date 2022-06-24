<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Type;

use CollectHouse\XML\XSDReader\Schema\Inheritance\Base;
use CollectHouse\XML\XSDReader\Schema\Inheritance\Extension;
use CollectHouse\XML\XSDReader\Schema\Inheritance\Restriction;
use CollectHouse\XML\XSDReader\Schema\Schema;
use CollectHouse\XML\XSDReader\Schema\SchemaItem;
use CollectHouse\XML\XSDReader\Schema\SchemaItemTrait;

abstract class Type implements SchemaItem
{
    use SchemaItemTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var bool
     */
    protected $abstract = false;

    /**
     * @var Restriction|null
     */
    protected $restriction;

    /**
     * @var Extension|null
     */
    protected $extension;

    public function __construct(Schema $schema, string $name = null)
    {
        $this->name = $name ?: null;
        $this->schema = $schema;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return strval($this->name);
    }

    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    public function setAbstract(bool $abstract): void
    {
        $this->abstract = $abstract;
    }

    /**
     * @return Restriction|Extension|null
     */
    public function getParent(): ?Base
    {
        return $this->restriction ?: $this->extension;
    }

    public function getRestriction(): ?Restriction
    {
        return $this->restriction;
    }

    public function setRestriction(Restriction $restriction): void
    {
        $this->restriction = $restriction;
    }

    public function getExtension(): ?Extension
    {
        return $this->extension;
    }

    public function setExtension(Extension $extension): void
    {
        $this->extension = $extension;
    }
}
