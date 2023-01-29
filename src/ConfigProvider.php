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

namespace Phayne\Behat;

use GuzzleHttp\ClientInterface;
use Phayne\Behat\Context\ParameterBag;

/**
 * Class ConfigProvider
 *
 * @package Phayne\Behat
 * @author Julien Guittard <julien.guittard@me.com>
 */
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    ClientInterface::class => Container\GuzzleClientFactory::class,
                ],
                'invokables' => [
                    ParameterBag\ParameterBagInterface::class => ParameterBag\InMemoryPlaceholderBag::class,
                ],
            ],
        ];
    }
}
