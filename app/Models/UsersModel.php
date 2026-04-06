<?php

namespace App\Models;

use PDO;

class UsersModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function findByEmail(string $email): ?array
  {
    $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, email, password,
                   phone, role, google_id, provider, status, created_at, updated_at
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function findById(int $id): ?array
  {
    $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, email,
                   phone, role, google_id, provider, status, created_at, updated_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function create(array $data): int
  {
    $stmt = $this->db->prepare("
            INSERT INTO users 
                (first_name, last_name, email, password, phone, role, provider, created_at)
            VALUES 
                (:first_name, :last_name, :email, :password, :phone, 'user', 'local', NOW())
        ");

    $stmt->execute([
      ':first_name' => trim($data['first_name']),
      ':last_name' => trim($data['last_name']),
      ':email' => strtolower(trim($data['email'])),
      ':password' => $data['password'], // ✔ already hashed
      ':phone' => $data['phone'] ?? null
    ]);

    return (int) $this->db->lastInsertId();
  }

  public function findByGoogleId(string $googleId): ?array
  {
    $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, email,
                   phone, role, google_id, provider, status, created_at, updated_at
            FROM users
            WHERE google_id = ?
            LIMIT 1
        ");
    $stmt->execute([$googleId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}
