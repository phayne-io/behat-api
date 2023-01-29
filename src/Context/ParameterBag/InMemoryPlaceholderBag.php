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

use Phayne\Exception;

/**
 * Class InMemoryPlaceholderBag
 *
 * @package Phayne\Behat\Context\ParameterBag
 * @author Julien Guittard <julien.guittard@me.com>
 */
class InMemoryPlaceholderBag extends InMemoryParameterBag
{
    public function set($name, $value): void
    {
        if (is_object($value)) {
            throw new Exception\UnexpectedValueException('An object cannot be the value of a placeholder');
        }

        parent::set($name, $value);
    }

    public function replace(string $string): string
    {
        foreach ($this->bag as $key => $value) {
            $string = str_replace($key, $value, $string);
        }

        return $string;
    }
}
