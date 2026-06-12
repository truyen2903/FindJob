<?php
// app/models/Role.php
require_once __DIR__ . '/Database.php';

class Role extends Database {
    public function getAllRoles(): array {
        $roles = [];
        $sql = "SELECT * FROM roles ORDER BY id ASC";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $roles[] = $row;
            }
            $result->free();
        }
        return $roles;
    }

    public function getAllWithUserCounts(): array {
        $roles = [];
        $sql = "SELECT r.*, COUNT(u.id) AS user_count
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.id
                GROUP BY r.id
                ORDER BY r.id";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $roles[] = $row;
            }
            $result->free();
        }
        return $roles;
    }

    public function getRoleById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        $role = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();
        return $role ?: null;
    }

    public function createRole(string $name, ?string $description = null): ?int {
        $stmt = $this->conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
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

    public function updateRole(int $id, string $name, ?string $description = null): bool {
        $stmt = $this->conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('ssi', $name, $description, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteRole(int $id): bool {
        // prevent deletion of built-in roles (1=admin,2=employer,3=candidate)
        if (in_array($id, [1, 2, 3], true)) {
            return false;
        }
        $stmt = $this->conn->prepare("DELETE FROM roles WHERE id = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getPermissionIds(int $roleId): array {
        $ids = [];
        $stmt = $this->conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        if ($stmt === false) {
            return $ids;
        }
        $stmt->bind_param('i', $roleId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $pid = (int)($row['permission_id'] ?? 0);
                    if ($pid > 0) {
                        $ids[] = $pid;
                    }
                }
                $result->free();
            }
        }
        $stmt->close();
        return $ids;
    }

    public function syncPermissions(int $roleId, array $permissionIds): bool {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        $this->conn->begin_transaction();
        $delete = $this->conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        if ($delete === false) {
            $this->conn->rollback();
            return false;
        }
        $delete->bind_param('i', $roleId);
        if (!$delete->execute()) {
            $delete->close();
            $this->conn->rollback();
            return false;
        }
        $delete->close();

        if (!empty($permissionIds)) {
            $insert = $this->conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            if ($insert === false) {
                $this->conn->rollback();
                return false;
            }
            foreach ($permissionIds as $pid) {
                $insert->bind_param('ii', $roleId, $pid);
                if (!$insert->execute()) {
                    $insert->close();
                    $this->conn->rollback();
                    return false;
                }
            }
            $insert->close();
        }

        $this->conn->commit();
        return true;
    }
}

