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

namespace PhayneTest\Behat\Json;

use Phayne\Behat\Json\Json;
use Phayne\Exception;
use PHPUnit\Framework\TestCase;

/**
 * Class JsonTest
 *
 * @package PhayneTest\Behat\Json
 * @author Julien Guittard <julien.guittard@me.com>
 */
class JsonTest extends TestCase
{
    public function testRaiseExceptionIfInvalidJSON()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        new Json('foo');
    }

    public function testEncode()
    {
        $str = '{"foo":"bar"}';
        $json = new Json($str);
        $this->assertEquals($str, $json->encode(false));
    }

    public function testPrettyEncode()
    {
        $str = <<<JSON
        {
            "foo": "bar"
        }
        JSON;
        $json = new Json($str);
        $this->assertEquals($str, $json->encode());
    }
}
