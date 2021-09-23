<?php

namespace Constants;

class AuthErrorCodes
{
  const REGISTER_EMAIL_USERNAME_EXISTS_CHECK_FAIL = 100;
  const REGISTER_USER_CREATE_FAILED = 102;
  const REGISTER_TOKEN_CREATE_FAILED = 103;

  const LOGIN_EMAIL_USERNAME_EXISTS_CHECK_FAIL = 104;
  const LOGIN_PASSWORD_GET_FAILED = 105;
  const LOGIN_GET_USER_FAILED = 106;
}
