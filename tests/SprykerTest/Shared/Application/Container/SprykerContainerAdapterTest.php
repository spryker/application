<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Shared\Application\Container;

use Codeception\Test\Unit;
use Spryker\Shared\Application\Container\SprykerContainerAdapter;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Shared
 * @group Application
 * @group Container
 * @group SprykerContainerAdapterTest
 * Add your own group annotations below this line
 */
class SprykerContainerAdapterTest extends Unit
{
    public function testImplementsSymfonyContainerInterface(): void
    {
        $adapter = new SprykerContainerAdapter(new ContainerProxy());

        $this->assertInstanceOf(SymfonyContainerInterface::class, $adapter);
    }

    public function testSetAndGetDelegateToTheWrappedContainer(): void
    {
        $sprykerContainer = new ContainerProxy();
        $adapter = new SprykerContainerAdapter($sprykerContainer);
        $service = new stdClass();

        $adapter->set('foo', $service);

        $this->assertSame($service, $sprykerContainer->get('foo'));
        $this->assertSame($service, $adapter->get('foo'));
    }

    public function testHasReflectsTheWrappedContainer(): void
    {
        $sprykerContainer = new ContainerProxy();
        $adapter = new SprykerContainerAdapter($sprykerContainer);

        $this->assertFalse($adapter->has('foo'));

        $sprykerContainer->set('foo', new stdClass());

        $this->assertTrue($adapter->has('foo'));
    }

    public function testInitializedReflectsWhetherTheServiceIsPresent(): void
    {
        $sprykerContainer = new ContainerProxy();
        $adapter = new SprykerContainerAdapter($sprykerContainer);

        $this->assertFalse($adapter->initialized('foo'));

        $sprykerContainer->set('foo', new stdClass());

        $this->assertTrue($adapter->initialized('foo'));
    }

    public function testGetThrowsServiceNotFoundExceptionForAMissingServiceByDefault(): void
    {
        $adapter = new SprykerContainerAdapter(new ContainerProxy());

        $this->expectException(ServiceNotFoundException::class);

        $adapter->get('missing');
    }

    public function testGetReturnsNullForAMissingServiceWithNullOnInvalidReference(): void
    {
        $adapter = new SprykerContainerAdapter(new ContainerProxy());

        $result = $adapter->get('missing', SymfonyContainerInterface::NULL_ON_INVALID_REFERENCE);

        $this->assertNull($result);
    }

    public function testGetThrowsServiceNotFoundExceptionWhenTheStoredValueIsNotAnObject(): void
    {
        $sprykerContainer = new ContainerProxy();
        $sprykerContainer->set('scalar', 'not-an-object');
        $adapter = new SprykerContainerAdapter($sprykerContainer);

        $this->expectException(ServiceNotFoundException::class);

        $adapter->get('scalar');
    }

    public function testGetParameterReturnsTheStoredValue(): void
    {
        $sprykerContainer = new ContainerProxy();
        $sprykerContainer->set('sprykerContainerAdapterTestParameter', true);
        $adapter = new SprykerContainerAdapter($sprykerContainer);

        $this->assertTrue($adapter->getParameter('sprykerContainerAdapterTestParameter'));
    }

    public function testGetParameterThrowsParameterNotFoundExceptionWhenMissing(): void
    {
        $adapter = new SprykerContainerAdapter(new ContainerProxy());

        $this->expectException(ParameterNotFoundException::class);

        $adapter->getParameter('missing');
    }

    public function testHasParameterReflectsTheWrappedContainer(): void
    {
        $sprykerContainer = new ContainerProxy();
        $adapter = new SprykerContainerAdapter($sprykerContainer);

        $this->assertFalse($adapter->hasParameter('sprykerContainerAdapterTestParameter'));

        $sprykerContainer->set('sprykerContainerAdapterTestParameter', true);

        $this->assertTrue($adapter->hasParameter('sprykerContainerAdapterTestParameter'));
    }

    public function testSetParameterStoresTheValueInTheWrappedContainer(): void
    {
        $sprykerContainer = new ContainerProxy();
        $adapter = new SprykerContainerAdapter($sprykerContainer);

        $adapter->setParameter('sprykerContainerAdapterTestParameter', true);

        $this->assertTrue($sprykerContainer->get('sprykerContainerAdapterTestParameter'));
    }
}
