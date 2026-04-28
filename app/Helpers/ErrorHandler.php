<?php

namespace App\Helpers;

class ErrorHandler
{
  /**
   * Handle exceptions and return clean JSON response
   * Never expose database/file structure to frontend
   */
  public static function handle(\Throwable $e): void
  {
    // Get the error code
    $code = $e->getCode() ?: 500;

    // Determine if it's a database error
    $isDatabaseError = (
      strpos($e->getMessage(), 'SQLSTATE') !== false ||
      strpos($e->getMessage(), 'Integrity constraint') !== false ||
      strpos($e->getMessage(), 'Foreign key') !== false ||
      strpos($e->getMessage(), 'Duplicate entry') !== false ||
      get_class($e) === 'PDOException'
    );

    // Log the ACTUAL error internally (for debugging)
    self::logError($e);

    // Send CLEAN response to frontend
    http_response_code(500);

    Response::json(false, self::getCleanMessage($e, $isDatabaseError));
  }

  /**
   * Get clean error message based on error type
   */
  private static function getCleanMessage(\Throwable $e, bool $isDatabaseError): string
  {
    // Database/constraint errors
    if ($isDatabaseError) {
      $message = $e->getMessage();

      // Integrity constraint - specific errors
      if (strpos($message, 'Integrity constraint violation') !== false) {
        if (strpos($message, 'fk_admin_logs_admin_id') !== false) {
          return 'Admin user not found. Please try again.';
        }
        if (strpos($message, 'fk_order_history_order_id') !== false) {
          return 'Order not found. Please try again.';
        }
        return 'Data validation failed. Please check your input.';
      }

      // Duplicate entry
      if (
        strpos($message, 'Duplicate entry') !== false ||
        strpos($message, 'unique constraint') !== false
      ) {
        return 'This record already exists.';
      }

      // Generic database error
      return 'Database operation failed. Please try again later.';
    }

    // Authentication errors
    if (strpos($e->getMessage(), 'Invalid email or password') !== false) {
      return 'Invalid email or password.';
    }

    // File not found errors
    if (strpos($e->getMessage(), 'Class') !== false && strpos($e->getMessage(), 'not found') !== false) {
      return 'Application error. Please contact support.';
    }

    // Default
    return 'Something went wrong. Please try again later.';
  }

  /**
   * Log error for internal debugging (don't expose to user)
   */
  private static function logError(\Throwable $e): void
  {
    $logFile = __DIR__ . '/../../logs/errors.log';

    // Create logs directory if it doesn't exist
    if (!file_exists(dirname($logFile))) {
      mkdir(dirname($logFile), 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $message = "[{$timestamp}] " . get_class($e) . ": " . $e->getMessage() . "\n";
    $message .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $message .= "Trace: " . $e->getTraceAsString() . "\n";
    $message .= str_repeat("-", 80) . "\n";

    file_put_contents($logFile, $message, FILE_APPEND);
  }
}