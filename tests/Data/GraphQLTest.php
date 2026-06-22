<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Data;

use BlueFission\Chronicler\Data\GraphQL\FieldNode;
use BlueFission\Chronicler\Data\GraphQL\SchemaRegistry;
use BlueFission\Chronicler\Data\GraphQL\SelectionSet;
use BlueFission\Data\Schema;
use PHPUnit\Framework\TestCase;

final class GraphQLTest extends TestCase
{
    public function testSelectionSetBuildsGraphQL(): void
    {
        $selection = SelectionSet::fromArray([
            'user' => [
                'id',
                'name',
            ],
        ]);

        $query = (new FieldNode('viewer'))->select('id')->select(new FieldNode('profile', [], $selection));

        $this->assertStringContainsString('viewer', $query->toGraphQL());
        $this->assertStringContainsString('profile', $query->toGraphQL());
        $this->assertStringContainsString('user', $query->toGraphQL());
    }

    public function testFieldArgumentsAreRendered(): void
    {
        $field = new FieldNode('user', ['id' => 'abc', 'active' => true]);

        $this->assertSame('user (id: "abc", active: true)', $field->toGraphQL());
    }

    public function testSchemaRegistryCachesSchemasAndTypes(): void
    {
        $registry = new SchemaRegistry();
        $schema = new Schema(['id' => ['type' => 'string', 'required' => true]]);

        $registry->registerSchema('User', $schema);
        $registry->registerType('User', ['fields' => ['id', 'name']]);

        $this->assertTrue($registry->has('User'));
        $this->assertTrue($registry->validate('User', ['id' => '1']));
        $this->assertSame(['id', 'name'], $registry->traverse('User'));
    }
}
