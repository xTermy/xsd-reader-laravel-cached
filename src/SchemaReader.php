<?php

declare(strict_types=1);

namespace CollectHouse\XML\XSDReader;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use CollectHouse\XML\XSDReader\Documentation\DocumentationReader;
use CollectHouse\XML\XSDReader\Documentation\StandardDocumentationReader;
use CollectHouse\XML\XSDReader\Exception\IOException;
use CollectHouse\XML\XSDReader\Exception\TypeException;
use CollectHouse\XML\XSDReader\Schema\Attribute\Attribute;
use CollectHouse\XML\XSDReader\Schema\Attribute\AttributeContainer;
use CollectHouse\XML\XSDReader\Schema\Attribute\AttributeDef;
use CollectHouse\XML\XSDReader\Schema\Attribute\AttributeItem;
use CollectHouse\XML\XSDReader\Schema\Attribute\Group as AttributeGroup;
use CollectHouse\XML\XSDReader\Schema\Element\Element;
use CollectHouse\XML\XSDReader\Schema\Element\ElementContainer;
use CollectHouse\XML\XSDReader\Schema\Element\ElementDef;
use CollectHouse\XML\XSDReader\Schema\Element\ElementRef;
use CollectHouse\XML\XSDReader\Schema\Element\Group;
use CollectHouse\XML\XSDReader\Schema\Element\GroupRef;
use CollectHouse\XML\XSDReader\Schema\Element\InterfaceSetDefault;
use CollectHouse\XML\XSDReader\Schema\Element\InterfaceSetMinMax;
use CollectHouse\XML\XSDReader\Schema\Exception\TypeNotFoundException;
use CollectHouse\XML\XSDReader\Schema\Inheritance\Base;
use CollectHouse\XML\XSDReader\Schema\Inheritance\Extension;
use CollectHouse\XML\XSDReader\Schema\Inheritance\Restriction;
use CollectHouse\XML\XSDReader\Schema\Item;
use CollectHouse\XML\XSDReader\Schema\Schema;
use CollectHouse\XML\XSDReader\Schema\SchemaItem;
use CollectHouse\XML\XSDReader\Schema\Type\BaseComplexType;
use CollectHouse\XML\XSDReader\Schema\Type\ComplexType;
use CollectHouse\XML\XSDReader\Schema\Type\ComplexTypeSimpleContent;
use CollectHouse\XML\XSDReader\Schema\Type\SimpleType;
use CollectHouse\XML\XSDReader\Schema\Type\Type;
use CollectHouse\XML\XSDReader\Utils\SchemaFileUtils;
use CollectHouse\XML\XSDReader\Utils\UrlUtils;

class SchemaReader
{
    public const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

    public const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @var DocumentationReader
     */
    private $documentationReader;

    /**
     * @var Schema[]
     */
    private $loadedFiles = [];

    /**
     * @var Schema[][]
     */
    private $loadedSchemas = [];

    /**
     * @var string[]
     */
    protected $knownLocationSchemas = [
        'http://www.w3.org/2001/xml.xsd' => (
            __DIR__.'/Resources/xml.xsd'
        ),
        'http://www.w3.org/2001/XMLSchema.xsd' => (
            __DIR__.'/Resources/XMLSchema.xsd'
        ),
        'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' => (
            __DIR__.'/Resources/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        ),
        'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd' => (
            __DIR__.'/Resources/oasis-200401-wss-wssecurity-utility-1.0.xsd'
        ),
        'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd' => (
            __DIR__.'/Resources/xmldsig-core-schema.xsd'
        ),
        'http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd' => (
            __DIR__.'/Resources/xmldsig-core-schema.xsd'
        ),
    ];

    /**
     * @var string[]
     */
    protected $knownNamespaceSchemaLocations = [];

    /**
     * @var string[]
     */
    protected static $globalSchemaInfo = [
        self::XML_NS => 'http://www.w3.org/2001/xml.xsd',
        self::XSD_NS => 'http://www.w3.org/2001/XMLSchema.xsd',
    ];

    private function extractErrorMessage(): \Exception
    {
        $errors = [];

        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf("Error[%s] code %s: %s in '%s' at position %s:%s", $error->level, $error->code, trim($error->message), $error->file, $error->line, $error->column);
        }
        $e = new \Exception(implode('; ', $errors));
        libxml_use_internal_errors(false);

        return $e;
    }

    public function __construct(DocumentationReader $documentationReader = null)
    {
        if (null === $documentationReader) {
            $documentationReader = new StandardDocumentationReader();
        }
        $this->documentationReader = $documentationReader;
    }

    /**
     * Override remote location with a local file.
     *
     * @param string $remote remote schema URL
     * @param string $local  local file path
     */
    public function addKnownSchemaLocation(string $remote, string $local): void
    {
        $this->knownLocationSchemas[$remote] = $local;
    }

    /**
     * Specify schema location by namespace.
     * This can be used for schemas which import namespaces but do not specify schemaLocation attributes.
     *
     * @param string $namespace namespace
     * @param string $location  schema URL
     */
    public function addKnownNamespaceSchemaLocation(string $namespace, string $location): void
    {
        $this->knownNamespaceSchemaLocations[$namespace] = $location;
    }

    private function loadAttributeGroup(
        Schema $schema,
        DOMElement $node
    ): Closure {
        $attGroup = new AttributeGroup($schema, $node->getAttribute('name'));
        $attGroup->setDoc($this->getDocumentation($node));
        $schema->addAttributeGroup($attGroup);

        return function () use ($schema, $node, $attGroup): void {
            SchemaReader::againstDOMNodeList(
                $node,
                function (
                    DOMElement $node,
                    DOMElement $childNode
                ) use (
                    $schema,
                    $attGroup
                ): void {
                    switch ($childNode->localName) {
                        case 'attribute':
                            $attribute = $this->getAttributeFromAttributeOrRef(
                                $childNode,
                                $schema,
                                $node
                            );
                            $attGroup->addAttribute($attribute);
                            break;
                        case 'attributeGroup':
                            $this->findSomethingLikeAttributeGroup(
                                $schema,
                                $node,
                                $childNode,
                                $attGroup
                            );
                            break;
                    }
                }
            );
        };
    }

    private function getAttributeFromAttributeOrRef(
        DOMElement $childNode,
        Schema $schema,
        DOMElement $node
    ): AttributeItem {
        if ($childNode->hasAttribute('ref')) {
            $attribute = $this->findAttributeItem($schema, $node, $childNode->getAttribute('ref'));
        } else {
            /**
             * @var Attribute
             */
            $attribute = $this->loadAttribute($schema, $childNode);
        }

        return $attribute;
    }

    private function loadAttribute(
        Schema $schema,
        DOMElement $node
    ): Attribute {
        $attribute = new Attribute($schema, $node->getAttribute('name'));
        $attribute->setDoc($this->getDocumentation($node));
        $this->fillItem($attribute, $node);

        if ($node->hasAttribute('nillable')) {
            $attribute->setNil($node->getAttribute('nillable') == 'true');
        }
        if ($node->hasAttribute('form')) {
            $attribute->setQualified($node->getAttribute('form') == 'qualified');
        }
        if ($node->hasAttribute('use')) {
            $attribute->setUse($node->getAttribute('use'));
        }

        return $attribute;
    }

    private function loadAttributeOrElementDef(
        Schema $schema,
        DOMElement $node,
        bool $attributeDef
    ): Closure {
        $name = $node->getAttribute('name');
        if ($attributeDef) {
            $attribute = new AttributeDef($schema, $name);
            $schema->addAttribute($attribute);
        } else {
            $attribute = new ElementDef($schema, $name);
            $attribute->setDoc($this->getDocumentation($node));
            $schema->addElement($attribute);
        }

        return function () use ($attribute, $node): void {
            $this->fillItem($attribute, $node);
        };
    }

    private function loadAttributeDef(Schema $schema, DOMElement $node): Closure
    {
        return $this->loadAttributeOrElementDef($schema, $node, true);
    }

    private function getDocumentation(DOMElement $node): string
    {
        return $this->documentationReader->get($node);
    }

    /**
     * @return Closure[]
     */
    private function schemaNode(Schema $schema, DOMElement $node, Schema $parent = null): array
    {
        $this->setSchemaThingsFromNode($schema, $node, $parent);
        $functions = [];

        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $schema,
                &$functions
            ): void {
                $callback = null;

                switch ($childNode->localName) {
                    case 'attributeGroup':
                        $callback = $this->loadAttributeGroup($schema, $childNode);
                        break;
                    case 'include':
                    case 'import':
                        $callback = $this->loadImport($schema, $childNode);
                        break;
                    case 'element':
                        $callback = $this->loadElementDef($schema, $childNode);
                        break;
                    case 'attribute':
                        $callback = $this->loadAttributeDef($schema, $childNode);
                        break;
                    case 'group':
                        $callback = $this->loadGroup($schema, $childNode);
                        break;
                    case 'complexType':
                        $callback = $this->loadComplexType($schema, $childNode);
                        break;
                    case 'simpleType':
                        $callback = $this->loadSimpleType($schema, $childNode);
                        break;
                }

                if ($callback instanceof Closure) {
                    $functions[] = $callback;
                }
            }
        );

        return $functions;
    }

    private function loadGroupRef(Group $referenced, DOMElement $node): GroupRef
    {
        $ref = new GroupRef($referenced);
        $ref->setDoc($this->getDocumentation($node));

        self::maybeSetMax($ref, $node);
        self::maybeSetMin($ref, $node);

        return $ref;
    }

    private static function maybeSetMax(InterfaceSetMinMax $ref, DOMElement $node): void
    {
        if ($node->hasAttribute('maxOccurs')) {
            $ref->setMax($node->getAttribute('maxOccurs') == 'unbounded' ? -1 : (int) $node->getAttribute('maxOccurs'));
        }
    }

    private static function maybeSetMin(InterfaceSetMinMax $ref, DOMElement $node): void
    {
        if ($node->hasAttribute('minOccurs')) {
            $ref->setMin((int) $node->getAttribute('minOccurs'));
            if ($ref->getMin() > $ref->getMax() && $ref->getMax() !== -1) {
                $ref->setMax($ref->getMin());
            }
        }
    }

    private static function maybeSetDefault(InterfaceSetDefault $ref, DOMElement $node): void
    {
        if ($node->hasAttribute('default')) {
            $ref->setDefault($node->getAttribute('default'));
        }
    }

    private function loadSequence(ElementContainer $elementContainer, DOMElement $node, int $max = null): void
    {
        $max =
            (
                (is_int($max) && (bool) $max) ||
                $node->getAttribute('maxOccurs') == 'unbounded' ||
                $node->getAttribute('maxOccurs') > 1
            )
                ? 2
                : null;

        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $elementContainer,
                $max
            ): void {
                $this->loadSequenceChildNode(
                    $elementContainer,
                    $node,
                    $childNode,
                    $max
                );
            }
        );
    }

    private function loadSequenceChildNode(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ): void {
        switch ($childNode->localName) {
            case 'sequence':
            case 'choice':
            case 'all':
                $this->loadSequence(
                    $elementContainer,
                    $childNode,
                    $max
                );
                break;
            case 'element':
                $this->loadSequenceChildNodeLoadElement(
                    $elementContainer,
                    $node,
                    $childNode,
                    $max
                );
                break;
            case 'group':
                $this->addGroupAsElement(
                    $elementContainer->getSchema(),
                    $node,
                    $childNode,
                    $elementContainer
                );
                break;
        }
    }

    private function loadSequenceChildNodeLoadElement(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ): void {
        if ($childNode->hasAttribute('ref')) {
            $elementDef = $this->findElement($elementContainer->getSchema(), $node, $childNode->getAttribute('ref'));
            $element = new ElementRef($elementDef);
            $element->setDoc($this->getDocumentation($childNode));

            self::maybeSetMax($element, $childNode);
            self::maybeSetMin($element, $childNode);

            $xp = new \DOMXPath($node->ownerDocument);
            $xp->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

            if ($xp->query('ancestor::xs:choice', $childNode)->length) {
                $element->setMin(0);
            }

            if ($childNode->hasAttribute('nillable')) {
                $element->setNil($childNode->getAttribute('nillable') == 'true');
            }
            if ($childNode->hasAttribute('form')) {
                $element->setQualified($childNode->getAttribute('form') == 'qualified');
            }
        } else {
            $element = $this->loadElement(
                $elementContainer->getSchema(),
                $childNode
            );
        }
        if ($max > 1) {
            /*
            * although one might think the typecast is not needed with $max being `? int $max` after passing > 1,
            * phpstan@a4f89fa still thinks it's possibly null.
            * see https://github.com/phpstan/phpstan/issues/577 for related issue
            */
            $element->setMax((int) $max);
        }
        $elementContainer->addElement($element);
    }

    private function addGroupAsElement(
        Schema $schema,
        DOMElement $node,
        DOMElement $childNode,
        ElementContainer $elementContainer
    ): void {
        $referencedGroup = $this->findGroup(
            $schema,
            $node,
            $childNode->getAttribute('ref')
        );

        $group = $this->loadGroupRef($referencedGroup, $childNode);
        $elementContainer->addElement($group);
    }

    private function loadGroup(Schema $schema, DOMElement $node): Closure
    {
        $group = new Group($schema, $node->getAttribute('name'));
        $group->setDoc($this->getDocumentation($node));
        $groupOriginal = $group;

        if ($node->hasAttribute('maxOccurs') || $node->hasAttribute('maxOccurs')) {
            $group = new GroupRef($group);

            if ($node->hasAttribute('maxOccurs')) {
                self::maybeSetMax($group, $node);
            }
            if ($node->hasAttribute('minOccurs')) {
                self::maybeSetMin($group, $node);
            }
        }

        $schema->addGroup($group);

        return function () use ($groupOriginal, $node): void {
            static::againstDOMNodeList(
                $node,
                function (DOMelement $node, DOMElement $childNode) use ($groupOriginal): void {
                    switch ($childNode->localName) {
                        case 'sequence':
                        case 'choice':
                        case 'all':
                            $this->loadSequence($groupOriginal, $childNode);
                            break;
                    }
                }
            );
        };
    }

    private function loadComplexType(Schema $schema, DOMElement $node, Closure $callback = null): Closure
    {
        /**
         * @var bool
         */
        $isSimple = false;

        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                &$isSimple
            ): void {
                if ($isSimple) {
                    return;
                }
                if ($childNode->localName === 'simpleContent') {
                    $isSimple = true;
                }
            }
        );

        $type = $isSimple ? new ComplexTypeSimpleContent($schema, $node->getAttribute('name')) : new ComplexType($schema, $node->getAttribute('name'));

        $type->setDoc($this->getDocumentation($node));
        if ($node->getAttribute('name')) {
            $schema->addType($type);
        }

        return function () use ($type, $node, $schema, $callback): void {
            $this->fillTypeNode($type, $node, true);

            self::againstDOMNodeList(
                $node,
                function (
                    DOMElement $node,
                    DOMElement $childNode
                ) use (
                    $schema,
                    $type
                ): void {
                    $this->loadComplexTypeFromChildNode(
                        $type,
                        $node,
                        $childNode,
                        $schema
                    );
                }
            );

            if ($callback instanceof Closure) {
                call_user_func($callback, $type);
            }
        };
    }

    private function loadComplexTypeFromChildNode(
        BaseComplexType $type,
        DOMElement $node,
        DOMElement $childNode,
        Schema $schema
    ): void {
        switch ($childNode->localName) {
            case 'sequence':
            case 'choice':
            case 'all':
                if ($type instanceof ElementContainer) {
                    $this->loadSequence(
                        $type,
                        $childNode
                    );
                }
                break;
            case 'attribute':
                $this->addAttributeFromAttributeOrRef(
                    $type,
                    $childNode,
                    $schema,
                    $node
                );
                break;
            case 'attributeGroup':
                $this->findSomethingLikeAttributeGroup(
                    $schema,
                    $node,
                    $childNode,
                    $type
                );
                break;
            case 'group':
                if (
                    $type instanceof ComplexType
                ) {
                    $this->addGroupAsElement(
                        $schema,
                        $node,
                        $childNode,
                        $type
                    );
                }
                break;
        }
    }

    private function loadSimpleType(Schema $schema, DOMElement $node, Closure $callback = null): Closure
    {
        $type = new SimpleType($schema, $node->getAttribute('name'));
        $type->setDoc($this->getDocumentation($node));
        if ($node->getAttribute('name')) {
            $schema->addType($type);
        }

        return function () use ($type, $node, $callback): void {
            $this->fillTypeNode($type, $node, true);

            self::againstDOMNodeList(
                $node,
                function (DOMElement $node, DOMElement $childNode) use ($type): void {
                    switch ($childNode->localName) {
                        case 'union':
                            $this->loadUnion($type, $childNode);
                            break;
                        case 'list':
                            $this->loadList($type, $childNode);
                            break;
                    }
                }
            );

            if ($callback instanceof Closure) {
                call_user_func($callback, $type);
            }
        };
    }

    private function loadList(SimpleType $type, DOMElement $node): void
    {
        if ($node->hasAttribute('itemType')) {
            /**
             * @var SimpleType
             */
            $listType = $this->findSomeType($type, $node, 'itemType');
            $type->setList($listType);
        } else {
            self::againstDOMNodeList(
                $node,
                function (
                    DOMElement $node,
                    DOMElement $childNode
                ) use (
                    $type
                ): void {
                    $this->loadTypeWithCallback(
                        $type->getSchema(),
                        $childNode,
                        function (SimpleType $list) use ($type): void {
                            $type->setList($list);
                        }
                    );
                }
            );
        }
    }

    private function findSomeType(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem {
        return $this->findSomeTypeFromAttribute(
            $fromThis,
            $node,
            $node->getAttribute($attributeName)
        );
    }

    private function findSomeTypeFromAttribute(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem {
        $out = $this->findType(
            $fromThis->getSchema(),
            $node,
            $attributeName
        );

        return $out;
    }

    private function loadUnion(SimpleType $type, DOMElement $node): void
    {
        if ($node->hasAttribute('memberTypes')) {
            $types = preg_split('/\s+/', $node->getAttribute('memberTypes'));
            foreach ($types as $typeName) {
                /**
                 * @var SimpleType
                 */
                $unionType = $this->findSomeTypeFromAttribute(
                    $type,
                    $node,
                    $typeName
                );
                $type->addUnion($unionType);
            }
        }
        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $type
            ): void {
                $this->loadTypeWithCallback(
                    $type->getSchema(),
                    $childNode,
                    function (SimpleType $unType) use ($type): void {
                        $type->addUnion($unType);
                    }
                );
            }
        );
    }

    private function fillTypeNode(Type $type, DOMElement $node, bool $checkAbstract = false): void
    {
        if ($checkAbstract) {
            $type->setAbstract($node->getAttribute('abstract') === 'true' || $node->getAttribute('abstract') === '1');
        }

        self::againstDOMNodeList(
            $node,
            function (DOMElement $node, DOMElement $childNode) use ($type): void {
                switch ($childNode->localName) {
                    case 'restriction':
                        $this->loadRestriction($type, $childNode);
                        break;
                    case 'extension':
                        if ($type instanceof BaseComplexType) {
                            $this->loadExtension($type, $childNode);
                        }
                        break;
                    case 'simpleContent':
                    case 'complexContent':
                        $this->fillTypeNode($type, $childNode);
                        break;
                }
            }
        );
    }

    private function loadExtension(BaseComplexType $type, DOMElement $node): void
    {
        $extension = new Extension();
        $type->setExtension($extension);

        if ($node->hasAttribute('base')) {
            $this->findAndSetSomeBase(
                $type,
                $extension,
                $node
            );
        }
        $this->loadExtensionChildNodes($type, $node);
    }

    private function findAndSetSomeBase(
        Type $type,
        Base $setBaseOnThis,
        DOMElement $node
    ): void {
        /**
         * @var Type
         */
        $parent = $this->findSomeType($type, $node, 'base');
        $setBaseOnThis->setBase($parent);
    }

    private function loadExtensionChildNodes(
        BaseComplexType $type,
        DOMElement $node
    ): void {
        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $type
            ): void {
                switch ($childNode->localName) {
                    case 'sequence':
                    case 'choice':
                    case 'all':
                        if ($type instanceof ElementContainer) {
                            $this->loadSequence(
                                $type,
                                $childNode
                            );
                        }
                        break;
                    case 'attribute':
                        $this->addAttributeFromAttributeOrRef(
                            $type,
                            $childNode,
                            $type->getSchema(),
                            $node
                        );
                        break;
                    case 'attributeGroup':
                        $this->findSomethingLikeAttributeGroup(
                            $type->getSchema(),
                            $node,
                            $childNode,
                            $type
                        );
                        break;
                }
            }
        );
    }

    private function loadRestriction(Type $type, DOMElement $node): void
    {
        $restriction = new Restriction();
        $type->setRestriction($restriction);
        if ($node->hasAttribute('base')) {
            $this->findAndSetSomeBase($type, $restriction, $node);
        } else {
            self::againstDOMNodeList(
                $node,
                function (
                    DOMElement $node,
                    DOMElement $childNode
                ) use (
                    $type,
                    $restriction
                ): void {
                    $this->loadTypeWithCallback(
                        $type->getSchema(),
                        $childNode,
                        function (Type $restType) use ($restriction): void {
                            $restriction->setBase($restType);
                        }
                    );
                }
            );
        }
        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $restriction
            ): void {
                if (
                in_array(
                    $childNode->localName,
                    [
                        'enumeration',
                        'pattern',
                        'length',
                        'minLength',
                        'maxLength',
                        'minInclusive',
                        'maxInclusive',
                        'minExclusive',
                        'maxExclusive',
                        'fractionDigits',
                        'totalDigits',
                        'whiteSpace',
                    ],
                    true
                )
                ) {
                    $restriction->addCheck(
                        $childNode->localName,
                        [
                            'value' => $childNode->getAttribute('value'),
                            'doc' => $this->getDocumentation($childNode),
                        ]
                    );
                }
            }
        );
    }

    /**
     * @return mixed[]
     */
    private static function splitParts(DOMElement $node, string $typeName): array
    {
        $prefix = null;
        $name = $typeName;
        if (strpos($typeName, ':') !== false) {
            list($prefix, $name) = explode(':', $typeName);
        }

        /**
         * @psalm-suppress PossiblyNullArgument
         */
        $namespace = $node->lookupNamespaceUri($prefix);

        return [
            $name,
            $namespace,
            $prefix,
        ];
    }

    private function findAttributeItem(Schema $schema, DOMElement $node, string $typeName): AttributeItem
    {
        list($name, $namespace) = static::splitParts($node, $typeName);

        /**
         * @var string|null $namespace
         */
        $namespace = $namespace ?: $schema->getTargetNamespace();

        try {
            /**
             * @var AttributeItem $out
             */
            $out = $schema->findAttribute((string) $name, $namespace);

            return $out;
        } catch (TypeNotFoundException $e) {
            throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", 'attribute', $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI), 0, $e);
        }
    }

    private function findAttributeGroup(Schema $schema, DOMElement $node, string $typeName): AttributeGroup
    {
        list($name, $namespace) = static::splitParts($node, $typeName);

        /**
         * @var string|null $namespace
         */
        $namespace = $namespace ?: $schema->getTargetNamespace();

        try {
            /**
             * @var AttributeGroup $out
             */
            $out = $schema->findAttributeGroup((string) $name, $namespace);

            return $out;
        } catch (TypeNotFoundException $e) {
            throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", 'attributegroup', $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI), 0, $e);
        }
    }

    private function findElement(Schema $schema, DOMElement $node, string $typeName): ElementDef
    {
        list($name, $namespace) = static::splitParts($node, $typeName);

        /**
         * @var string|null $namespace
         */
        $namespace = $namespace ?: $schema->getTargetNamespace();

        try {
            return $schema->findElement((string) $name, $namespace);
        } catch (TypeNotFoundException $e) {
            throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", 'element', $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI), 0, $e);
        }
    }

    private function findGroup(Schema $schema, DOMElement $node, string $typeName): Group
    {
        list($name, $namespace) = static::splitParts($node, $typeName);

        /**
         * @var string|null $namespace
         */
        $namespace = $namespace ?: $schema->getTargetNamespace();

        try {
            /**
             * @var Group $out
             */
            $out = $schema->findGroup((string) $name, $namespace);

            return $out;
        } catch (TypeNotFoundException $e) {
            throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", 'group', $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI), 0, $e);
        }
    }

    private function findType(Schema $schema, DOMElement $node, string $typeName): SchemaItem
    {
        list($name, $namespace) = static::splitParts($node, $typeName);

        /**
         * @var string|null $namespace
         */
        $namespace = $namespace ?: $schema->getTargetNamespace();

        $tryFindType = static function (Schema $schema, string $name, ?string $namespace): ?SchemaItem {
            try {
                return $schema->findType($name, $namespace);
            } catch (TypeNotFoundException $e) {
                return null;
            }
        };

        $interestingSchemas = array_merge([$schema], $this->loadedSchemas[$namespace] ?? []);
        foreach ($interestingSchemas as $interestingSchema) {
            if ($result = $tryFindType($interestingSchema, $name, $namespace)) {
                return $result;
            }
        }

        throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", 'type', $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI));
    }

    private function loadElementDef(Schema $schema, DOMElement $node): Closure
    {
        return $this->loadAttributeOrElementDef($schema, $node, false);
    }

    private function fillItem(Item $element, DOMElement $node): void
    {
        /**
         * @var bool
         */
        $skip = false;
        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $element,
                &$skip
            ): void {
                if (
                    !$skip &&
                    in_array(
                        $childNode->localName,
                        [
                            'complexType',
                            'simpleType',
                        ],
                        true
                    )
                ) {
                    $this->loadTypeWithCallback(
                        $element->getSchema(),
                        $childNode,
                        function (Type $type) use ($element): void {
                            $element->setType($type);
                        }
                    );
                    $skip = true;
                }
            }
        );
        if ($skip) {
            return;
        }
        $this->fillItemNonLocalType($element, $node);
    }

    private function fillItemNonLocalType(Item $element, DOMElement $node): void
    {
        if ($node->getAttribute('type')) {
            /**
             * @var Type
             */
            $type = $this->findSomeType($element, $node, 'type');
        } else {
            /**
             * @var Type
             */
            $type = $this->findSomeTypeFromAttribute(
                $element,
                $node,
                ($node->lookupPrefix(self::XSD_NS).':anyType')
            );
        }

        $element->setType($type);
    }

    private function loadImport(
        Schema $schema,
        DOMElement $node
    ): Closure {
        $namespace = $node->getAttribute('namespace');
        $schemaLocation = $node->getAttribute('schemaLocation');
        if (!$schemaLocation && isset($this->knownNamespaceSchemaLocations[$namespace])) {
            $schemaLocation = $this->knownNamespaceSchemaLocations[$namespace];
        }

        // postpone schema loading
        if ($namespace && !$schemaLocation && !isset(self::$globalSchemaInfo[$namespace])) {
            return function () use ($schema, $namespace): void {
                if (!empty($this->loadedSchemas[$namespace])) {
                    foreach ($this->loadedSchemas[$namespace] as $s) {
                        $schema->addSchema($s, $namespace);
                    }
                }
            };
        } elseif ($namespace && !$schemaLocation && !empty($this->loadedSchemas[$namespace])) {
            foreach ($this->loadedSchemas[$namespace] as $s) {
                $schema->addSchema($s, $namespace);
            }
        }

        $base = urldecode($node->ownerDocument->documentURI);
        $file = UrlUtils::resolveRelativeUrl($base, $schemaLocation);

        $file = SchemaFileUtils::cacheFile($namespace, $file);

        if (isset($this->loadedFiles[$file])) {
            $schema->addSchema($this->loadedFiles[$file]);

            return function (): void {
            };
        }

        return $this->loadImportFresh($namespace, $schema, $file);
    }

    private function createOrUseSchemaForNs(
        Schema $schema,
        string $namespace
    ): Schema {
        if (('' !== trim($namespace))) {
            $newSchema = new Schema();
            $newSchema->addSchema($this->getGlobalSchema());
            $schema->addSchema($newSchema);
        } else {
            $newSchema = $schema;
        }

        return $newSchema;
    }

    private function loadImportFresh(
        string $namespace,
        Schema $schema,
        string $file
    ): Closure {
        return function () use ($namespace, $schema, $file): void {
            $dom = $this->getDOM(
                isset($this->knownLocationSchemas[$file])
                    ? $this->knownLocationSchemas[$file]
                    : $file
            );

            $schemaNew = $this->createOrUseSchemaForNs($schema, $namespace);

            $this->setLoadedFile($file, $schemaNew);

            $callbacks = $this->schemaNode($schemaNew, $dom->documentElement, $schema);

            foreach ($callbacks as $callback) {
                $callback();
            }
        };
    }

    /**
     * @var Schema|null
     */
    protected $globalSchema;

    public function getGlobalSchema(): Schema
    {
        if (!($this->globalSchema instanceof Schema)) {
            $callbacks = [];
            $globalSchemas = [];
            /**
             * @var string $namespace
             */
            foreach (self::$globalSchemaInfo as $namespace => $uri) {
                $this->setLoadedFile(
                    $uri,
                    $globalSchemas[$namespace] = $schema = new Schema()
                );
                $this->setLoadedSchema($namespace, $schema);
                if ($namespace === self::XSD_NS) {
                    $this->globalSchema = $schema;
                }
                $xml = $this->getDOM($this->knownLocationSchemas[$uri]);
                $callbacks = array_merge($callbacks, $this->schemaNode($schema, $xml->documentElement));
            }

            $globalSchemas[(string) static::XSD_NS]->addType(new SimpleType($globalSchemas[(string) static::XSD_NS], 'anySimpleType'));
            $globalSchemas[(string) static::XSD_NS]->addType(new SimpleType($globalSchemas[(string) static::XSD_NS], 'anyType'));

            $globalSchemas[(string) static::XML_NS]->addSchema(
                $globalSchemas[(string) static::XSD_NS],
                (string) static::XSD_NS
            );
            $globalSchemas[(string) static::XSD_NS]->addSchema(
                $globalSchemas[(string) static::XML_NS],
                (string) static::XML_NS
            );

            /**
             * @var Closure $callback
             */
            foreach ($callbacks as $callback) {
                $callback();
            }
        }

        if (!($this->globalSchema instanceof Schema)) {
            throw new TypeException('Global schema not discovered');
        }

        return $this->globalSchema;
    }

    /**
     * @param DOMNode[] $nodes
     */
    public function readNodes(array $nodes, string $file = null): Schema
    {
        $rootSchema = new Schema();
        $rootSchema->addSchema($this->getGlobalSchema());

        if ($file !== null) {
            $this->setLoadedFile($file, $rootSchema);
        }

        $all = [];
        foreach ($nodes as $k => $node) {
            if (($node instanceof \DOMElement) && $node->namespaceURI === self::XSD_NS && $node->localName == 'schema') {
                $holderSchema = new Schema();
                $holderSchema->addSchema($this->getGlobalSchema());

                $this->setLoadedSchemaFromElement($node, $holderSchema);

                $rootSchema->addSchema($holderSchema);

                $callbacks = $this->schemaNode($holderSchema, $node);
                $all = array_merge($callbacks, $all);
            }
        }

        foreach ($all as $callback) {
            call_user_func($callback);
        }

        return $rootSchema;
    }

    public function readNode(DOMElement $node, string $file = null): Schema
    {
        $rootSchema = new Schema();
        $rootSchema->addSchema($this->getGlobalSchema());

        if ($file !== null) {
            $this->setLoadedFile($file, $rootSchema);
        }

        $this->setLoadedSchemaFromElement($node, $rootSchema);
        $callbacks = $this->schemaNode($rootSchema, $node);

        foreach ($callbacks as $callback) {
            call_user_func($callback);
        }

        return $rootSchema;
    }

    /**
     * @throws IOException
     */
    public function readString(string $content, string $file = 'schema.xsd'): Schema
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        if (!@$xml->loadXML($content)) {
            throw new IOException("Can't load the schema", 0, $this->extractErrorMessage());
        }
        libxml_use_internal_errors(false);
        $xml->documentURI = $file;

        return $this->readNode($xml->documentElement, $file);
    }

    /**
     * @throws IOException
     */
    public function readFile(string $file): Schema
    {
        $file = SchemaFileUtils::cacheFile('', $file);
        $xml = $this->getDOM($file);
        return $this->readNode($xml->documentElement, $file);
    }

    /**
     * @throws IOException
     */
    private function getDOM(string $file): DOMDocument
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        if (!@$xml->load($file)) {
            libxml_use_internal_errors(false);
            throw new IOException("Can't load the file '$file'", 0, $this->extractErrorMessage());
        }
        libxml_use_internal_errors(false);

        return $xml;
    }

    private static function againstDOMNodeList(
        DOMElement $node,
        Closure $againstNodeList
    ): void {
        $limit = $node->childNodes->length;
        for ($i = 0; $i < $limit; ++$i) {
            /**
             * @var DOMNode
             */
            $childNode = $node->childNodes->item($i);

            if ($childNode instanceof DOMElement) {
                $againstNodeList(
                    $node,
                    $childNode
                );
            }
        }
    }

    private function loadTypeWithCallback(
        Schema $schema,
        DOMElement $childNode,
        Closure $callback
    ): void {
        /**
         * @var Closure|null $func
         */
        $func = null;

        switch ($childNode->localName) {
            case 'complexType':
                $func = $this->loadComplexType($schema, $childNode, $callback);
                break;
            case 'simpleType':
                $func = $this->loadSimpleType($schema, $childNode, $callback);
                break;
        }

        if ($func instanceof Closure) {
            call_user_func($func);
        }
    }

    private function loadElement(
        Schema $schema,
        DOMElement $node
    ): Element {
        $element = new Element($schema, $node->getAttribute('name'));
        $element->setDoc($this->getDocumentation($node));

        $this->fillItem($element, $node);

        self::maybeSetMax($element, $node);
        self::maybeSetMin($element, $node);
        self::maybeSetDefault($element, $node);

        $xp = new \DOMXPath($node->ownerDocument);
        $xp->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        if ($xp->query('ancestor::xs:choice', $node)->length) {
            $element->setMin(0);
        }

        if ($node->hasAttribute('nillable')) {
            $element->setNil($node->getAttribute('nillable') == 'true');
        }
        if ($node->hasAttribute('form')) {
            $element->setQualified($node->getAttribute('form') == 'qualified');
        }

        $parentNode = $node->parentNode;

        if ($parentNode->localName != 'schema' || $parentNode->namespaceURI != 'http://www.w3.org/2001/XMLSchema') {
            $element->setLocal(true);
        }

        return $element;
    }

    private function addAttributeFromAttributeOrRef(
        BaseComplexType $type,
        DOMElement $childNode,
        Schema $schema,
        DOMElement $node
    ): void {
        $attribute = $this->getAttributeFromAttributeOrRef(
            $childNode,
            $schema,
            $node
        );

        $type->addAttribute($attribute);
    }

    private function findSomethingLikeAttributeGroup(
        Schema $schema,
        DOMElement $node,
        DOMElement $childNode,
        AttributeContainer $addToThis
    ): void {
        $attribute = $this->findAttributeGroup($schema, $node, $childNode->getAttribute('ref'));
        $addToThis->addAttribute($attribute);
    }

    private function setLoadedFile(string $key, Schema $schema): void
    {
        $this->loadedFiles[$key] = $schema;
    }

    private function setLoadedSchemaFromElement(DOMElement $node, Schema $schema): void
    {
        if ($node->hasAttribute('targetNamespace')) {
            $this->setLoadedSchema($node->getAttribute('targetNamespace'), $schema);
        }
    }

    private function setLoadedSchema(string $namespace, Schema $schema): void
    {
        if (!isset($this->loadedSchemas[$namespace])) {
            $this->loadedSchemas[$namespace] = [];
        }
        if (!in_array($schema, $this->loadedSchemas[$namespace], true)) {
            $this->loadedSchemas[$namespace][] = $schema;
        }
    }

    private function setSchemaThingsFromNode(
        Schema $schema,
        DOMElement $node,
        Schema $parent = null
    ): void {
        $schema->setDoc($this->getDocumentation($node));

        if ($node->hasAttribute('targetNamespace')) {
            $schema->setTargetNamespace($node->getAttribute('targetNamespace'));
        } elseif ($parent instanceof Schema) {
            $schema->setTargetNamespace($parent->getTargetNamespace());
        }
        $schema->setElementsQualification($node->getAttribute('elementFormDefault') == 'qualified');
        $schema->setAttributesQualification($node->getAttribute('attributeFormDefault') == 'qualified');
        $schema->setDoc($this->getDocumentation($node));
    }
}
