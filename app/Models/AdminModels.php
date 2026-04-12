<?php

namespace App\Models;

use PDO;

class AdminModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  /**
   * Find admin by email
   */
  public function findByEmail(string $email): ?array
  {
    $stmt = $this->db->prepare("
      SELECT id, first_name, last_name, email, password, role, is_active, status
      FROM users
      WHERE email = ? AND role IN ('admin', 'super_admin')
      LIMIT 1
    ");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Find admin by ID
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->db->prepare("
      SELECT id, first_name, last_name, email, role, is_active, status, created_at
      FROM users
      WHERE id = ? AND role IN ('admin', 'super_admin')
      LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Update last login
   */
  public function updateLastLogin(int $adminId): void
  {
    $stmt = $this->db->prepare("
      UPDATE users
      SET last_login = NOW()
      WHERE id = ?
    ");
    $stmt->execute([$adminId]);
  }

  /**
   * Log admin action
   */
  public function logAction(int $adminId, string $action, ?string $resourceType = null, ?int $resourceId = null, ?array $data = null): void
  {
    $stmt = $this->db->prepare("
      INSERT INTO admin_logs (admin_id, action, resource_type, resource_id, new_data, ip_address, created_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $jsonData = $data ? json_encode($data) : null;

    $stmt->execute([
      $adminId,
      $action,
      $resourceType,
      $resourceId,
      $jsonData,
      $ipAddress
    ]);
  }

  /**
   * Get admin activity logs
   */
  public function getActivityLogs(int $limit = 50, int $offset = 0): array
  {
    $stmt = $this->db->prepare("
      SELECT
        al.id,
        al.admin_id,
        u.first_name,
        u.last_name,
        al.action,
        al.resource_type,
        al.resource_id,
        al.ip_address,
        al.created_at
      FROM admin_logs al
      JOIN users u ON al.admin_id = u.id
      ORDER BY al.created_at DESC
      LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Count admin activity logs
   */
  public function countActivityLogs(): int
  {
    $stmt = $this->db->query("SELECT COUNT(*) as count FROM admin_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $result['count'];
  }
}