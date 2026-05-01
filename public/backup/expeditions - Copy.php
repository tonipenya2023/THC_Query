<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$port = '5432';
$dbname = 'test';
$user = 'postgres';
$password = 'system';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// --------------------------------------------------------------
// 1. COLUMNAS DISPONIBLES (TODAS LAS DE LA TABLA + CALCULADAS)
// --------------------------------------------------------------
$tableColumns = [
    'expedition_id' => 'ID Expedición',
    'user_id'       => 'ID Usuario',
    'reserve_id'    => 'ID Reserva',
    'map_id'        => 'ID Mapa',
    'start_ts'      => 'Timestamp inicio',
    'end_ts'        => 'Timestamp fin',
    'start_at'      => 'Fecha inicio',
    'end_at'        => 'Fecha fin',
    'x'             => 'Coord X',
    'y'             => 'Coord Y',
    'z'             => 'Coord Z',
    'location_id'   => 'ID Localización',
    'raw_json'      => 'JSON',
    'created_at'    => 'Creado',
    'updated_at'    => 'Actualizado',
    'player_name'   => 'Jugador',
    'reserve_name'  => 'Reserva',
];

$calculatedColumns = [
    'total_kills'      => 'Muertes (total)',
    'total_hits'       => 'Disparos',
    'total_weapon_kills' => 'Muertes (arma)',
    'total_animal_kills' => 'Muertes (animal)',
    'total_spots'      => 'Avistamientos',
    'total_tracks'     => 'Rastros',
    'total_collectables' => 'Coleccionables'
];

$allColumns = array_merge($tableColumns, $calculatedColumns);

// Persistencia de columnas seleccionadas
session_start();
if (isset($_POST['save_columns'])) {
    $_SESSION['exp_columns'] = $_POST['columns'] ?? [];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
$selected = $_SESSION['exp_columns'] ?? ['expedition_id', 'player_name', 'reserve_name', 'start_at', 'total_kills'];

// --------------------------------------------------------------
// 2. FILTROS Y PAGINACIÓN
// --------------------------------------------------------------
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
if (!empty($_GET['player'])) {
    $where[] = "player_name ILIKE :player";
    $params[':player'] = '%' . $_GET['player'] . '%';
}
if (!empty($_GET['reserve'])) {
    $where[] = "reserve_name ILIKE :reserve";
    $params[':reserve'] = '%' . $_GET['reserve'] . '%';
}
if (!empty($_GET['date_from'])) {
    $where[] = "start_at >= :date_from";
    $params[':date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[] = "start_at <= :date_to";
    $params[':date_to'] = $_GET['date_to'] . ' 23:59:59';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// --------------------------------------------------------------
// 3. CONSULTA PRINCIPAL (con estadísticas agregadas)
// --------------------------------------------------------------
$selectFields = [];
foreach ($selected as $col) {
    if (isset($tableColumns[$col]) && $col !== 'expedition_id') {
        $selectFields[] = "e.$col";
    } elseif ($col === 'expedition_id') {
        $selectFields[] = "e.expedition_id";
    } elseif ($col === 'total_kills') {
        $selectFields[] = "(SELECT COUNT(*) FROM exp.exp_kills WHERE expedition_id = e.expedition_id) AS total_kills";
    } elseif ($col === 'total_hits') {
        $selectFields[] = "(SELECT COUNT(*) FROM exp.exp_hits WHERE kill_id IN (SELECT kill_id FROM exp.exp_kills WHERE expedition_id = e.expedition_id)) AS total_hits";
    } elseif ($col === 'total_weapon_kills') {
        $selectFields[] = "(SELECT SUM(kills) FROM exp.exp_weapon_stats WHERE expedition_id = e.expedition_id) AS total_weapon_kills";
    } elseif ($col === 'total_animal_kills') {
        $selectFields[] = "(SELECT SUM(kills) FROM exp.exp_animal_stats WHERE expedition_id = e.expedition_id) AS total_animal_kills";
    } elseif ($col === 'total_spots') {
        $selectFields[] = "(SELECT SUM(spots) FROM exp.exp_animal_stats WHERE expedition_id = e.expedition_id) AS total_spots";
    } elseif ($col === 'total_tracks') {
        $selectFields[] = "(SELECT SUM(tracks) FROM exp.exp_animal_stats WHERE expedition_id = e.expedition_id) AS total_tracks";
    } elseif ($col === 'total_collectables') {
        $selectFields[] = "(SELECT SUM(collected) FROM exp.exp_collectables WHERE expedition_id = e.expedition_id) AS total_collectables";
    }
}
if (!in_array('expedition_id', $selected)) {
    $selectFields[] = 'e.expedition_id';
}
$selectSql = implode(', ', array_unique($selectFields));

$sql = "SELECT $selectSql
        FROM exp.exp_expeditions e
        $whereSql
        ORDER BY e.start_at DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$expeditions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSql = "SELECT COUNT(*) FROM exp.exp_expeditions e $whereSql";
$stmt = $pdo->prepare($totalSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$total = $stmt->fetchColumn();
$pages = ceil($total / $limit);

$players = $pdo->query("SELECT DISTINCT player_name FROM exp.exp_expeditions WHERE player_name IS NOT NULL ORDER BY player_name LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
$reserves = $pdo->query("SELECT DISTINCT reserve_name FROM exp.exp_expeditions WHERE reserve_name IS NOT NULL ORDER BY reserve_name LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>THC - Expediciones</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f4; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { margin-top: 0; }
        .filters, .selector-panel { background: #e9ecef; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .selector-panel label { margin-right: 15px; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; }
        .expand-btn { background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; }
        .subtable { width: 100%; margin-top: 8px; border-collapse: collapse; }
        .subtable th, .subtable td { border: 1px solid #ccc; padding: 6px; background: #f9f9f9; }
        .kill-row { background-color: #e2e3e5; font-weight: bold; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 2px; border: 1px solid #dee2e6; background: white; text-decoration: none; color: #007bff; }
        .pagination .current { background: #007bff; color: white; }
        .loading { text-align: center; padding: 20px; }
        #mainTable tbody tr { cursor: pointer; }
        #mainTable tbody tr:hover { background-color: #f1f1f1; }
        .btn { background: #007bff; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h1>Expediciones (<?= number_format($total) ?> registros)</h1>

    <!-- Filtros -->
    <form method="GET" class="filters">
        <select name="player">
            <option value="">Todos los jugadores</option>
            <?php foreach ($players as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= isset($_GET['player']) && $_GET['player'] == $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="reserve">
            <option value="">Todas las reservas</option>
            <?php foreach ($reserves as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>" <?= isset($_GET['reserve']) && $_GET['reserve'] == $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        <button type="submit" class="btn">Filtrar</button>
        <a href="expeditions.php" class="btn" style="background:#6c757d">Limpiar</a>
    </form>

    <!-- Selector de columnas -->
    <div class="selector-panel">
        <strong>📋 Columnas a mostrar:</strong>
        <form method="POST" style="display:inline;">
            <?php foreach ($allColumns as $col => $label): ?>
                <label><input type="checkbox" name="columns[]" value="<?= $col ?>" <?= in_array($col, $selected) ? 'checked' : '' ?>> <?= $label ?></label>
            <?php endforeach; ?>
            <button type="submit" name="save_columns" class="btn">Guardar</button>
        </form>
    </div>

    <!-- Tabla principal -->
    <table id="mainTable">
        <thead>
            <tr>
                <?php foreach ($selected as $col): ?>
                    <th><?= $allColumns[$col] ?></th>
                <?php endforeach; ?>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expeditions as $exp): ?>
            <tr data-expedition-id="<?= $exp['expedition_id'] ?>">
                <?php foreach ($selected as $col): ?>
                    <td>
                        <?php
                        $val = $exp[$col] ?? '-';
                        if (in_array($col, ['start_at','end_at','created_at','updated_at']) && $val != '-') {
                            echo date('d/m/Y H:i', strtotime($val));
                        } elseif ($col == 'raw_json') {
                            echo 'JSON';
                        } elseif (is_numeric($val)) {
                            echo number_format((float)$val, 2);
                        } else {
                            echo htmlspecialchars($val);
                        }
                        ?>
                    </td>
                <?php endforeach; ?>
                <td><button class="expand-btn" data-id="<?= $exp['expedition_id'] ?>">▼ Expandir</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>">« Primera</a>
            <a href="?page=<?= $page-1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>">‹ Anterior</a>
        <?php endif; ?>
        <span class="current">Página <?= $page ?> de <?= $pages ?></span>
        <?php if ($page < $pages): ?>
            <a href="?page=<?= $page+1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>">Siguiente ›</a>
            <a href="?page=<?= $pages ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>">Última »</a>
        <?php endif; ?>
    </div>
</div>

<script>
// Función que expande/contrae la subtabla (misma para botón y para clic en fila)
async function toggleExpand(row) {
    const expeditionId = row.dataset.expeditionId;
    if (!expeditionId) return;

    const btn = row.querySelector('.expand-btn');
    const nextRow = row.nextElementSibling;

    if (nextRow && nextRow.classList.contains('subrow')) {
        nextRow.remove();
        if (btn) btn.textContent = '▼ Expandir';
        return;
    }

    const loader = document.createElement('tr');
    loader.classList.add('subrow');
    loader.innerHTML = `<td colspan="<?= count($selected)+1 ?>" class="loading">Cargando...<\/td>`;
    row.insertAdjacentElement('afterend', loader);

    try {
        const res = await fetch(`expeditions_api.php?expedition_id=${encodeURIComponent(expeditionId)}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        if (data.error) throw new Error(data.error);

        const kills = new Map();
        for (const hit of data) {
            if (!kills.has(hit.kill_id)) {
                kills.set(hit.kill_id, {
                    kill: {
                        kill_id: hit.kill_id,
                        species_name: hit.species_name,
                        score: hit.score,
                        weight_kg: hit.weight_kg
                    },
                    hits: []
                });
            }
            kills.get(hit.kill_id).hits.push({
                hit_index: hit.hit_index,
                weapon_text: hit.weapon_text,
                distance_m: hit.distance_m,
                body_part_text: hit.body_part_text,
                animal_state_text: hit.animal_state_text
            });
        }

        const safe = (value) => {
            if (value === null || value === undefined || value === '') return '-';
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        };

        let html = `<td colspan="<?= count($selected)+1 ?>"><table class="subtable">`;
        html += `<thead><tr><th>Kill ID</th><th>Especie</th><th>Puntuación</th><th>Peso (kg)</th><th>Disparo</th><th>Arma</th><th>Distancia (m)</th><th>Parte</th><th>Estado animal</th></tr></thead><tbody>`;

        if (kills.size === 0) {
            html += `<tr><td colspan="9" class="loading">No hay muertes para esta expedición</td></tr>`;
        } else {
            for (const k of kills.values()) {
                const hits = k.hits.length ? k.hits : [{}];
                const span = hits.length;

                for (let i = 0; i < span; i++) {
                    html += `<tr class="${i === 0 ? 'kill-row' : ''}">`;
                    if (i === 0) {
                        html += `<td rowspan="${span}">${safe(k.kill.kill_id)}</td>`;
                        html += `<td rowspan="${span}">${safe(k.kill.species_name)}</td>`;
                        html += `<td rowspan="${span}">${safe(k.kill.score)}</td>`;
                        html += `<td rowspan="${span}">${safe(k.kill.weight_kg)}</td>`;
                    }

                    const h = hits[i];
                    html += `<td>${safe(h.hit_index)}</td>`;
                    html += `<td>${safe(h.weapon_text)}</td>`;
                    html += `<td>${safe(h.distance_m)}</td>`;
                    html += `<td>${safe(h.body_part_text)}</td>`;
                    html += `<td>${safe(h.animal_state_text)}</td>`;
                    html += `</tr>`;
                }
            }
        }

        html += `</tbody></table></td>`;
        loader.innerHTML = html;
        if (btn) btn.textContent = '▲ Contraer';
    } catch (e) {
        loader.innerHTML = `<td colspan="<?= count($selected)+1 ?>" class="loading">Error: ${e.message}</td>`;
        console.error(e);
    }
}

// Un único evento delegado: sirve para el botón y para cualquier clic en la fila.
document.addEventListener('click', (e) => {
    const row = e.target.closest('#mainTable tbody tr[data-expedition-id]');
    if (!row) return;

    // Evita activar la subtabla al pulsar enlaces u otros controles ajenos al botón de expandir.
    if (e.target.closest('a, input, select, textarea, label') && !e.target.closest('.expand-btn')) return;

    toggleExpand(row);
});
</script>
</body>
</html>