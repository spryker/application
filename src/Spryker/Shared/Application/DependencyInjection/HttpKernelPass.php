<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Shared\Application\DependencyInjection;

use Spryker\Service\Container\ContainerDelegator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Compiler pass to inject Spryker's EventDispatcher and ControllerResolver into Symfony's HttpKernel.
 *
 * This pass modifies the http_kernel service definition after FrameworkBundle registers it,
 * replacing Symfony's default event_dispatcher and controller_resolver arguments with
 * Spryker's implementations (via bridge services).
 *
 * This ensures that Symfony's HttpKernel uses:
 * - Spryker's EventDispatcher with all event plugins
 * - Spryker's ControllerResolver with custom routing logic
 *
 * While maintaining:
 * - Symfony's RequestStack
 * - Symfony's ArgumentResolver
 */
class HttpKernelPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Only process if http_kernel service exists (i.e., FrameworkBundle is enabled)
        if (!$container->hasDefinition('http_kernel')) {
            return;
        }

        $this->registerSprykerBridgeServices($container);

        // Only process if our bridge services exist
        if (!$container->hasDefinition('spryker.event_dispatcher') || !$container->hasDefinition('spryker.controller_resolver')) {
            return;
        }

        $httpKernelDefinition = $container->getDefinition('http_kernel');

        // Get current arguments
        $arguments = $httpKernelDefinition->getArguments();

        // Replace argument 0: EventDispatcher with Spryker's dispatcher
        $arguments[0] = new Reference('spryker.event_dispatcher');

        // Replace argument 1: ControllerResolver with Spryker's resolver
        $arguments[1] = new Reference('spryker.controller_resolver');

        // Keep arguments 2, 3, 4 as-is (RequestStack, ArgumentResolver, handleAllThrowables)

        // Update the service definition
        $httpKernelDefinition->setArguments($arguments);

        $this->registerSymfonyListenersToSprykerDispatcher($container);
    }

    protected function registerSprykerBridgeServices(ContainerBuilder $container): void
    {
        // This makes the ContainerDelegator available in the ContainerBuilder of Symfony.
        $container->register(ContainerDelegator::class, ContainerDelegator::class)
            ->setFactory([ContainerDelegator::class, 'getInstance'])
            ->setPublic(true);

        // This will register the Spryker EventDispatcher bridge service. Which will then be used inside the HttpKernel.
        $container->setDefinition(
            'spryker.event_dispatcher',
            (new Definition(EventDispatcherInterface::class))
                ->setFactory([new Reference(ContainerDelegator::class), 'get'])
                ->setArguments(['dispatcher'])
                ->setPublic(true),
        );

        // This will register the Spryker ControllerResolver bridge service. Which will then be used inside the HttpKernel.
        $container->setDefinition(
            'spryker.controller_resolver',
            (new Definition(ControllerResolverInterface::class))
                ->setFactory([new Reference(ContainerDelegator::class), 'get'])
                ->setArguments(['controller-resolver'])
                ->setPublic(true),
        );
    }

    protected function registerSymfonyListenersToSprykerDispatcher(ContainerBuilder $container): void
    {
        $sprykerDispatcherDefinition = $container->getDefinition('spryker.event_dispatcher');
        $taggedServices = $container->findTaggedServiceIds('kernel.event_listener');

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $sprykerDispatcherDefinition->addMethodCall('addListener', [
                    $attributes['event'],
                    [new Reference($id), $attributes['method']],
                    $attributes['priority'] ?? 0,
                ]);
            }
        }
    }
}
