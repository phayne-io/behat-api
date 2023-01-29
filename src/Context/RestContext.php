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

namespace Phayne\Behat\Context;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Assert\InvalidArgumentException;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use DateTimeInterface;
use DateTimeZone;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Json\Json as LaminasJson;
use Phayne\Behat\Json\Json;
use Phayne\Behat\Json\JsonInspector;
use Phayne\Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use ResourceBundle;
use Symfony\Component\Console\Output\ConsoleOutput;

use function array_key_exists;
use function file_exists;
use function fopen;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefilledrectangle;
use function imagegif;
use function imagejpeg;
use function imagepng;
use function is_array;
use function is_readable;
use function is_string;
use function method_exists;
use function mime_content_type;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function trim;

/**
 * Class RestContext
 *
 * @package Phayne\Behat\Context
 * @author Julien Guittard <julien.guittard@me.com>
 */
class RestContext implements Context
{
    protected RequestInterface $request;

    protected ?ResponseInterface $response = null;

    protected array $requestOptions = [];

    protected ?JsonInspector $jsonInspector = null;

    private ?ConsoleOutput $output = null;

    public function __construct(
        protected ClientInterface $client,
        protected ?ParameterBag\ParameterBagInterface $parameterBag = null
    ) {
        $this->resetRequest();
    }

    protected function resetRequest(): void
    {
        $this->request = new Psr7\Request(
            RequestMethodInterface::METHOD_GET,
            $this->client->getConfig('base_uri')
        );
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        if (null === $this->response) {
            throw new Exception\RuntimeException('Response is not yet initialized');
        }

        return $this->response;
    }

    protected function getParameterBag(): ParameterBag\ParameterBagInterface
    {
        if (null === $this->parameterBag) {
            $this->parameterBag = new ParameterBag\InMemoryParameterBag();
        }

        return $this->parameterBag;
    }

    protected function getJsonInspector(): JsonInspector
    {
        if (null === $this->jsonInspector) {
            $this->jsonInspector = new JsonInspector('javascript');
        }

        return $this->jsonInspector;
    }

    protected function getJson(): Json
    {
        return new Json((string)$this->getResponse()->getBody());
    }

    protected function requestHasMultipart(): bool
    {
        return
            array_key_exists('multipart', $this->requestOptions) &&
            is_array($this->requestOptions['multipart']) &&
            ! empty($this->requestOptions['multipart']);
    }

    protected function addMultipartPart(array $part): void
    {
        if (! array_key_exists('multipart', $this->requestOptions)) {
            $this->requestOptions['multipart'] = [];
        }

        $this->requestOptions['multipart'][] = $part;
    }

    protected function extractFromParameterBag(string $value): string
    {
        if (method_exists($this->getParameterBag(), 'replace')) {
            return $this->getParameterBag()->replace($value);
        }

        return $value;
    }

    protected function validateResponseCode(int $code): int
    {
        try {
            Assertion::range($code, 100, 599, sprintf('Response code must be between 100 and 599, got %d.', $code));
        } catch (AssertionFailedException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage());
        }

        return $code;
    }

    protected function getResponseBody(): array | object
    {
        return (new Json((string)$this->getResponse()->getBody()))->getContent();
    }

    protected function filterUUID(string $str): string
    {
        if (preg_match("/@.*@/", $str)) {
            return trim((string)preg_replace_callback('/@([\w-]+)@/', function (array $value) {
                return Uuid::uuid5(Uuid::NAMESPACE_OID, $value[1])->toString();
            }, $str));
        }

        return $str;
    }

    protected function checkFilePath(string $path): void
    {
        if (false === file_exists($path)) {
            throw new Exception\InvalidArgumentException(sprintf('File "%s" does not exist', $path));
        }

        if (false === is_readable($path)) {
            throw new Exception\InvalidArgumentException(sprintf('File "%s" is not readable', $path));
        }
    }

    protected function setupRequest(string $method, string $path): void
    {
        $path = $this->extractFromParameterBag($path);
        $path = $this->filterUUID($path);

        $uri = Psr7\UriResolver::resolve($this->client->getConfig('base_uri'), Psr7\Utils::uriFor($path));
        $this->request = $this->request->withMethod($method)->withUri($uri)->withoutHeader('Content-Type');
    }

    protected function setRequestBody(PyStringNode | string $stringNode): void
    {
        $resource = @fopen('data://text/plain,' . $stringNode, 'r');
        $this->request = $this->request->withBody(Psr7\Utils::streamFor($resource));
    }

    protected function sendRequest(): void
    {
        try {
            $this->response = $this->client->send(
                $this->request,
                $this->requestOptions
            );
        } catch (RequestException $e) {
            if ($e->getResponse() !== null) {
                $exception = json_decode((string)$e->getResponse()->getBody());
                $this->response = new JsonResponse($exception, $e->getCode());
            } else {
                $this->response = new JsonResponse($e->getMessage(), StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE);
            }
        } catch (GuzzleException $e) {
            $this->response = new JsonResponse($e->getMessage(), StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE);
        }
    }

    protected function getOutput(): ConsoleOutput
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    /**
     * @BeforeSuite
     *
     * @param BeforeSuiteScope $scope
     * @return void
     */
    public static function prepare(BeforeSuiteScope $scope): void
    {
    }

    /**
     * @AfterSuite
     *
     * @param AfterSuiteScope $scope
     * @return void
     */
    public static function end(AfterSuiteScope $scope): void
    {
    }

    /**
     * @BeforeFeature
     *
     * @param BeforeFeatureScope $scope
     * @return void
     */
    public static function cleanDB(BeforeFeatureScope $scope): void
    {
    }

    /**
     * Defines the request authorization header
     *
     * @param string $token
     * @return void
     *
     * @Given /^I set bearer authentication with "([^"]*)" token$/
     */
    public function iSetTokenAuth(string $token): void
    {
        $this->iSetRequestHeader('Authorization', sprintf('Bearer %s', $token));
    }

    /**
     * @param string $header
     * @param string $value
     * @return void
     *
     * @Given /^I set "([^"]*)" request header to "([^"]*)"$/
     */
    public function iSetRequestHeader(string $header, string $value): void
    {
        $value = $this->filterUUID($value);

        if ($this->request->hasHeader($header)) {
            $this->request = $this->request->withHeader($header, $value);
        } else {
            $this->request = $this->request->withAddedHeader($header, $value);
        }
    }

    /**
     * @param string $header
     * @param string $key
     *
     * @Then /^I save "([^"]*)" header value as "([^"]*)"$/
     */
    public function iRetrieveHeaderValue(string $header, string $key): void
    {
        if ($this->getResponse()->hasHeader($header)) {
            $this->getParameterBag()->set($key, $this->getResponse()->getHeaderLine($header));
        }
    }

    /**
     * @param string $path
     * @param string $partName
     * @return void
     *
     * @Given /^I attach "([^"]*)" file to the request as "([^"]*)"$/
     */
    public function iAttachAFile(string $path, string $partName): void
    {
        $this->checkFilePath($path);

        $this->addMultipartPart([
            'name' => $partName,
            'contents' => @fopen($path, 'r'),
            'filename' => basename($path)
        ]);
    }

    /**
     * @param string $method
     * @param string $uri
     * @return void
     *
     * @When /^I send a "(GET|DELETE|OPTIONS)" request to "([^"]*)"$/
     */
    public function iSendRequestTo(string $method, string $uri): void
    {
        $this->setupRequest($method, $uri);
        $this->sendRequest();
    }

    /**
     * @param string $method
     * @param string $uri
     * @param PyStringNode $pyStringNode
     * @return void
     *
     * @When /^I send a "(POST|PUT|PATCH)" request to "([^"]*)" with following body:$/
     */
    public function iSendRequestToWithJSONContent(string $method, string $uri, PyStringNode $pyStringNode): void
    {
        $this->setupRequest($method, $uri);
        $newStringNodes = [];

        foreach ($pyStringNode->getStrings() as $string) {
            $newStringNodes[] = $this->filterUUID($string);
        }
        $pyStringNode = new PyStringNode($newStringNodes, 0);

        if ($this->requestHasMultipart() && $method === 'POST') {
            $multiparts = LaminasJson::decode($pyStringNode->getRaw(), LaminasJson::TYPE_OBJECT);
            foreach ($multiparts as $field => $value) {
                if (is_object($value)) {
                    $value = LaminasJson::encode($value);
                }
                $this->addMultipartPart([
                    'name' => $field,
                    'contents' => $value
                ]);
            }
        } else {
            unset($this->requestOptions['multipart']);
            $this->iSetRequestHeader('Content-Type', 'application/json');
            $this->setRequestBody($pyStringNode->getRaw());
        }

        $this->sendRequest();
    }

    /**
     * @param string $method
     * @param string $uri
     * @param string $path
     * @return void
     *
     * @When /^I send a "(POST|PUT|PATCH)" request to "([^"]*)" with "([^"]*)" file as body:$/
     */
    public function iSendRequestToWithFile(string $method, string $uri, string $path): void
    {
        $this->checkFilePath($path);
        $this->setupRequest($method, $uri);

        $mime = is_string(mime_content_type($path)) ? mime_content_type($path) : 'application/octet-stream';

        $this->iSetRequestHeader('Content-Type', $mime);
        $this->setRequestBody($path);

        $this->sendRequest();
    }

    /**
     * @param int $width
     * @param int $height
     * @param string $type
     * @param string $partName
     * @When /^I attach a (?P<width>\d+)x(?P<height>\d+) (JPEG|PNG|GIF) image file to the request as "([^"]*)"$/
     */
    public function iUploadAnImageFileWithDimensions(int $width, int $height, string $type, string $partName): void
    {
        unset($this->requestOptions['multipart']);
        $this->iAttachAFile($this->createImage($type, $width, $height), $partName);
    }

    protected function extract(string $value): string
    {
        $value = $this->extractFromParameterBag($value);
        return $this->filterUUID($value);
    }

    protected function createImage(string $type, int $width, int $height): string
    {
        $fileName = tempnam(sys_get_temp_dir(), '');
        $fileName .= '.' . strtolower($type);
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        switch ($type) {
            case 'JPEG':
                imagejpeg($image, $fileName, 100);
                break;
            case 'PNG':
                imagepng($image, $fileName, 9);
                break;
            case 'GIF':
                imagegif($image, $fileName);
        }
        imagedestroy($image);

        return $fileName;
    }

    /**
     * Assert the HTTP response code
     *
     * @param int $code
     * @return void
     *
     * @Then /^(?:the )?response status should be (\d+)$/
     * @throws AssertionFailedException
     */
    public function assertResponseCodeIs(int $code): void
    {
        Assertion::same(
            $actual = $this->getResponse()->getStatusCode(),
            $expected = $this->validateResponseCode($code),
            sprintf('Expected response code %d, got %d.', $expected, $actual)
        );
    }

    /**
     * Assert the HTTP response code is not a specific code
     *
     * @param int $code
     * @return void
     *
     * @Then /^(?:the )?response status should not be (\d+)$/
     * @throws AssertionFailedException
     */
    public function assertResponseCodeIsNot(int $code): void
    {
        Assertion::notSame(
            $actual = $this->getResponse()->getStatusCode(),
            $this->validateResponseCode($code),
            sprintf('Did not expect response code %d.', $actual)
        );
    }

    /**
     * Assert that the HTTP response reason phrase equals a given value
     *
     * @param string $phrase Expected HTTP response reason phrase
     * @return void
     *
     * @Then /^(?:the )?response reason phrase should be "([^"]*)"$/
     * @throws AssertionFailedException
     */
    public function assertResponseReasonPhraseIs(string $phrase): void
    {
        Assertion::same($phrase, $actual = $this->getResponse()->getReasonPhrase(), sprintf(
            'Expected response reason phrase "%s", got "%s".',
            $phrase,
            $actual
        ));
    }

    /**
     * Assert that the HTTP response reason phrase does not equal a given value
     *
     * @param string $phrase Reason phrase that the HTTP response should not equal
     * @return void
     *
     * @Then /^(?:the )?response reason phrase should not be "([^"]*)"$/
     * @throws AssertionFailedException
     */
    public function assertResponseReasonPhraseIsNot(string $phrase): void
    {
        Assertion::notSame($phrase, $this->getResponse()->getReasonPhrase(), sprintf(
            'Did not expect response reason phrase "%s".',
            $phrase
        ));
    }

    /**
     * Assert that a response header exists
     *
     * @param string $header Then name of the header
     * @return void
     *
     * @Then /^(?:the )?"([^"]*)" response header should exist$/
     * @throws AssertionFailedException
     */
    public function assertResponseHeaderExists(string $header): void
    {
        Assertion::true(
            $this->getResponse()->hasHeader($header),
            sprintf('The "%s" response header does not exist.', $header)
        );
    }

    /**
     * Assert that a response header does not exist
     *
     * @param string $header Then name of the header
     * @return void
     *
     * @Then /^(?:the )?"([^"]*)" response header should not exist$/
     * @throws AssertionFailedException
     */
    public function assertResponseHeaderDoesNotExist(string $header): void
    {
        Assertion::false(
            $this->getResponse()->hasHeader($header),
            sprintf('The "%s" response header should not exist.', $header)
        );
    }

    /**
     * Compare a response header value against a string
     *
     * @param string $header The name of the header
     * @param string $value The value to compare with
     * @return void
     *
     * @Then /^(?:the )?"([^"]*)" response header should be equal to "([^"]*)"$/
     * @throws AssertionFailedException
     */
    public function assertResponseHeaderIs(string $header, string $value): void
    {
        $value = $this->extract($value);
        Assertion::same(
            $actual = $this->getResponse()->getHeaderLine($header),
            $value,
            sprintf(
                'Expected the "%s" response header to be "%s", got "%s".',
                $header,
                $value,
                $actual
            )
        );
    }

    /**
     * Assert that a response header is not a value
     *
     * @param string $header The name of the header
     * @param string $value The value to compare with
     * @return void
     *
     * @Then /^(?:the )?"([^"]*)" response header should not be equal to "([^"]*)"$/
     * @throws AssertionFailedException
     */
    public function assertResponseHeaderIsNot(string $header, string $value): void
    {
        Assertion::notSame(
            $this->getResponse()->getHeaderLine($header),
            $value,
            sprintf(
                'Did not expect the "%s" response header to be "%s".',
                $header,
                $value
            )
        );
    }

    /**
     * Match a response header value against a regular expression pattern
     *
     * @param string $header The name of the header
     * @param string $pattern The regular expression pattern
     * @return void
     *
     * @Then /^(?:the )?"([^"]*)" response header should match "([^"]*)"
     * @throws AssertionFailedException
     */
    public function assertResponseHeaderMatches(string $header, string $pattern): void
    {
        Assertion::regex(
            $actual = $this->getResponse()->getHeaderLine($header),
            $pattern,
            sprintf(
                'Expected the "%s" response header to match the regular expression "%s", got "%s".',
                $header,
                $pattern,
                $actual
            )
        );
    }

    /**
     * Compare a response body text against a string
     *
     * @param string $text
     * @return void
     *
     * @Then /^(?:the )?response text should be "([^"]*)"$/
     * @throws AssertionFailedException
     */
    public function assertResponseTextIs(string $text): void
    {
        $actual = (string)$this->getResponse()->getBody();

        Assertion::same($actual, $text, sprintf('The response is equal to: %s', $actual));
    }

    /**
     * Checks that given JSON node exists
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should exist$/
     */
    public function assertJsonNodeExist(string $jsonNode): void
    {
        try {
            $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);
        } catch (Exception\InvalidArgumentException $exception) {
            throw new InvalidArgumentException(sprintf(
                'The node \'%s\' does not exist',
                $jsonNode,
            ), Assertion::INVALID_KEY_EXISTS);
        }
    }

    /**
     * Checks that given JSON node does not exist
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should not exist$/
     */
    public function assertJsonNodeNotExist(string $jsonNode): void
    {
        try {
            $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);
            throw new InvalidArgumentException(sprintf(
                'The node \'%s\' does exist',
                $jsonNode
            ), Assertion::INVALID_KEY_NOT_EXISTS);
        } catch (Exception\InvalidArgumentException) {
        }
    }

    /**
     * Checks that given JSON node is null
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be null$/
     */
    public function assertJsonNodeIsNull(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::null($actual, 'The node value is not null');
    }

    /**
     * Checks that given JSON node is not null
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should not be null$/
     * @throws AssertionFailedException
     */
    public function assertJsonNodeIsNotNull(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::notNull($actual, 'The node value is null');
    }

    /**
     * Checks that given JSON node is equal to another JSON
     *
     * @param PyStringNode $content
     * @return void
     *
     * @Then /^(?:the )?JSON should be equal to:$/
     * @throws AssertionFailedException
     */
    public function assetJsonIsEqualTo(PyStringNode $content): void
    {
        $actual = $this->getJson()->encode(false);
        $expected = new Json($content->getRaw());

        Assertion::same($expected->encode(false), $actual, sprintf('The JSON is equal to: %s', $actual));
    }

    /**
     * Checks that given JSON node matches a pattern
     *
     * @param string $jsonNode
     * @param string $pattern
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should match pattern "(.*)"$/
     * @throws AssertionFailedException
     */
    public function assertJsonNodeMatchPattern(string $jsonNode, string $pattern): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::regex(
            $actual,
            $pattern,
            sprintf(
                'The node \'%s\' does not match the regular expression \'%s\'',
                $jsonNode,
                $pattern
            )
        );
    }

    /**
     * Checks that given JSON node is equal to a string value
     *
     * @param string $jsonNode
     * @param string $value
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be equal to string "([^"]*)"$/
     * @throws AssertionFailedException
     */
    public function assertJsonNodeIsEqualToString(string $jsonNode, string $value): void
    {
        $expected = $this->filterUUID($this->extractFromParameterBag($value));
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::same($expected, $actual, sprintf(
            'The node value is \'%s\'',
            is_array($actual) ? implode(' ', $actual) : (string)$actual
        ));
    }

    /**
     * Checks that given JSON node is equal to a numeric value
     *
     * @param string $jsonNode
     * @param mixed $number
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be equal to number (-?\d+(?:\.\d+)?)$/
     * @throws AssertionFailedException
     */
    public function assertJsonNodeIsEqualToNumber(string $jsonNode, mixed $number): void
    {
        $expected = $this->extractFromParameterBag($number);
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::numeric($number);
        Assertion::eq($expected, $actual, sprintf(
            'The node value is %d',
            is_array($actual) ? implode(' ', $actual) : (string)$actual
        ));
    }

    /**
     * @param string $jsonNode
     * @param string $boolean
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be equal to boolean "(true|false)"$/
     */
    public function assertJsonIsEqualToBool(string $jsonNode, string $boolean): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        forward_static_call([Assertion::class, $boolean], $actual);
    }

    /**
     * Checks that given JSON node is a valid UUID 4
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be a valid UUID$/
     */
    public function assertJsonNodeIsValidUUID(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        if (is_string($actual)) {
            Assertion::uuid($actual, 'The node value is not a valid UUID');
        } else {
            throw new InvalidArgumentException('The node value is not a valid UUID', Assertion::INVALID_UUID);
        }
    }

    /**
     * Checks that given JSON node is a valid ISO8601 datetime
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be a valid ISO8601 formatted datetime$/
     * @throws AssertionFailedException
     */
    public function assertJsonNodeShouldBeValidDateTime(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::date($actual, DateTimeInterface::ATOM, 'The node value cannot validate as a datetime value');
    }

    /**
     * Checks that given JSON node is a valid URL
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )?JSON node "([^"]*)" should be a valid URL$/
     * @throws AssertionFailedException
     */
    public function assertJsonNodeShouldBeAValidURL(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::url($actual, 'The node value is not a valid URL');
    }

    /**
     * Checks that given JSON node is a valid PHP timezone
     *
     * @param string $jsonNode
     * @return void
     *
     * @Then /^(?:the )JSON node "([^"]*)" should be a valid PHP timezone$/
     * @throws AssertionFailedException
     */
    public function assertJSONNodeIsValidPHPTimezone(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::inArray($actual, DateTimeZone::listIdentifiers(), 'The node value is not a valid PHP timezone');
    }

    /**
     * Checks that given JSON node is a valid PHP timezone
     *
     * @param string $jsonNode
     *
     * @return void
     *
     * @Then /^(?:the )JSON node "([^"]*)" should be a valid language code$/
     * @throws AssertionFailedException
     */
    public function assertJSONNodeIsValidLanguageCode(string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);
        $languageCodes = ResourceBundle::getLocales('');

        Assertion::regex($actual, "/^[a-z]{2}_[A-Z]{2}$/", 'The node value is not a valid language code');
        Assertion::inArray($actual, $languageCodes, 'The node value is not a valid language code');
    }

    /**
     * Checks that given JSON array node has exact count
     *
     * @param string $jsonNode
     * @param int $count
     * @return void
     *
     * @Then /^(?:the )?JSON array node "([^"]*)" should have (\d+) elements$/
     * @throws AssertionFailedException
     */
    public function assertJsonArrayNodeCount(string $jsonNode, int $count): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $jsonNode);

        Assertion::isArray($actual, 'The node value is not an array');
        Assertion::count($actual, $count, sprintf('The node has %d elements, not %d', count($actual), $count));
    }

    /**
     * @param string $arrayNode
     * @param string $jsonNode
     *
     * @Then /^in each entry of (?:the )?"([^"]*)" JSON array node, the "([^"]*)" node should exist$/
     * @throws AssertionFailedException
     */
    public function assertEveryArrayJsonNodeShouldExist(string $arrayNode, string $jsonNode): void
    {
        $actual = $this->getJsonInspector()->evaluate($this->getJson(), $arrayNode);

        Assertion::isArray($actual, 'The node value is not an array');

        foreach ($actual as $entry) {
            $jsonEntry = new Json(LaminasJson::encode($entry));
            try {
                $this->getJsonInspector()->evaluate($jsonEntry, $jsonNode);
            } catch (Exception\InvalidArgumentException $exception) {
                throw new InvalidArgumentException(sprintf(
                    'The node \'%s\' does not exist',
                    $jsonNode
                ), Assertion::INVALID_KEY_EXISTS);
            }
        }
    }
}
