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

namespace Phayne\Behat\Exception;

use Phayne\Exception\LogicException;

/**
 * Class AssertionFailedException
 *
 * @package Phayne\Behat\Exception
 * @author Julien Guittard <julien.guittard@me.com>
 */
final class AssertionFailedException extends LogicException
{
}
