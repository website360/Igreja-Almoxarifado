<?php
/**
 * Criar/Editar Evento
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

$id = intval($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) {
    requirePermission('eventos', 'edit');
    $pageTitle = 'Editar Evento';
} else {
    requirePermission('eventos', 'create');
    $pageTitle = 'Novo Evento';
}

$db = Database::getInstance();

// Buscar evento se edição
$evento = null;
if ($isEdit) {
    $evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$id]);
    if (!$evento) {
        setFlash('error', 'Evento não encontrado.');
        redirect('/eventos');
    }
}

// Buscar ministérios e templates
$ministerios = $db->fetchAll("SELECT id, nome FROM ministerios WHERE ativo = 1 ORDER BY nome");
$templates = $db->fetchAll("SELECT id, nome FROM whatsapp_templates WHERE ativo = 1 ORDER BY nome");

// Buscar pessoas para participantes
$pessoas = $db->fetchAll("SELECT id, nome, foto_url, ministerio_id FROM users WHERE status = 'ativo' ORDER BY nome");

// Buscar pessoas por ministério para facilitar seleção em grupo
$pessoasPorMinisterio = [];
foreach ($ministerios as $min) {
    $pessoasPorMinisterio[$min['id']] = $db->fetchAll(
        "SELECT id, nome, foto_url FROM users WHERE ministerio_id = ? AND status = 'ativo' ORDER BY nome",
        [$min['id']]
    );
}

// Buscar participantes existentes (se edição)
$participantesIds = [];
if ($isEdit) {
    $participantes = $db->fetchAll("SELECT user_id FROM event_participants WHERE event_id = ?", [$id]);
    $participantesIds = array_column($participantes, 'user_id');
}

// Buscar configurações padrão
$configCheckinAbre = $db->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'checkin_abre_offset_min'");
$configCheckinFecha = $db->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'checkin_fecha_offset_min'");
$configTolerancia = $db->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'tolerancia_whatsapp_min'");

$defaultCheckinAbre = $configCheckinAbre['setting_value'] ?? 0;
$defaultCheckinFecha = $configCheckinFecha['setting_value'] ?? 120;
$defaultTolerancia = $configTolerancia['setting_value'] ?? 15;

$errors = [];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $data = [
            'titulo' => trim($_POST['titulo'] ?? ''),
            'tipo' => $_POST['tipo'] ?? 'culto',
            'descricao' => trim($_POST['descricao'] ?? ''),
            'local' => trim($_POST['local'] ?? ''),
            'inicio_at' => $_POST['inicio_data'] . ' ' . $_POST['inicio_hora'],
            'fim_at' => !empty($_POST['fim_data']) ? $_POST['fim_data'] . ' ' . ($_POST['fim_hora'] ?? '00:00') : null,
            'ministerio_responsavel_id' => !empty($_POST['ministerio_id']) ? intval($_POST['ministerio_id']) : null,
            'status' => $_POST['status'] ?? 'planejado',
            'tolerancia_minutos' => intval($_POST['tolerancia_minutos'] ?? $defaultTolerancia),
            'whatsapp_template_id' => !empty($_POST['template_id']) ? intval($_POST['template_id']) : null,
            'automation_enabled' => isset($_POST['automation_enabled']) ? 1 : 0,
            'destaque' => isset($_POST['destaque']) ? 1 : 0
        ];

        // Validações
        if (empty($data['titulo'])) {
            $errors[] = 'O título é obrigatório.';
        }
        if (empty($_POST['inicio_data']) || empty($_POST['inicio_hora'])) {
            $errors[] = 'Data e hora de início são obrigatórios.';
        }

        // Calcular janela de check-in
        if (!empty($_POST['checkin_abre_at'])) {
            $data['checkin_abre_at'] = $_POST['checkin_abre_at'];
        } else {
            $data['checkin_abre_at'] = date('Y-m-d H:i:s', strtotime($data['inicio_at']) - ($defaultCheckinAbre * 60));
        }

        if (!empty($_POST['checkin_fecha_at'])) {
            $data['checkin_fecha_at'] = $_POST['checkin_fecha_at'];
        } else {
            $data['checkin_fecha_at'] = date('Y-m-d H:i:s', strtotime($data['inicio_at']) + ($defaultCheckinFecha * 60));
        }

        // Upload de imagem
        if (!empty($_FILES['imagem']['name'])) {
            $imagemUrl = uploadFile($_FILES['imagem'], 'eventos');
            if ($imagemUrl) {
                $data['imagem_url'] = $imagemUrl;
            }
        }

        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $db->update('events', $data, 'id = :id', ['id' => $id]);
                    $eventoId = $id;
                    Audit::log('update', 'events', $id, $data);
                    setFlash('success', 'Evento atualizado com sucesso!');
                } else {
                    $data['created_by'] = $currentUser['id'];
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $eventoId = $db->insert('events', $data);
                    Audit::log('create', 'events', $eventoId, $data);
                    setFlash('success', 'Evento criado com sucesso!');
                }
                
                // Salvar participantes
                $participantesSelecionados = $_POST['participantes'] ?? [];
                
                // Remover participantes antigos
                $db->delete('event_participants', 'event_id = ?', [$eventoId]);
                
                // Inserir novos participantes
                foreach ($participantesSelecionados as $userId) {
                    $db->insert('event_participants', [
                        'event_id' => $eventoId,
                        'user_id' => intval($userId),
                        'status' => 'convidado'
                    ]);
                }
                
                redirect('/eventos');
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
        <p class="page-subtitle"><?= $isEdit ? 'Atualize as informações do evento' : 'Preencha os dados do novo evento' ?></p>
    </div>
    <a href="<?= url('/eventos') ?>" class="btn btn-secondary">
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


<div class="tab-content" id="tab-dados" style="display: block;">
<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    
    <div class="pessoa-edit-layout">
        <!-- Sidebar com imagem e info -->
        <div class="pessoa-sidebar">
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 24px;">
                    <div class="evento-capa-wrapper" style="position: relative; width: 100%; margin-bottom: 16px;">
                        <?php if ($evento && $evento['imagem_url']): ?>
                        <div style="width: 100%; height: 180px; overflow: hidden; border-radius: 16px;">
                            <img src="<?= url($evento['imagem_url']) ?>" alt="Imagem" id="imagemPreview" style="width: 100%; height: 100%; object-fit: none; object-position: center; display: block;">
                        </div>
                        <?php else: ?>
                        <div id="imagemPlaceholder" style="width: 100%; height: 120px; background: var(--gray-100); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="image" style="width: 48px; height: 48px; color: var(--gray-400);"></i>
                        </div>
                        <?php endif; ?>
                        <button type="button" class="pessoa-foto-edit" title="Alterar imagem" onclick="mostrarOpcoesImagem(event)" style="position: absolute; bottom: 8px; right: 8px;">
                            <i data-lucide="camera"></i>
                        </button>
                        <input type="file" name="imagem" id="inputImagemGaleria" accept="image/*" style="display: none;" onchange="previewImagem(this)">
                        
                        <!-- Menu de opções de imagem -->
                        <div class="foto-opcoes-menu" id="imagemOpcoesMenu">
                            <button type="button" class="foto-opcao" onclick="abrirCameraEvento()">
                                <i data-lucide="camera"></i> Tirar Foto
                            </button>
                            <button type="button" class="foto-opcao" onclick="abrirGaleriaEvento()">
                                <i data-lucide="image"></i> Escolher da Galeria
                            </button>
                        </div>
                    </div>
                    <h3 style="margin: 16px 0 4px; font-size: 1.25rem;"><?= sanitize($evento['titulo'] ?? 'Novo Evento') ?></h3>
                    <p style="color: var(--gray-500); margin: 0 0 16px; font-size: 0.9rem;">
                        <?= $evento ? formatDateFull($evento['inicio_at']) : 'Defina a data do evento' ?>
                    </p>
                    
                    <?php if ($isEdit): ?>
                    <div class="pessoa-status-badge <?= ($evento['status'] ?? 'planejado') === 'confirmado' ? 'active' : '' ?>">
                        <i data-lucide="<?= ($evento['status'] ?? 'planejado') === 'confirmado' ? 'check-circle' : 'clock' ?>"></i>
                        <?= EVENT_STATUS[$evento['status'] ?? 'planejado'] ?? 'Planejado' ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($isEdit): ?>
                <div class="card-body" style="border-top: 1px solid var(--gray-200); padding: 16px 24px;">
                    <div class="pessoa-info-item">
                        <i data-lucide="tag"></i>
                        <span><?= EVENT_TYPES[$evento['tipo'] ?? 'culto'] ?? 'Culto' ?></span>
                    </div>
                    <?php if ($evento['local']): ?>
                    <div class="pessoa-info-item">
                        <i data-lucide="map-pin"></i>
                        <span><?= sanitize($evento['local']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="pessoa-info-item">
                        <i data-lucide="users-2"></i>
                        <span>Check-in: <?= $evento['status_checkin'] === 'aberto' ? '<span class="text-success">Aberto</span>' : '<span class="text-muted">Fechado</span>' ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulário principal -->
        <div class="pessoa-main-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="calendar" style="width: 18px; height: 18px;"></i> Informações do Evento</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label required">Título do Evento</label>
                            <input type="text" name="titulo" class="form-control" required
                                   value="<?= sanitize($evento['titulo'] ?? $_POST['titulo'] ?? '') ?>"
                                   placeholder="Ex: Culto de Domingo">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Tipo</label>
                            <select name="tipo" class="form-control" required>
                                <?php foreach (EVENT_TYPES as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($evento['tipo'] ?? $_POST['tipo'] ?? 'culto') === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Ministério Responsável</label>
                            <select name="ministerio_id" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($ministerios as $min): ?>
                                <option value="<?= $min['id'] ?>" <?= ($evento['ministerio_responsavel_id'] ?? $_POST['ministerio_id'] ?? '') == $min['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($min['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <?php foreach (EVENT_STATUS as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($evento['status'] ?? $_POST['status'] ?? 'planejado') === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Data de Início</label>
                            <input type="date" name="inicio_data" class="form-control" required
                                   value="<?= $evento ? date('Y-m-d', strtotime($evento['inicio_at'])) : ($_POST['inicio_data'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Hora de Início</label>
                            <input type="time" name="inicio_hora" class="form-control" required
                                   value="<?= $evento ? date('H:i', strtotime($evento['inicio_at'])) : ($_POST['inicio_hora'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Data de Término</label>
                            <input type="date" name="fim_data" class="form-control"
                                   value="<?= $evento && $evento['fim_at'] ? date('Y-m-d', strtotime($evento['fim_at'])) : ($_POST['fim_data'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Hora de Término</label>
                            <input type="time" name="fim_hora" class="form-control"
                                   value="<?= $evento && $evento['fim_at'] ? date('H:i', strtotime($evento['fim_at'])) : ($_POST['fim_hora'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Local</label>
                        <input type="text" name="local" class="form-control"
                               value="<?= sanitize($evento['local'] ?? $_POST['local'] ?? '') ?>"
                               placeholder="Ex: Templo Principal, Sala 3, Online (Zoom)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"
                                  placeholder="Descrição opcional do evento..."><?= sanitize($evento['descricao'] ?? $_POST['descricao'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" name="destaque" id="destaque" class="form-check-input"
                               <?= ($evento['destaque'] ?? 0) ? 'checked' : '' ?>>
                        <label for="destaque" class="form-check-label">Destacar este evento no Dashboard</label>
                    </div>
                </div>
            </div>

            <!-- Participantes -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="user-plus" style="width: 18px; height: 18px;"></i> Participantes</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Buscar e adicionar participantes</label>
                        <input type="text" id="buscaParticipante" class="form-control" placeholder="Digite o nome para buscar...">
                        <small class="form-text">Ou use o campo "Ministério Responsável" acima para adicionar todos os membros automaticamente</small>
                    </div>
                    
                    <div id="participantesSelecionados" class="participantes-lista" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;">
                        <?php foreach ($pessoas as $p): ?>
                            <?php if (in_array($p['id'], $participantesIds)): ?>
                            <div class="participante-tag" data-id="<?= $p['id'] ?>">
                                <?php if ($p['foto_url']): ?>
                                <img src="<?= url($p['foto_url']) ?>" alt="">
                                <?php else: ?>
                                <span class="participante-avatar" style="background: <?= getAvatarColor($p['nome']) ?>"><?= getInitials($p['nome']) ?></span>
                                <?php endif; ?>
                                <span><?= sanitize($p['nome']) ?></span>
                                <button type="button" onclick="removerParticipante(<?= $p['id'] ?>)"><i data-lucide="x"></i></button>
                                <input type="hidden" name="participantes[]" value="<?= $p['id'] ?>">
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="sugestoesParticipantes" class="sugestoes-dropdown" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: 8px; background: white;">
                    </div>
                    
                    <small class="form-text">Selecione as pessoas que devem participar deste evento</small>
                </div>
            </div>


            <div class="form-actions" style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= url('/eventos') ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    <?= $isEdit ? 'Salvar Alterações' : 'Criar Evento' ?>
                </button>
            </div>
        </div>
    </div>
</form>
</div>


<!-- Modal da Câmera -->
<div class="modal-backdrop" id="cameraBackdropEvento"></div>
<div class="modal" id="cameraModalEvento">
    <div class="modal-content" style="max-width: 500px; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div class="modal-header">
            <h3><i data-lucide="camera"></i> Tirar Foto</h3>
            <button type="button" class="modal-close" onclick="fecharCameraEvento()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 20px;">
            <video id="cameraVideoEvento" autoplay playsinline style="width: 100%; max-width: 400px; border-radius: 12px; background: #000;"></video>
            <canvas id="cameraCanvasEvento" style="display: none;"></canvas>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="fecharCameraEvento()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="capturarFotoEvento()">
                <i data-lucide="camera"></i> Capturar
            </button>
        </div>
    </div>
</div>

<script>
// Menu de opções de imagem
function mostrarOpcoesImagem(e) {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    var menu = document.getElementById('imagemOpcoesMenu');
    menu.classList.toggle('show');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

var cameraStreamEvento = null;

function abrirCameraEvento() {
    fecharMenuImagem();
    
    var video = document.getElementById('cameraVideoEvento');
    var modal = document.getElementById('cameraModalEvento');
    var backdrop = document.getElementById('cameraBackdropEvento');
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        .then(function(stream) {
            cameraStreamEvento = stream;
            video.srcObject = stream;
            modal.classList.add('show');
            backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(function(err) {
            console.error('Erro ao acessar câmera:', err);
            if (typeof showToast === 'function') {
                showToast('Não foi possível acessar a câmera. Verifique as permissões.', 'error');
            } else {
                alert('Não foi possível acessar a câmera. Verifique as permissões.');
            }
        });
}

function fecharCameraEvento() {
    var video = document.getElementById('cameraVideoEvento');
    var modal = document.getElementById('cameraModalEvento');
    var backdrop = document.getElementById('cameraBackdropEvento');
    
    if (cameraStreamEvento) {
        cameraStreamEvento.getTracks().forEach(function(track) { track.stop(); });
        cameraStreamEvento = null;
    }
    video.srcObject = null;
    modal.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
}

function capturarFotoEvento() {
    var video = document.getElementById('cameraVideoEvento');
    var canvas = document.getElementById('cameraCanvasEvento');
    var ctx = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    
    canvas.toBlob(function(blob) {
        var file = new File([blob], 'imagem_evento.jpg', { type: 'image/jpeg' });
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        
        var input = document.getElementById('inputImagemGaleria');
        input.files = dataTransfer.files;
        
        // Atualizar preview
        var imgUrl = URL.createObjectURL(blob);
        var placeholder = document.getElementById('imagemPlaceholder');
        var preview = document.getElementById('imagemPreview');
        
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        
        if (!preview) {
            preview = document.createElement('img');
            preview.id = 'imagemPreview';
            preview.style.width = '100%';
            preview.style.maxHeight = '180px';
            preview.style.objectFit = 'cover';
            preview.style.borderRadius = '12px';
            var wrapper = document.querySelector('.evento-capa-wrapper');
            wrapper.insertBefore(preview, wrapper.firstChild);
        }
        
        preview.src = imgUrl;
        preview.style.display = 'block';
        
        fecharCameraEvento();
        if (typeof showToast === 'function') {
            showToast('Foto capturada com sucesso!', 'success');
        }
    }, 'image/jpeg', 0.9);
}

function abrirGaleriaEvento() {
    fecharMenuImagem();
    document.getElementById('inputImagemGaleria').click();
}

function fecharMenuImagem() {
    var menu = document.getElementById('imagemOpcoesMenu');
    if (menu) menu.classList.remove('show');
}

// Fechar menu ao clicar fora
document.addEventListener('click', function(e) {
    var menu = document.getElementById('imagemOpcoesMenu');
    var btn = document.querySelector('.evento-capa-wrapper .pessoa-foto-edit');
    if (menu && !menu.contains(e.target) && btn && !btn.contains(e.target)) {
        menu.classList.remove('show');
    }
});

function previewImagem(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const placeholder = document.getElementById('imagemPlaceholder');
            let preview = document.getElementById('imagemPreview');
            
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            
            if (!preview) {
                preview = document.createElement('img');
                preview.id = 'imagemPreview';
                preview.style.width = '100%';
                preview.style.maxHeight = '180px';
                preview.style.objectFit = 'cover';
                preview.style.borderRadius = '12px';
                const wrapper = document.querySelector('.evento-capa-wrapper');
                wrapper.insertBefore(preview, wrapper.firstChild);
            }
            
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}


// Sistema de participantes
const pessoasDisponiveis = <?= json_encode($pessoas) ?>;
const pessoasPorMinisterio = <?= json_encode($pessoasPorMinisterio) ?>;
const participantesSelecionadosIds = new Set(<?= json_encode(array_map('intval', $participantesIds)) ?>);

const buscaInput = document.getElementById('buscaParticipante');
const sugestoesDiv = document.getElementById('sugestoesParticipantes');
const selecionadosDiv = document.getElementById('participantesSelecionados');
const ministerioResponsavelSelect = document.querySelector('select[name="ministerio_id"]');

// Integração com campo "Ministério Responsável"
if (ministerioResponsavelSelect) {
    ministerioResponsavelSelect.addEventListener('change', function() {
        const ministerioId = parseInt(this.value);
        
        if (!ministerioId || !pessoasPorMinisterio[ministerioId]) {
            return;
        }
        
        // Limpar participantes atuais
        participantesSelecionadosIds.clear();
        selecionadosDiv.innerHTML = '';
        
        // Adicionar todos os membros do ministério selecionado
        const pessoas = pessoasPorMinisterio[ministerioId];
        pessoas.forEach(p => {
            adicionarParticipante(p.id, p.nome, p.foto_url || '');
        });
        
        if (pessoas.length > 0) {
            showToast(`${pessoas.length} participante(s) adicionado(s) do ministério`, 'success');
        } else {
            showToast('Este ministério não possui membros cadastrados', 'info');
        }
        
        lucide.createIcons();
    });
}

if (buscaInput) {
    buscaInput.addEventListener('input', function() {
        const termo = this.value.toLowerCase().trim();
        
        if (termo.length < 2) {
            sugestoesDiv.style.display = 'none';
            return;
        }
        
        const filtradas = pessoasDisponiveis.filter(p => 
            p.nome.toLowerCase().includes(termo) && !participantesSelecionadosIds.has(p.id)
        ).slice(0, 10);
        
        if (filtradas.length === 0) {
            sugestoesDiv.innerHTML = '<div style="padding: 12px; color: var(--gray-500);">Nenhuma pessoa encontrada</div>';
        } else {
            sugestoesDiv.innerHTML = filtradas.map(p => `
                <div class="sugestao-item" onclick="adicionarParticipante(${p.id}, '${escapeHtml(p.nome)}', '${p.foto_url || ''}')" 
                     style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; cursor: pointer; border-bottom: 1px solid var(--gray-100);"
                     onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
                    ${p.foto_url 
                        ? `<img src="<?= url('') ?>${p.foto_url}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">` 
                        : `<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">${getInitials(p.nome)}</div>`
                    }
                    <span>${escapeHtml(p.nome)}</span>
                </div>
            `).join('');
        }
        
        sugestoesDiv.style.display = 'block';
    });
    
    buscaInput.addEventListener('blur', function() {
        setTimeout(() => sugestoesDiv.style.display = 'none', 200);
    });
}

function adicionarParticipante(id, nome, fotoUrl) {
    if (participantesSelecionadosIds.has(id)) return;
    
    participantesSelecionadosIds.add(id);
    
    const tag = document.createElement('div');
    tag.className = 'participante-tag';
    tag.dataset.id = id;
    tag.innerHTML = `
        ${fotoUrl 
            ? `<img src="<?= url('') ?>${fotoUrl}" alt="">` 
            : `<span class="participante-avatar">${getInitials(nome)}</span>`
        }
        <span>${escapeHtml(nome)}</span>
        <button type="button" onclick="removerParticipante(${id})"><i data-lucide="x"></i></button>
        <input type="hidden" name="participantes[]" value="${id}">
    `;
    
    selecionadosDiv.appendChild(tag);
    buscaInput.value = '';
    sugestoesDiv.style.display = 'none';
    lucide.createIcons();
}

function removerParticipante(id) {
    participantesSelecionadosIds.delete(id);
    const tag = selecionadosDiv.querySelector(`.participante-tag[data-id="${id}"]`);
    if (tag) tag.remove();
}

function getInitials(nome) {
    return nome.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
.participante-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: var(--gray-100);
    border-radius: 20px;
    font-size: 13px;
}
.participante-tag img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
}
.participante-tag .participante-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 600;
    color: white;
    background: var(--primary);
}
.participante-tag button {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: var(--gray-500);
    display: flex;
    align-items: center;
}
.participante-tag button:hover {
    color: var(--danger);
}
.participante-tag button svg {
    width: 14px;
    height: 14px;
}

/* Estilos para menu de opções de imagem - sobrescrever estilos globais */
.evento-capa-wrapper #imagemOpcoesMenu {
    position: absolute !important;
    bottom: 50px !important;
    right: 8px !important;
    left: auto !important;
    top: auto !important;
    transform: none !important;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    padding: 8px;
    display: none;
    flex-direction: column;
    gap: 4px;
    min-width: 180px;
    z-index: 1000;
}

.evento-capa-wrapper #imagemOpcoesMenu.show {
    display: flex !important;
}
</style>

<?php include BASE_PATH . 'includes/footer.php'; ?>
