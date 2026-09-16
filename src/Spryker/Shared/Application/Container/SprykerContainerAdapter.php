<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace Spryker\Shared\Application\Container;

use Spryker\Service\Container\ContainerInterface as SprykerContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Adapts the Spryker `ContainerInterface` (PSR-11-based `ContainerProxy`) to Symfony's
 * `ContainerInterface` so it can stand in for `Kernel::$container` on projects that have not
 * set up the Symfony container yet (no `config/{APPLICATION}/bundles.php`).
 *
 * Symfony 6.4 leaves `Kernel::$container` untyped, so assigning the Spryker container there
 * directly was always legal. Symfony 7.4 types the property `?ContainerInterface`, so that bare
 * assignment now fatals - this adapter satisfies the type on both, delegating every call to the
 * wrapped Spryker container.
 */
class SprykerContainerAdapter implements SymfonyContainerInterface
{
    /**
     * @param \Spryker\Service\Container\ContainerInterface $container The Spryker container to delegate every call to.
     */
    public function __construct(protected SprykerContainerInterface $container)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function set(string $id, ?object $service): void
    {
        $this->container->set($id, $service);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        $service = $this->container->has($id) ? $this->container->get($id) : null;

        if (is_object($service)) {
            return $service;
        }

        if ($invalidBehavior === static::EXCEPTION_ON_INVALID_REFERENCE) {
            throw new ServiceNotFoundException($id);
        }

        return null;
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function initialized(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * {@inheritDoc}
     *
     * @phpstan-return \UnitEnum|array<mixed>|string|float|int|bool|null
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException
     */
    public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        if (!$this->container->has($name)) {
            throw new ParameterNotFoundException($name);
        }

        /** @var \UnitEnum|array<mixed>|string|float|int|bool|null $value */
        $value = $this->container->get($name);

        return $value;
    }

    public function hasParameter(string $name): bool
    {
        return $this->container->has($name);
    }

    /**
     * @param \UnitEnum|array<mixed>|string|float|int|bool|null $value
     *
     * @return void
     */
    public function setParameter(string $name, $value): void
    {
        $this->container->set($name, $value);
    }
}
