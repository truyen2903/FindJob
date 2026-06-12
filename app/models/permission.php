<?php
// app/models/Permission.php
require_once __DIR__ . '/Database.php';

class Permission extends Database {
    public function getAll(): array {
        $permissions = [];
        $sql = "SELECT * FROM permissions ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $permissions[] = $row;
            }
            $result->free();
        }
        return $permissions;
    }

    public function getPermissionsByRole($role_id): array {
        $sql = "SELECT p.id, p.name, p.description
                FROM permissions p
                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                WHERE rp.role_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $permissions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $permissions[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $permissions;
    }

    public function hasPermission($role_id, $permission_name): bool {
        $sql = "SELECT COUNT(*) AS total 
                FROM permissions p
                JOIN role_permissions rp ON rp.permission_id = p.id
                WHERE rp.role_id = ? AND p.name = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param("is", $role_id, $permission_name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($result['total']) && (int)$result['total'] > 0;
    }

    public function create(string $name, ?string $description = null): ?int {
        $stmt = $this->conn->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('ss', $name, $description);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $newId = (int)$this->conn->insert_id;
        $stmt->close();
        return $newId;
    }

    public function update(int $id, string $name, ?string $description = null): bool {
        $stmt = $this->conn->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('ssi', $name, $description, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM permissions WHERE id = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

