<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [\League\OAuth2\Server\Exception\OAuthServerException::class];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = ['password', 'password_confirmation'];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if ($exception->getMessage() == 'no message') {
            // no need to report empty messages
            return;
        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return mixed
     */
    public function render($request, Exception $e)
    {
        if (
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ||
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        ) {
            return response()->json(
                [
                    'errors' => [
                        'message' => "Not found",
                        'status_code' => 404,
                    ],
                ],
                404
            );
        }
        // handling unauthenticated kickstart requests
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            log_error($e);
            if (
                $request->wantsJson() ||
                $request->expectsJson() ||
                array_get($request->route()->action, 'prefix') == 'api'
            ) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $project_id = array_get($request->route()->parameters(), 'project', false);
            if ($project_id) {
                // FIXME: weird logout bug, simply redirect to the same page for now
                if (array_get($request->route()->action, 'prefix') == 'kickstart') {
                    return redirect()->route('kickstart', ['project' => $project_id]);
                }
            }
            return response('Unauthenticated.', 401);
        }

        if ($this->isHttpException($e)) {
            $message = $e->getMessage();
            if (
                !$message &&
                $e instanceof
                    \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
            ) {
                $message = "Method not allowed";
            }
            return response()->json(
                [
                    'errors' => [
                        'message' => $message,
                        'status_code' => $e->getStatusCode(),
                    ],
                ],
                $e->getStatusCode()
            );
        } elseif (
            $request->wantsJson() &&
            !($e instanceof \Illuminate\Validation\ValidationException) &&
            !($e instanceof \Illuminate\Http\Exceptions\HttpResponseException)
        ) {
            $message = $e->getMessage();
            if (!$message && $e instanceof \Illuminate\Session\TokenMismatchException) {
                $message = "CSRF Token Mismatch";
            }
            return response()->json(
                [
                    'errors' => [
                        'message' => $message,
                        'status_code' => 500,
                    ],
                ],
                500
            );
        }

        return parent::render($request, $e);
    }
}
