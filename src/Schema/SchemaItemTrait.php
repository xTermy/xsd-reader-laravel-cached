<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema;

trait SchemaItemTrait
{
    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var string
     */
    protected $doc = '';

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getDoc(): string
    {
        return $this->doc;
    }

    public function setDoc(string $doc): void
    {
        $this->doc = $doc;
    }
}
