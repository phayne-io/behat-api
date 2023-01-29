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

namespace Phayne\Behat\Context\ParameterBag;

/**
 * Class InMemoryParameterBag
 *
 * @package Phayne\Behat\Context\ParameterBag
 * @author Julien Guittard <julien.guittard@me.com>
 */
class InMemoryParameterBag implements ParameterBagInterface
{
    protected array $bag = [];

    public function set(string $name, mixed $value): void
    {
        $this->bag[$name] = $value;
    }

    public function get(string $name): mixed
    {
        if (isset($this->bag[$name])) {
            return $this->bag[$name];
        }

        return null;
    }

    public function getAll(): array
    {
        return $this->bag;
    }
}
