<?php
// app/models/User.php
require_once __DIR__ . '/Database.php';

class User extends Database {
    public function findByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($email, $password, $role_id = 3, $name = null) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (email, password_hash, role_id, name) VALUES (?,?,?,?)");
        $stmt->bind_param("ssis", $email, $hash, $role_id, $name);
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }

    public function countByRole($role_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role_id = ?");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return isset($result['total']) ? (int)$result['total'] : 0;
    }

    public function countAll() {
        $sql = "SELECT COUNT(*) AS total FROM users";
        $result = $this->conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())) {
            return (int)$row['total'];
        }
        return 0;
    }

        // Thêm mới người dùng đầy đủ (dùng cho trang thêm trong admin)
    public function createFull($email, $password, $role_id, $name, $phone, $address, $created_at) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("
            INSERT INTO users (email, password_hash, role_id, name, phone, address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissss", $email, $hash, $role_id, $name, $phone, $address, $created_at);
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }


    public function verifyPassword($email, $password) {
        $user = $this->findByEmail($email);
        if (!$user) return false;
        if (password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }

    public function assignRole($user_id, $role_id) {
        $stmt = $this->conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $role_id, $user_id);
        return $stmt->execute();
    }

    // Set or update avatar path for a user
    public function setAvatar($user_id, $avatar_path) {
        $stmt = $this->conn->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        $stmt->bind_param("si", $avatar_path, $user_id);
        return $stmt->execute();
    }

    // Remove avatar path (set to NULL)
    public function clearAvatar($user_id) {
        $stmt = $this->conn->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }

    public function updateBasicInfo(int $user_id, array $data): bool {
        $allowed = ['name', 'phone'];
        $fields = [];
        $types = '';
        $params = [];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $fields[] = $column . ' = ?';
            $types .= 's';
            $params[] = $data[$column] === '' ? null : $data[$column];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $types .= 'i';
        $params[] = $user_id;

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
}

