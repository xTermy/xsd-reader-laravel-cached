<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Schema\Element;

use CollectHouse\XML\XSDReader\Schema\Type\Type;

interface ElementSingle extends ElementItem, InterfaceSetMinMax, InterfaceSetDefault
{
    public function getType(): ? Type;

    public function isQualified(): bool;

    public function setQualified(bool $qualified): void;

    public function isLocal(): bool;

    public function setLocal(bool $local): void;

    public function isNil(): bool;

    public function setNil(bool $nil): void;
}
