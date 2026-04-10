<?php
/**
 * Integrações - WhatsApp (Evolution API / Z-API)
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('integracoes', 'view');

$pageTitle = 'Integrações';
$db = Database::getInstance();

// Buscar configurações atuais
$whatsappIntegration = $db->fetch("SELECT * FROM whatsapp_integrations ORDER BY id DESC LIMIT 1");
$templates = $db->fetchAll("SELECT * FROM whatsapp_templates ORDER BY nome");
$currentProvider = $whatsappIntegration['provider'] ?? 'evolution';

// Estatísticas de mensagens
$msgStats = $db->fetch(
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as enviadas,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendentes,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as falhas
     FROM message_queue
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);

// Últimas mensagens
$ultimasMensagens = $db->fetchAll(
    "SELECT mq.*, u.nome as pessoa_nome, e.titulo as evento_titulo
     FROM message_queue mq
     LEFT JOIN users u ON mq.person_id = u.id
     LEFT JOIN events e ON mq.event_id = e.id
     ORDER BY mq.created_at DESC
     LIMIT 20"
);

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Integrações WhatsApp</h1>
        <p class="page-subtitle">Conecte o WhatsApp para envio de mensagens</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon primary"><i data-lucide="message-circle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $msgStats['total'] ?? 0 ?></div>
            <div class="stat-label">Mensagens (30 dias)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i data-lucide="check-circle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $msgStats['enviadas'] ?? 0 ?></div>
            <div class="stat-label">Enviadas</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i data-lucide="clock"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $msgStats['pendentes'] ?? 0 ?></div>
            <div class="stat-label">Pendentes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i data-lucide="alert-circle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $msgStats['falhas'] ?? 0 ?></div>
            <div class="stat-label">Falhas</div>
        </div>
    </div>
</div>

<!-- Seletor de Provedor -->
<div class="card mb-3">
    <div class="card-body" style="padding: 24px;">
        <div style="text-align: center; margin-bottom: 24px;">
            <h3 style="margin: 0 0 8px 0; font-size: 1.5rem;">
                <i data-lucide="message-circle" style="color: #25D366;"></i>
                Conecte seu WhatsApp
            </h3>
            <p class="text-muted" style="margin: 0;">Escolha como deseja conectar o WhatsApp ao sistema</p>
        </div>
        
        <div class="d-flex gap-2" style="gap: 20px; max-width: 800px; margin: 0 auto;">
            <label class="provider-card <?= $currentProvider === 'evolution' ? 'active' : '' ?>" onclick="selecionarProvedor('evolution')" style="flex: 1; cursor: pointer; padding: 24px; border: 3px solid <?= $currentProvider === 'evolution' ? '#10b981' : 'var(--gray-200)' ?>; border-radius: 12px; text-align: center; transition: all 0.3s; background: <?= $currentProvider === 'evolution' ? '#f0fdf4' : 'white' ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <input type="radio" name="provider_select" value="evolution" <?= $currentProvider === 'evolution' ? 'checked' : '' ?> style="display: none;">
                <div style="font-size: 3rem; margin-bottom: 12px;">�</div>
                <strong style="font-size: 1.2rem; display: block; margin-bottom: 8px; color: #10b981;">Evolution API</strong>
                <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.5;">
                    ✓ Totalmente gratuito<br>
                    ✓ Integrado ao sistema<br>
                    ✓ Conecte com QR Code<br>
                    ✓ Sem mensalidades
                </p>
                <span class="badge badge-success" style="margin-top: 12px; padding: 6px 12px; font-size: 0.85rem;">✨ Recomendado</span>
            </label>
            
            <label class="provider-card <?= $currentProvider === 'zapi' ? 'active' : '' ?>" onclick="selecionarProvedor('zapi')" style="flex: 1; cursor: pointer; padding: 24px; border: 3px solid <?= $currentProvider === 'zapi' ? '#3b82f6' : 'var(--gray-200)' ?>; border-radius: 12px; text-align: center; transition: all 0.3s; background: <?= $currentProvider === 'zapi' ? '#eff6ff' : 'white' ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <input type="radio" name="provider_select" value="zapi" <?= $currentProvider === 'zapi' ? 'checked' : '' ?> style="display: none;">
                <div style="font-size: 3rem; margin-bottom: 12px;">�</div>
                <strong style="font-size: 1.2rem; display: block; margin-bottom: 8px; color: #3b82f6;">Z-API</strong>
                <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.5;">
                    • Serviço externo<br>
                    • Requer conta própria<br>
                    • Planos pagos<br>
                    • Mais recursos
                </p>
                <span class="badge badge-primary" style="margin-top: 12px; padding: 6px 12px; font-size: 0.85rem;">Avançado</span>
            </label>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- EVOLUTION API -->
<!-- ============================================= -->
<div id="panel-evolution" style="display: <?= $currentProvider === 'evolution' ? 'block' : 'none' ?>;">
    <div class="grid grid-2" style="gap: 24px;">
        <!-- Conexão WhatsApp -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="smartphone" style="color: #25D366;"></i>
                    Conexão WhatsApp
                </h3>
                <span id="evoStatusBadge" class="badge badge-secondary">Verificando...</span>
            </div>
            <div class="card-body">
                <!-- Estado: Sem instância -->
                <div id="evoNoInstance" style="display: none; text-align: center; padding: 20px 0;">
                    <div style="font-size: 3rem; margin-bottom: 12px;">📱</div>
                    <h4 style="margin-bottom: 8px;">Conecte seu WhatsApp</h4>
                    <p class="text-muted mb-3">Crie uma instância para gerar o QR Code e conectar seu WhatsApp ao sistema.</p>
                    <div class="form-group" style="max-width: 300px; margin: 0 auto 16px;">
                        <input type="text" id="evoInstanceName" class="form-control" 
                               placeholder="Nome da instância (ex: igreja-sede)"
                               value="igreja-<?= strtolower(preg_replace('/[^a-zA-Z0-9]/', '', getSetting('igreja_nome', 'conectada'))) ?>">
                        <small class="text-muted">Apenas letras, números e hífens</small>
                    </div>
                    <button class="btn btn-primary" id="btnCriarInstancia" onclick="criarInstancia()">
                        <i data-lucide="plus-circle"></i> Criar Instância e Gerar QR Code
                    </button>
                </div>
                
                <!-- Estado: QR Code -->
                <div id="evoQrCode" style="display: none; text-align: center; padding: 20px 0;">
                    <h4 style="margin-bottom: 4px;">Escaneie o QR Code</h4>
                    <p class="text-muted mb-3">Abra o WhatsApp no celular → Menu (⋮) → Aparelhos conectados → Conectar</p>
                    <div id="evoQrImage" style="margin: 0 auto 16px; padding: 16px; background: white; border-radius: 12px; display: inline-block; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="width: 256px; height: 256px; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="loader" style="animation: spin 1s linear infinite;"></i> Carregando...
                        </div>
                    </div>
                    <p class="text-muted" style="font-size: 0.85rem;">
                        <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                        O QR Code atualiza automaticamente a cada 30 segundos
                    </p>
                </div>
                
                <!-- Estado: Conectado -->
                <div id="evoConnected" style="display: none; text-align: center; padding: 20px 0;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                        <i data-lucide="check" style="width: 32px; height: 32px; color: #16a34a;"></i>
                    </div>
                    <h4 style="margin-bottom: 4px; color: #16a34a;">WhatsApp Conectado!</h4>
                    <p class="text-muted mb-0" id="evoPhoneInfo">Pronto para enviar mensagens</p>
                    <div style="margin-top: 16px;">
                        <button class="btn btn-outline-danger btn-sm" onclick="desconectarInstancia()">
                            <i data-lucide="log-out"></i> Desconectar
                        </button>
                    </div>
                </div>
                
                <!-- Loading -->
                <div id="evoLoading" style="text-align: center; padding: 30px 0;">
                    <i data-lucide="loader" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                    <p class="text-muted mt-2">Verificando conexão...</p>
                </div>
            </div>
        </div>
        
        <!-- Info Evolution -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="info"></i>
                    Sobre a Evolution API
                </h3>
            </div>
            <div class="card-body">
                <div style="padding: 8px 0;">
                    <div class="d-flex align-center mb-2" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <i data-lucide="check-circle" style="color: var(--success); flex-shrink: 0;"></i>
                        <div>
                            <strong>Integrada ao sistema</strong>
                            <br><small class="text-muted">Não precisa configurar nenhuma credencial</small>
                        </div>
                    </div>
                    <div class="d-flex align-center mb-2" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <i data-lucide="qr-code" style="color: var(--primary); flex-shrink: 0;"></i>
                        <div>
                            <strong>Conexão por QR Code</strong>
                            <br><small class="text-muted">Escaneie com o WhatsApp e pronto</small>
                        </div>
                    </div>
                    <div class="d-flex align-center mb-2" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <i data-lucide="zap" style="color: var(--warning); flex-shrink: 0;"></i>
                        <div>
                            <strong>Envio instantâneo</strong>
                            <br><small class="text-muted">Mensagens enviadas em tempo real</small>
                        </div>
                    </div>
                    <div class="d-flex align-center" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <i data-lucide="shield-check" style="color: var(--info); flex-shrink: 0;"></i>
                        <div>
                            <strong>Seguro</strong>
                            <br><small class="text-muted">Criptografia ponta a ponta do WhatsApp</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- Z-API -->
<!-- ============================================= -->
<div id="panel-zapi" style="display: <?= $currentProvider === 'zapi' ? 'block' : 'none' ?>;">
    <div class="grid grid-2" style="gap: 24px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="message-circle" style="color: #25D366;"></i>
                    Configurações Z-API
                </h3>
                <?php if ($whatsappIntegration && $whatsappIntegration['ativo'] && $currentProvider === 'zapi'): ?>
                <span class="badge badge-success">Ativo</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('/integracoes/api.php') ?>" id="formZapi">
                    <input type="hidden" name="action" value="save_whatsapp">
                    <input type="hidden" name="provider" value="zapi">
                    <?= csrfField() ?>
                    
                    <div class="form-group">
                        <label class="form-label">Instance ID</label>
                        <input type="text" name="instance_id" class="form-control" autocomplete="off"
                               value="<?= sanitize($whatsappIntegration['instance_id'] ?? '') ?>"
                               placeholder="Seu Instance ID do Z-API">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Token</label>
                        <input type="text" name="token" class="form-control" autocomplete="off"
                               value="<?= sanitize($whatsappIntegration['token'] ?? '') ?>"
                               placeholder="Seu Token do Z-API">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Client Token (Security)</label>
                        <input type="text" name="api_key" class="form-control" autocomplete="off"
                               value="<?= sanitize($whatsappIntegration['api_key'] ?? '') ?>"
                               placeholder="Cole aqui o Client Token da Z-API">
                        <small class="text-muted">
                            Encontre em: Painel Z-API → sua instância → Security → Client Token
                        </small>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input type="checkbox" name="ativo" id="zapiAtivo" class="form-check-input"
                               <?= ($whatsappIntegration['ativo'] ?? 0) && $currentProvider === 'zapi' ? 'checked' : '' ?>>
                        <label for="zapiAtivo" class="form-check-label">Integração ativa</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i> Salvar Configurações Z-API
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Info Z-API -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sobre a Z-API</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">A Z-API é um serviço externo pago para envio de mensagens via WhatsApp.</p>
                <div class="d-flex align-center mb-2" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                    <i data-lucide="external-link" style="color: var(--primary); flex-shrink: 0;"></i>
                    <div>
                        <strong>Requer conta Z-API</strong>
                        <br><small class="text-muted">Acesse <a href="https://z-api.io" target="_blank">z-api.io</a> para criar sua conta</small>
                    </div>
                </div>
                <div class="d-flex align-center mb-2" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                    <i data-lucide="key" style="color: var(--warning); flex-shrink: 0;"></i>
                    <div>
                        <strong>3 credenciais necessárias</strong>
                        <br><small class="text-muted">Instance ID, Token e Client Token</small>
                    </div>
                </div>
                <div class="d-flex align-center" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                    <i data-lucide="credit-card" style="color: var(--danger); flex-shrink: 0;"></i>
                    <div>
                        <strong>Serviço pago</strong>
                        <br><small class="text-muted">Planos a partir de R$ 49,90/mês</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Templates -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Templates de Mensagem</h3>
        <?php if (can('integracoes', 'manage_settings')): ?>
        <button class="btn btn-sm btn-primary" onclick="novoTemplate()">
            <i data-lucide="plus"></i> Novo
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
        <?php if (empty($templates)): ?>
        <p class="text-muted text-center">Nenhum template cadastrado.</p>
        <?php else: ?>
            <?php foreach ($templates as $tpl): ?>
            <div class="d-flex justify-between align-center mb-2" style="padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                <div>
                    <strong><?= sanitize($tpl['nome']) ?></strong>
                    <?= $tpl['ativo'] ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-secondary">Inativo</span>' ?>
                    <br><small class="text-muted"><?= sanitize(substr($tpl['mensagem'], 0, 60)) ?>...</small>
                </div>
                <?php if (can('integracoes', 'manage_settings')): ?>
                <div class="d-flex gap-1">
                    <button class="btn btn-icon btn-sm btn-secondary" onclick="editarTemplate(<?= $tpl['id'] ?>)">
                        <i data-lucide="edit"></i>
                    </button>
                    <button class="btn btn-icon btn-sm btn-outline-danger" onclick="excluirTemplate(<?= $tpl['id'] ?>)">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Teste de Envio -->
<?php if ($whatsappIntegration && $whatsappIntegration['ativo'] && can('integracoes', 'manage_settings')): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <i data-lucide="send"></i>
            Testar Envio de Mensagem
        </h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Envie uma mensagem de teste para verificar se a integração está funcionando.</p>
        
        <form id="formTesteEnvio" onsubmit="enviarMensagemTeste(event)">
            <div class="grid grid-2" style="gap: 16px;">
                <div class="form-group">
                    <label class="form-label required">Número (com DDD)</label>
                    <input type="text" id="testPhone" class="form-control" 
                           placeholder="Ex: 11999999999" required
                           pattern="[0-9]{10,11}">
                </div>
                <div class="form-group">
                    <label class="form-label required">Mensagem</label>
                    <textarea id="testMessage" class="form-control" rows="3" required
                              placeholder="Mensagem de teste...">Olá! Teste do sistema da igreja. Se recebeu, a integração está funcionando! 🙏</textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" id="btnEnviarTeste">
                <i data-lucide="send"></i> Enviar Teste
            </button>
        </form>
        
        <div id="testeResultado" class="mt-3" style="display: none;"></div>
    </div>
</div>
<?php endif; ?>

<!-- Log de Mensagens -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Log de Mensagens</h3>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Destinatário</th>
                    <th>Evento</th>
                    <th>Status</th>
                    <th>Tentativas</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimasMensagens)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted" style="padding: 30px;">
                        Nenhuma mensagem enviada ainda.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($ultimasMensagens as $msg): ?>
                    <tr>
                        <td>
                            <?= sanitize($msg['pessoa_nome'] ?? 'Desconhecido') ?><br>
                            <small class="text-muted"><?= formatPhone($msg['phone']) ?></small>
                        </td>
                        <td><?= sanitize($msg['evento_titulo'] ?? '-') ?></td>
                        <td><?= statusBadge($msg['status']) ?></td>
                        <td><?= $msg['attempts'] ?></td>
                        <td>
                            <?= formatDateTime($msg['created_at']) ?>
                            <?php if ($msg['last_error']): ?>
                            <br><small class="text-danger"><?= sanitize(substr($msg['last_error'], 0, 40)) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.provider-card { position: relative; }
.provider-card:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 8px 16px rgba(0,0,0,0.12) !important;
}
.provider-card.active { 
    transform: translateY(-2px);
}
</style>

<script>
const API_URL = '<?= url('/integracoes/api.php') ?>';
const CSRF = window.csrfToken;
let qrInterval = null;
let statusInterval = null;
let currentProvider = '<?= $currentProvider ?>';
let instanceName = '<?= sanitize($whatsappIntegration['instance_name'] ?? '') ?>';

// =============================================
// SELETOR DE PROVEDOR
// =============================================
function selecionarProvedor(provider) {
    document.getElementById('panel-evolution').style.display = provider === 'evolution' ? 'block' : 'none';
    document.getElementById('panel-zapi').style.display = provider === 'zapi' ? 'block' : 'none';
    
    // Atualizar visual dos cards
    document.querySelectorAll('.provider-card').forEach(card => {
        card.classList.remove('active');
    });
    document.querySelectorAll('.provider-card')[provider === 'evolution' ? 0 : 1].classList.add('active');
    
    // Salvar escolha
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'set_provider', provider: provider })
    }).then(() => {
        showToast(provider === 'evolution' ? 'Evolution API selecionada' : 'Z-API selecionada', 'success');
    });
    
    currentProvider = provider;
    
    if (provider === 'evolution') {
        verificarStatusEvolution();
    } else {
        pararVerificacao();
    }
}

// =============================================
// EVOLUTION API
// =============================================
function criarInstancia() {
    const nameInput = document.getElementById('evoInstanceName');
    const name = nameInput.value.trim().replace(/[^a-zA-Z0-9-]/g, '').toLowerCase();
    
    if (!name || name.length < 3) {
        showToast('Nome da instância deve ter pelo menos 3 caracteres', 'error');
        return;
    }
    
    const btn = document.getElementById('btnCriarInstancia');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader"></i> Criando instância...';
    lucide.createIcons();
    
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'evo_create_instance', instance_name: name })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="plus-circle"></i> Criar Instância e Gerar QR Code';
        lucide.createIcons();
        
        if (data.success) {
            instanceName = name;
            showToast('Instância criada! Escaneie o QR Code.', 'success');
            
            if (data.qrcode) {
                mostrarQrCode(data.qrcode);
            } else {
                buscarQrCode();
            }
            
            iniciarVerificacaoStatus();
        } else {
            showToast(data.message || data.error || 'Erro ao criar instância', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="plus-circle"></i> Criar Instância e Gerar QR Code';
        lucide.createIcons();
        showToast('Erro de conexão', 'error');
    });
}

function buscarQrCode() {
    if (!instanceName) return;
    
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'evo_get_qrcode', instance_name: instanceName })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.qrcode) {
            mostrarQrCode(data.qrcode);
        }
    });
}

function mostrarQrCode(qrData) {
    mostrarEstado('qrcode');
    
    const container = document.getElementById('evoQrImage');
    
    if (qrData.startsWith('data:image') || qrData.startsWith('http')) {
        container.innerHTML = `<img src="${qrData}" alt="QR Code" style="width: 256px; height: 256px;">`;
    } else {
        // Base64
        container.innerHTML = `<img src="data:image/png;base64,${qrData}" alt="QR Code" style="width: 256px; height: 256px;">`;
    }
    
    // Atualizar QR a cada 30s
    clearInterval(qrInterval);
    qrInterval = setInterval(buscarQrCode, 30000);
}

function verificarStatusEvolution() {
    if (!instanceName) {
        mostrarEstado('no-instance');
        return;
    }
    
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'evo_check_status', instance_name: instanceName })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const state = (data.state || '').toLowerCase();
            
            if (state === 'open' || state === 'connected') {
                mostrarEstado('connected');
                document.getElementById('evoStatusBadge').className = 'badge badge-success';
                document.getElementById('evoStatusBadge').textContent = 'Conectado';
                if (data.phone) {
                    document.getElementById('evoPhoneInfo').textContent = 'Número: ' + data.phone;
                }
                pararVerificacao();
            } else if (state === 'close' || state === 'disconnected') {
                buscarQrCode();
                iniciarVerificacaoStatus();
            } else {
                mostrarEstado('no-instance');
            }
        } else {
            mostrarEstado('no-instance');
            document.getElementById('evoStatusBadge').className = 'badge badge-secondary';
            document.getElementById('evoStatusBadge').textContent = 'Desconectado';
        }
    })
    .catch(() => {
        mostrarEstado('no-instance');
    });
}

function iniciarVerificacaoStatus() {
    clearInterval(statusInterval);
    statusInterval = setInterval(verificarStatusEvolution, 5000);
}

function pararVerificacao() {
    clearInterval(qrInterval);
    clearInterval(statusInterval);
}

function desconectarInstancia() {
    if (!confirm('Tem certeza que deseja desconectar o WhatsApp?')) return;
    
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'evo_logout', instance_name: instanceName })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('WhatsApp desconectado', 'success');
            mostrarEstado('no-instance');
            document.getElementById('evoStatusBadge').className = 'badge badge-secondary';
            document.getElementById('evoStatusBadge').textContent = 'Desconectado';
        } else {
            showToast(data.message || 'Erro ao desconectar', 'error');
        }
    });
}

function mostrarEstado(state) {
    document.getElementById('evoLoading').style.display = 'none';
    document.getElementById('evoNoInstance').style.display = state === 'no-instance' ? 'block' : 'none';
    document.getElementById('evoQrCode').style.display = state === 'qrcode' ? 'block' : 'none';
    document.getElementById('evoConnected').style.display = state === 'connected' ? 'block' : 'none';
}

// =============================================
// TESTE DE ENVIO
// =============================================
function enviarMensagemTeste(event) {
    event.preventDefault();
    
    const phone = document.getElementById('testPhone').value;
    const message = document.getElementById('testMessage').value;
    const btn = document.getElementById('btnEnviarTeste');
    const resultado = document.getElementById('testeResultado');
    
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader"></i> Enviando...';
    lucide.createIcons();
    resultado.style.display = 'none';
    
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'test_whatsapp', phone: phone, message: message })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send"></i> Enviar Teste';
        lucide.createIcons();
        
        resultado.innerHTML = data.success
            ? `<div class="alert alert-success"><strong>Mensagem enviada com sucesso!</strong> Verifique o WhatsApp do destinatário.</div>`
            : `<div class="alert alert-danger"><strong>Erro:</strong> ${data.message || data.error || 'Erro desconhecido'}</div>`;
        resultado.style.display = 'block';
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send"></i> Enviar Teste';
        lucide.createIcons();
        resultado.innerHTML = `<div class="alert alert-danger"><strong>Erro de conexão.</strong> Tente novamente.</div>`;
        resultado.style.display = 'block';
    });
}

// =============================================
// TEMPLATES
// =============================================
function novoTemplate() {
    openModal({
        title: 'Novo Template',
        body: `
            <form id="formTemplate">
                <div class="form-group">
                    <label class="form-label required">Nome</label>
                    <input type="text" id="tplNome" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">Mensagem</label>
                    <textarea id="tplMensagem" class="form-control" rows="5" required
                              placeholder="Use variáveis: {{nome}}, {{evento}}, {{data}}, {{hora}}"></textarea>
                </div>
                <div class="form-check">
                    <input type="checkbox" id="tplAtivo" class="form-check-input" checked>
                    <label for="tplAtivo" class="form-check-label">Ativo</label>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="salvarTemplate()">Salvar</button>
        `
    });
}

function salvarTemplate(id = null) {
    const data = {
        action: id ? 'update_template' : 'create_template',
        id: id,
        nome: document.getElementById('tplNome').value,
        mensagem: document.getElementById('tplMensagem').value,
        ativo: document.getElementById('tplAtivo').checked ? 1 : 0
    };
    
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Template salvo!', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao salvar', 'error');
        }
    });
}

function editarTemplate(id) {
    fetch(API_URL + '?action=get_template&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const tpl = data.data;
                openModal({
                    title: 'Editar Template',
                    body: `
                        <form id="formTemplate">
                            <div class="form-group">
                                <label class="form-label required">Nome</label>
                                <input type="text" id="tplNome" class="form-control" value="${tpl.nome}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Mensagem</label>
                                <textarea id="tplMensagem" class="form-control" rows="5" required>${tpl.mensagem}</textarea>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="tplAtivo" class="form-check-input" ${tpl.ativo ? 'checked' : ''}>
                                <label for="tplAtivo" class="form-check-label">Ativo</label>
                            </div>
                        </form>
                    `,
                    footer: `
                        <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                        <button class="btn btn-primary" onclick="salvarTemplate(${id})">Salvar</button>
                    `
                });
            }
        });
}

function excluirTemplate(id) {
    confirmAction('Deseja excluir este template?', () => {
        fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ action: 'delete_template', id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Template excluído', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        });
    });
}

// =============================================
// INICIALIZAÇÃO
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    if (currentProvider === 'evolution') {
        verificarStatusEvolution();
    }
});
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
