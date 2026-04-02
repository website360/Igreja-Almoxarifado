<?php
/**
 * Sistema de Autenticação
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Tenta autenticar usuário
     */
    public function attempt(string $email, string $password, bool $remember = false): bool {
        $user = $this->db->fetch(
            "SELECT id, email, password, nome, status FROM users WHERE email = ? AND status = 'ativo'",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $this->login($user['id'], $remember);
        return true;
    }

    /**
     * Realiza login do usuário
     */
    public function login(int $userId, bool $remember = false): void {
        session_regenerate_id(true);
        // Regenerar token CSRF após regenerar sessão
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in_at'] = time();

        // Atualiza último login
        $this->db->update('users', 
            ['last_login_at' => date('Y-m-d H:i:s')], 
            'id = :id', 
            ['id' => $userId]
        );

        // Token "lembrar-me"
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $this->db->update('users', 
                ['remember_token' => password_hash($token, PASSWORD_DEFAULT)], 
                'id = :id', 
                ['id' => $userId]
            );
            setcookie('remember_token', $userId . '|' . $token, time() + (86400 * 30), '/', '', false, true);
        }

        // Log de auditoria
        Audit::log('login', 'users', $userId);
    }

    /**
     * Realiza logout
     */
    public function logout(): void {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            $this->db->update('users', ['remember_token' => null], 'id = :id', ['id' => $userId]);
            Audit::log('logout', 'users', $userId);
        }

        // Limpa cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        session_unset();
        session_destroy();
    }

    /**
     * Verifica se está autenticado
     */
    public function check(): bool {
        if (isset($_SESSION['user_id'])) {
            return true;
        }

        // Verifica token "lembrar-me"
        if (isset($_COOKIE['remember_token'])) {
            return $this->loginWithRememberToken($_COOKIE['remember_token']);
        }

        return false;
    }

    /**
     * Login via token "lembrar-me"
     */
    private function loginWithRememberToken(string $cookie): bool {
        $parts = explode('|', $cookie);
        if (count($parts) !== 2) return false;

        [$userId, $token] = $parts;
        $user = $this->db->fetch(
            "SELECT id, remember_token FROM users WHERE id = ? AND status = 'ativo'",
            [$userId]
        );

        if ($user && $user['remember_token'] && password_verify($token, $user['remember_token'])) {
            $this->login($user['id'], true);
            return true;
        }

        return false;
    }

    /**
     * Retorna usuário atual
     */
    public function getCurrentUser(): ?array {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return $this->db->fetch(
            "SELECT u.*, m.nome as ministerio_nome 
             FROM users u 
             LEFT JOIN ministerios m ON u.ministerio_id = m.id 
             WHERE u.id = ?",
            [$_SESSION['user_id']]
        );
    }

    /**
     * Retorna ID do usuário atual
     */
    public function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Registra novo usuário
     */
    public function register(array $data): int {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $userId = $this->db->insert('users', $data);

        // Atribui papel padrão (membro)
        $this->db->insert('user_roles', [
            'user_id' => $userId,
            'role_id' => 4 // Membro
        ]);

        Audit::log('register', 'users', $userId);

        return $userId;
    }

    /**
     * Atualiza senha
     */
    public function updatePassword(int $userId, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $affected = $this->db->update('users', 
            ['password' => $hash, 'remember_token' => null], 
            'id = :id', 
            ['id' => $userId]
        );

        if ($affected) {
            Audit::log('password_changed', 'users', $userId);
        }

        return $affected > 0;
    }

    /**
     * Gera token de reset de senha
     */
    public function createResetToken(string $email): ?string {
        $user = $this->db->fetch("SELECT id FROM users WHERE email = ? AND status = 'ativo'", [$email]);
        
        if (!$user) return null;

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->update('users', [
            'reset_token' => password_hash($token, PASSWORD_DEFAULT),
            'reset_token_expires_at' => $expires
        ], 'id = :id', ['id' => $user['id']]);

        return $token;
    }

    /**
     * Valida token de reset
     */
    public function validateResetToken(string $email, string $token): bool {
        $user = $this->db->fetch(
            "SELECT id, reset_token, reset_token_expires_at 
             FROM users 
             WHERE email = ? AND status = 'ativo' AND reset_token_expires_at > NOW()",
            [$email]
        );

        return $user && password_verify($token, $user['reset_token']);
    }

    /**
     * Reseta senha com token
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool {
        if (!$this->validateResetToken($email, $token)) {
            return false;
        }

        $user = $this->db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        
        $this->db->update('users', [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_token_expires_at' => null,
            'remember_token' => null
        ], 'id = :id', ['id' => $user['id']]);

        Audit::log('password_reset', 'users', $user['id']);

        return true;
    }

    /**
     * Verifica se usuário tem papel específico
     */
    public function hasRole(int $userId, string $roleName): bool {
        $result = $this->db->fetch(
            "SELECT 1 FROM user_roles ur 
             JOIN roles r ON ur.role_id = r.id 
             WHERE ur.user_id = ? AND r.name = ?",
            [$userId, $roleName]
        );
        return $result !== null;
    }

    /**
     * Retorna papéis do usuário
     */
    public function getUserRoles(int $userId): array {
        return $this->db->fetchAll(
            "SELECT r.* FROM roles r 
             JOIN user_roles ur ON r.id = ur.role_id 
             WHERE ur.user_id = ?",
            [$userId]
        );
    }

    /**
     * Atribui papel ao usuário
     */
    public function assignRole(int $userId, int $roleId): void {
        try {
            $this->db->insert('user_roles', [
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
            Audit::log('role_assigned', 'user_roles', null, [
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
        } catch (Exception $e) {
            // Já existe, ignorar
        }
    }

    /**
     * Remove papel do usuário
     */
    public function removeRole(int $userId, int $roleId): void {
        $this->db->delete('user_roles', 'user_id = ? AND role_id = ?', [$userId, $roleId]);
        Audit::log('role_removed', 'user_roles', null, [
            'user_id' => $userId,
            'role_id' => $roleId
        ]);
    }

    /**
     * Sincroniza papéis do usuário
     */
    public function syncRoles(int $userId, array $roleIds): void {
        $this->db->delete('user_roles', 'user_id = ?', [$userId]);
        foreach ($roleIds as $roleId) {
            $this->assignRole($userId, $roleId);
        }
    }
}

/**
 * Helper global para verificar autenticação
 */
function requireAuth(): void {
    $auth = new Auth();
    if (!$auth->check()) {
        if (isAjaxRequest()) {
            jsonResponse(['error' => 'Não autorizado'], 401);
        }
        redirect('/login.php');
    }
}

/**
 * Helper global para verificar papel
 */
function requireRole(string $role): void {
    requireAuth();
    $auth = new Auth();
    if (!$auth->hasRole($_SESSION['user_id'], $role)) {
        if (isAjaxRequest()) {
            jsonResponse(['error' => 'Acesso negado'], 403);
        }
        redirect('/acesso-negado.php');
    }
}
