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

namespace PhayneTest\Behat;

use Phayne\Behat\ConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigProviderTest
 *
 * @package PhayneTest\Behat
 * @author Julien Guittard <julien.guittard@me.com>
 */
class ConfigProviderTest extends TestCase
{
    public function testInvoke()
    {
        $configProvider = new ConfigProvider();
        $config = $configProvider();
        $this->assertCount(1, $config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('factories', $config['dependencies']);
        $this->assertArrayHasKey('invokables', $config['dependencies']);
    }
}
