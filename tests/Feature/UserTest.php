<?php

namespace Tests\Feature;

use Constants\RedisKeys;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Tests\TestConstants;

class UserTest extends TestCase
{
  /**
   * A basic test example.
   *
   * @return void
   */
  public function testThrowsErrorIfNoDataIsSupplied()
  {
    $messages = TestConstants::getValidationMessages();

    $response = $this->postJson('/api/register', []);

    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => str_replace(":attribute", "email", $messages["required"]),
      "username" => str_replace(":attribute", "username", $messages["required"]),
      "password" => str_replace(":attribute", "password", $messages["required"]),
      "password_confirm" => str_replace(":attribute", "password confirm", $messages["required"]),
    ]);
  }

  public function testThrowsErrorWhenInvalidEmailIsSupplied()
  {
    $messages = TestConstants::getValidationMessages();

    $error = str_replace(":attribute", "email", $messages["email"]);

    $response = $this->postJson('/api/register', [
      "email" => "btrtbtb.com"
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => $error
    ]);

    $response = $this->postJson('/api/register', [
      "email" => "btrtbtb asgasg"
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => $error
    ]);

    $response = $this->postJson('/api/register', [
      "email" => "btrtbtb@@agasgasg"
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => $error
    ]);

    $response = $this->postJson('/api/register', [
      "email" => "btrtbtb @agasgasg"
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => $error
    ]);

    $response = $this->postJson('/api/register', [
      "email" => "btrtbtb @agasgasg.com"
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => $error
    ]);

    $response = $this->postJson('/api/register', [
      "email" => "btrtbtb@@agasgasg.com"
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => $error
    ]);
  }

  public function testThrowsErrorIfLongUsernameOrLongPasswordIsSupplied()
  {
    $messages = TestConstants::getValidationMessages();

    $response = $this->postJson('/api/register', [
      "username" => "usernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusernameusername",
      "password" => "passwordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpasswordpassword"
    ]);

    $usernameErr = str_replace(":attribute", "username", $messages["max"]["string"]);
    $usernameErr = str_replace(":max", "255", $usernameErr);

    $passwordErr = str_replace(":attribute", "password", $messages["max"]["string"]);
    $passwordErr = str_replace(":max", "255", $passwordErr);

    $response->assertStatus(422)->assertJsonValidationErrors([
      "username" => $usernameErr,
      "password" => $passwordErr
    ]);
  }

  public function testThrowsErrorIfShortUsernameOrLongPasswordIsSupplied()
  {
    $messages = TestConstants::getValidationMessages();

    $response = $this->postJson('/api/register', [
      "username" => "u",
      "password" => "p"
    ]);

    $usernameErr = str_replace(":attribute", "username", $messages["min"]["string"]);
    $usernameErr = str_replace(":min", "2", $usernameErr);

    $passwordErr = str_replace(":attribute", "password", $messages["min"]["string"]);
    $passwordErr = str_replace(":min", "2", $passwordErr);

    $response->assertStatus(422)->assertJsonValidationErrors([
      "username" => $usernameErr,
      "password" =>  $passwordErr
    ]);
  }

  public function testThrowsErrorIfEmailExists()
  {
    $email = "test@email.com";

    Redis::hset(RedisKeys::USER_EMAIL_PRIMARY_IDX, $email, "1");

    $response = $this->postJson("/api/register", [
      "email" => $email,
      "username" => "nousername",
      "password" => "password",
      "password_confirm" => "password"
    ]);

    $response->assertStatus(409)->assertJsonValidationErrors([
      "email" => "This email already exists"
    ]);
  }

  public function testThrowsErrorIfUsernameExists()
  {
    $username = "testusernamecom";

    Redis::hset(RedisKeys::USER_USERNAME_PRIMARY_IDX, $username, "1");

    $response = $this->postJson("/api/register", [
      "email" => "new@gmail.com",
      "username" => $username,
      "password" => "password",
      "password_confirm" => "password"
    ]);

    $response->assertStatus(409)->assertJsonValidationErrors([
      "username" => "This username already exists"
    ]);
  }

  public function testCreatesUser()
  {
    $response = $this->postJson("/api/register", [
      "email" => "new@gmail.com",
      "username" => "newusername",
      "password" => "password",
      "password_confirm" => "password"
    ]);

    $response->assertStatus(200);
  }
}
