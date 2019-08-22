<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use App\Http\Paginate\Paginate;
use App\Http\Transformers\Transformer;

use Illuminate\Support\Facades\Lang;

abstract class ApiController extends Controller
{
    /** \App\Http\Transformers\Transformer
     *
     * @var null
     */
    protected $transformer = null;

    /**
     * Return generic json response with the given data.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respond($data, $statusCode = 200, $headers = [])
    {
        return response()->json($data, $statusCode, $headers);
    }

    /**
     * Respond with data after applying transformer.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithTransformer($data, $statusCode = 200, $headers = [])
    {
        $this->checkTransformer();

        if ($data instanceof Collection) {
            $data = $this->transformer->collection($data);
        } else {
            $data = $this->transformer->item($data);
        }

        return $this->respond($data, $statusCode, $headers);
    }

    /**
     * Respond with pagination.
     *
     * @param mixed $paginated
     * @param int $statusCode
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithPagination($paginated, $statusCode = 200, $headers = [])
    {
        $this->checkPaginated($paginated);

        $this->checkTransformer();

        $data = $this->transformer->paginate($paginated);

        return $this->respond($data, $statusCode, $headers);
    }

    /**
     * Respond with success.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondSuccess()
    {
        return $this->respond(null);
    }

    /**
     * Respond with success message.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondMessage($message)
    {
        return $this->respond(
            [
                'success' => true,
                'message' => $message
            ],
            200
        );
    }

    /**
     * Respond with created.
     *
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondCreated($data)
    {
        return $this->respond($data, 201);
    }

    /**
     * Respond with no content.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondNoContent()
    {
        return $this->respond(null, 204);
    }

    /**
     * Respond with error.
     *
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondError($message, $statusCode = 400)
    {
        return $this->respond(
            [
                'errors' => [
                    'message' => $message,
                    'status_code' => $statusCode
                ]
            ],
            $statusCode
        );
    }

    /**
     * Respond with unauthorized.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondUnauthorized($message = 'Unauthorized')
    {
        return $this->respondError(Lang::getFromJson($message), 401);
    }

    /**
     * Respond with forbidden.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondForbidden($message = 'Access Denied')
    {
        return $this->respondError(Lang::getFromJson($message), 403);
    }

    /**
     * Respond with not found.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondNotFound($message = 'Not Found')
    {
        return $this->respondError(Lang::getFromJson($message), 404);
    }

    /**
     * Respond with failed login.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondFailedLogin()
    {
        return $this->respondError(Lang::getFromJson('email or password is invalid'), 422);
    }

    /**
     * Respond with internal error.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondInternalError($message = 'Internal Error')
    {
        return $this->respondError($message, 500);
    }

    /**
     * Check if valid transformer is set.
     *
     * @throws Exception
     */
    private function checkTransformer()
    {
        if ($this->transformer === null || !$this->transformer instanceof Transformer) {
            throw new \Exception('Invalid data transformer.');
        }
    }

    /**
     * Check if valid paginate instance.
     *
     * @param App\Http\Paginate\Paginate $paginated
     * @throws Exception
     */
    private function checkPaginated($paginated)
    {
        if (!$paginated instanceof Paginate) {
            throw new \Exception('Expected instance of Paginate.');
        }
    }
}
