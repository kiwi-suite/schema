<?php
declare(strict_types=1);

namespace Ixocreate\Test\Schema\Type;

use Ixocreate\Schema\Builder\BuilderInterface;
use Ixocreate\Schema\Element\DateElement;
use Ixocreate\Schema\Element\SectionElement;
use Ixocreate\Schema\Element\TextElement;
use Ixocreate\Schema\Schema;
use Ixocreate\Schema\Type\DateType;
use Ixocreate\Schema\Type\SchemaType;
use Ixocreate\Schema\Type\Type;
use Ixocreate\ServiceManager\Exception\ServiceNotFoundException;
use Ixocreate\ServiceManager\ServiceManagerInterface;
use Ixocreate\ServiceManager\SubManager\SubManagerInterface;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ixocreate\Schema\Type\SchemaType
 */
class SchemaTypeTest extends TestCase
{
    public function setUp()
    {
        $typesToRegister = [
            DateType::class => new DateType(),
            DateType::serviceName() => new DateType(),
            SchemaType::class => new SchemaType(
                $this->createMock(ServiceManagerInterface::class),
                $this->createMock(BuilderInterface::class)
            ),
            SchemaType::serviceName() => new SchemaType(
                $this->createMock(ServiceManagerInterface::class),
                $this->createMock(BuilderInterface::class)
            ),
        ];

        $reflection = new \ReflectionClass(Type::class);
        $type = $reflection->newInstanceWithoutConstructor();
        $container = (new MockBuilder($this, SubManagerInterface::class))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();
        $container->method('get')->willReturnCallback(function ($requestedName) use ($typesToRegister){
            if (\array_key_exists($requestedName, $typesToRegister)) {
                return $typesToRegister[$requestedName];
            }

            throw new ServiceNotFoundException('Type not found');
        });

        $container->method('has')->willReturnCallback(function ($requestedName) use ($typesToRegister){
            if (\array_key_exists($requestedName, $typesToRegister)) {
                return true;
            }
            return false;
        });
        $reflection = new \ReflectionProperty($type, 'subManager');
        $reflection->setAccessible(true);
        $reflection->setValue($type, $container);
        $reflection = new \ReflectionProperty(Type::class, 'type');
        $reflection->setAccessible(true);
        $reflection->setValue($type);
    }

    /**
     * @dataProvider providerSchemaTransformations
     * @covers \Ixocreate\Schema\Type\SchemaType::transform
     * @runInSeparateProcess
     */
    public function testTransformationToArray(Schema $schema, $data, $check)
    {
        $schemaType = new SchemaType(
            $this->createMock(ServiceManagerInterface::class),
            $this->createMock(BuilderInterface::class)
        );

        $schemaType = $schemaType->create($data, ['schema' => $schema]);

        foreach ($check as $key => $value) {
            if (\is_object($value)) {
                $this->assertSame($value->jsonSerialize(), $schemaType->{$key}->jsonSerialize());
                continue;
            }

            $this->assertSame($value, $schemaType->{$key});
        }

        $this->assertFalse(isset($schemaType->doesntExist));
        $this->assertNull($schemaType->doesntExist);
    }

    public function providerSchemaTransformations()
    {
        return [
            [
                'schema' => new Schema(),
                'data' => [],
                'check' => []
            ],

            [
                'schema' => (new Schema())
                    ->withAddedElement((new TextElement())->withName('test1'))
                    ->withAddedElement((new TextElement())->withName('test2'))
                ,
                'data' => [
                    'test1' => 'isset'
                ],
                'check' => [
                    'test1' => 'isset',
                    'test2' => null
                ]
            ],

            [
                'schema' => (new Schema())
                    ->withAddedElement((new DateElement())->withName('test1'))
                    ->withAddedElement((new TextElement())->withName('test2'))
                ,
                'data' => [
                    'test1' => '2016-02-04'
                ],
                'check' => [
                    'test1' => (new DateType())->create('2016-02-04'),
                    'test2' => null
                ]
            ],

            [
                'schema' => (new Schema())
                    ->withAddedElement((new SectionElement())
                        ->withName('test1')
                        ->withAddedElement((new TextElement())->withName('section1'))
                        ->withAddedElement((new TextElement())->withName('section2'))
                    )
                    ->withAddedElement((new TextElement())->withName('test2'))
                ,
                'data' => [
                    'section1' => 'text',
                ],
                'check' => [
                    'section1' => 'text',
                    'section2' => null,
                    'test2' => null,
                ]
            ]
        ];
    }
}
