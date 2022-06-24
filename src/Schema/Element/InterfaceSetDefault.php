<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Element;

interface InterfaceSetDefault
{
    public function getDefault(): ?string;

    public function setDefault(string $default): void;
}
