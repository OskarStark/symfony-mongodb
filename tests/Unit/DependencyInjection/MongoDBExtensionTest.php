<?php

declare(strict_types=1);

/**
 * Copyright 2023-present MongoDB, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace MongoDB\Bundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use MongoDB\Bundle\DependencyInjection\MongoDBExtension;
use MongoDB\Client;
use MongoDB\Driver\ServerApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/** @covers \MongoDB\Bundle\DependencyInjection\MongoDBExtension */
final class MongoDBExtensionTest extends TestCase
{
    public function testGetClientServiceName(): void
    {
        $this->assertSame('mongodb.client.default', MongoDBExtension::createClientServiceId('default'));
    }

    public function testGetClientServiceNameThrowsExceptionIfProvidedClientIdIsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The client id cannot be empty.');

        MongoDBExtension::createClientServiceId(' ');
    }

    public function testLoadWithSingleClient(): void
    {
        $container = $this->getContainer([[
            'clients' => [
                ['id' => 'default', 'uri' => 'mongodb://localhost:27017'],
            ],
        ],
        ]);

        $this->assertTrue($container->hasDefinition('mongodb.client.default'));
        $this->assertTrue($container->hasAlias(Client::class));
        $this->assertSame('mongodb.client.default', (string) $container->getAlias(Client::class));

        // Check service definition
        $definition = $container->getDefinition('mongodb.client.default');
        $this->assertSame(Client::class, $definition->getClass());
        $this->assertSame('mongodb://localhost:27017', $definition->getArgument('$uri'));

        // Check alias definition
        $alias = $container->getAlias(Client::class);
        $this->assertSame('mongodb.client.default', (string) $alias);
    }

    public function testLoadWithMultipleClients(): void
    {
        $container = $this->getContainer([[
            'default_client' => 'secondary',
            'clients' => [
                [
                    'id' => 'default',
                    'uri' => 'mongodb://localhost:27017',
                    'uriOptions' => ['readPreference' => 'primary'],
                ],
                [
                    'id' => 'secondary',
                    'uri' => 'mongodb://localhost:27018',
                    'driverOptions' => ['serverApi' => new ServerApi((string) ServerApi::V1)],
                ],
            ],
        ],
        ]);

        $this->assertTrue($container->hasDefinition('mongodb.client.default'));
        $this->assertTrue($container->hasDefinition('mongodb.client.secondary'));
        $this->assertTrue($container->hasAlias(Client::class));
        $this->assertSame('mongodb.client.secondary', (string) $container->getAlias(Client::class));

        // Check service definitions
        $definition = $container->getDefinition('mongodb.client.default');
        $this->assertSame(Client::class, $definition->getClass());
        $this->assertSame('mongodb://localhost:27017', $definition->getArgument('$uri'));
        $this->assertSame(['readPreference' => 'primary'], $definition->getArgument('$uriOptions'));

        $definition = $container->getDefinition('mongodb.client.secondary');
        $this->assertSame(Client::class, $definition->getClass());
        $this->assertSame('mongodb://localhost:27018', $definition->getArgument('$uri'));
        $this->assertEquals(['serverApi' => new ServerApi((string) ServerApi::V1)], $definition->getArgument('$driverOptions'));
    }

    private function getContainer(array $config = [], array $thirdPartyDefinitions = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());

        foreach ($thirdPartyDefinitions as $id => $definition) {
            $container->setDefinition($id, $definition);
        }

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        $loader = new MongoDBExtension();
        $loader->load($config, $container);
        $container->compile();

        return $container;
    }
}
