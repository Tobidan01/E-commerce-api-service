<?php
declare(strict_types=1);

namespace App\Helpers;

class Response
{
  public static function json(bool $success, string $message, $data = null): void
  {
    error_log("Response::json called with: " . json_encode(func_get_args()));

    header('Content-Type: application/json');
    echo json_encode([
      'success' => $success,
      'message' => $message,
      'data' => $data
    ]);
    exit;
  }
}
