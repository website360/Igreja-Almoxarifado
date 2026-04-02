<?php
/**
 * Perfil do Usuário
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

$pageTitle = 'Meu Perfil';
$db = Database::getInstance();

$user = $db->fetch(
    "SELECT u.*, m.nome as ministerio_nome 
     FROM users u 
     LEFT JOIN ministerios m ON u.ministerio_id = m.id 
     WHERE u.id = ?",
    [$currentUser['id']]
);

// Histórico de presenças
$presencas = $db->fetchAll(
    "SELECT a.*, e.titulo as evento_titulo, e.inicio_at as evento_data
     FROM attendance a
     JOIN events e ON a.event_id = e.id
     WHERE a.person_id = ?
     ORDER BY e.inicio_at DESC
     LIMIT 10",
    [$currentUser['id']]
);

// Stats
$stats = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
        COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
        COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados
     FROM attendance WHERE person_id = ?",
    [$currentUser['id']]
);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $data = [
                'nome' => trim($_POST['nome'] ?? ''),
                'telefone_whatsapp' => cleanPhone($_POST['telefone_whatsapp'] ?? ''),
                'data_nascimento' => $_POST['data_nascimento'] ?: null,
                'aceita_whatsapp' => isset($_POST['aceita_whatsapp']) ? 1 : 0,
                'aceita_email' => isset($_POST['aceita_email']) ? 1 : 0
            ];

            if (empty($data['nome'])) {
                $errors[] = 'Nome é obrigatório.';
            }

            // Upload de foto via arquivo
            if (!empty($_FILES['foto']['name'])) {
                $uploadError = null;
                $fotoUrl = uploadFileWithError($_FILES['foto'], 'pessoas', $uploadError);
                if ($fotoUrl) {
                    if ($user['foto_url']) {
                        deleteFile($user['foto_url']);
                    }
                    $data['foto_url'] = $fotoUrl;
                } elseif ($uploadError) {
                    $errors[] = $uploadError;
                }
            }
            // Upload de foto via câmera (base64)
            elseif (!empty($_POST['foto_base64'])) {
                $base64 = $_POST['foto_base64'];
                if (preg_match('/^data:image\/(jpeg|png|gif);base64,/', $base64, $matches)) {
                    $ext = $matches[1];
                    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
                    $imageData = base64_decode($base64Data);
                    
                    if ($imageData) {
                        $uploadDir = BASE_PATH . 'uploads/pessoas/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        
                        $filename = generateCode() . '.' . $ext;
                        $filepath = $uploadDir . $filename;
                        
                        if (file_put_contents($filepath, $imageData)) {
                            if ($user['foto_url']) {
                                deleteFile($user['foto_url']);
                            }
                            $data['foto_url'] = '/uploads/pessoas/' . $filename;
                        }
                    }
                }
            }
            
            // Remover foto se solicitado
            if (($_POST['remove_foto'] ?? '0') === '1' && empty($_FILES['foto']['name']) && empty($_POST['foto_base64'])) {
                if ($user['foto_url']) {
                    deleteFile($user['foto_url']);
                }
                $data['foto_url'] = null;
            }

            if (empty($errors)) {
                $db->update('users', $data, 'id = :id', ['id' => $currentUser['id']]);
                Audit::log('profile_updated', 'users', $currentUser['id']);
                $success = 'Perfil atualizado com sucesso!';
                $user = array_merge($user, $data);
                
                // Atualizar sessão para refletir mudanças nos menus
                if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $_SESSION['user'] = array_merge($_SESSION['user'], $data);
                    $currentUser = $_SESSION['user'];
                }
            }

        } elseif ($action === 'change_password') {
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';

            if (strlen($novaSenha) < 8) {
                $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
            } elseif (!preg_match('/[A-Z]/', $novaSenha)) {
                $errors[] = 'A senha deve conter pelo menos uma letra maiúscula.';
            } elseif (!preg_match('/[0-9]/', $novaSenha)) {
                $errors[] = 'A senha deve conter pelo menos um número.';
            } elseif ($novaSenha !== $confirmarSenha) {
                $errors[] = 'As senhas não coincidem.';
            } else {
                $auth = new Auth();
                $auth->updatePassword($currentUser['id'], $novaSenha);
                $success = 'Senha alterada com sucesso!';
            }
        }
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Perfil do Usuário</h1>
        <p class="page-subtitle">Gerencie suas informações pessoais</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <div class="alert-content">
        <i data-lucide="alert-circle"></i>
        <div><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div>
    </div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <div class="alert-content">
        <i data-lucide="check-circle"></i>
        <span><?= sanitize($success) ?></span>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-3" style="gap: 24px;">
    <!-- Coluna Principal -->
    <div style="grid-column: span 2;">
        <!-- Card do Perfil -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-center gap-2 mb-3">
                    <div class="profile-photo-container" style="position: relative; cursor: pointer;" onclick="togglePhotoMenu(event)">
                        <div class="user-avatar" id="profileAvatar" style="width: 80px; height: 80px; font-size: 1.5rem; background-color: <?= getAvatarColor($user['nome']) ?>; border: 3px solid var(--gray-200);">
                            <?php if ($user['foto_url']): ?>
                            <img src="<?= url($user['foto_url']) ?>" alt="" id="profileImage" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                            <span id="profileInitials"><?= getInitials($user['nome']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="photo-overlay" style="position: absolute; inset: 0; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s;">
                            <i data-lucide="camera" style="color: white; width: 24px; height: 24px;"></i>
                        </div>
                        <!-- Menu de opções -->
                        <div id="photoMenu" class="photo-menu" style="display: none; position: absolute; top: 85px; left: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); overflow: hidden; z-index: 100; min-width: 180px;">
                            <button type="button" onclick="openCamera()" style="display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 16px; border: none; background: none; cursor: pointer; font-size: 14px; text-align: left; transition: background 0.2s;">
                                <i data-lucide="camera" style="width: 18px; height: 18px; color: var(--primary);"></i>
                                Tirar Foto
                            </button>
                            <button type="button" onclick="openFileUpload()" style="display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 16px; border: none; background: none; cursor: pointer; font-size: 14px; text-align: left; border-top: 1px solid var(--gray-100); transition: background 0.2s;">
                                <i data-lucide="upload" style="width: 18px; height: 18px; color: var(--primary);"></i>
                                Escolher Arquivo
                            </button>
                            <?php if ($user['foto_url']): ?>
                            <button type="button" onclick="removePhoto()" style="display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 16px; border: none; background: none; cursor: pointer; font-size: 14px; text-align: left; border-top: 1px solid var(--gray-100); color: var(--danger); transition: background 0.2s;">
                                <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                                Remover Foto
                            </button>
                            <?php endif; ?>
                        </div>
                        <!-- Inputs hidden -->
                        <input type="file" id="fotoInputFile" name="foto" accept="image/*" style="display: none;" form="profileForm">
                        <input type="hidden" id="removePhotoFlag" name="remove_foto" value="0" form="profileForm">
                        <input type="hidden" id="fotoBase64" name="foto_base64" value="" form="profileForm">
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 1.5rem;"><?= sanitize($user['nome']) ?></h2>
                        <span class="badge badge-primary"><?= MEMBER_POSITIONS[$user['cargo']] ?? $user['cargo'] ?></span>
                        <p class="text-muted mb-0">
                            <?= sanitize($user['email']) ?>
                            <?php if ($user['telefone_whatsapp']): ?>
                            • <?= formatPhone($user['telefone_whatsapp']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($user['ministerio_nome']): ?>
                        <small class="text-muted"><?= sanitize($user['ministerio_nome']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs mb-3">
            <button class="tab-link active" data-tab="tab-dados">Informações Pessoais</button>
            <button class="tab-link" data-tab="tab-historico">Histórico</button>
            <button class="tab-link" data-tab="tab-senha">Alterar Senha</button>
        </div>

        <!-- Tab Dados -->
        <div id="tab-dados" class="tab-content active">
            <form method="POST" enctype="multipart/form-data" class="card" id="profileForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label required">Nome Completo</label>
                            <input type="text" name="nome" class="form-control" required
                                   value="<?= sanitize($user['nome']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled>
                            <small class="form-text">O email não pode ser alterado.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone/WhatsApp</label>
                            <input type="text" name="telefone_whatsapp" class="form-control" data-mask="phone"
                                   value="<?= formatPhone($user['telefone_whatsapp']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" name="data_nascimento" class="form-control"
                                   value="<?= $user['data_nascimento'] ?? '' ?>">
                        </div>
                    </div>

                    <hr style="margin: 20px 0; border-color: var(--gray-200);">

                    <h4 class="mb-2">Preferências de Comunicação</h4>
                    
                    <div class="form-check">
                        <input type="checkbox" name="aceita_whatsapp" id="aceita_whatsapp" class="form-check-input"
                               <?= $user['aceita_whatsapp'] ? 'checked' : '' ?>>
                        <label for="aceita_whatsapp" class="form-check-label">Aceito receber mensagens via WhatsApp</label>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="aceita_email" id="aceita_email" class="form-check-input"
                               <?= $user['aceita_email'] ? 'checked' : '' ?>>
                        <label for="aceita_email" class="form-check-label">Aceito receber emails</label>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab Histórico -->
        <div id="tab-historico" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Histórico de Presenças</h3>
                </div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Check-in</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($presencas)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding: 30px;">
                                    Nenhum registro de presença.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($presencas as $p): ?>
                                <tr>
                                    <td><?= sanitize($p['evento_titulo']) ?></td>
                                    <td><?= formatDate($p['evento_data']) ?></td>
                                    <td><?= statusBadge($p['status']) ?></td>
                                    <td><?= $p['checkin_at'] ? formatDateTime($p['checkin_at']) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab Senha -->
        <div id="tab-senha" class="tab-content">
            <form method="POST" class="card">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="card-header">
                    <h3 class="card-title">Alterar Senha</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-2" style="background: var(--blue-50); border: 1px solid var(--blue-200); border-radius: 8px; padding: 12px;">
                        <div style="display: flex; gap: 10px;">
                            <i data-lucide="shield-check" style="color: var(--primary); flex-shrink: 0;"></i>
                            <div>
                                <strong style="color: var(--primary);">Dicas para uma senha segura:</strong>
                                <ul style="margin: 8px 0 0 0; padding-left: 20px; color: var(--gray-600); font-size: 13px;">
                                    <li>Mínimo de 8 caracteres</li>
                                    <li>Pelo menos uma letra maiúscula</li>
                                    <li>Pelo menos um número</li>
                                    <li>Evite usar informações pessoais</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Nova Senha</label>
                        <input type="password" name="nova_senha" id="nova_senha" class="form-control" required minlength="8">
                        <div id="passwordStrength" style="margin-top: 8px;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Confirmar Nova Senha</label>
                        <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control" required>
                        <div id="passwordMatch" style="margin-top: 5px; font-size: 13px;"></div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="lock"></i> Alterar Senha
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Coluna Lateral -->
    <div>
        <!-- Stats -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Resumo</h3>
            </div>
            <div class="card-body">
                <div class="stat-card mb-2" style="padding: 12px;">
                    <div class="stat-icon success" style="width: 40px; height: 40px;">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['presentes'] ?? 0 ?></div>
                        <div class="stat-label">Presenças</div>
                    </div>
                </div>
                <div class="stat-card mb-2" style="padding: 12px;">
                    <div class="stat-icon danger" style="width: 40px; height: 40px;">
                        <i data-lucide="x-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['ausentes'] ?? 0 ?></div>
                        <div class="stat-label">Ausências</div>
                    </div>
                </div>
                <div class="stat-card" style="padding: 12px;">
                    <div class="stat-icon warning" style="width: 40px; height: 40px;">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $stats['justificados'] ?? 0 ?></div>
                        <div class="stat-label">Justificados</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações da Conta -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Conta</h3>
            </div>
            <div class="card-body">
                <p><strong>Membro desde:</strong><br>
                <?= $user['data_entrada'] ? formatDate($user['data_entrada']) : formatDate($user['created_at']) ?></p>
                
                <p><strong>Último login:</strong><br>
                <?= $user['last_login_at'] ? formatDateTime($user['last_login_at']) : 'Nunca' ?></p>
                
                <p><strong>Status:</strong><br>
                <?= statusBadge($user['status']) ?></p>
            </div>
        </div>
    </div>
</div>

<script>
// Validação de força da senha em tempo real
document.getElementById('nova_senha')?.addEventListener('input', function() {
    const senha = this.value;
    const strengthDiv = document.getElementById('passwordStrength');
    let strength = 0;
    let tips = [];
    
    if (senha.length >= 8) strength++; else tips.push('8+ caracteres');
    if (/[A-Z]/.test(senha)) strength++; else tips.push('letra maiúscula');
    if (/[a-z]/.test(senha)) strength++; else tips.push('letra minúscula');
    if (/[0-9]/.test(senha)) strength++; else tips.push('número');
    if (/[^A-Za-z0-9]/.test(senha)) strength++; else tips.push('caractere especial');
    
    let color, text, width;
    if (strength <= 2) { color = '#ef4444'; text = 'Fraca'; width = '33%'; }
    else if (strength <= 3) { color = '#f59e0b'; text = 'Média'; width = '66%'; }
    else { color = '#10b981'; text = 'Forte'; width = '100%'; }
    
    strengthDiv.innerHTML = `
        <div style="background: var(--gray-200); border-radius: 4px; height: 6px; overflow: hidden;">
            <div style="background: ${color}; height: 100%; width: ${width}; transition: all 0.3s;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 4px; font-size: 12px;">
            <span style="color: ${color}; font-weight: 500;">${text}</span>
            ${tips.length ? '<span style="color: var(--gray-500);">Falta: ' + tips.slice(0,2).join(', ') + '</span>' : ''}
        </div>
    `;
    
    checkPasswordMatch();
});

document.getElementById('confirmar_senha')?.addEventListener('input', checkPasswordMatch);

function checkPasswordMatch() {
    const nova = document.getElementById('nova_senha')?.value || '';
    const confirmar = document.getElementById('confirmar_senha')?.value || '';
    const matchDiv = document.getElementById('passwordMatch');
    
    if (!confirmar) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (nova === confirmar) {
        matchDiv.innerHTML = '<span style="color: #10b981;"><i data-lucide="check" style="width: 14px; height: 14px; display: inline;"></i> Senhas coincidem</span>';
    } else {
        matchDiv.innerHTML = '<span style="color: #ef4444;"><i data-lucide="x" style="width: 14px; height: 14px; display: inline;"></i> Senhas não coincidem</span>';
    }
    lucide.createIcons();
}

// Hover effect para foto do perfil
const photoContainer = document.querySelector('.profile-photo-container');
const photoOverlay = document.querySelector('.photo-overlay');

if (photoContainer && photoOverlay) {
    photoContainer.addEventListener('mouseenter', () => photoOverlay.style.opacity = '1');
    photoContainer.addEventListener('mouseleave', () => photoOverlay.style.opacity = '0');
}

// Menu de foto
let photoMenuOpen = false;

function togglePhotoMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('photoMenu');
    photoMenuOpen = !photoMenuOpen;
    menu.style.display = photoMenuOpen ? 'block' : 'none';
    if (photoMenuOpen) {
        lucide.createIcons();
    }
}

function closePhotoMenu() {
    const menu = document.getElementById('photoMenu');
    menu.style.display = 'none';
    photoMenuOpen = false;
}

function openCamera() {
    closePhotoMenu();
    
    // Verificar suporte a câmera
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Seu navegador não suporta acesso à câmera. Use a opção "Escolher Arquivo".');
        return;
    }
    
    // Criar modal de câmera
    const modal = document.createElement('div');
    modal.id = 'cameraModal';
    modal.innerHTML = `
        <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="position: relative; max-width: 500px; width: 100%;">
                <video id="cameraVideo" autoplay playsinline style="width: 100%; border-radius: 12px;"></video>
                <canvas id="cameraCanvas" style="display: none;"></canvas>
            </div>
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <button type="button" onclick="capturePhoto()" style="width: 70px; height: 70px; border-radius: 50%; background: white; border: 4px solid var(--primary); cursor: pointer; display: flex; align-items: center; justify-content: center;">
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary);"></div>
                </button>
            </div>
            <button type="button" onclick="closeCameraModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 20px;">✕</button>
            <button type="button" id="switchCameraBtn" onclick="switchCamera()" style="position: absolute; top: 20px; left: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: none;">
                <i data-lucide="refresh-cw" style="width: 20px; height: 20px;"></i>
            </button>
        </div>
    `;
    document.body.appendChild(modal);
    lucide.createIcons();
    
    // Iniciar câmera
    startCamera();
}

let currentStream = null;
let facingMode = 'user';

async function startCamera() {
    try {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
        }
        
        const constraints = {
            video: { facingMode: facingMode, width: { ideal: 640 }, height: { ideal: 480 } }
        };
        
        currentStream = await navigator.mediaDevices.getUserMedia(constraints);
        const video = document.getElementById('cameraVideo');
        video.srcObject = currentStream;
        
        // Mostrar botão de trocar câmera em dispositivos com múltiplas câmeras
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        if (videoDevices.length > 1) {
            document.getElementById('switchCameraBtn').style.display = 'flex';
        }
    } catch (err) {
        console.error('Erro ao acessar câmera:', err);
        closeCameraModal();
        alert('Não foi possível acessar a câmera. Verifique as permissões do navegador.');
    }
}

function switchCamera() {
    facingMode = facingMode === 'user' ? 'environment' : 'user';
    startCamera();
}

function capturePhoto() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    
    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
    
    // Atualizar preview
    const avatar = document.getElementById('profileAvatar');
    const initials = document.getElementById('profileInitials');
    let img = document.getElementById('profileImage');
    
    if (!img) {
        img = document.createElement('img');
        img.id = 'profileImage';
        img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
        if (initials) initials.remove();
        avatar.appendChild(img);
    }
    
    img.src = dataUrl;
    
    // Salvar base64 para envio
    document.getElementById('fotoBase64').value = dataUrl;
    document.getElementById('removePhotoFlag').value = '0';
    
    closeCameraModal();
    showToast('Foto capturada! Clique em "Salvar Alterações" para confirmar.', 'info');
}

function closeCameraModal() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
    const modal = document.getElementById('cameraModal');
    if (modal) modal.remove();
}

function openFileUpload() {
    closePhotoMenu();
    document.getElementById('fotoInputFile').click();
}

function removePhoto() {
    closePhotoMenu();
    showConfirm({
        title: 'Remover foto',
        message: 'Deseja remover sua foto de perfil?',
        type: 'warning',
        icon: 'image',
        confirmText: 'Remover',
        onConfirm: () => {
            document.getElementById('removePhotoFlag').value = '1';
            const avatar = document.getElementById('profileAvatar');
            const img = document.getElementById('profileImage');
            if (img) {
                img.remove();
                const initials = document.createElement('span');
                initials.id = 'profileInitials';
                initials.textContent = '<?= getInitials($user['nome']) ?>';
                avatar.appendChild(initials);
            }
            showToast('Foto será removida ao salvar as alterações.', 'info');
        }
    });
}

// Fechar menu ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.closest('.profile-photo-container')) {
        closePhotoMenu();
    }
});

// Handler para os dois inputs de foto
function handlePhotoSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validar tipo
    if (!file.type.startsWith('image/')) {
        alert('Por favor, selecione uma imagem.');
        return;
    }
    
    // Validar tamanho (10MB)
    if (file.size > 10 * 1024 * 1024) {
        alert('A imagem deve ter no máximo 10MB.');
        return;
    }
    
    // Resetar flag de remover
    document.getElementById('removePhotoFlag').value = '0';
    
    // Preview
    const reader = new FileReader();
    reader.onload = function(ev) {
        const avatar = document.getElementById('profileAvatar');
        const initials = document.getElementById('profileInitials');
        let img = document.getElementById('profileImage');
        
        if (!img) {
            img = document.createElement('img');
            img.id = 'profileImage';
            img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
            if (initials) initials.remove();
            avatar.appendChild(img);
        }
        
        img.src = ev.target.result;
        showToast('Foto selecionada! Clique em "Salvar Alterações" para confirmar.', 'info');
    };
    reader.readAsDataURL(file);
}

document.getElementById('fotoInputFile')?.addEventListener('change', handlePhotoSelect);

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = `<i data-lucide="${type === 'info' ? 'info' : 'check-circle'}"></i> ${message}`;
    toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: var(--primary); color: white; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px; z-index: 9999; animation: slideInRight 0.3s ease;';
    document.body.appendChild(toast);
    lucide.createIcons();
    setTimeout(() => toast.remove(), 4000);
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
