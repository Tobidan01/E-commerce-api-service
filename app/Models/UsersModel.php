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

  // ==================== READ ====================

  // Find user by email
  public function findByEmail(string $email): ?array
  {
    try {
      $email = strtolower(trim($email));

      if (empty($email)) {
        return null;
      }

      $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, password,
                       phone, role, google_id, provider, status, created_at, updated_at
                FROM users
                WHERE email = ?
                LIMIT 1
            ");
      $stmt->execute([$email]);
      return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } catch (\PDOException $e) {
      error_log("findByEmail error: " . $e->getMessage());
      return null;
    }
  }

  // Find user by ID
  public function findById(int $id): ?array
  {
    try {
      $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email,
                       phone, role, google_id, provider, status, created_at, updated_at
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } catch (\PDOException $e) {
      error_log("findById error: " . $e->getMessage());
      return null;
    }
  }

  // Find user by Google ID
  public function findByGoogleId(string $googleId): ?array
  {
    try {
      $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email,
                       phone, role, google_id, provider, status, created_at, updated_at
                FROM users
                WHERE google_id = ?
                LIMIT 1
            ");
      $stmt->execute([$googleId]);
      return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } catch (\PDOException $e) {
      error_log("findByGoogleId error: " . $e->getMessage());
      return null;
    }
  }

  // ==================== CREATE ====================

  // Create regular user
  public function create(array $data): int
  {
    try {
      if (
        empty($data['first_name']) || empty($data['last_name'])
        || empty($data['email']) || empty($data['password'])
      ) {
        throw new \InvalidArgumentException("Missing required fields");
      }

      $stmt = $this->db->prepare("
                INSERT INTO users 
                    (first_name, last_name, email, password, phone, role, provider, created_at)
                VALUES 
                    (:first_name, :last_name, :email, :password, :phone, 'user', 'local', NOW())
            ");

      $stmt->execute([
        ':first_name' => htmlspecialchars(trim($data['first_name'])),
        ':last_name' => htmlspecialchars(trim($data['last_name'])),
        ':email' => strtolower(trim($data['email'])),
        ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
        ':phone' => $data['phone'] ?? null
      ]);

      return (int) $this->db->lastInsertId();

    } catch (\PDOException $e) {
      error_log("create error: " . $e->getMessage());
      throw new \RuntimeException("Could not create user. Please try again.");
    }
  }

  // Create Google user
  public function createGoogleUser(array $data): int
  {
    try {
      if (empty($data['email']) || empty($data['first_name']) || empty($data['google_id'])) {
        throw new \InvalidArgumentException("Missing required Google user data");
      }

      $email = strtolower(trim($data['email']));

      // Avoid duplicates — return existing user id if found
      $existing = $this->findByEmail($email);
      if ($existing) {
        return $existing['id'];
      }

      $stmt = $this->db->prepare("
                INSERT INTO users 
                    (first_name, last_name, email, google_id, role, provider, status, created_at)
                VALUES 
                    (:first_name, :last_name, :email, :google_id, 'user', 'google', 'active', NOW())
            ");

      $stmt->execute([
        ':first_name' => htmlspecialchars(trim($data['first_name'])),
        ':last_name' => htmlspecialchars(trim($data['last_name'] ?? '')),
        ':email' => $email,
        ':google_id' => trim($data['google_id'])
      ]);

      return (int) $this->db->lastInsertId();

    } catch (\PDOException $e) {
      error_log("createGoogleUser error: " . $e->getMessage());
      throw new \RuntimeException("Could not create Google user. Please try again.");

    } catch (\InvalidArgumentException $e) {
      error_log("createGoogleUser validation error: " . $e->getMessage());
      throw $e;
    }
  }

  // ==================== UPDATE ====================

  // Update profile
  public function update(int $id, array $data): bool
  {
    try {
      if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
        throw new \InvalidArgumentException("Missing required fields for update");
      }

      // Check email not taken by another user
      $existing = $this->findByEmail($data['email']);
      if ($existing && $existing['id'] !== $id) {
        throw new \RuntimeException("Email already in use by another account");
      }

      $stmt = $this->db->prepare("
                UPDATE users
                SET first_name = :first_name,
                    last_name  = :last_name,
                    email      = :email,
                    phone      = :phone,
                    updated_at = NOW()
                WHERE id = :id
            ");

      return $stmt->execute([
        ':first_name' => htmlspecialchars(trim($data['first_name'])),
        ':last_name' => htmlspecialchars(trim($data['last_name'])),
        ':email' => strtolower(trim($data['email'])),
        ':phone' => $data['phone'] ?? null,
        ':id' => $id
      ]);

    } catch (\PDOException $e) {
      error_log("update error: " . $e->getMessage());
      return false;
    }
  }

  // Update password
  public function updatePassword(int $id, string $currentPassword, string $newPassword): bool
  {
    try {
      // Get current hashed password
      $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
      $stmt->execute([$id]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        throw new \RuntimeException("User not found");
      }

      // Verify current password
      if (!password_verify($currentPassword, $user['password'])) {
        throw new \RuntimeException("Current password is incorrect");
      }

      if (strlen($newPassword) < 8) {
        throw new \InvalidArgumentException("New password must be at least 8 characters");
      }

      $stmt = $this->db->prepare("
                UPDATE users 
                SET password = :password, updated_at = NOW()
                WHERE id = :id
            ");

      return $stmt->execute([
        ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ':id' => $id
      ]);

    } catch (\PDOException $e) {
      error_log("updatePassword error: " . $e->getMessage());
      return false;
    }
  }

  // Update account status (admin action)
  public function updateStatus(int $id, string $status): bool
  {
    try {
      $allowed = ['active', 'inactive', 'banned'];

      if (!in_array($status, $allowed)) {
        throw new \InvalidArgumentException("Invalid status: $status");
      }

      $stmt = $this->db->prepare("
                UPDATE users 
                SET status = :status, updated_at = NOW()
                WHERE id = :id
            ");

      return $stmt->execute([
        ':status' => $status,
        ':id' => $id
      ]);

    } catch (\PDOException $e) {
      error_log("updateStatus error: " . $e->getMessage());
      return false;
    }
  }

  // Link Google account to existing local account
  public function linkGoogleAccount(int $id, string $googleId): bool
  {
    try {
      $stmt = $this->db->prepare("
                UPDATE users
                SET google_id  = :google_id,
                    provider   = 'google',
                    updated_at = NOW()
                WHERE id = :id
            ");

      return $stmt->execute([
        ':google_id' => trim($googleId),
        ':id' => $id
      ]);

    } catch (\PDOException $e) {
      error_log("linkGoogleAccount error: " . $e->getMessage());
      return false;
    }
  }
}