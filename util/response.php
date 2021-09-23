<?php

use Constants\AuthErrorCodes;
use Illuminate\Support\Facades\Log;

const HTTP_MESSAGES = [
  200 => "Success",
  409 => "Record already exists",
  500 => "Internal server error",
];

function sendSuccessResponse(string|array|null $message)
{
  return response()->json([
    "data" => $message ?? HTTP_MESSAGES[200]
  ], 200);
}

function sendErrorResponse(
  int $responseCode,
  int|null $errorCode = null,
  Exception|null $exception = null,
  string|null $errorSubject = null,
  string|array|null $message = null
) {
  if (!is_null($exception)) {
    $line = $exception->getLine();
    Log::critical($errorSubject, [
      "message" => $exception->getMessage(),
      "code" => $errorCode,
      "line" => $line,
    ]);
  }

  $response = [
    "errors" => $message ?? HTTP_MESSAGES[$responseCode],
    "code" => $errorCode
  ];
  if (is_null($errorCode)) {
    unset($response["code"]);
  }

  return response()->json($response, $responseCode);
}
