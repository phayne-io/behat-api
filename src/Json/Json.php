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

namespace Phayne\Behat\Json;

use Phayne\Exception;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use function json_decode;
use function json_encode;
use function json_last_error;

/**
 * Class Json
 *
 * @package Phayne\Behat\Json
 * @author Julien Guittard <julien.guittard@me.com>
 */
class Json
{
    protected object | array $content;

    /**
     * Json constructor.
     *
     * @param string $content
     */
    public function __construct(string $content)
    {
        $this->content = $this->decode($content);
    }

    public function getContent(): object | array
    {
        return $this->content;
    }

    public function read(array | string $expression, PropertyAccessor $accessor): mixed
    {
        if (is_array($this->content)) {
            $expression = preg_replace('/^root/', '', $expression);
        } else {
            $expression = preg_replace('/^root./', '', $expression);
        }

        if (is_string($expression)) {
            if (strlen(trim($expression)) <= 0) {
                return $this->content;
            } else {
                return $accessor->getValue($this->content, $expression);
            }
        }

        return $this->content;
    }

    public function encode(bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (true === $pretty && defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string)json_encode($this->content, $flags);
    }

    private function decode(string $content): object
    {
        /** @var object $result */
        $result = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception\InvalidArgumentException(sprintf(
                'The string \'%s\' is not valid json',
                $content
            ));
        }

        return $result;
    }
}
