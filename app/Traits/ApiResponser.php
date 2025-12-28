<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponser
{
    /**
     * Build a success response.
     *
     * @param  mixed  $data
     * @param  string $message
     * @param  int    $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Build an error response.
     *
     * @param  string $message
     * @param  int    $statusCode
     * @param  mixed  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message, int $statusCode, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Build a response for a created resource.
     *
     * @param  mixed  $data
     * @param  string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function created($data, string $message = 'Resource created successfully.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Build a validation error response.
     *
     * @param  mixed  $errors
     * @param  string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationError($errors, string $message = 'Validation failed.'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Build a response with pagination data.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param mixed $formattedData
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function withPagination($paginator, $formattedData, string $message = 'Resources retrieved successfully.'): JsonResponse
    {
        $response = [
            'success' => true,
            'status' => 200,
            'message' => $message,
            'data' => $formattedData,
            'pagination' => [
                'total_rows' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_pages' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ]
        ];

        return response()->json($response, 200);
    }

    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized access.'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function serverError(string $message = 'Something went wrong.'): JsonResponse
    {
        return $this->error($message, 500);
    }
}
