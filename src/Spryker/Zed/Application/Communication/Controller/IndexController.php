<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Application\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method \Spryker\Zed\Application\Business\ApplicationFacadeInterface getFacade()
 * @method \Spryker\Zed\Application\Communication\ApplicationCommunicationFactory getFactory()
 */
class IndexController extends AbstractController
{
    public function indexAction(): ?Response
    {
        $redirectUrl = $this->getFactory()->getConfig()->getIndexActionRedirectUrl();

        if ($redirectUrl === null) {
            return null;
        }

        return $this->redirectResponse($redirectUrl);
    }
}
