<?php

namespace Tests\Feature\Auth;

use Illuminate\Testing\Fluent\AssertableJson;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Tests\TestConstants;

class LoginTest extends TestCase
{
  public function testThrowsErrorIfNoDataIsSent()
  {
    $messages = TestConstants::getValidationMessages();

    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)->assertJsonValidationErrors([
      "email" => str_replace(":attribute", "email", $messages["required"]),
      "password" => str_replace(":attribute", "password", $messages["required"]),
    ]);
  }

  public function testThrowsErrorWhenIncorrectEmailIsSent()
  {
    $response = $this->postJson("/api/login", [
      "email" => "noreply@email.com",
      "password" => "newpassword"
    ]);

    $response->assertStatus(404)->assertJsonValidationErrors([
      "email" => "This email or username doesn't exist"
    ]);
  }

  public function testThrowsErrorWhenIncorrectUsernameIsSent()
  {
    $this->postJson("/api/register", [
      "email" => "new@email.com",
      "username" => "newusername",
      "password" => "password",
      "password_confirm" => "password"
    ]);

    $response = $this->postJson("/api/login", [
      "email" => "nousername",
      "password" => "newpassword"
    ]);

    $response->assertStatus(404)->assertJsonValidationErrors([
      "email" => "This email or username doesn't exist"
    ]);
  }

  public function testThrowsErrorIfIncorrectPasswordIsSent()
  {
    $this->postJson("/api/register", [
      "email" => "new@email.com",
      "username" => "newusername",
      "password" => "password",
      "password_confirm" => "password"
    ]);

    $response = $this->postJson("/api/login", [
      "email" => "new@email.com",
      "password" => "incorrectpassword"
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors([
      "password" => "Wrong password entered"
    ]);
  }

  public function testCanLogin()
  {
    $this->postJson("/api/register", [
      "email" => "new@email.com",
      "username" => "newusername",
      "password" => "password",
      "password_confirm" => "password"
    ]);

    $response = $this->postJson("/api/login", [
      "email" => "new@email.com",
      "password" => "password"
    ]);

    $response->assertStatus(200);

    $jsonResponse = json_decode($response->getContent(), true)["data"];
    AssertableJson::fromArray($jsonResponse)
      ->whereType("token", "string")
      ->has("user", 5);

    AssertableJson::fromArray($jsonResponse["user"])
      ->whereType("id", "string")
      ->whereType("username", "string")
      ->whereType("email", "string")
      ->whereType("created_at", "string")
      ->whereType("updated_at", "string")
      ->missing("password");
  }
}
