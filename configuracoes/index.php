<?php
/**
 * Configurações Gerais
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('configuracoes', 'view');

$pageTitle = 'Configurações';
$db = Database::getInstance();

// Buscar configurações atuais
$settings = [];
$settingsRows = $db->fetchAll("SELECT * FROM app_settings");
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Ministérios
$ministerios = $db->fetchAll("SELECT * FROM ministerios ORDER BY nome");

// Unidades
$unidades = $db->fetchAll("SELECT * FROM unidades ORDER BY nome");

// Motivos de Justificativas
$justificativas = $db->fetchAll("SELECT * FROM justification_reasons ORDER BY nome");

$errors = [];
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('configuracoes', 'manage_settings')) {
    if (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'geral') {
            $configs = [
                'igreja_nome' => trim($_POST['igreja_nome'] ?? ''),
                'sessao_timeout_min' => intval($_POST['sessao_timeout_min'] ?? 120)
            ];

            foreach ($configs as $key => $value) {
                $existing = $db->fetch("SELECT id FROM app_settings WHERE setting_key = ?", [$key]);
                if ($existing) {
                    $db->update('app_settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
                } else {
                    $db->insert('app_settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_type' => 'string']);
                }
            }

            // Upload de logo
            if (!empty($_FILES['logo']['name'])) {
                $logoUrl = uploadFile($_FILES['logo'], 'config');
                if ($logoUrl) {
                    $existing = $db->fetch("SELECT id FROM app_settings WHERE setting_key = 'igreja_logo_url'");
                    if ($existing) {
                        $db->update('app_settings', ['setting_value' => $logoUrl], "setting_key = 'igreja_logo_url'");
                    } else {
                        $db->insert('app_settings', ['setting_key' => 'igreja_logo_url', 'setting_value' => $logoUrl]);
                    }
                }
            }

            Audit::log('settings_updated', 'app_settings', null, $configs);
            $success = 'Configurações salvas com sucesso!';
            
            // Recarregar configurações
            $settingsRows = $db->fetchAll("SELECT * FROM app_settings");
            foreach ($settingsRows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

        } elseif ($action === 'ministerio_criar') {
            $nome = trim($_POST['ministerio_nome'] ?? '');
            $descricao = trim($_POST['ministerio_descricao'] ?? '');
            
            if (empty($nome)) {
                $errors[] = 'Nome do ministério é obrigatório.';
            } else {
                $db->insert('ministerios', [
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'ativo' => 1
                ]);
                $success = 'Ministério criado!';
                $ministerios = $db->fetchAll("SELECT * FROM ministerios ORDER BY nome");
                redirect('/configuracoes?tab=tab-ministerios');
            }

        } elseif ($action === 'ministerio_excluir') {
            $id = intval($_POST['ministerio_id'] ?? 0);
            $db->delete('ministerios', 'id = ?', [$id]);
            $success = 'Ministério excluído!';
            $ministerios = $db->fetchAll("SELECT * FROM ministerios ORDER BY nome");
            redirect('/configuracoes?tab=tab-ministerios');

        } elseif ($action === 'unidade_criar') {
            $nome = trim($_POST['unidade_nome'] ?? '');
            $descricao = trim($_POST['unidade_descricao'] ?? '');
            
            if (empty($nome)) {
                $errors[] = 'Nome da unidade é obrigatório.';
            } else {
                $db->insert('unidades', [
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'ativo' => 1
                ]);
                $success = 'Unidade criada!';
                $unidades = $db->fetchAll("SELECT * FROM unidades ORDER BY nome");
                redirect('/configuracoes?tab=tab-unidades');
            }

        } elseif ($action === 'unidade_excluir') {
            $id = intval($_POST['unidade_id'] ?? 0);
            $db->delete('unidades', 'id = ?', [$id]);
            $success = 'Unidade excluída!';
            $unidades = $db->fetchAll("SELECT * FROM unidades ORDER BY nome");
            redirect('/configuracoes?tab=tab-unidades');

        } elseif ($action === 'evolution_api') {
            $apiUrl = trim($_POST['evolution_api_url'] ?? '');
            $apiKey = trim($_POST['evolution_api_key'] ?? '');
            
            if (empty($apiUrl) || empty($apiKey)) {
                $errors[] = 'URL e API Key da Evolution são obrigatórios.';
            } else {
                // Salvar URL
                $existing = $db->fetch("SELECT id FROM app_settings WHERE setting_key = 'evolution_api_url'");
                if ($existing) {
                    $db->update('app_settings', ['setting_value' => $apiUrl], "setting_key = 'evolution_api_url'");
                } else {
                    $db->insert('app_settings', ['setting_key' => 'evolution_api_url', 'setting_value' => $apiUrl, 'setting_type' => 'string']);
                }
                
                // Salvar API Key
                $existing = $db->fetch("SELECT id FROM app_settings WHERE setting_key = 'evolution_api_key'");
                if ($existing) {
                    $db->update('app_settings', ['setting_value' => $apiKey], "setting_key = 'evolution_api_key'");
                } else {
                    $db->insert('app_settings', ['setting_key' => 'evolution_api_key', 'setting_value' => $apiKey, 'setting_type' => 'string']);
                }
                
                Audit::log('settings_updated', 'app_settings', null, ['evolution_api_configured' => true]);
                $success = 'Configurações da Evolution API salvas com sucesso!';
                
                // Recarregar configurações
                $settingsRows = $db->fetchAll("SELECT * FROM app_settings");
                foreach ($settingsRows as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                
                redirect('/configuracoes?tab=tab-evolution');
            }

        } elseif ($action === 'justificativa_criar') {
            $nome = trim($_POST['justificativa_nome'] ?? '');
            $descricao = trim($_POST['justificativa_descricao'] ?? '');
            
            if (empty($nome)) {
                $errors[] = 'Nome do motivo é obrigatório.';
            } else {
                $db->insert('justification_reasons', [
                    'nome' => $nome,
                    'descricao' => $descricao
                ]);
                $success = 'Motivo de justificativa criado!';
                $justificativas = $db->fetchAll("SELECT * FROM justification_reasons ORDER BY nome");
                redirect('/configuracoes?tab=tab-justificativas');
            }

        } elseif ($action === 'justificativa_excluir') {
            $id = intval($_POST['justificativa_id'] ?? 0);
            $db->delete('justification_reasons', 'id = ?', [$id]);
            $success = 'Motivo de justificativa excluído!';
            $justificativas = $db->fetchAll("SELECT * FROM justification_reasons ORDER BY nome");
            redirect('/configuracoes?tab=tab-justificativas');
        }
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Configurações</h1>
        <p class="page-subtitle">Ajustes gerais do sistema</p>
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

<div class="tabs mb-3">
    <button class="tab-link active" data-tab="tab-geral">Geral</button>
    <button class="tab-link" data-tab="tab-evolution">Evolution API</button>
    <button class="tab-link" data-tab="tab-unidades">Unidades</button>
    <button class="tab-link" data-tab="tab-ministerios">Ministérios</button>
    <button class="tab-link" data-tab="tab-justificativas">Justificativas</button>
    <button class="tab-link" data-tab="tab-auditoria">Auditoria</button>
</div>

<!-- Tab Geral -->
<div id="tab-geral" class="tab-content active">
    <form method="POST" enctype="multipart/form-data" class="card">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="geral">
        
        <div class="card-header">
            <h3 class="card-title">Configurações Gerais</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Nome da Igreja</label>
                    <input type="text" name="igreja_nome" class="form-control"
                           value="<?= sanitize($settings['igreja_nome'] ?? 'Igreja Conectada') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Logo da Igreja</label>
                <input type="file" name="logo" class="form-control" accept="image/*">
                <?php if (!empty($settings['igreja_logo_url'])): ?>
                <div class="mt-1">
                    <img src="<?= url($settings['igreja_logo_url']) ?>" alt="Logo" style="max-height: 60px;">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Tempo de Sessão (minutos)</label>
                <input type="number" name="sessao_timeout_min" class="form-control" min="15" max="480"
                       value="<?= $settings['sessao_timeout_min'] ?? 120 ?>">
                <small class="form-text">Tempo de inatividade antes de expirar a sessão</small>
            </div>
        </div>
        
        <?php if (can('configuracoes', 'manage_settings')): ?>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i> Salvar Configurações
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Tab Evolution API -->
<div id="tab-evolution" class="tab-content">
    <form method="POST" class="card">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="evolution_api">
        
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="server"></i>
                Configuração da Evolution API
            </h3>
        </div>
        
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <div class="alert-content">
                    <i data-lucide="info"></i>
                    <div>
                        <strong>Configuração para Administradores</strong>
                        <p class="mb-0">Configure aqui a URL e API Key da sua Evolution API. Após configurar, os usuários poderão criar instâncias WhatsApp diretamente no sistema sem precisar de credenciais.</p>
                    </div>
                </div>
            </div>
            
            <?php if (can('configuracoes', 'manage_settings')): ?>
            <div class="form-group">
                <label class="form-label required">URL da Evolution API</label>
                <input type="url" name="evolution_api_url" class="form-control" 
                       value="<?= sanitize($settings['evolution_api_url'] ?? '') ?>"
                       placeholder="https://seu-servidor.com:8080"
                       required>
                <small class="text-muted">
                    URL completa da sua Evolution API (incluindo porta se necessário).<br>
                    Exemplo: https://evolution.seudominio.com.br ou http://seu-ip:8080
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label required">API Key Global</label>
                <input type="text" name="evolution_api_key" class="form-control" 
                       value="<?= sanitize($settings['evolution_api_key'] ?? '') ?>"
                       placeholder="SUA_API_KEY_GLOBAL"
                       required>
                <small class="text-muted">
                    API Key global configurada na sua Evolution API (variável AUTHENTICATION_API_KEY)
                </small>
            </div>
            
            <div style="padding: 16px; background: var(--gray-50); border-radius: var(--border-radius); margin-bottom: 16px;">
                <h4 style="margin: 0 0 12px 0; font-size: 0.95rem;">
                    <i data-lucide="help-circle" style="width: 16px; height: 16px;"></i>
                    Como encontrar essas informações?
                </h4>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem;">
                    <li><strong>URL:</strong> Endereço onde sua Evolution API está rodando</li>
                    <li><strong>API Key:</strong> Valor definido na variável de ambiente <code>AUTHENTICATION_API_KEY</code> ao instalar a Evolution API</li>
                    <li>Se você instalou via Docker, verifique o comando <code>docker run</code> ou arquivo <code>docker-compose.yml</code></li>
                </ul>
            </div>
            
            <div style="padding: 16px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: var(--border-radius); margin-bottom: 16px;">
                <strong style="color: #92400e;">⚠️ Importante:</strong>
                <p style="margin: 8px 0 0 0; color: #92400e; font-size: 0.9rem;">
                    Essas credenciais são sensíveis e permitem criar instâncias WhatsApp. Mantenha-as seguras e não compartilhe.
                </p>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i> Salvar Configurações da Evolution API
            </button>
            <?php else: ?>
            <p class="text-muted">Você não tem permissão para editar as configurações.</p>
            <?php endif; ?>
        </div>
    </form>
    
    <?php if (!empty($settings['evolution_api_url'])): ?>
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="check-circle" style="color: var(--success);"></i>
                Status da Configuração
            </h3>
        </div>
        <div class="card-body">
            <div class="d-flex align-center mb-2" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                <i data-lucide="server" style="color: var(--success); flex-shrink: 0;"></i>
                <div style="flex: 1;">
                    <strong>URL Configurada</strong>
                    <br><code style="font-size: 0.85rem;"><?= sanitize($settings['evolution_api_url']) ?></code>
                </div>
            </div>
            <div class="d-flex align-center" style="gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                <i data-lucide="key" style="color: var(--success); flex-shrink: 0;"></i>
                <div style="flex: 1;">
                    <strong>API Key Configurada</strong>
                    <br><code style="font-size: 0.85rem;"><?= str_repeat('•', 20) . substr($settings['evolution_api_key'], -4) ?></code>
                </div>
            </div>
            <div class="mt-3" style="padding: 12px; background: #dcfce7; border-radius: var(--border-radius);">
                <i data-lucide="check-circle" style="color: #16a34a; width: 16px; height: 16px;"></i>
                <strong style="color: #16a34a;">Evolution API configurada!</strong>
                <p style="margin: 4px 0 0 0; color: #166534; font-size: 0.9rem;">
                    Os usuários já podem criar instâncias WhatsApp em <a href="<?= url('/integracoes') ?>">Integrações</a>.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tab Unidades -->
<div id="tab-unidades" class="tab-content">
    <div class="grid grid-2" style="gap: 24px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Unidades Cadastradas</h3>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($unidades)): ?>
                <p class="text-muted">Nenhuma unidade cadastrada.</p>
                <?php else: ?>
                    <?php foreach ($unidades as $unidade): ?>
                    <div class="d-flex justify-between align-center mb-2" style="padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <div>
                            <strong><?= sanitize($unidade['nome']) ?></strong>
                            <?= $unidade['ativo'] ? '' : '<span class="badge badge-secondary">Inativa</span>' ?>
                            <?php if ($unidade['descricao']): ?>
                            <br><small class="text-muted"><?= sanitize($unidade['descricao']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if (can('configuracoes', 'manage_settings')): ?>
                        <form method="POST" style="display: inline;" id="form-excluir-unidade-<?= $unidade['id'] ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="unidade_excluir">
                            <input type="hidden" name="unidade_id" value="<?= $unidade['id'] ?>">
                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" 
                                    onclick="excluirUnidade(<?= $unidade['id'] ?>, '<?= sanitize($unidade['nome']) ?>')">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (can('configuracoes', 'manage_settings')): ?>
        <form method="POST" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="unidade_criar">
            
            <div class="card-header">
                <h3 class="card-title">Nova Unidade</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label required">Nome</label>
                    <input type="text" name="unidade_nome" class="form-control" placeholder="Ex: Sede, Congregação Centro..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="unidade_descricao" class="form-control" rows="2" placeholder="Descrição opcional da unidade"></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="plus"></i> Criar Unidade
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Justificativas -->
<div id="tab-justificativas" class="tab-content">
    <div class="grid grid-2" style="gap: 24px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Motivos de Justificativas</h3>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($justificativas)): ?>
                <p class="text-muted">Nenhum motivo cadastrado.</p>
                <?php else: ?>
                    <?php foreach ($justificativas as $just): ?>
                    <div class="d-flex justify-between align-center mb-2" style="padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <div>
                            <strong><?= sanitize($just['nome']) ?></strong>
                            <?php if ($just['descricao']): ?>
                            <br><small class="text-muted"><?= sanitize($just['descricao']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if (can('configuracoes', 'manage_settings')): ?>
                        <form method="POST" style="display: inline;" id="form-excluir-justificativa-<?= $just['id'] ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="justificativa_excluir">
                            <input type="hidden" name="justificativa_id" value="<?= $just['id'] ?>">
                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" 
                                    onclick="excluirJustificativa(<?= $just['id'] ?>, '<?= sanitize($just['nome']) ?>')">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (can('configuracoes', 'manage_settings')): ?>
        <form method="POST" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="justificativa_criar">
            
            <div class="card-header">
                <h3 class="card-title">Novo Motivo de Justificativa</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label required">Nome</label>
                    <input type="text" name="justificativa_nome" class="form-control" placeholder="Ex: Doença, Viagem, Trabalho..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="justificativa_descricao" class="form-control" rows="2" placeholder="Descrição opcional do motivo"></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="plus"></i> Criar Motivo
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Ministérios -->
<div id="tab-ministerios" class="tab-content">
    <div class="grid grid-2" style="gap: 24px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ministérios Cadastrados</h3>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($ministerios)): ?>
                <p class="text-muted">Nenhum ministério cadastrado.</p>
                <?php else: ?>
                    <?php foreach ($ministerios as $min): ?>
                    <div class="d-flex justify-between align-center mb-2" style="padding: 12px; background: var(--gray-50); border-radius: var(--border-radius);">
                        <div>
                            <strong><?= sanitize($min['nome']) ?></strong>
                            <?= $min['ativo'] ? '' : '<span class="badge badge-secondary">Inativo</span>' ?>
                            <?php if ($min['descricao']): ?>
                            <br><small class="text-muted"><?= sanitize($min['descricao']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if (can('configuracoes', 'manage_settings')): ?>
                        <form method="POST" style="display: inline;" id="form-excluir-ministerio-<?= $min['id'] ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="ministerio_excluir">
                            <input type="hidden" name="ministerio_id" value="<?= $min['id'] ?>">
                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" 
                                    onclick="excluirMinisterio(<?= $min['id'] ?>, '<?= sanitize($min['nome']) ?>')">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (can('configuracoes', 'manage_settings')): ?>
        <form method="POST" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="ministerio_criar">
            
            <div class="card-header">
                <h3 class="card-title">Novo Ministério</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label required">Nome</label>
                    <input type="text" name="ministerio_nome" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="ministerio_descricao" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="plus"></i> Criar Ministério
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Integrações -->
<div id="tab-integracoes" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Integrações WhatsApp e Email</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Configure as integrações de WhatsApp e Email para envio automático de mensagens.</p>
            
            <div class="d-flex gap-2">
                <a href="<?= url('/integracoes') ?>" class="btn btn-primary">
                    <i data-lucide="link"></i> Gerenciar Integrações
                </a>
            </div>

            <hr style="margin: 24px 0;">

            <h4 style="font-size: 0.95rem; margin-bottom: 16px;">Recursos Disponíveis:</h4>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 8px 0; border-bottom: 1px solid var(--gray-100);">
                    <i data-lucide="check" style="width: 16px; height: 16px; color: var(--success);"></i>
                    <strong>WhatsApp API</strong> - Envio automático de mensagens
                </li>
                <li style="padding: 8px 0; border-bottom: 1px solid var(--gray-100);">
                    <i data-lucide="check" style="width: 16px; height: 16px; color: var(--success);"></i>
                    <strong>Templates de Mensagens</strong> - Personalize suas mensagens
                </li>
                <li style="padding: 8px 0; border-bottom: 1px solid var(--gray-100);">
                    <i data-lucide="check" style="width: 16px; height: 16px; color: var(--success);"></i>
                    <strong>Fila de Mensagens</strong> - Controle de envios
                </li>
                <li style="padding: 8px 0;">
                    <i data-lucide="check" style="width: 16px; height: 16px; color: var(--success);"></i>
                    <strong>Logs e Estatísticas</strong> - Acompanhe os envios
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Tab Auditoria -->
<div id="tab-auditoria" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Logs de Auditoria</h3>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Entidade</th>
                        <th>IP</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = Audit::getLogs([], 30);
                    foreach ($logs as $log):
                    ?>
                    <tr>
                        <td><?= sanitize($log['actor_name'] ?? 'Sistema') ?></td>
                        <td><span class="badge badge-secondary"><?= Audit::translateAction($log['action']) ?></span></td>
                        <td><?= Audit::translateEntityType($log['entity_type']) ?> #<?= $log['entity_id'] ?? '-' ?></td>
                        <td><small><?= $log['ip_address'] ?></small></td>
                        <td><?= formatDateTime($log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function excluirMinisterio(id, nome) {
    showConfirm({
        title: 'Excluir Ministério',
        message: `Tem certeza que deseja excluir o ministério "${nome}"?`,
        type: 'danger',
        icon: 'trash-2',
        confirmText: 'Excluir',
        onConfirm: () => {
            document.getElementById('form-excluir-ministerio-' + id).submit();
        }
    });
}

function excluirUnidade(id, nome) {
    showConfirm({
        title: 'Excluir Unidade',
        message: `Tem certeza que deseja excluir a unidade "${nome}"?`,
        onConfirm: () => {
            document.getElementById('form-excluir-unidade-' + id).submit();
        }
    });
}

function excluirJustificativa(id, nome) {
    showConfirm({
        title: 'Excluir Motivo de Justificativa',
        message: `Tem certeza que deseja excluir o motivo "${nome}"?`,
        type: 'danger',
        icon: 'trash-2',
        confirmText: 'Excluir',
        onConfirm: () => {
            document.getElementById('form-excluir-justificativa-' + id).submit();
        }
    });
}

// Restaurar aba ativa após reload
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    
    if (activeTab) {
        // Desativar todas as abas
        document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Ativar a aba correta
        const tabButton = document.querySelector(`[data-tab="${activeTab}"]`);
        const tabContent = document.getElementById(activeTab);
        
        if (tabButton && tabContent) {
            tabButton.classList.add('active');
            tabContent.classList.add('active');
        }
    }
});
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
