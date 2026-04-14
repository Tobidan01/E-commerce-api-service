<?php

namespace App\Helpers;

class Response
{
  public static function json(
    bool $success,
    string $message,
    $data = null,
    int $statusCode = 200
  ): void {
    http_response_code($statusCode);

    header('Content-Type: application/json');

    echo json_encode([
      'success' => $success,
      'message' => $message,
      'data' => $data
    ]);

    exit;
  }
}