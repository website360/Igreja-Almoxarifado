<?php
/**
 * Criar/Editar Item do Almoxarifado
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

$id = intval($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) {
    requirePermission('almoxarifado', 'edit');
    $pageTitle = 'Editar Item';
} else {
    requirePermission('almoxarifado', 'create');
    $pageTitle = 'Novo Item';
}

$db = Database::getInstance();

$inventoryItem = null;
if ($isEdit) {
    $inventoryItem = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$id]);
    if (!$inventoryItem) {
        setFlash('error', 'Item não encontrado.');
        redirect('/almoxarifado');
    }
}

$categorias = $db->fetchAll("SELECT id, nome FROM inventory_categories ORDER BY nome");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        // Debug: verificar valor recebido
        // error_log('valor_estimado POST: ' . ($_POST['valor_estimado'] ?? 'VAZIO'));
        
        $data = [
            'nome' => trim($_POST['nome'] ?? ''),
            'categoria_id' => !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null,
            'patrimonio_codigo' => trim($_POST['patrimonio_codigo'] ?? '') ?: null,
            'descricao' => trim($_POST['descricao'] ?? ''),
            'status' => $_POST['status'] ?? 'disponivel',
            'localizacao' => trim($_POST['localizacao'] ?? ''),
            'quantidade' => max(1, intval($_POST['quantidade'] ?? 1)),
            'valor_estimado' => !empty($_POST['valor_estimado']) ? floatval(str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,.]/', '', $_POST['valor_estimado'])))) : null
        ];

        if (empty($data['nome'])) {
            $errors[] = 'O nome é obrigatório.';
        }

        // Verificar patrimônio duplicado
        if ($data['patrimonio_codigo']) {
            $patrimonioCheck = $db->fetch(
                "SELECT id FROM inventory_items WHERE patrimonio_codigo = ? AND id != ?",
                [$data['patrimonio_codigo'], $id]
            );
            if ($patrimonioCheck) {
                $errors[] = 'Este código de patrimônio já está cadastrado.';
            }
        }

        // Upload de foto via arquivo
        if (!empty($_FILES['foto']['name'])) {
            $uploadError = null;
            $fotoUrl = uploadFileWithError($_FILES['foto'], 'almoxarifado', $uploadError);
            if ($fotoUrl) {
                if ($inventoryItem && !empty($inventoryItem['foto_capa_url'])) {
                    deleteFile($inventoryItem['foto_capa_url']);
                }
                $data['foto_capa_url'] = $fotoUrl;
            } elseif ($uploadError) {
                $errors[] = 'Erro no upload da foto: ' . $uploadError;
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
                    $uploadDir = BASE_PATH . 'uploads/almoxarifado/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    
                    $filename = generateCode() . '.' . $ext;
                    $filepath = $uploadDir . $filename;
                    
                    if (file_put_contents($filepath, $imageData)) {
                        if ($inventoryItem && !empty($inventoryItem['foto_capa_url'])) {
                            deleteFile($inventoryItem['foto_capa_url']);
                        }
                        $data['foto_capa_url'] = '/uploads/almoxarifado/' . $filename;
                    }
                }
            }
        }
        
        // Remover foto se solicitado
        if (($_POST['remove_foto'] ?? '0') === '1' && empty($_FILES['foto']['name']) && empty($_POST['foto_base64'])) {
            if ($inventoryItem && !empty($inventoryItem['foto_capa_url'])) {
                deleteFile($inventoryItem['foto_capa_url']);
            }
            $data['foto_capa_url'] = null;
        }

        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $db->update('inventory_items', $data, 'id = :id', ['id' => $id]);
                    Audit::log('update', 'inventory_items', $id);
                    setFlash('success', 'Item atualizado com sucesso!');
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $newId = $db->insert('inventory_items', $data);
                    Audit::log('create', 'inventory_items', $newId);
                    setFlash('success', 'Item cadastrado com sucesso!');
                }
                redirect('/almoxarifado');
            } catch (Exception $e) {
                $errors[] = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <p class="page-subtitle"><?= $isEdit ? 'Atualize as informações do item' : 'Cadastre um novo item no almoxarifado' ?></p>
    </div>
    <a href="<?= url('/almoxarifado') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <div class="alert-content">
        <i data-lucide="alert-circle"></i>
        <div>
            <?php foreach ($errors as $error): ?>
            <div><?= sanitize($error) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    
    <div class="pessoa-edit-layout">
        <!-- Sidebar com foto e status -->
        <div class="pessoa-sidebar">
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 24px;">
                    <div class="pessoa-foto-wrapper">
                        <?php if ($inventoryItem && $inventoryItem['foto_capa_url']): ?>
                        <img src="<?= url($inventoryItem['foto_capa_url']) ?>" alt="Foto" class="pessoa-foto-grande" id="photoPreview">
                        <?php else: ?>
                        <div class="pessoa-foto-placeholder" id="photoPlaceholder">
                            <i data-lucide="package" style="width: 48px; height: 48px;"></i>
                        </div>
                        <?php endif; ?>
                        <button type="button" class="pessoa-foto-edit" title="Alterar foto" onclick="togglePhotoMenu(event)">
                            <i data-lucide="camera"></i>
                        </button>
                        <input type="file" name="foto" id="fotoInputFile" accept="image/*" style="display: none;" onchange="previewFoto(this)">
                        <input type="hidden" id="fotoBase64" name="foto_base64" value="">
                        <input type="hidden" id="removePhotoFlag" name="remove_foto" value="0">
                        
                        <!-- Menu de opções de foto -->
                        <div class="foto-opcoes-menu" id="photoMenu">
                            <button type="button" class="foto-opcao" onclick="openCamera()">
                                <i data-lucide="camera"></i> Tirar Foto
                            </button>
                            <button type="button" class="foto-opcao" onclick="openFileUpload()">
                                <i data-lucide="image"></i> Escolher Arquivo
                            </button>
                            <?php if ($inventoryItem && !empty($inventoryItem['foto_capa_url'])): ?>
                            <button type="button" class="foto-opcao" onclick="removePhoto()" style="color: var(--danger);">
                                <i data-lucide="trash-2"></i> Remover Foto
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 style="margin: 16px 0 4px; font-size: 1.25rem;"><?= sanitize($inventoryItem['nome'] ?? 'Novo Item') ?></h3>
                    <p style="color: var(--gray-500); margin: 0 0 16px; font-size: 0.9rem;"><?= sanitize($inventoryItem['patrimonio_codigo'] ?? '') ?></p>
                    
                    <?php if ($isEdit): ?>
                    <div class="pessoa-status-badge <?= ($inventoryItem['status'] ?? 'disponivel') === 'disponivel' ? 'active' : 'inactive' ?>">
                        <i data-lucide="<?= ($inventoryItem['status'] ?? 'disponivel') === 'disponivel' ? 'check-circle' : 'alert-circle' ?>"></i>
                        <?= ucfirst($inventoryItem['status'] ?? 'disponivel') ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($isEdit): ?>
                <div class="card-body" style="border-top: 1px solid var(--gray-200); padding: 16px 24px;">
                    <div class="pessoa-info-item">
                        <i data-lucide="hash"></i>
                        <div>
                            <span class="pessoa-info-label">Quantidade</span>
                            <span class="pessoa-info-value"><?= $inventoryItem['quantidade'] ?? 1 ?></span>
                        </div>
                    </div>
                    <div class="pessoa-info-item">
                        <i data-lucide="map-pin"></i>
                        <div>
                            <span class="pessoa-info-label">Localização</span>
                            <span class="pessoa-info-value"><?= sanitize($inventoryItem['localizacao'] ?? '-') ?></span>
                        </div>
                    </div>
                    <?php if ($inventoryItem['valor_estimado']): ?>
                    <div class="pessoa-info-item">
                        <i data-lucide="dollar-sign"></i>
                        <div>
                            <span class="pessoa-info-label">Valor Estimado</span>
                            <span class="pessoa-info-value">R$ <?= formatMoney($inventoryItem['valor_estimado']) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Formulário principal -->
        <div class="pessoa-main">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="package"></i> Informações do Item</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label required">Nome do Item</label>
                            <input type="text" name="nome" class="form-control" required
                                   value="<?= sanitize($inventoryItem['nome'] ?? $_POST['nome'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código de Patrimônio</label>
                            <input type="text" name="patrimonio_codigo" class="form-control"
                                   value="<?= sanitize($inventoryItem['patrimonio_codigo'] ?? $_POST['patrimonio_codigo'] ?? '') ?>"
                                   placeholder="Ex: PAT-001">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Categoria</label>
                            <select name="categoria_id" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($inventoryItem['categoria_id'] ?? $_POST['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($cat['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="disponivel" <?= ($inventoryItem['status'] ?? 'disponivel') === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                                <option value="emprestado" <?= ($inventoryItem['status'] ?? '') === 'emprestado' ? 'selected' : '' ?>>Emprestado</option>
                                <option value="manutencao" <?= ($inventoryItem['status'] ?? '') === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                                <option value="baixado" <?= ($inventoryItem['status'] ?? '') === 'baixado' ? 'selected' : '' ?>>Baixado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Quantidade</label>
                            <input type="number" name="quantidade" class="form-control" min="1"
                                   value="<?= $inventoryItem['quantidade'] ?? $_POST['quantidade'] ?? 1 ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Valor Estimado (R$)</label>
                            <input type="text" name="valor_estimado" class="form-control" data-mask="money"
                                   value="<?= ($inventoryItem && !empty($inventoryItem['valor_estimado'])) ? formatMoney($inventoryItem['valor_estimado']) : '' ?>">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Localização</label>
                            <input type="text" name="localizacao" class="form-control"
                                   value="<?= sanitize($inventoryItem['localizacao'] ?? $_POST['localizacao'] ?? '') ?>"
                                   placeholder="Ex: Armário 3, Prateleira 2">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"><?= sanitize($inventoryItem['descricao'] ?? $_POST['descricao'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="card-footer">
                    <a href="<?= url('/almoxarifado') ?>" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i>
                        <?= $isEdit ? 'Salvar Alterações' : 'Cadastrar Item' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function togglePhotoMenu(e) {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    var menu = document.getElementById('photoMenu');
    menu.classList.toggle('show');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closePhotoMenu() {
    var menu = document.getElementById('photoMenu');
    if (menu) menu.classList.remove('show');
}

document.addEventListener('click', function(e) {
    var menu = document.getElementById('photoMenu');
    var btn = document.querySelector('.pessoa-foto-edit');
    if (menu && !menu.contains(e.target) && btn && !btn.contains(e.target)) {
        menu.classList.remove('show');
    }
});

function previewFoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var wrapper = document.querySelector('.pessoa-foto-wrapper');
            var existingImg = wrapper.querySelector('.pessoa-foto-grande');
            var existingPlaceholder = wrapper.querySelector('.pessoa-foto-placeholder');
            
            if (existingImg) {
                existingImg.src = e.target.result;
            } else if (existingPlaceholder) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.alt = 'Foto';
                img.className = 'pessoa-foto-grande';
                img.id = 'photoPreview';
                existingPlaceholder.replaceWith(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openFileUpload() {
    closePhotoMenu();
    document.getElementById('fotoInputFile').click();
}

function removePhoto() {
    closePhotoMenu();
    document.getElementById('removePhotoFlag').value = '1';
    var wrapper = document.querySelector('.pessoa-foto-wrapper');
    var existingImg = wrapper.querySelector('.pessoa-foto-grande');
    if (existingImg) {
        var placeholder = document.createElement('div');
        placeholder.className = 'pessoa-foto-placeholder';
        placeholder.id = 'photoPlaceholder';
        placeholder.innerHTML = '<i data-lucide="package" style="width: 48px; height: 48px;"></i>';
        existingImg.replaceWith(placeholder);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    showToast('Foto será removida ao salvar', 'info');
}

function openCamera() {
    closePhotoMenu();
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Seu navegador não suporta acesso à câmera. Use a opção "Escolher Arquivo".');
        return;
    }
    
    const modal = document.createElement('div');
    modal.id = 'cameraModal';
    modal.innerHTML = `
        <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="position: relative; max-width: 500px; width: 100%;">
                <video id="cameraVideo" autoplay playsinline style="width: 100%; border-radius: 12px;"></video>
                <canvas id="cameraCanvas" style="display: none;"></canvas>
            </div>
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <button type="button" onclick="capturePhoto()" style="width: 70px; height: 70px; border-radius: 50%; background: white; border: 4px solid var(--primary); cursor: pointer;">
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary); margin: auto;"></div>
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
    startCamera();
}

let currentStream = null;
let facingMode = 'environment';

async function startCamera() {
    try {
        if (currentStream) currentStream.getTracks().forEach(track => track.stop());
        currentStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: facingMode, width: { ideal: 640 }, height: { ideal: 480 } }
        });
        document.getElementById('cameraVideo').srcObject = currentStream;
        
        const devices = await navigator.mediaDevices.enumerateDevices();
        if (devices.filter(d => d.kind === 'videoinput').length > 1) {
            document.getElementById('switchCameraBtn').style.display = 'flex';
        }
    } catch (err) {
        closeCameraModal();
        alert('Não foi possível acessar a câmera.');
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
    canvas.getContext('2d').drawImage(video, 0, 0);
    
    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
    updatePhotoPreview(dataUrl);
    document.getElementById('fotoBase64').value = dataUrl;
    document.getElementById('removePhotoFlag').value = '0';
    
    closeCameraModal();
}

function closeCameraModal() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
    document.getElementById('cameraModal')?.remove();
}

function updatePhotoPreview(src) {
    var wrapper = document.querySelector('.pessoa-foto-wrapper');
    var existingImg = wrapper.querySelector('.pessoa-foto-grande');
    var existingPlaceholder = wrapper.querySelector('.pessoa-foto-placeholder');
    
    if (existingImg) {
        existingImg.src = src;
    } else if (existingPlaceholder) {
        var img = document.createElement('img');
        img.src = src;
        img.alt = 'Foto';
        img.className = 'pessoa-foto-grande';
        img.id = 'photoPreview';
        existingPlaceholder.replaceWith(img);
    }
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
