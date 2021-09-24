<?php

namespace App\Http\Controllers;

use Constants\AuthErrorCodes;
use Constants\RedisKeys;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class AuthController extends Controller
{
  /**
   * 
   * Authenticates the user
   * 
   * @param Request $request 
   * @return JsonResponse 
   */
  public function login(Request $request)
  {
    $validation = Validator::make($request->all(), [
      "email" => "required|string",
      "password" => "required|string",
    ]);

    if ($validation->fails()) {
      return sendErrorResponse(
        responseCode: 422,
        message: (new ValidationException($validation))->errors()
      );
    }

    $userId = null;

    try {
      [$emailExists, $usernameExists] = $this->checkIfEmailOrUsernameExists();

      if (!$emailExists && !$usernameExists) {
        return sendErrorResponse(
          responseCode: 404,
          message: [
            "email" => "This email or username doesn't exist"
          ]
        );
      }

      $userId = $emailExists;
    } catch (Exception $e) {
      return sendErrorResponse(
        responseCode: 500,
        errorCode: AuthErrorCodes::LOGIN_EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        exception: $e,
        errorSubject: "failed to check if email/username exists",
      );
    }

    $key = str_replace("<id>", $userId, RedisKeys::USER);

    try {
      $password = Redis::hget($key, "password");
      if (!Hash::check($request->get('password'), $password)) {
        return sendErrorResponse(
          responseCode: 422,
          message: [
            "password" => "Wrong password entered"
          ]
        );
      }
    } catch (Exception $e) {
      return sendErrorResponse(
        responseCode: 500,
        errorCode: AuthErrorCodes::LOGIN_EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        exception: $e,
        errorSubject: "failed to verify password",
      );
    }

    try {
      $user = Redis::hgetall($key);
    } catch (Exception $e) {
      return sendErrorResponse(
        responseCode: 500,
        errorCode: AuthErrorCodes::LOGIN_GET_USER_FAILED,
        exception: $e,
        errorSubject: "failed to get user",
      );
    }

    $token = $this->generateToken($userId);
    if ($token instanceof JsonResponse) {
      return $token;
    }

    $user["id"] = $userId;
    unset($user["password"]);

    return sendSuccessResponse([
      "token" => $token,
      "user" => $user
    ]);
  }

  /**
   * Registers and authenticates the user.
   * 
   * @param Request $request 
   * @return JsonResponse 
   */
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
        responseCode: 422,
        message: (new ValidationException($validation))->errors()
      );
    }

    try {
      [$emailExists, $usernameExists] = $this->checkIfEmailOrUsernameExists();

      if ($emailExists) {
        return sendErrorResponse(
          responseCode: 409,
          message: [
            "email" => "This email already exists"
          ]
        );
      }

      if ($usernameExists) {
        return sendErrorResponse(
          responseCode: 409,
          message: [
            "username" => "This username already exists"
          ]
        );
      }
    } catch (Exception $e) {
      return sendErrorResponse(
        responseCode: 500,
        errorCode: AuthErrorCodes::REGISTER_EMAIL_USERNAME_EXISTS_CHECK_FAIL,
        exception: $e,
        errorSubject: "failed to check if email/username exists",
        message: null
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
      return sendErrorResponse(
        responseCode: 500,
        errorCode: AuthErrorCodes::REGISTER_USER_CREATE_FAILED,
        exception: $e,
        errorSubject: "failed to create user",
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

  /**
   * Checks if email or username exists at once
   * using redis pipeline.
   * 
   * @return mixed 
   * @throws BindingResolutionException 
   */
  private function checkIfEmailOrUsernameExists()
  {
    $request = request();

    return Redis::pipeline(function ($pipeline) use ($request) {
      $pipeline->hget(RedisKeys::USER_EMAIL_PRIMARY_IDX, $request->get("email"));
      $pipeline->hget(RedisKeys::USER_USERNAME_PRIMARY_IDX, $request->get("username"));
    });
  }

  /**
   * Generates a user id for the newly
   * created user.
   * 
   * @return string 
   */
  private function generateUserId(): string
  {
    return Uuid::uuid4()->toString();
  }

  /**
   * Generates a uuid based authentication
   * token.
   * 
   * @param string $userId 
   * @return JsonResponse|string 
   * @throws BindingResolutionException 
   */
  private function generateToken(string $userId): JsonResponse | string
  {
    $token = Uuid::uuid4()->toString();

    try {
      $key = str_replace("<id>", $userId, RedisKeys::USER_TOKEN);
      Redis::hset($key, "token", $token);
      return $token;
    } catch (Exception $e) {
      return sendErrorResponse(
        responseCode: 500,
        errorCode: AuthErrorCodes::REGISTER_TOKEN_CREATE_FAILED,
        exception: $e,
        errorSubject: "failed to issue auth token",
      );
    }
  }
}
