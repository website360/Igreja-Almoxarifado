<?php
/**
 * Funções Auxiliares do Sistema
 */

/**
 * Redireciona para uma URL
 */
function redirect(string $url): void {
    header("Location: " . APP_URL . $url);
    exit;
}

/**
 * Retorna URL completa
 */
function url(string $path = ''): string {
    return APP_URL . $path;
}

/**
 * Verifica se é requisição AJAX
 */
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Retorna resposta JSON
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitiza string de entrada
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Gera CSRF token field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
}

/**
 * Valida CSRF token
 */
function validateCsrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Define mensagem flash
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtém e limpa mensagem flash
 */
function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Formata data para exibição
 */
function formatDate(?string $date, string $format = 'd/m/Y'): string {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Formata data e hora para exibição
 */
function formatDateTime(?string $datetime, string $format = 'd/m/Y H:i'): string {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Formata data completa em português (substitui strftime deprecado)
 */
function formatDateFull(?string $date): string {
    if (!$date) return '-';
    
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    $timestamp = strtotime($date);
    $diaSemana = $dias[date('w', $timestamp)];
    $dia = date('d', $timestamp);
    $mes = $meses[intval(date('n', $timestamp))];
    
    return "{$diaSemana}, {$dia} de {$mes}";
}

/**
 * Retorna nome do mês em português
 */
function getMesPt(?string $date, bool $abreviado = false): string {
    if (!$date) return '-';
    
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $mesesAbrev = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    
    $mes = intval(date('n', strtotime($date)));
    return $abreviado ? $mesesAbrev[$mes] : $meses[$mes];
}

/**
 * Formata data com mês em português (ex: Jan/2025, Fevereiro de 2025)
 */
function formatDatePt(?string $date, string $formato = 'M/Y'): string {
    if (!$date) return '-';
    
    $timestamp = strtotime($date);
    $mes = getMesPt($date, true);
    $mesFull = getMesPt($date, false);
    $ano = date('Y', $timestamp);
    $dia = date('d', $timestamp);
    
    switch ($formato) {
        case 'M/Y':
            return "{$mes}/{$ano}";
        case 'F/Y':
            return "{$mesFull}/{$ano}";
        case 'F Y':
            return "{$mesFull} {$ano}";
        case 'd F Y':
            return "{$dia} de {$mesFull} de {$ano}";
        default:
            return "{$mes}/{$ano}";
    }
}

/**
 * Formata valor monetário
 */
function formatMoney(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Formata telefone
 */
function formatPhone(?string $phone): string {
    if (!$phone) return '-';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    }
    return $phone;
}

/**
 * Formata CPF
 */
function formatCpf(?string $cpf): string {
    if (!$cpf) return '-';
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

/**
 * Máscara CPF para exibição restrita
 */
function maskCpf(?string $cpf): string {
    if (!$cpf) return '-';
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) === 11) {
        return '***.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
    }
    return '***';
}

/**
 * Gera slug a partir de string
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

/**
 * Gera código único
 */
function generateCode(string $prefix = '', int $length = 8): string {
    return strtoupper($prefix . substr(bin2hex(random_bytes($length)), 0, $length));
}

/**
 * Valida email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida CPF
 */
function isValidCpf(string $cpf): bool {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

/**
 * Valida telefone
 */
function isValidPhone(string $phone): bool {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

/**
 * Upload de arquivo
 */
function uploadFile(array $file, string $folder = 'uploads'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return null;
    }

    $uploadDir = BASE_PATH . 'uploads/' . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = generateCode() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return '/uploads/' . $folder . '/' . $filename;
    }

    return null;
}

/**
 * Upload de arquivo com retorno de erro detalhado
 */
function uploadFileWithError(array $file, string $folder = 'uploads', ?string &$error = null): ?string {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
        UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi feito parcialmente.',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada no servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco.',
        UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload.',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = $errorMessages[$file['error']] ?? 'Erro desconhecido no upload.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $error = 'Tipo de arquivo não permitido. Use: ' . implode(', ', ALLOWED_EXTENSIONS);
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $error = 'O arquivo excede o tamanho máximo de ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB.';
        return null;
    }

    $uploadDir = BASE_PATH . 'uploads/' . $folder . '/';
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            $error = 'Não foi possível criar o diretório de upload. Verifique as permissões.';
            return null;
        }
    }

    if (!is_writable($uploadDir)) {
        $error = 'O diretório de upload não tem permissão de escrita.';
        return null;
    }

    $filename = generateCode() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return '/uploads/' . $folder . '/' . $filename;
    }

    $error = 'Falha ao mover o arquivo para o destino final.';
    return null;
}

/**
 * Deleta arquivo
 */
function deleteFile(string $path): bool {
    $fullPath = BASE_PATH . ltrim($path, '/');
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Paginação
 */
function paginate(int $total, int $currentPage = 1, int $perPage = ITEMS_PER_PAGE): array {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Gera HTML de paginação
 */
function paginationHtml(array $pagination, string $baseUrl): string {
    if ($pagination['total_pages'] <= 1) return '';

    $html = '<nav class="pagination-wrapper"><ul class="pagination">';
    
    // Anterior
    if ($pagination['has_prev']) {
        $html .= '<li><a href="' . $baseUrl . '?page=' . ($pagination['current_page'] - 1) . '" class="pagination-link">
            <i data-lucide="chevron-left"></i>
        </a></li>';
    }

    // Páginas
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $html .= '<li><a href="' . $baseUrl . '?page=1" class="pagination-link">1</a></li>';
        if ($start > 2) $html .= '<li><span class="pagination-ellipsis">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= '<li><a href="' . $baseUrl . '?page=' . $i . '" class="pagination-link ' . $active . '">' . $i . '</a></li>';
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) $html .= '<li><span class="pagination-ellipsis">...</span></li>';
        $html .= '<li><a href="' . $baseUrl . '?page=' . $pagination['total_pages'] . '" class="pagination-link">' . $pagination['total_pages'] . '</a></li>';
    }

    // Próximo
    if ($pagination['has_next']) {
        $html .= '<li><a href="' . $baseUrl . '?page=' . ($pagination['current_page'] + 1) . '" class="pagination-link">
            <i data-lucide="chevron-right"></i>
        </a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Tempo relativo
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . ' dias atrás';
    return formatDate($datetime);
}

/**
 * Obtém iniciais do nome
 */
function getInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    return $initials ?: 'U';
}

/**
 * Cores para avatar
 */
function getAvatarColor(string $name): string {
    $colors = [
        '#3B82F6', '#EF4444', '#10B981', '#F59E0B', 
        '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'
    ];
    $index = ord($name[0] ?? 'A') % count($colors);
    return $colors[$index];
}

/**
 * Badge de status
 */
function statusBadge(string $status, string $type = 'default'): string {
    $classes = [
        'ativo' => 'badge-success',
        'inativo' => 'badge-danger',
        'presente' => 'badge-success',
        'ausente' => 'badge-danger',
        'justificado' => 'badge-warning',
        'pendente' => 'badge-warning',
        'aprovada' => 'badge-success',
        'recusada' => 'badge-danger',
        'planejado' => 'badge-info',
        'em_andamento' => 'badge-primary',
        'concluido' => 'badge-success',
        'cancelado' => 'badge-danger',
        'aberto' => 'badge-success',
        'fechado' => 'badge-secondary',
        'disponivel' => 'badge-success',
        'emprestado' => 'badge-warning',
        'manutencao' => 'badge-info',
        'baixado' => 'badge-danger',
        'sent' => 'badge-success',
        'pending' => 'badge-warning',
        'failed' => 'badge-danger'
    ];

    $labels = [
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'presente' => 'Presente',
        'ausente' => 'Ausente',
        'justificado' => 'Justificado',
        'pendente' => 'Pendente',
        'aprovada' => 'Aprovada',
        'recusada' => 'Recusada',
        'planejado' => 'Planejado',
        'em_andamento' => 'Em Andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'aberto' => 'Aberto',
        'fechado' => 'Fechado',
        'disponivel' => 'Disponível',
        'emprestado' => 'Emprestado',
        'manutencao' => 'Manutenção',
        'baixado' => 'Baixado',
        'sent' => 'Enviado',
        'pending' => 'Pendente',
        'failed' => 'Falhou'
    ];

    $class = $classes[$status] ?? 'badge-secondary';
    $label = $labels[$status] ?? ucfirst($status);

    return '<span class="badge ' . $class . '">' . $label . '</span>';
}

/**
 * Exporta dados para CSV
 */
function exportToCsv(array $data, string $filename, array $headers = []): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    
    if (!empty($headers)) {
        fputcsv($output, $headers, ';');
    } elseif (!empty($data)) {
        fputcsv($output, array_keys($data[0]), ';');
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Obtém IP do cliente
 */
function getClientIp(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            return trim($ips[0]);
        }
    }
    return '0.0.0.0';
}

/**
 * Limpa números de telefone
 */
function cleanPhone(string $phone): string {
    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * Formata número para WhatsApp
 */
function formatPhoneWhatsApp(string $phone): string {
    $phone = cleanPhone($phone);
    if (strlen($phone) === 11 || strlen($phone) === 10) {
        return '55' . $phone;
    }
    return $phone;
}

/**
 * Busca configuração do sistema
 */
function getSetting(string $key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM app_settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}
