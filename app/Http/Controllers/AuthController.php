<?php

namespace App\Http\Controllers;

use Constants\AuthErrorCodes;
use Constants\RedisKeys;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class AuthController extends Controller
{
  public function login(Request $request)
  {
  }

  public function register(Request $request)
  {
    $validation = Validator::make($request->all(), [
      "email" => "required|email:filter",
      "username" => "required|string|min:2|max:255",
      "password" => "required|string|min:2|max:255",
      "password_confirm" => "required|same:password",
    ]);

    if ($validation->fails()) {
      return sendErrorResponse(
        422,
        AuthErrorCodes::VALIDATION_FAILED,
        (new ValidationException($validation))->errors()
      );
    }

    try {
      [$emailExists, $usernameExists] = Redis::pipeline(function ($pipeline) use ($request) {
        $pipeline->hget(RedisKeys::USER_EMAIL_PRIMARY_IDX, $request->get("email"));
        $pipeline->hget(RedisKeys::USER_USERNAME_PRIMARY_IDX, $request->get("username"));
      });

      if ($emailExists) {
        return sendErrorResponse(409, AuthErrorCodes::EMAIL_EXISTS, [
          "email" => "This email already exists"
        ]);
      }

      if ($usernameExists) {
        return sendErrorResponse(409, AuthErrorCodes::USERNAME_EXISTS, [
          "username" => "This username already exists"
        ]);
      }
    } catch (Exception $e) {
      $line = $e->getLine();
      Log::critical("failed to check if email/username exists", [
        "message" => $e->getMessage(),
        "code" => AuthErrorCodes::EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        "line" => $line,
      ]);

      return sendErrorResponse(
        500,
        AuthErrorCodes::EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        null
      );
    }

    $userId = $this->generateUserId();

    $timestamp = time();
    $user = $request->only(["email", "username"]);
    $user["created_at"] = $timestamp;
    $user["updated_at"] = $timestamp;
    $user["password"] = Hash::make($request->get("password"));

    try {
      Redis::pipeline(function ($pipeline) use ($userId, $timestamp, $user) {
        $timestamp = time();

        $pipeline->hset(RedisKeys::USER_EMAIL_PRIMARY_IDX, $user["email"], $userId);
        $pipeline->hset(RedisKeys::USER_USERNAME_PRIMARY_IDX, $user["username"], $userId);

        $pipeline->zadd(RedisKeys::USER_CREATED_IDX, $timestamp, $userId);
        $pipeline->zadd(RedisKeys::USER_UPDATED_IDX, $timestamp, $userId);

        $pipeline->hmset(str_replace("<id>", $userId, RedisKeys::USER), $user);
      });
    } catch (Exception $e) {
      $line = $e->getLine();
      Log::critical("failed to create user", [
        "message" => $e->getMessage(),
        "code" => AuthErrorCodes::USER_CREATE_FAILED,
        "line" => $line,
      ]);

      return sendErrorResponse(
        500,
        AuthErrorCodes::USER_CREATE_FAILED,
        null
      );
    }

    $token = $this->generateToken($userId);
    if ($token instanceof JsonResponse) {
      return $token;
    }

    $user["id"] = $userId;
    $user["password"] = null;

    return sendSuccessResponse([
      "token" => $token,
      "user" => $user
    ]);
  }

  private function generateUserId(): string
  {
    return Uuid::uuid4()->toString();
  }

  private function generateToken(string $userId): JsonResponse | string
  {
    $token = Uuid::uuid4()->toString();

    try {
      $key = str_replace("<id>", $userId, RedisKeys::USER_TOKEN);
      Redis::hset($key, "token", $token);
      return $token;
    } catch (Exception $e) {
      $line = $e->getLine();
      Log::critical("failed to issue auth token", [
        "message" => $e->getMessage(),
        "code" => AuthErrorCodes::TOKEN_CREATE_FAILED,
        "line" => $line,
      ]);

      return sendErrorResponse(
        500,
        AuthErrorCodes::TOKEN_CREATE_FAILED,
        null
      );
    }
  }
}
