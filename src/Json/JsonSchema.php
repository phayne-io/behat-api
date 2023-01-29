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

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Phayne\Exception;

/**
 * Class JsonSchema
 *
 * @package Phayne\Behat\Json
 * @author Julien Guittard <julien.guittard@me.com>
 */
class JsonSchema extends Json
{
    public function __construct(string $content, private readonly ?string $uri = null)
    {
        parent::__construct($content);
    }

    public function resolve(SchemaStorage $resolver): self
    {
        if (null === $this->uri) {
            return $this;
        }

        $this->content = $resolver->resolveRef($this->uri);

        return $this;
    }

    public function validate(Json $json, Validator $validator): bool
    {
        $validator->check($json->getContent(), $this->getContent());

        if (false === $validator->isValid()) {
            $message = 'JSON does not validate. Violations:' . PHP_EOL;

            /** @var array<array-key|string> $error */
            foreach ($validator->getErrors() as $error) {
                $message .= sprintf("  - [%s] %s" . PHP_EOL, $error['property'], $error['message']);
            }

            throw new Exception\InvalidArgumentException($message);
        }

        return true;
    }
}
