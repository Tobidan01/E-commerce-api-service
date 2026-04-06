<?php

namespace App\Helpers;

class Password
{
  public static function normalize(string $password): string
  {
    // Remove invisible characters + trim
    return trim($password);
  }

  public static function hash(string $password): string
  {
    return password_hash(self::normalize($password), PASSWORD_BCRYPT);
  }

  public static function verify(string $input, string $hash): bool
  {
    return password_verify(self::normalize($input), $hash);
  }
}