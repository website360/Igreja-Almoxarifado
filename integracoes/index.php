<?php
/**
 * Integrações - WhatsApp e Email
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
        <h1 class="page-title">Integrações</h1>
        <p class="page-subtitle">Configurações de WhatsApp e Email</p>
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

<div class="grid grid-2" style="gap: 24px;">
    <!-- WhatsApp -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="message-circle" style="color: #25D366;"></i>
                WhatsApp (Z-API)
            </h3>
            <?php if ($whatsappIntegration && $whatsappIntegration['ativo']): ?>
            <span class="badge badge-success">Conectado</span>
            <?php else: ?>
            <span class="badge badge-secondary">Desconectado</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (can('integracoes', 'manage_settings')): ?>
            <form method="POST" action="<?= url('/integracoes/api.php') ?>" id="formWhatsApp">
                <input type="hidden" name="action" value="save_whatsapp">
                <?= csrfField() ?>
                
                <div class="form-group">
                    <label class="form-label">Instance ID</label>
                    <input type="text" name="instance_id" class="form-control"
                           value="<?= sanitize($whatsappIntegration['instance_id'] ?? '') ?>"
                           placeholder="Seu Instance ID do Z-API">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Token</label>
                    <input type="password" name="token" class="form-control"
                           value="<?= $whatsappIntegration['token'] ?? '' ?>"
                           placeholder="Seu Token do Z-API">
                </div>
                
                <div class="form-group">
                    <label class="form-label">API Key (Client Token)</label>
                    <input type="password" name="api_key" class="form-control"
                           value="<?= $whatsappIntegration['api_key'] ?? '' ?>"
                           placeholder="Ex: A1B2C3D4E5F6...">
                    <small class="text-muted">
                        <strong>Atenção:</strong> Cole apenas o Client Token (Security Token) da Z-API, 
                        NÃO cole a URL completa. Exemplo: A1B2C3D4E5F6G7H8I9J0
                    </small>
                </div>
                
                <div class="form-check mb-2">
                    <input type="checkbox" name="ativo" id="whatsappAtivo" class="form-check-input"
                           <?= ($whatsappIntegration['ativo'] ?? 0) ? 'checked' : '' ?>>
                    <label for="whatsappAtivo" class="form-check-label">Integração ativa</label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i> Salvar Configurações
                </button>
            </form>
            <?php else: ?>
            <p class="text-muted">Você não tem permissão para editar as configurações.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Templates -->
    <div class="card">
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
        <p class="text-muted mb-3">Envie uma mensagem de teste para verificar se a integração está funcionando corretamente.</p>
        
        <form id="formTesteEnvio" onsubmit="enviarMensagemTeste(event)">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Número de Telefone (com DDD)</label>
                    <input type="text" id="testPhone" class="form-control" 
                           placeholder="Ex: 11999999999" required
                           pattern="[0-9]{10,11}"
                           title="Digite apenas números (DDD + telefone)">
                    <small class="text-muted">Digite apenas números, sem espaços ou caracteres especiais</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Mensagem</label>
                    <textarea id="testMessage" class="form-control" rows="4" required
                              placeholder="Digite a mensagem de teste...">Olá! Esta é uma mensagem de teste do sistema da igreja. Se você recebeu esta mensagem, a integração está funcionando corretamente! 🙏</textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" id="btnEnviarTeste">
                <i data-lucide="send"></i> Enviar Mensagem de Teste
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
        <a href="<?= url('/integracoes/logs.php') ?>" class="text-primary text-sm">Ver todos →</a>
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

<script>
function enviarMensagemTeste(event) {
    event.preventDefault();
    
    const phone = document.getElementById('testPhone').value;
    const message = document.getElementById('testMessage').value;
    const btnEnviar = document.getElementById('btnEnviarTeste');
    const resultado = document.getElementById('testeResultado');
    
    // Validar telefone
    if (!/^[0-9]{10,11}$/.test(phone)) {
        showToast('Número de telefone inválido. Use apenas números (DDD + telefone)', 'error');
        return;
    }
    
    // Desabilitar botão
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<i data-lucide="loader"></i> Enviando...';
    lucide.createIcons();
    
    // Limpar resultado anterior
    resultado.style.display = 'none';
    
    fetch('<?= url('/integracoes/api.php') ?>', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken 
        },
        body: JSON.stringify({
            action: 'test_whatsapp',
            phone: phone,
            message: message
        })
    })
    .then(r => r.json())
    .then(data => {
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = '<i data-lucide="send"></i> Enviar Mensagem de Teste';
        lucide.createIcons();
        
        if (data.success) {
            resultado.innerHTML = `
                <div class="alert alert-success">
                    <div class="alert-content">
                        <i data-lucide="check-circle"></i>
                        <div>
                            <strong>Mensagem enviada com sucesso!</strong>
                            <p class="mb-0">A mensagem foi enviada para ${phone}. Verifique o WhatsApp do destinatário.</p>
                            ${data.message_id ? `<small class="text-muted">ID da mensagem: ${data.message_id}</small>` : ''}
                        </div>
                    </div>
                </div>
            `;
            showToast('Mensagem de teste enviada!', 'success');
        } else {
            resultado.innerHTML = `
                <div class="alert alert-danger">
                    <div class="alert-content">
                        <i data-lucide="alert-circle"></i>
                        <div>
                            <strong>Erro ao enviar mensagem</strong>
                            <p class="mb-0">${data.message || 'Erro desconhecido. Verifique as configurações da API.'}</p>
                            ${data.error ? `<small class="text-muted">Detalhes: ${data.error}</small>` : ''}
                        </div>
                    </div>
                </div>
            `;
            showToast(data.message || 'Erro ao enviar', 'error');
        }
        
        resultado.style.display = 'block';
        lucide.createIcons();
    })
    .catch(error => {
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = '<i data-lucide="send"></i> Enviar Mensagem de Teste';
        lucide.createIcons();
        
        resultado.innerHTML = `
            <div class="alert alert-danger">
                <div class="alert-content">
                    <i data-lucide="alert-circle"></i>
                    <div>
                        <strong>Erro de conexão</strong>
                        <p class="mb-0">Não foi possível conectar ao servidor. Tente novamente.</p>
                    </div>
                </div>
            </div>
        `;
        resultado.style.display = 'block';
        lucide.createIcons();
        showToast('Erro de conexão', 'error');
    });
}

function testarWhatsApp() {
    const telefone = prompt('Digite o número para teste (com DDD):');
    if (!telefone) return;
    
    fetch('<?= url('/integracoes/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({
            action: 'test_whatsapp',
            phone: telefone
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Mensagem de teste enviada!', 'success');
        } else {
            showToast(data.message || 'Erro ao enviar', 'error');
        }
    });
}

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
    
    fetch('<?= url('/integracoes/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
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
    fetch('<?= url('/integracoes/api.php') ?>?action=get_template&id=' + id)
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
        fetch('<?= url('/integracoes/api.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
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
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
