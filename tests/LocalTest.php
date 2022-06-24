<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader\Tests;

use CollectHouse\XML\XSDReader\Schema\Element\Element;
use CollectHouse\XML\XSDReader\Schema\Element\ElementRef;
use CollectHouse\XML\XSDReader\Schema\Schema;
use CollectHouse\XML\XSDReader\Schema\Type\ComplexType;

class LocalTest extends BaseTest
{
    public function testSchemaCanBeLoaded()
    {
        $schema = $this->reader->readFile(__DIR__.'/schema/local.xsd');
        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testTypesLocalPropertyIsValid()
    {
        $schema = $this->reader->readFile(__DIR__.'/schema/local.xsd');
        $this->assertInstanceOf(Schema::class, $schema);

        $type1 = $schema->getType('type1');
        $this->assertInstanceOf(ComplexType::class, $type1);

        $localElements = $type1->getElements();
        $this->assertCount(1, $localElements);
        /** @var ElementRef $localElement */
        $localElement = $localElements[0];

        $this->assertInstanceOf(ElementRef::class, $localElement);
        $this->assertFalse($localElement->isLocal());

        $referencedElement = $localElement->getReferencedElement();
        $this->assertNotNull($referencedElement);

        /** @var ComplexType $referencedElementType */
        $referencedElementType = $referencedElement->getType();
        $this->assertNotNull($referencedElementType);

        $this->assertInstanceOf(ComplexType::class, $referencedElementType);
        $elements = $referencedElementType->getElements();
        $this->assertCount(1, $elements);

        $dataElement = $elements[0];
        $this->assertInstanceOf(Element::class, $dataElement);

        $this->assertEquals('data', $dataElement->getName());
        $this->assertTrue($dataElement->isLocal());
    }
}
