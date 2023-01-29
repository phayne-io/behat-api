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

use JsonSchema as RainbowSchema;
use Phayne\Exception;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Class JsonInspector
 *
 * @package Phayne\Behat\Json
 * @author Julien Guittard <julien.guittard@me.com>
 */
class JsonInspector
{
    private PropertyAccessor $accessor;

    public function __construct(private readonly string $evaluationMode)
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function evaluate(Json $json, string $expression): mixed
    {
        if ($this->evaluationMode === 'javascript') {
            $expression = str_replace('->', '.', $expression);
        }

        try {
            return $json->read($expression, $this->accessor);
        } catch (NoSuchPropertyException | Exception\InvalidArgumentException) {
            throw new Exception\InvalidArgumentException(sprintf('Failed to evaluate expression \'%s\'', $expression));
        }
    }

    public function validate(Json $json, JsonSchema $schema): bool
    {
        $validator = new RainbowSchema\Validator();
        $resolver = new RainbowSchema\SchemaStorage(
            new RainbowSchema\Uri\UriRetriever(),
            new RainbowSchema\Uri\UriResolver()
        );
        $schema->resolve($resolver);

        return $schema->validate($json, $validator);
    }
}
