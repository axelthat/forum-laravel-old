<?php

namespace Constants;

class RedisKeys
{
  const USER_CREATED_IDX = "idx:users";
  const USER_UPDATED_IDX = "idx:upd:users";
  const USER_EMAIL_PRIMARY_IDX = "idx:primary:email:users";
  const USER_USERNAME_PRIMARY_IDX = "idx:primary:username:users";
  const USER = "users:<id>";
  const USER_TOKEN = "token:users:<id>";
}
