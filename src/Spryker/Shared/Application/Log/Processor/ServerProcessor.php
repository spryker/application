<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Application\Log\Processor;

use Spryker\Service\UtilNetwork\Model\Host;

/**
 * @deprecated Use `ServerProcessorPlugin`s from Log module instead.
 */
class ServerProcessor
{
    /**
     * @var string
     */
    public const EXTRA = 'server';

    /**
     * @var string
     */
    public const URL = 'url';

    /**
     * @var string
     */
    public const IS_HTTPS = 'is_https';

    /**
     * @var string
     */
    public const HOST_NAME = 'hostname';

    /**
     * @var string
     */
    public const USER_AGENT = 'user_agent';

    /**
     * @var string
     */
    public const USER_IP = 'user_ip';

    /**
     * @var string
     */
    public const REQUEST_METHOD = 'request_method';

    /**
     * @var string
     */
    public const REFERER = 'referer';

    /**
     * @var string
     */
    public const RECORD_EXTRA = 'extra';

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        $record[static::RECORD_EXTRA][static::EXTRA] = $this->getData();

        return $record;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return [
            static::URL => $this->getUrl(),
            static::IS_HTTPS => (int)$this->isSecureConnection(),
            static::HOST_NAME => $this->getHost(),
            static::USER_AGENT => $this->getUserAgent(),
            static::USER_IP => $this->getRemoteAddress(),
            static::REQUEST_METHOD => $this->getRequestMethod(),
            static::REFERER => $this->getHttpReferer(),
        ];
    }

    /**
     * @return string
     */
    protected function getUrl()
    {
        $serverName = $_SERVER['SERVER_NAME'] ?? null;
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        $protocol = 'http://';

        if ($this->isSecureConnection()) {
            $protocol = 'https://';
        }

        $url = '';
        if ($serverName) {
            $url = $protocol . $serverName . $requestUri;
        }

        return $url;
    }

    /**
     * @return bool
     */
    protected function isSecureConnection()
    {
        if (
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        $utilNetworkHost = new Host();

        return $_SERVER['COMPUTERNAME'] ?? $utilNetworkHost->getHostname();
    }

    /**
     * @return string|null
     */
    protected function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * @return string|null
     */
    protected function getRemoteAddress()
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * @return string
     */
    protected function getRequestMethod()
    {
        return isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'cli';
    }

    /**
     * @return string|null
     */
    protected function getHttpReferer()
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }
}
