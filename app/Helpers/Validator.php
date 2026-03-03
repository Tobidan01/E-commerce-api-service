<?php

namespace App\Helpers;

class Validator
{
  public static function required(array $data, array $fields): array
  {
    $errors = [];

    foreach ($fields as $field) {
      if (!isset($data[$field]) || $data[$field] === '') {
        $errors[$field] = "$field is required";
      }
    }

    return $errors;
  }

  public static function numeric(array $data, array $fields): array
  {
    $errors = [];

    foreach ($fields as $field) {
      if (isset($data[$field]) && !is_numeric($data[$field])) {
        $errors[$field] = "$field must be numeric";
      }
    }

    return $errors;
  }

  public static function minLength(array $data, string $field, int $min): ?string
  {
    if (isset($data[$field]) && strlen($data[$field]) < $min) {
      return "$field must be at least $min characters";
    }
    return null;
  }

  public static function email(string $email): ?string
  {
    return filter_var($email, FILTER_VALIDATE_EMAIL)
      ? null
      : "Invalid email format";
  }

  public static function in(string $value, array $allowed): ?string
  {
    return in_array($value, $allowed)
      ? null
      : "Invalid value: $value";
  }
}