<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        // this is from the parent method
        if ($this->shouldntReport($exception)) {
            return;
        }

        // this is from the parent method
        try {
            $logger = $this->container->make(\Psr\Log\LoggerInterface::class);
        } catch (Exception $ex) {
            throw $exception; // throw the original exception
        }

        // this is the new custom handling of guzzle exceptions
        if ($exception instanceof \GuzzleHttp\Exception\RequestException) {
            // get the full text of the exception (including stack trace),
            // and replace the original message (possibly truncated),
            // with the full text of the entire response body.
            $message = str_replace(
                rtrim($exception->getMessage()),
                (string)$exception->getResponse()->getBody(),
                (string)$exception
            );

            // log your new custom guzzle error message
            return $logger->error($message);
        }

        // make sure to still log non-guzzle exceptions
        $logger->error($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }
}
