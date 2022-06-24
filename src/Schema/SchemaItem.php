<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema;

interface SchemaItem
{
    public function getSchema(): Schema;

    public function getDoc(): ?string;
}
