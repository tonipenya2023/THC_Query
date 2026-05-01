<?php
header('Content-Type: application/json');
error_reporting(0);

$host = 'localhost';
$port = '5432';
$dbname = 'test';
$user = 'postgres';
$password = 'system';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Conexión fallida: ' . $e->getMessage()]);
    exit;
}

$expeditionId = isset($_GET['expedition_id']) ? (int)$_GET['expedition_id'] : 0;
if (!$expeditionId) {
    echo json_encode(['error' => 'ID de expedición no válido']);
    exit;
}

$sql = "SELECT 
            k.kill_id,
            COALESCE(s.especie_es, k.species_name) AS species_name,
            k.score,
            (k.weight / 1000)::numeric(10,2) AS weight_kg,
            k.harvest_value,
            k.trophy_integrity,
            h.hit_index,
            (h.distance / 1000)::numeric(10,2) AS distance_m,
            kf.shot_count_text,
            kf.shot_location_text AS kill_shot_location,
            kf2.weapon_text,
            kf2.scope_text,
            kf2.ammo_text,
            kf2.shot_distance_text,
            kf2.animal_state_text,
            kf2.body_part_text,
            kf2.posture_text,
            kf2.platform_text,
            kf2.shot_location_text AS hit_shot_location
        FROM exp.exp_kills k
        LEFT JOIN exp.exp_hits h ON h.kill_id = k.kill_id
        LEFT JOIN exp.species s ON s.id_especie = k.species_id
        LEFT JOIN (
            SELECT DISTINCT ON (kill_id) kill_id, shot_count_text, shot_location_text
            FROM exp.exp_kills_ficha
            ORDER BY kill_id, scraped_at DESC
        ) kf ON kf.kill_id = k.kill_id
        LEFT JOIN (
            SELECT DISTINCT ON (kill_id) kill_id, weapon_text, scope_text, ammo_text, shot_distance_text,
                   animal_state_text, body_part_text, posture_text, platform_text, shot_location_text
            FROM exp.exp_kills_ficha
            ORDER BY kill_id, scraped_at DESC
        ) kf2 ON kf2.kill_id = h.kill_id
        WHERE k.expedition_id = :expedition_id
        ORDER BY k.kill_id, h.hit_index ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':expedition_id' => $expeditionId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data, JSON_UNESCAPED_UNICODE);