<?php

/**
 * (c) Spryker Systems GmbH copyright protected
 */

namespace SprykerEngine\Shared\Application\Communication;

use Silex\Application\TranslationTrait;
use Silex\Application\TwigTrait;
use Silex\Application\UrlGeneratorTrait;
use SprykerEngine\Shared\Gui\Form\AbstractForm;
use Symfony\Cmf\Component\Routing\ChainRouter;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Routing\RouterInterface;

class Application extends \Silex\Application
{

    use UrlGeneratorTrait;
    use TwigTrait;
    use TranslationTrait;

    const COOKIES = 'cookies';
    const REQUEST = 'request';
    const ROUTERS = 'routers';
    const REQUEST_STACK = 'request_stack';

    /**
     * Returns a form.
     *
     * @see createBuilder()
     *
     * @param string|FormTypeInterface $type The type of the form
     * @param mixed $data The initial data
     * @param array $options The options
     *
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException if any given option is not applicable to the given type
     *
     * @return FormInterface The form named after the type
     *
     * @deprecated Use buildForm() instead.
     */
    public function createForm($type = 'form', $data = null, array $options = [])
    {
        /** @var FormInterface $form */
        $form = $this['form.factory']->create($type, $data, $options);
        $request = ($this[self::REQUEST_STACK]) ? $this[self::REQUEST_STACK]->getCurrentRequest() : $this[self::REQUEST];
        $form->handleRequest($request);

        return $form;
    }

    /**
     * @param AbstractForm $form
     * @param array $options The options
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     *
     * @return FormInterface The form named after the type
     */
    public function buildForm(AbstractForm $form, array $options = [])
    {
        return $this['form.factory']->create($form, $form->populateFormFields(), $options);
    }

    /**
     * @return \ArrayObject
     */
    public function getCookieBag()
    {
        return $this[self::COOKIES];
    }

    /**
     * Add a router to the list of routers.
     *
     * @param RouterInterface $router The router
     * @param int $priority The priority of the router
     *
     * @return void
     */
    public function addRouter(RouterInterface $router, $priority = 0)
    {
        /* @var \Pimple $this */
        $this[self::ROUTERS] = $this->share($this->extend(self::ROUTERS, function (ChainRouter $chainRouter) use ($router, $priority) {
            $chainRouter->add($router, $priority);

            return $chainRouter;
        }));
    }

}
