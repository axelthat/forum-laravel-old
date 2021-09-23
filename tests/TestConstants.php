<?php

namespace Tests;

const VALDIATION_MESSAGE_PATH = __DIR__ . "/./../resources/lang/en/validation.php";

class TestConstants
{
  public static function getValidationMessages(): array
  {
    return include(VALDIATION_MESSAGE_PATH);
  }
}
