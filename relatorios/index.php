<?php
/**
 * Relatórios
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('relatorios', 'view');

$pageTitle = 'Relatórios';
$db = Database::getInstance();

// Parâmetros
$tipo = $_GET['tipo'] ?? 'frequencia';
$periodo = $_GET['periodo'] ?? 'mes';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-t');
$ministerioId = $_GET['ministerio'] ?? '';

// Ministérios para filtro
$ministerios = $db->fetchAll("SELECT id, nome FROM ministerios WHERE ativo = 1 ORDER BY nome");

// Dados do relatório baseado no tipo
$dados = [];
$totais = [];

switch ($tipo) {
    case 'frequencia':
        // Frequência por evento
        $where = ['1=1'];
        $params = [];
        
        $where[] = 'DATE(e.inicio_at) BETWEEN ? AND ?';
        $params[] = $dataInicio;
        $params[] = $dataFim;
        
        if ($ministerioId) {
            $where[] = 'e.ministerio_responsavel_id = ?';
            $params[] = $ministerioId;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $dados = $db->fetchAll(
            "SELECT e.id, e.titulo, e.inicio_at, e.tipo,
                    COUNT(CASE WHEN a.status = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN a.status = 'ausente' THEN 1 END) as ausentes,
                    COUNT(CASE WHEN a.status = 'justificado' THEN 1 END) as justificados
             FROM events e
             LEFT JOIN attendance a ON e.id = a.event_id
             WHERE {$whereClause}
             GROUP BY e.id
             ORDER BY e.inicio_at DESC",
            $params
        );
        
        $totais = $db->fetch(
            "SELECT 
                COUNT(DISTINCT e.id) as total_eventos,
                COUNT(CASE WHEN a.status = 'presente' THEN 1 END) as total_presentes,
                COUNT(CASE WHEN a.status = 'ausente' THEN 1 END) as total_ausentes,
                COUNT(CASE WHEN a.status = 'justificado' THEN 1 END) as total_justificados
             FROM events e
             LEFT JOIN attendance a ON e.id = a.event_id
             WHERE {$whereClause}",
            $params
        );
        break;

    case 'pessoas':
        // Frequência por pessoa
        $dados = $db->fetchAll(
            "SELECT u.id, u.nome, u.email, u.cargo, m.nome as ministerio,
                    COUNT(CASE WHEN a.status = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN a.status = 'ausente' THEN 1 END) as ausentes,
                    COUNT(CASE WHEN a.status = 'justificado' THEN 1 END) as justificados,
                    COUNT(a.id) as total
             FROM users u
             LEFT JOIN attendance a ON u.id = a.person_id
             LEFT JOIN events e ON a.event_id = e.id AND DATE(e.inicio_at) BETWEEN ? AND ?
             LEFT JOIN ministerios m ON u.ministerio_id = m.id
             WHERE u.status = 'ativo'
             GROUP BY u.id
             HAVING total > 0
             ORDER BY presentes DESC",
            [$dataInicio, $dataFim]
        );
        break;

    case 'ministerios':
        // Frequência por ministério
        $dados = $db->fetchAll(
            "SELECT m.id, m.nome,
                    COUNT(DISTINCT e.id) as total_eventos,
                    COUNT(CASE WHEN a.status = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN a.status = 'ausente' THEN 1 END) as ausentes
             FROM ministerios m
             LEFT JOIN events e ON m.id = e.ministerio_responsavel_id AND DATE(e.inicio_at) BETWEEN ? AND ?
             LEFT JOIN attendance a ON e.id = a.event_id
             WHERE m.ativo = 1
             GROUP BY m.id
             ORDER BY total_eventos DESC",
            [$dataInicio, $dataFim]
        );
        break;

    case 'mensal':
        // Relatório mensal
        $dados = $db->fetchAll(
            "SELECT DATE_FORMAT(e.inicio_at, '%Y-%m') as mes,
                    COUNT(DISTINCT e.id) as total_eventos,
                    COUNT(CASE WHEN a.status = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN a.status = 'ausente' THEN 1 END) as ausentes,
                    COUNT(CASE WHEN a.status = 'justificado' THEN 1 END) as justificados
             FROM events e
             LEFT JOIN attendance a ON e.id = a.event_id
             WHERE e.inicio_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(e.inicio_at, '%Y-%m')
             ORDER BY mes DESC"
        );
        break;
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Relatórios</h1>
        <p class="page-subtitle">Análise de frequência e estatísticas</p>
    </div>
    <?php if (can('relatorios', 'export')): ?>
    <button class="btn btn-secondary" onclick="exportToExcel('relatorioTable', 'relatorio_<?= $tipo ?>')">
        <i data-lucide="download"></i> Exportar
    </button>
    <?php endif; ?>
</div>

<!-- Tabs de Tipo -->
<div class="tabs mb-3">
    <a href="?tipo=frequencia&data_inicio=<?= $dataInicio ?>&data_fim=<?= $dataFim ?><?= $ministerioId ? '&ministerio=' . $ministerioId : '' ?>" class="tab-link <?= $tipo === 'frequencia' ? 'active' : '' ?>">
        <i data-lucide="calendar"></i> Por Evento
    </a>
    <a href="?tipo=pessoas&data_inicio=<?= $dataInicio ?>&data_fim=<?= $dataFim ?>" class="tab-link <?= $tipo === 'pessoas' ? 'active' : '' ?>">
        <i data-lucide="users"></i> Por Pessoa
    </a>
    <a href="?tipo=ministerios&data_inicio=<?= $dataInicio ?>&data_fim=<?= $dataFim ?>" class="tab-link <?= $tipo === 'ministerios' ? 'active' : '' ?>">
        <i data-lucide="layers"></i> Por Ministério
    </a>
    <a href="?tipo=mensal" class="tab-link <?= $tipo === 'mensal' ? 'active' : '' ?>">
        <i data-lucide="bar-chart-2"></i> Mensal
    </a>
</div>

<!-- Filtros -->
<?php if ($tipo !== 'mensal'): ?>
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <input type="hidden" name="tipo" value="<?= $tipo ?>">
        
        <div class="filter-group">
            <label class="filter-label">De:</label>
            <input type="date" name="data_inicio" class="filter-input" value="<?= $dataInicio ?>">
        </div>
        
        <div class="filter-group">
            <label class="filter-label">Até:</label>
            <input type="date" name="data_fim" class="filter-input" value="<?= $dataFim ?>">
        </div>

        <?php if ($tipo === 'frequencia'): ?>
        <div class="filter-group">
            <select name="ministerio" class="filter-select">
                <option value="">Todos os Ministérios</option>
                <?php foreach ($ministerios as $min): ?>
                <option value="<?= $min['id'] ?>" <?= $ministerioId == $min['id'] ? 'selected' : '' ?>><?= sanitize($min['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">
            <i data-lucide="filter"></i> Aplicar
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Totais -->
<?php if ($tipo === 'frequencia' && $totais): ?>
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon primary"><i data-lucide="calendar"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $totais['total_eventos'] ?></div>
            <div class="stat-label">Eventos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i data-lucide="check-circle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $totais['total_presentes'] ?></div>
            <div class="stat-label">Presenças</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i data-lucide="x-circle"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $totais['total_ausentes'] ?></div>
            <div class="stat-label">Ausências</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i data-lucide="file-text"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= $totais['total_justificados'] ?></div>
            <div class="stat-label">Justificados</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tabela de Dados -->
<div class="card">
    <div class="table-wrapper">
        <table class="table" id="relatorioTable">
            <thead>
                <?php if ($tipo === 'frequencia'): ?>
                <tr>
                    <th>Evento</th>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th class="text-center">Presentes</th>
                    <th class="text-center">Ausentes</th>
                    <th class="text-center">Justificados</th>
                    <th class="text-center">%</th>
                </tr>
                <?php elseif ($tipo === 'pessoas'): ?>
                <tr>
                    <th>Pessoa</th>
                    <th>Cargo</th>
                    <th>Ministério</th>
                    <th class="text-center">Presenças</th>
                    <th class="text-center">Ausências</th>
                    <th class="text-center">Justificados</th>
                    <th class="text-center">%</th>
                </tr>
                <?php elseif ($tipo === 'ministerios'): ?>
                <tr>
                    <th>Ministério</th>
                    <th class="text-center">Eventos</th>
                    <th class="text-center">Presenças</th>
                    <th class="text-center">Ausências</th>
                    <th class="text-center">Taxa</th>
                </tr>
                <?php elseif ($tipo === 'mensal'): ?>
                <tr>
                    <th>Mês</th>
                    <th class="text-center">Eventos</th>
                    <th class="text-center">Presenças</th>
                    <th class="text-center">Ausências</th>
                    <th class="text-center">Justificados</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if (empty($dados)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding: 40px;">
                        Nenhum dado encontrado para o período selecionado.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($dados as $row): ?>
                    <tr>
                        <?php if ($tipo === 'frequencia'): ?>
                            <?php $total = $row['presentes'] + $row['ausentes'] + $row['justificados']; ?>
                            <td><strong><?= sanitize($row['titulo']) ?></strong></td>
                            <td><?= formatDate($row['inicio_at']) ?></td>
                            <td><?= EVENT_TYPES[$row['tipo']] ?? $row['tipo'] ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= $row['presentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= $row['ausentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning"><?= $row['justificados'] ?></span></td>
                            <td class="text-center"><?= $total > 0 ? round(($row['presentes'] / $total) * 100) : 0 ?>%</td>
                        <?php elseif ($tipo === 'pessoas'): ?>
                            <?php $total = $row['presentes'] + $row['ausentes'] + $row['justificados']; ?>
                            <td>
                                <div class="d-flex align-center gap-1">
                                    <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($row['nome']) ?>">
                                        <?= getInitials($row['nome']) ?>
                                    </div>
                                    <strong><?= sanitize($row['nome']) ?></strong>
                                </div>
                            </td>
                            <td><?= MEMBER_POSITIONS[$row['cargo']] ?? $row['cargo'] ?></td>
                            <td><?= sanitize($row['ministerio'] ?? '-') ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= $row['presentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= $row['ausentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning"><?= $row['justificados'] ?></span></td>
                            <td class="text-center"><?= $total > 0 ? round(($row['presentes'] / $total) * 100) : 0 ?>%</td>
                        <?php elseif ($tipo === 'ministerios'): ?>
                            <?php $total = $row['presentes'] + $row['ausentes']; ?>
                            <td><strong><?= sanitize($row['nome']) ?></strong></td>
                            <td class="text-center"><?= $row['total_eventos'] ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= $row['presentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= $row['ausentes'] ?></span></td>
                            <td class="text-center"><?= $total > 0 ? round(($row['presentes'] / $total) * 100) : 0 ?>%</td>
                        <?php elseif ($tipo === 'mensal'): ?>
                            <?php 
                            $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
                                      '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
                            $partes = explode('-', $row['mes']);
                            $mesNome = $meses[$partes[1]] . '/' . $partes[0];
                            ?>
                            <td><strong><?= $mesNome ?></strong></td>
                            <td class="text-center"><?= $row['total_eventos'] ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= $row['presentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= $row['ausentes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning"><?= $row['justificados'] ?></span></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($tipo === 'mensal' && !empty($dados)): ?>
<!-- Gráfico Mensal -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Evolução Mensal</h3>
    </div>
    <div class="card-body">
        <div class="chart-container" style="height: 300px;">
            <canvas id="chartMensal"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dados = <?= json_encode(array_reverse($dados)) ?>;
    const labels = dados.map(d => {
        const [ano, mes] = d.mes.split('-');
        const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return meses[parseInt(mes)-1] + '/' + ano.substr(2);
    });
    
    new Chart(document.getElementById('chartMensal'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Presenças',
                data: dados.map(d => d.presentes),
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.3
            }, {
                label: 'Ausências',
                data: dados.map(d => d.ausentes),
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#F1F5F9' } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include BASE_PATH . 'includes/footer.php'; ?>
