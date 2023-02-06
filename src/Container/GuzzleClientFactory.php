<?php

/**
 * This file is part of phayne-io/behat-api package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see       https://github.com/phayne-io/behat-api for the canonical source repository
 * @copyright Copyright (c) 2023 Phayne. (https://phayne.io)
 */

declare(strict_types=1);

namespace Phayne\Behat\Container;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Laminas\Uri\Uri;
use Phayne\Exception\UnexpectedValueException;
use Psr\Container\ContainerInterface;

/**
 * Class GuzzleClientFactory
 *
 * @package Phayne\Behat\Container
 * @author Julien Guittard <julien.guittard@me.com>
 */
class GuzzleClientFactory
{
    public function __invoke(ContainerInterface $container): ClientInterface
    {
        $config = $container->get('config');
        $behatConfig = $config['behat'] ?? [];
        $url = $this->stripScheme($behatConfig['url'] ?? 'localhost');
        $ssl = (bool)$behatConfig['ssl'] ?? false;
        $url = $this->prependScheme($url, $ssl);

        if (false === ($baseUri = $this->validateConnection($url, $ssl))) {
            throw new UnexpectedValueException('Provided URL is invalid or cannot be reached');
        }

        return new Client(['base_uri' => $baseUri, 'verify' => false]);
    }

    private function validateConnection(string $strUri, bool $ssl): false | string
    {
        $uri = new Uri($strUri);

        if (! $uri->isValid()) {
            return false;
        }

        set_error_handler(function () {
            return true;
        });

        $resource = fsockopen($uri->getHost(), $uri->getPort() ?? 80);
        restore_error_handler();

        if ($resource === false) {
            return false;
        }

        fclose($resource);

        return $uri->toString();
    }

    public function stripScheme(string $url): string
    {
        return preg_replace('/^http(s):\/\//', '', $url);
    }

    public function prependScheme(string $url, bool $ssl): string
    {
        return ($ssl ? 'https' : 'http') . '://' . $url;
    }
}
