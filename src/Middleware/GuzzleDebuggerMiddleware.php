<?php
declare(strict_types=1);

namespace Szemul\DebuggerGuzzleBridge\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Szemul\Debugger\DebuggerInterface;
use Szemul\Debugger\Event\HttpRequestSentEvent;
use Szemul\Debugger\Event\HttpResponseReceivedEvent;
use Throwable;

class GuzzleDebuggerMiddleware
{
    public function __construct(protected DebuggerInterface $debugger)
    {
    }

    public function __invoke(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
                $startEvent = new HttpRequestSentEvent($request);
                $this->debugger->handleEvent($startEvent);
                $successCallback = function (ResponseInterface $response) use ($startEvent): ResponseInterface {
                    $this->debugger->handleEvent(new HttpResponseReceivedEvent($startEvent, $response));

                    return $response;
                };

                $failureCallback = function (Throwable $exception) use ($startEvent) {
                    $response = null;
                    if ($exception instanceof RequestException) {
                        $response = $exception->getResponse();
                    }
                    $this->debugger->handleEvent(new HttpResponseReceivedEvent($startEvent, $response, $exception));

                    throw $exception;
                };

                return $handler($request, $options)->then($successCallback, $failureCallback);
            };
        };
    }
}
