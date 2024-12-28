<?php

namespace Wtsergo\Misc;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Psr7\Internal\PsrInputStream;
use Amp\Http\Client\Psr7\Internal\PsrMessageStream;
use Amp\Http\Client\Psr7\PsrHttpClientException;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Server\ClientException;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\Form as AmpFormParser;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Exce;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ServerRequestFactoryInterface as PsrServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactory;
use Psr\Http\Message\ResponseInterface as PsrResponse;

class PsrServerAdapter
{
    public function __construct(
        private readonly PsrServerRequestFactory $requestFactory,
        private readonly PsrResponseFactory $responseFactory,
    ) {
    }

    public function fromPsrServerRequest(PsrServerRequest $source, Client $client, bool $withBody=true): Request
    {
        /** @psalm-suppress ArgumentTypeCoercion Wrong typehints in PSR */
        $target = new Request(
            $client,
            $source->getMethod(),
            $source->getUri(),
            protocol: $source->getProtocolVersion(),
        );
        $target->setHeaders($source->getHeaders());
        foreach ($source->getAttributes() as $name => $value) {
            $target->setAttribute($name, $value);
        }
        foreach ($source->getCookieParams() as $name => $value) {
            $target->setCookie(new RequestCookie($name, $value));
        }
        $target->setQueryParameters($source->getQueryParams());

        // TODO: convert from $source->getParsedBody()
        /** @psalm-suppress ArgumentTypeCoercion Wrong typehints in PSR */
        if ($withBody) {
            $target->setBody(new PsrInputStream($source->getBody()));
        }

        return $target;
    }

    public function fromPsrServerResponse(PsrResponse $source, Request $request, bool $withBody=true): Response
    {
        /** @psalm-suppress ArgumentTypeCoercion Wrong typehints in PSR */
        $response = new Response(
            $source->getStatusCode(),
            $source->getHeaders()
        );
        // TODO: check how to use
        //$source->getProtocolVersion()
        //$request
        $response->setStatus($source->getStatusCode(), $source->getReasonPhrase());
        if ($withBody) {
            $response->setBody(new PsrInputStream($source->getBody()));
        }
        return $response;
    }

    /**
     * @throws ClientException
     * @throws BufferException
     */
    public function toPsrServerRequest(Request $source, bool $withBody=true, ?string $protocolVersion = null): PsrServerRequest
    {
        $target = $this->toPsrServerRequestWithoutBody($source, $protocolVersion);

        if ($withBody) {
            $bodyStr = $source->getBody()->buffer();
            $source->setBody($bodyStr);
            $formValues = array_map(
                fn ($_) => count($_)>1 ? $_ : $_[0],
                AmpFormParser::fromRequest($source)->getValues()
            );
            $source->setBody($bodyStr);
            $target = $target->withParsedBody($formValues);
            return $target->withBody(new PsrMessageStream(new ReadableBuffer($bodyStr)));
        } else {
            return $target;
        }
    }

    public function toPsrServerResponse(Response $response, bool $withBody=true): PsrResponse
    {
        $psrResponse = $this->responseFactory->createResponse($response->getStatus(), $response->getReason());

        // TODO: check how to use
        //->withProtocolVersion($response->getProtocolVersion());

        foreach ($response->getHeaderPairs() as [$headerName, $headerValue]) {
            $psrResponse = $psrResponse->withAddedHeader($headerName, $headerValue);
        }

        return !$withBody ? $psrResponse : $psrResponse->withBody(new PsrMessageStream($response->getBody()));
    }

    private function toPsrServerRequestWithoutBody(
        Request $source,
        ?string $protocolVersion = null
    ): PsrServerRequest {
        $target = $this->requestFactory->createServerRequest($source->getMethod(), $source->getUri());

        foreach ($source->getHeaderPairs() as [$headerName, $headerValue]) {
            $target = $target->withAddedHeader($headerName, $headerValue);
        }

        foreach ($source->getAttributes() as $name => $value) {
            $target = $target->withAttribute($name, $value);
        }
        $cookies = [];
        foreach ($source->getCookies() as $value) {
            $cookies[$value->getName()] = $value->getValue();
        }
        $target = $target->withCookieParams($cookies);
        $target = $target->withQueryParams($source->getQueryParameters());

        // TODO: check how to integrate
        /*$protocolVersions = $source->getProtocolVersions();
        if ($protocolVersion !== null) {
            if (!\in_array($protocolVersion, $protocolVersions, true)) {
                throw new PsrHttpClientException(
                    "Source request doesn't support the provided HTTP protocol version: {$protocolVersion}",
                    request: $target,
                );
            }

            return $target->withProtocolVersion($protocolVersion);
        }

        if (\count($protocolVersions) === 1) {
            return $target->withProtocolVersion($protocolVersions[0]);
        }

        if (!\in_array($target->getProtocolVersion(), $protocolVersions)) {
            throw new PsrHttpClientException(
                "Can't choose HTTP protocol version automatically: [" . \implode(', ', $protocolVersions) . ']',
                request: $target,
            );
        }
        */

        return $target;
    }
}
