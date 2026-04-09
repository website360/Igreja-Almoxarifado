<?php
/**
 * Criar/Editar Pessoa
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

$id = intval($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) {
    requirePermission('pessoas', 'edit');
    $pageTitle = 'Editar Pessoa';
} else {
    requirePermission('pessoas', 'create');
    $pageTitle = 'Nova Pessoa';
}

$db = Database::getInstance();

$pessoa = null;
if ($isEdit) {
    $pessoa = $db->fetch(
        "SELECT u.*, m.nome as ministerio_nome 
         FROM users u 
         LEFT JOIN ministerios m ON u.ministerio_id = m.id 
         WHERE u.id = ?", 
        [$id]
    );
    if (!$pessoa) {
        setFlash('error', 'Pessoa não encontrada.');
        redirect('/pessoas');
    }
}

$ministerios = $db->fetchAll("SELECT id, nome FROM ministerios WHERE ativo = 1 ORDER BY nome");
$unidades = $db->fetchAll("SELECT id, nome FROM unidades WHERE ativo = 1 ORDER BY nome");
$roles = $db->fetchAll("SELECT id, name, display_name FROM roles ORDER BY id");

// Papéis atuais do usuário
$userRoles = [];
if ($isEdit) {
    $userRolesData = $db->fetchAll("SELECT role_id FROM user_roles WHERE user_id = ?", [$id]);
    $userRoles = array_column($userRolesData, 'role_id');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se o POST está vazio (pode indicar upload muito grande)
    if (empty($_POST) && empty($_FILES)) {
        $errors[] = 'Erro no envio do formulário. O arquivo pode ser muito grande (máximo 40MB).';
    } elseif (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido. Por favor, recarregue a página e tente novamente.';
    } else {
        $data = [
            'nome' => trim($_POST['nome'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefone_whatsapp' => cleanPhone($_POST['telefone_whatsapp'] ?? ''),
            'cpf' => cleanPhone($_POST['cpf'] ?? ''),
            'cargo' => $_POST['cargo'] ?? 'membro',
            'ministerio_id' => !empty($_POST['ministerio_id']) ? intval($_POST['ministerio_id']) : null,
            'unidade_id' => !empty($_POST['unidade_id']) ? intval($_POST['unidade_id']) : null,
            'status' => $_POST['status'] ?? 'ativo',
            'data_entrada' => $_POST['data_entrada'] ?: null,
            'data_nascimento' => $_POST['data_nascimento'] ?: null,
            'data_batismo' => $_POST['data_batismo'] ?: null,
            'cep' => trim($_POST['cep'] ?? ''),
            'logradouro' => trim($_POST['logradouro'] ?? ''),
            'numero' => trim($_POST['numero'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'bairro' => trim($_POST['bairro'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'estado' => trim($_POST['estado'] ?? ''),
            'aceita_whatsapp' => isset($_POST['aceita_whatsapp']) ? 1 : 0,
            'aceita_email' => isset($_POST['aceita_email']) ? 1 : 0
        ];

        // Validações
        if (empty($data['nome'])) {
            $errors[] = 'O nome é obrigatório.';
        }
        if (empty($data['email'])) {
            $errors[] = 'O email é obrigatório.';
        } elseif (!isValidEmail($data['email'])) {
            $errors[] = 'Email inválido.';
        }

        // Verificar email duplicado
        $emailCheck = $db->fetch(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$data['email'], $id]
        );
        if ($emailCheck) {
            $errors[] = 'Este email já está cadastrado.';
        }

        // Validar CPF se informado
        if (!empty($data['cpf']) && !isValidCpf($data['cpf'])) {
            $errors[] = 'CPF inválido.';
        }

        // Senha para novo usuário
        if (!$isEdit) {
            $password = $_POST['password'] ?? '';
            if (empty($password)) {
                $errors[] = 'A senha é obrigatória para novos usuários.';
            } elseif (strlen($password) < 6) {
                $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
            } else {
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
        } elseif (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
            } else {
                $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
        }

        // Upload de foto
        if (!empty($_FILES['foto']['name'])) {
            $uploadError = null;
            $fotoUrl = uploadFileWithError($_FILES['foto'], 'pessoas', $uploadError);
            if ($fotoUrl) {
                $data['foto_url'] = $fotoUrl;
            } elseif ($uploadError) {
                $errors[] = $uploadError;
            }
        }

        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $db->update('users', $data, 'id = :id', ['id' => $id]);
                    Audit::log('update', 'users', $id);
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $id = $db->insert('users', $data);
                    Audit::log('create', 'users', $id);
                }

                // Sincronizar papéis
                if (can('usuarios', 'manage_roles')) {
                    $selectedRoles = $_POST['roles'] ?? [];
                    $auth = new Auth();
                    $auth->syncRoles($id, $selectedRoles);
                }

                setFlash('success', $isEdit ? 'Pessoa atualizada com sucesso!' : 'Pessoa cadastrada com sucesso! Agora você pode adicionar fotos e documentos.');
                redirect('/pessoas/criar.php?id=' . $id);
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
        <p class="page-subtitle"><?= $isEdit ? 'Atualize as informações' : 'Preencha os dados da nova pessoa' ?></p>
    </div>
    <a href="<?= url('/pessoas') ?>" class="btn btn-secondary">
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

<?php if ($isEdit): ?>
<div class="tabs-nav" style="margin-bottom: 24px;">
    <a href="javascript:void(0)" class="tab-item active" data-tab="dados">
        <i data-lucide="user"></i> Dados Cadastrais
    </a>
    <a href="javascript:void(0)" class="tab-item" data-tab="documentos">
        <i data-lucide="file-text"></i> Documentos
    </a>
</div>
<?php endif; ?>

<div class="tab-content" id="tab-dados" style="display: block;">
<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    
    <div class="pessoa-edit-layout">
        <!-- Sidebar com foto e status -->
        <div class="pessoa-sidebar">
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 24px;">
                    <div class="pessoa-foto-wrapper">
                        <?php if ($pessoa && $pessoa['foto_url']): ?>
                        <img src="<?= url($pessoa['foto_url']) ?>" alt="Foto" class="pessoa-foto-grande">
                        <?php else: ?>
                        <div class="pessoa-foto-placeholder">
                            <i data-lucide="user" style="width: 48px; height: 48px;"></i>
                        </div>
                        <?php endif; ?>
                        <button type="button" class="pessoa-foto-edit" title="Alterar foto" onclick="mostrarOpcoesFoto(event)">
                            <i data-lucide="camera"></i>
                        </button>
                        <input type="file" name="foto" id="inputFotoGaleria" accept="image/*" style="display: none;" onchange="previewFoto(this)">
                        
                        <!-- Menu de opções de foto -->
                        <div class="foto-opcoes-menu" id="fotoOpcoesMenu">
                            <button type="button" class="foto-opcao" onclick="abrirCamera()">
                                <i data-lucide="camera"></i> Tirar Foto
                            </button>
                            <button type="button" class="foto-opcao" onclick="abrirGaleria()">
                                <i data-lucide="image"></i> Escolher da Galeria
                            </button>
                        </div>
                    </div>
                    <h3 style="margin: 16px 0 4px; font-size: 1.25rem;"><?= sanitize($pessoa['nome'] ?? 'Nova Pessoa') ?></h3>
                    <p style="color: var(--gray-500); margin: 0 0 16px; font-size: 0.9rem;"><?= sanitize($pessoa['email'] ?? '') ?></p>
                    
                    <?php if ($isEdit): ?>
                    <div class="pessoa-status-badge <?= ($pessoa['status'] ?? 'ativo') === 'ativo' ? 'active' : 'inactive' ?>">
                        <i data-lucide="<?= ($pessoa['status'] ?? 'ativo') === 'ativo' ? 'check-circle' : 'x-circle' ?>"></i>
                        <?= ($pessoa['status'] ?? 'ativo') === 'ativo' ? 'Ativo' : 'Inativo' ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($isEdit): ?>
                <div class="card-body" style="border-top: 1px solid var(--gray-200); padding: 16px 24px;">
                    <div class="pessoa-info-item">
                        <i data-lucide="briefcase"></i>
                        <span><?= MEMBER_POSITIONS[$pessoa['cargo'] ?? 'membro'] ?? 'Membro' ?></span>
                    </div>
                    <?php if ($pessoa['ministerio_nome'] ?? null): ?>
                    <div class="pessoa-info-item">
                        <i data-lucide="users"></i>
                        <span><?= sanitize($pessoa['ministerio_nome']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pessoa['data_entrada']): ?>
                    <div class="pessoa-info-item">
                        <i data-lucide="calendar"></i>
                        <span>Desde <?= formatDate($pessoa['data_entrada'], 'd/m/Y') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conteúdo principal -->
        <div class="pessoa-content">
            <!-- Card Dados Pessoais -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="user" style="width: 18px; height: 18px;"></i> Dados Pessoais</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label required">Nome Completo</label>
                            <input type="text" name="nome" class="form-control" required
                                   value="<?= sanitize($pessoa['nome'] ?? $_POST['nome'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Email</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= sanitize($pessoa['email'] ?? $_POST['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Telefone/WhatsApp</label>
                            <input type="text" name="telefone_whatsapp" class="form-control" data-mask="phone"
                                   value="<?= formatPhone($pessoa['telefone_whatsapp'] ?? $_POST['telefone_whatsapp'] ?? '') ?>">
                        </div>
                        
                        <?php if (can('pessoas', 'view_cpf')): ?>
                        <div class="form-group">
                            <label class="form-label">CPF</label>
                            <input type="text" name="cpf" class="form-control" data-mask="cpf"
                                   value="<?= formatCpf($pessoa['cpf'] ?? $_POST['cpf'] ?? '') ?>">
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" name="data_nascimento" class="form-control"
                                   value="<?= $pessoa['data_nascimento'] ?? $_POST['data_nascimento'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Data de Entrada</label>
                            <input type="date" name="data_entrada" class="form-control"
                                   value="<?= $pessoa['data_entrada'] ?? $_POST['data_entrada'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Data de Batismo</label>
                            <input type="date" name="data_batismo" class="form-control"
                                   value="<?= $pessoa['data_batismo'] ?? $_POST['data_batismo'] ?? '' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Endereço -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="map-pin" style="width: 18px; height: 18px;"></i> Endereço</h3>
                </div>
                <div class="card-body">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">CEP</label>
                            <input type="text" name="cep" id="cep" class="form-control" data-mask="cep"
                                   value="<?= sanitize($pessoa['cep'] ?? $_POST['cep'] ?? '') ?>"
                                   placeholder="00000-000">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" id="logradouro" class="form-control"
                                   value="<?= sanitize($pessoa['logradouro'] ?? $_POST['logradouro'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Número</label>
                            <input type="text" name="numero" id="numero" class="form-control"
                                   value="<?= sanitize($pessoa['numero'] ?? $_POST['numero'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Complemento</label>
                            <input type="text" name="complemento" class="form-control"
                                   value="<?= sanitize($pessoa['complemento'] ?? $_POST['complemento'] ?? '') ?>"
                                   placeholder="Apto, Bloco, etc.">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Bairro</label>
                            <input type="text" name="bairro" id="bairro" class="form-control"
                                   value="<?= sanitize($pessoa['bairro'] ?? $_POST['bairro'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" id="cidade" class="form-control"
                                   value="<?= sanitize($pessoa['cidade'] ?? $_POST['cidade'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <select name="estado" id="estado" class="form-control">
                                <option value="">Selecione...</option>
                                <?php
                                $estados = ['AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins'];
                                foreach ($estados as $uf => $nome):
                                ?>
                                <option value="<?= $uf ?>" <?= ($pessoa['estado'] ?? $_POST['estado'] ?? '') === $uf ? 'selected' : '' ?>><?= $nome ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Informações da Igreja -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="church" style="width: 18px; height: 18px;"></i> Informações da Igreja</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cargo</label>
                            <select name="cargo" class="form-control">
                                <?php foreach (MEMBER_POSITIONS as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($pessoa['cargo'] ?? $_POST['cargo'] ?? 'membro') === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Unidade</label>
                            <select name="unidade_id" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($unidades as $unidade): ?>
                                <option value="<?= $unidade['id'] ?>" <?= ($pessoa['unidade_id'] ?? $_POST['unidade_id'] ?? '') == $unidade['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($unidade['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Ministério</label>
                            <select name="ministerio_id" class="form-control">
                                <option value="">Nenhum</option>
                                <?php foreach ($ministerios as $min): ?>
                                <option value="<?= $min['id'] ?>" <?= ($pessoa['ministerio_id'] ?? $_POST['ministerio_id'] ?? '') == $min['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($min['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="ativo" <?= ($pessoa['status'] ?? $_POST['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inativo" <?= ($pessoa['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                    </div>

                    <?php if (can('usuarios', 'manage_roles')): ?>
                    <div class="form-group">
                        <label class="form-label">Papéis no Sistema</label>
                        <div class="roles-grid">
                            <?php foreach ($roles as $role): ?>
                            <div class="form-check">
                                <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>" 
                                       id="role_<?= $role['id'] ?>" class="form-check-input"
                                       <?= in_array($role['id'], $userRoles) ? 'checked' : '' ?>>
                                <label for="role_<?= $role['id'] ?>" class="form-check-label">
                                    <?= sanitize($role['display_name']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Acesso e Preferências -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="shield" style="width: 18px; height: 18px;"></i> Acesso e Preferências</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= $isEdit ? 'Nova Senha (deixe em branco para manter)' : 'Senha' ?></label>
                            <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>
                                   minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>
                    </div>

                    <div class="preferencias-grid">
                        <label class="preferencia-item">
                            <input type="checkbox" name="aceita_whatsapp" <?= ($pessoa['aceita_whatsapp'] ?? 1) ? 'checked' : '' ?>>
                            <div class="preferencia-content">
                                <i data-lucide="message-circle" class="preferencia-icon whatsapp"></i>
                                <div>
                                    <strong>WhatsApp</strong>
                                    <span>Aceita receber mensagens</span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="preferencia-item">
                            <input type="checkbox" name="aceita_email" <?= ($pessoa['aceita_email'] ?? 1) ? 'checked' : '' ?>>
                            <div class="preferencia-content">
                                <i data-lucide="mail" class="preferencia-icon email"></i>
                                <div>
                                    <strong>Email</strong>
                                    <span>Aceita receber emails</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="form-actions">
                <a href="<?= url('/pessoas') ?>" class="btn btn-secondary btn-lg">
                    <i data-lucide="x"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i data-lucide="save"></i>
                    <?= $isEdit ? 'Salvar Alterações' : 'Cadastrar Pessoa' ?>
                </button>
            </div>
        </div>
    </div>
</form>
</div>

<?php if ($isEdit): ?>
<div class="tab-content" id="tab-documentos" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Documentos Anexados</h3>
            <button type="button" class="btn btn-primary" id="btn-abrir-modal">
                <i data-lucide="plus"></i> Anexar Documento
            </button>
        </div>
        <div class="card-body">
            <div id="documentos-list">
                <div class="loading-placeholder">
                    <i data-lucide="loader" class="spin"></i> Carregando documentos...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Anexar Documento -->
<div class="modal-backdrop" id="modal-backdrop"></div>
<div class="modal" id="modal-documento">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Anexar Documento</h3>
            <button type="button" class="modal-close" id="btn-fechar-modal">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="form-documento" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Tipo de Documento</label>
                    <select name="tipo_documento" id="tipo_documento" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="CPF">CPF</option>
                        <option value="RG">RG</option>
                        <option value="CNH">CNH</option>
                        <option value="Certidão de Nascimento">Certidão de Nascimento</option>
                        <option value="Certidão de Casamento">Certidão de Casamento</option>
                        <option value="Comprovante de Residência">Comprovante de Residência</option>
                        <option value="Comprovante de Batismo">Comprovante de Batismo</option>
                        <option value="Carta de Recomendação">Carta de Recomendação</option>
                        <option value="Declaração">Declaração</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição (opcional)</label>
                    <input type="text" name="descricao" id="doc_descricao" class="form-control" 
                           placeholder="Ex: Frente e verso, Atualizado 2024...">
                </div>
                <div class="form-group">
                    <label class="form-label required">Arquivo</label>
                    <input type="file" name="arquivo" id="doc_arquivo" class="form-control" required
                           accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                    <small class="form-text">Formatos aceitos: JPG, PNG, GIF, PDF, DOC, DOCX. Máximo 10MB.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-cancelar-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btn-upload-doc">
                    <i data-lucide="upload"></i> Anexar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal da Câmera -->
<div class="modal-backdrop" id="cameraBackdrop"></div>
<div class="modal" id="cameraModal">
    <div class="modal-content" style="max-width: 500px; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div class="modal-header">
            <h3><i data-lucide="camera"></i> Tirar Foto</h3>
            <button type="button" class="modal-close" onclick="fecharCamera()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 20px;">
            <video id="cameraVideo" autoplay playsinline style="width: 100%; max-width: 400px; border-radius: 12px; background: #000;"></video>
            <canvas id="cameraCanvas" style="display: none;"></canvas>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="fecharCamera()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="capturarFoto()">
                <i data-lucide="camera"></i> Capturar
            </button>
        </div>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>

<script>
// Menu de opções de foto
function mostrarOpcoesFoto(e) {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    var menu = document.getElementById('fotoOpcoesMenu');
    menu.classList.toggle('show');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

var cameraStream = null;

function abrirCamera() {
    fecharMenuFoto();
    
    var video = document.getElementById('cameraVideo');
    var modal = document.getElementById('cameraModal');
    var backdrop = document.getElementById('cameraBackdrop');
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then(function(stream) {
            cameraStream = stream;
            video.srcObject = stream;
            modal.classList.add('show');
            backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(function(err) {
            console.error('Erro ao acessar câmera:', err);
            showToast('Não foi possível acessar a câmera. Verifique as permissões.', 'error');
        });
}

function fecharCamera() {
    var video = document.getElementById('cameraVideo');
    var modal = document.getElementById('cameraModal');
    var backdrop = document.getElementById('cameraBackdrop');
    
    if (cameraStream) {
        cameraStream.getTracks().forEach(function(track) { track.stop(); });
        cameraStream = null;
    }
    video.srcObject = null;
    modal.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
}

function capturarFoto() {
    var video = document.getElementById('cameraVideo');
    var canvas = document.getElementById('cameraCanvas');
    var ctx = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    
    canvas.toBlob(function(blob) {
        var file = new File([blob], 'foto_camera.jpg', { type: 'image/jpeg' });
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        
        var input = document.getElementById('inputFotoGaleria');
        input.files = dataTransfer.files;
        
        // Atualizar preview
        var wrapper = document.querySelector('.pessoa-foto-wrapper');
        var existingImg = wrapper.querySelector('.pessoa-foto-grande');
        var existingPlaceholder = wrapper.querySelector('.pessoa-foto-placeholder');
        var imgUrl = URL.createObjectURL(blob);
        
        if (existingImg) {
            existingImg.src = imgUrl;
        } else if (existingPlaceholder) {
            var img = document.createElement('img');
            img.src = imgUrl;
            img.alt = 'Foto';
            img.className = 'pessoa-foto-grande';
            existingPlaceholder.replaceWith(img);
        }
        
        fecharCamera();
        showToast('Foto capturada com sucesso!', 'success');
    }, 'image/jpeg', 0.9);
}

function abrirGaleria() {
    fecharMenuFoto();
    document.getElementById('inputFotoGaleria').click();
}

function fecharMenuFoto() {
    var menu = document.getElementById('fotoOpcoesMenu');
    if (menu) menu.classList.remove('show');
}

// Fechar menu ao clicar fora
document.addEventListener('click', function(e) {
    var menu = document.getElementById('fotoOpcoesMenu');
    var btn = document.querySelector('.pessoa-foto-edit');
    if (menu && !menu.contains(e.target) && btn && !btn.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// Preview de foto ao selecionar
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
                existingPlaceholder.replaceWith(img);
            }
            
            // Copiar arquivo para o input principal se veio da câmera
            if (input.id === 'inputFotoCamera') {
                var mainInput = document.getElementById('inputFotoGaleria');
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(input.files[0]);
                mainInput.files = dataTransfer.files;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Busca automática de CEP via ViaCEP
document.getElementById('cep').addEventListener('blur', function() {
    const cep = this.value.replace(/\D/g, '');
    if (cep.length === 8) {
        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.erro) {
                    document.getElementById('logradouro').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                    document.getElementById('numero').focus();
                }
            })
            .catch(function(err) { console.log('Erro ao buscar CEP:', err); });
    }
});

<?php if ($isEdit): ?>
const userId = <?= $id ?>;
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

// Sistema de abas
document.querySelectorAll('.tab-item').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const tab = this.dataset.tab;
        
        // Atualizar botões
        document.querySelectorAll('.tab-item').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Atualizar conteúdo
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        document.getElementById('tab-' + tab).style.display = 'block';
        
        // Carregar documentos se necessário
        if (tab === 'documentos') {
            loadDocumentos();
        }
        
        // Reinicializar ícones
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
});

// Carregar documentos
function loadDocumentos() {
    const container = document.getElementById('documentos-list');
    container.innerHTML = '<div class="loading-placeholder"><i data-lucide="loader" class="spin"></i> Carregando...</div>';
    
    fetch(`<?= url('/pessoas/api.php') ?>?action=documentos_list&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDocumentos(data.data);
            } else {
                container.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar documentos</div>';
        });
}

function renderDocumentos(documentos) {
    const container = document.getElementById('documentos-list');
    
    if (documentos.length === 0) {
        container.innerHTML = `
            <div class="table-empty">
                <i data-lucide="file-x"></i>
                <p>Nenhum documento anexado</p>
                <p style="font-size: 0.85rem;">Clique no botão "Anexar Documento" para adicionar.</p>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table"><thead><tr>';
    html += '<th>Tipo</th><th>Descrição</th><th>Arquivo</th><th>Data</th><th class="text-right">Ações</th>';
    html += '</tr></thead><tbody>';
    
    documentos.forEach(doc => {
        const ext = doc.arquivo_nome.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(ext);
        const icon = isImage ? 'image' : (ext === 'pdf' ? 'file-text' : 'file');
        const tamanho = formatFileSize(doc.arquivo_tamanho);
        const data = new Date(doc.created_at).toLocaleDateString('pt-BR');
        
        html += `<tr>
            <td><strong>${escapeHtml(doc.tipo_documento)}</strong></td>
            <td>${doc.descricao ? escapeHtml(doc.descricao) : '<span class="text-muted">-</span>'}</td>
            <td>
                <div class="d-flex align-center gap-1">
                    <i data-lucide="${icon}" style="width: 16px; height: 16px;"></i>
                    <span>${escapeHtml(doc.arquivo_nome)}</span>
                    <small class="text-muted">(${tamanho})</small>
                </div>
            </td>
            <td>${data}</td>
            <td class="text-right">
                <div class="actions">
                    <a href="<?= url('') ?>${doc.arquivo_url}" target="_blank" class="btn btn-icon btn-sm btn-secondary" title="Visualizar">
                        <i data-lucide="eye"></i>
                    </a>
                    <a href="<?= url('') ?>${doc.arquivo_url}" download class="btn btn-icon btn-sm btn-secondary" title="Baixar">
                        <i data-lucide="download"></i>
                    </a>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" onclick="deleteDocumento(${doc.id})" title="Excluir">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modal de documento
function openDocumentoModal() {
    document.getElementById('modal-backdrop').classList.add('show');
    document.getElementById('modal-documento').classList.add('show');
    document.getElementById('form-documento').reset();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeDocumentoModal() {
    document.getElementById('modal-backdrop').classList.remove('show');
    document.getElementById('modal-documento').classList.remove('show');
}

// Event listeners para abrir/fechar modal
document.getElementById('btn-abrir-modal').addEventListener('click', openDocumentoModal);
document.getElementById('btn-fechar-modal').addEventListener('click', closeDocumentoModal);
document.getElementById('btn-cancelar-modal').addEventListener('click', closeDocumentoModal);
document.getElementById('modal-backdrop').addEventListener('click', closeDocumentoModal);

// Upload de documento
document.getElementById('form-documento')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn-upload-doc');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Enviando...';
    btn.disabled = true;
    
    const formData = new FormData(this);
    formData.append('action', 'documento_upload');
    formData.append('user_id', userId);
    formData.append('csrf_token', csrfToken);
    
    fetch('<?= url('/pessoas/api.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDocumentoModal();
            loadDocumentos();
            showToast('Documento anexado com sucesso!', 'success');
        } else {
            showToast(data.message || 'Erro ao anexar documento', 'error');
        }
    })
    .catch(err => {
        showToast('Erro ao anexar documento', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});

// Excluir documento
function deleteDocumento(docId) {
    if (typeof showConfirm === 'function') {
        showConfirm({
            title: 'Excluir documento',
            message: 'Tem certeza que deseja excluir este documento? Esta ação não pode ser desfeita.',
            type: 'danger',
            icon: 'trash-2',
            confirmText: 'Excluir',
            onConfirm: function() {
                executarExclusaoDocumento(docId);
            }
        });
    } else if (confirm('Tem certeza que deseja excluir este documento?')) {
        executarExclusaoDocumento(docId);
    }
}

function executarExclusaoDocumento(docId) {
    fetch('<?= url('/pessoas/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=documento_delete&id=' + docId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            loadDocumentos();
            showToast('Documento excluído com sucesso!', 'success');
        } else {
            showToast(data.message || 'Erro ao excluir documento', 'error');
        }
    })
    .catch(function(err) {
        showToast('Erro ao excluir documento', 'error');
    });
}
<?php endif; ?>
</script>
