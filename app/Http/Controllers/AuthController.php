<?php

namespace App\Http\Controllers;

use Constants\AuthErrorCodes;
use Constants\RedisKeys;
use Exception;
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
        "log" => AuthErrorCodes::EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        "line" => $line,
      ]);

      return sendErrorResponse(
        500,
        AuthErrorCodes::EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        null
      );
    }

    $userId = Uuid::uuid4();

    try {
      Redis::pipeline(function ($pipeline) use ($request, $userId) {
        $timestamp = time();

        $pipeline->hset(RedisKeys::USER_EMAIL_PRIMARY_IDX, $request->get("email"), $userId);
        $pipeline->hset(RedisKeys::USER_USERNAME_PRIMARY_IDX, $request->get("username"), $userId);

        $pipeline->zadd(RedisKeys::USER_CREATED_IDX, $timestamp, $userId);
        $pipeline->zadd(RedisKeys::USER_UPDATED_IDX, $timestamp, $userId);

        $data = $request->only(["email", "username"]);
        $data["created_at"] = $timestamp;
        $data["updated_at"] = $timestamp;
        $data["password"] = Hash::make($request->get("password"));

        $pipeline->hmset(str_replace("<id>", $userId, RedisKeys::USER), $data);
      });
    } catch (Exception $e) {
      $line = $e->getLine();
      Log::critical("failed to create user", [
        "message" => $e->getMessage(),
        "log" => AuthErrorCodes::USER_CREATE_FAILED,
        "line" => $line,
      ]);

      return sendErrorResponse(
        500,
        AuthErrorCodes::USER_CREATE_FAILED,
        null
      );
    }

    return sendSuccessResponse(null);
  }
}
