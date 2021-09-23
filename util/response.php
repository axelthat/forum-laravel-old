<?php

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

function sendErrorResponse(int $responseCode, int $errorCode, string|array|null $message)
{
  return response()->json([
    "errors" => $message ?? HTTP_MESSAGES[$responseCode],
    "code" => $errorCode
  ], $responseCode);
}
