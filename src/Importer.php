<?php

declare(strict_types=1);

final class Importer
{
    private PDO $pdo;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->pdo = new PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function importExpedition(int $userId, int $expeditionId): void
    {
        $payload = $this->fetchPayload($userId, $expeditionId);
        $expedition = $payload['expedition'] ?? null;
        $playerName = $this->getPlayerName($userId);
        $reserveName = isset($expedition['reserve']) ? $this->getReserveName((int) $expedition['reserve']) : null;

        if (!is_array($expedition) || !isset($expedition['id'])) {
            throw new RuntimeException('La respuesta no contiene expedition valida.');
        }

        $this->pdo->beginTransaction();

        try {
            $this->saveExpedition($userId, $playerName, $reserveName, $expedition, $payload);
            $this->saveStats($userId, $playerName, (int) $expedition['id'], $payload['stats'] ?? null);
            $this->saveAntlerCollectables($userId, $playerName, (int) $expedition['id'], $payload['antler_collectables'] ?? []);
            $this->saveKills($userId, $playerName, (int) $expedition['id'], $payload['kills'] ?? []);
            $this->savePayload($userId, $playerName, (int) $expedition['id'], $payload);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function expeditionExists(int $expeditionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM gpt.exp_expeditions WHERE expedition_id = :expedition_id LIMIT 1'
        );
        $stmt->execute([':expedition_id' => $expeditionId]);

        return (bool) $stmt->fetchColumn();
    }

    public function fetchExpeditionListPage(int $userId, int $offset = 0, int $limit = 20): array
    {
        $query = http_build_query([
            'user_id' => $userId,
            'offset' => $offset,
            'limit' => $limit,
        ]);

        $url = $this->config['api']['list_url'] . '?' . $query;
        $data = $this->fetchJson($url);
        $expeditions = $data['expeditions'] ?? null;

        if (!is_array($expeditions)) {
            throw new RuntimeException('La API de lista no devolvio expeditions valida.');
        }

        return $expeditions;
    }

    private function getPlayerName(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT player_name FROM gpt.tab_usuarios WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    private function getReserveName(int $reserveId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT reserva FROM gpt.tab_reservas WHERE id_reserva = :reserve_id LIMIT 1'
        );
        $stmt->execute([':reserve_id' => $reserveId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    private function fetchPayload(int $userId, int $expeditionId): array
    {
        $query = http_build_query([
            'user_id' => $userId,
            'expedition_id' => $expeditionId,
        ]);

        $url = $this->config['api']['base_url'] . '?' . $query;
        return $this->fetchJson($url);
    }

    private function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$this->config['api']['user_agent']}\r\n",
                'timeout' => $this->config['api']['timeout'],
            ],
        ]);

        $json = @file_get_contents($url, false, $context);

        if ($json === false) {
            throw new RuntimeException('No se pudo obtener respuesta de la API.');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('La API devolvio un JSON invalido.');
        }

        return $data;
    }
    private function saveExpedition(int $userId, ?string $playerName, ?string $reserveName, array $expedition, array $payload): void
    {
        $sql = <<<SQL
INSERT INTO gpt.exp_expeditions (
    expedition_id, user_id, player_name, reserve_id, reserve_name, map_id, start_ts, end_ts, start_at, end_at,
    x, y, z, location_id, raw_json, updated_at
) VALUES (
    :expedition_id, :user_id, :player_name, :reserve_id, :reserve_name, :map_id, :start_ts, :end_ts, :start_at, :end_at,
    :x, :y, :z, :location_id, CAST(:raw_json AS JSONB), NOW()
)
ON CONFLICT (expedition_id) DO UPDATE SET
    user_id = EXCLUDED.user_id,
    player_name = EXCLUDED.player_name,
    reserve_id = EXCLUDED.reserve_id,
    reserve_name = EXCLUDED.reserve_name,
    map_id = EXCLUDED.map_id,
    start_ts = EXCLUDED.start_ts,
    end_ts = EXCLUDED.end_ts,
    start_at = EXCLUDED.start_at,
    end_at = EXCLUDED.end_at,
    x = EXCLUDED.x,
    y = EXCLUDED.y,
    z = EXCLUDED.z,
    location_id = EXCLUDED.location_id,
    raw_json = EXCLUDED.raw_json,
    updated_at = NOW()
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':expedition_id' => $expedition['id'],
            ':user_id' => $userId,
            ':player_name' => $playerName,
            ':reserve_id' => $expedition['reserve'] ?? null,
            ':reserve_name' => $reserveName,
            ':map_id' => $expedition['map'] ?? null,
            ':start_ts' => $this->normalizeUnixTimestamp($expedition['start_ts'] ?? null),
            ':end_ts' => $this->normalizeUnixTimestamp($expedition['end_ts'] ?? null),
            ':start_at' => $this->unixToTimestamp($expedition['start_ts'] ?? null),
            ':end_at' => $this->unixToTimestamp($expedition['end_ts'] ?? null),
            ':x' => $expedition['x'] ?? null,
            ':y' => $expedition['y'] ?? null,
            ':z' => $expedition['z'] ?? null,
            ':location_id' => $expedition['location_id'] ?? null,
            ':raw_json' => json_encode($expedition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function saveStats(int $userId, ?string $playerName, int $expeditionId, ?array $stats): void
    {
        if (!is_array($stats)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_stats (expedition_id, user_id, player_name, duration, distance, raw_json)
             VALUES (:expedition_id, :user_id, :player_name, :duration, :distance, CAST(:raw_json AS JSONB))
             ON CONFLICT (expedition_id) DO UPDATE SET
                 user_id = EXCLUDED.user_id,
                 player_name = EXCLUDED.player_name,
                 duration = EXCLUDED.duration,
                 distance = EXCLUDED.distance,
                 raw_json = EXCLUDED.raw_json'
        );

        $stmt->execute([
            ':expedition_id' => $expeditionId,
            ':user_id' => $userId,
            ':player_name' => $playerName,
            ':duration' => $stats['duration'] ?? null,
            ':distance' => $stats['distance'] ?? null,
            ':raw_json' => json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $this->replaceAnimalStats($userId, $playerName, $expeditionId, $stats['data']['animal'] ?? []);
        $this->replaceWeaponStats($userId, $playerName, $expeditionId, $stats['data']['weapon'] ?? []);
        $this->replaceCollectables($userId, $playerName, $expeditionId, $stats['data']['collectables'] ?? []);
    }

    private function replaceAnimalStats(int $userId, ?string $playerName, int $expeditionId, array $animals): void
    {
        $this->pdo->prepare('DELETE FROM gpt.exp_animal_stats WHERE expedition_id = :expedition_id')
            ->execute([':expedition_id' => $expeditionId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_animal_stats (
                expedition_id, user_id, player_name, species_id, tracks, spots, kills, ethical_kills, raw_json
             ) VALUES (
                :expedition_id, :user_id, :player_name, :species_id, :tracks, :spots, :kills, :ethical_kills, CAST(:raw_json AS JSONB)
             )'
        );

        foreach ($animals as $speciesId => $values) {
            $stmt->execute([
                ':expedition_id' => $expeditionId,
                ':user_id' => $userId,
                ':player_name' => $playerName,
                ':species_id' => (int) $speciesId,
                ':tracks' => $values['tracks'] ?? null,
                ':spots' => $values['spots'] ?? null,
                ':kills' => $values['kills'] ?? null,
                ':ethical_kills' => $values['ethical_kills'] ?? null,
                ':raw_json' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    private function replaceWeaponStats(int $userId, ?string $playerName, int $expeditionId, array $weapons): void
    {
        $this->pdo->prepare('DELETE FROM gpt.exp_weapon_stats WHERE expedition_id = :expedition_id')
            ->execute([':expedition_id' => $expeditionId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_weapon_stats (
                expedition_id, user_id, player_name, weapon_id, ammo_id, ethical_kills, hits, misses, kills, distance, raw_json
             ) VALUES (
                :expedition_id, :user_id, :player_name, :weapon_id, :ammo_id, :ethical_kills, :hits, :misses, :kills, :distance, CAST(:raw_json AS JSONB)
             )'
        );

        foreach ($weapons as $weaponId => $ammoItems) {
            foreach ($ammoItems as $ammoId => $values) {
                $stmt->execute([
                    ':expedition_id' => $expeditionId,
                    ':user_id' => $userId,
                    ':player_name' => $playerName,
                    ':weapon_id' => (int) $weaponId,
                    ':ammo_id' => (int) $ammoId,
                    ':ethical_kills' => $values['ethical_kills'] ?? null,
                    ':hits' => $values['hits'] ?? null,
                    ':misses' => $values['misses'] ?? null,
                    ':kills' => $values['kills'] ?? null,
                    ':distance' => $values['distance'] ?? null,
                    ':raw_json' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }

    private function replaceCollectables(int $userId, ?string $playerName, int $expeditionId, array $collectables): void
    {
        $this->pdo->prepare('DELETE FROM gpt.exp_collectables WHERE expedition_id = :expedition_id')
            ->execute([':expedition_id' => $expeditionId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_collectables (
                expedition_id, user_id, player_name, collectable_type, collected, max_value, sum_value, max_id, raw_json
             ) VALUES (
                :expedition_id, :user_id, :player_name, :collectable_type, :collected, :max_value, :sum_value, :max_id, CAST(:raw_json AS JSONB)
             )'
        );

        foreach ($collectables as $type => $values) {
            $stmt->execute([
                ':expedition_id' => $expeditionId,
                ':user_id' => $userId,
                ':player_name' => $playerName,
                ':collectable_type' => (string) $type,
                ':collected' => $values['collected'] ?? null,
                ':max_value' => $this->toNumeric($values['max'] ?? null),
                ':sum_value' => $this->toNumeric($values['sum'] ?? null),
                ':max_id' => $values['max_id'] ?? null,
                ':raw_json' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    private function saveAntlerCollectables(int $userId, ?string $playerName, int $expeditionId, array $items): void
    {
        $this->pdo->prepare('DELETE FROM gpt.exp_antler_collectables WHERE expedition_id = :expedition_id')
            ->execute([':expedition_id' => $expeditionId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_antler_collectables (
                antler_collectable_id, expedition_id, user_id, player_name, species_id, score, collectable_type, collected_at, raw_json
             ) VALUES (
                :antler_collectable_id, :expedition_id, :user_id, :player_name, :species_id, :score, :collectable_type, :collected_at, CAST(:raw_json AS JSONB)
             )
             ON CONFLICT (antler_collectable_id) DO UPDATE SET
                expedition_id = EXCLUDED.expedition_id,
                user_id = EXCLUDED.user_id,
                player_name = EXCLUDED.player_name,
                species_id = EXCLUDED.species_id,
                score = EXCLUDED.score,
                collectable_type = EXCLUDED.collectable_type,
                collected_at = EXCLUDED.collected_at,
                raw_json = EXCLUDED.raw_json'
        );

        foreach ($items as $item) {
            if (!isset($item['id'])) {
                continue;
            }

            $stmt->execute([
                ':antler_collectable_id' => $item['id'],
                ':expedition_id' => $expeditionId,
                ':user_id' => $userId,
                ':player_name' => $playerName,
                ':species_id' => $item['species'] ?? null,
                ':score' => $this->toNumeric($item['score'] ?? null),
                ':collectable_type' => $item['type'] ?? null,
                ':collected_at' => $item['ts'] ?? null,
                ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    private function saveKills(int $userId, ?string $playerName, int $expeditionId, array $kills): void
    {
        $this->pdo->prepare('DELETE FROM gpt.exp_hits WHERE expedition_id = :expedition_id')
            ->execute([':expedition_id' => $expeditionId]);
        $this->pdo->prepare('DELETE FROM gpt.exp_kills WHERE expedition_id = :expedition_id')
            ->execute([':expedition_id' => $expeditionId]);

        $killStmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_kills (
                kill_id, expedition_id, user_id, player_name, species_id, species_name, weight, gender, texture,
                ethical, wound_time, confirm_ts, confirm_at, harvest_value, trophy_integrity, score,
                score_type, photo, raw_json
             ) VALUES (
                :kill_id, :expedition_id, :user_id, :player_name, :species_id, :species_name, :weight, :gender, :texture,
                CAST(NULLIF(:ethical, \'\') AS BOOLEAN), :wound_time, :confirm_ts, :confirm_at, :harvest_value, :trophy_integrity, :score,
                :score_type, :photo, CAST(:raw_json AS JSONB)
             )'
        );

        $hitStmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_hits (
                expedition_id, kill_id, hit_index, user_id, player_name, distance, weapon_id, ammo_id, organ, raw_json
             ) VALUES (
                :expedition_id, :kill_id, :hit_index, :user_id, :player_name, :distance, :weapon_id, :ammo_id, :organ, CAST(:raw_json AS JSONB)
             )'
        );

        foreach ($kills as $kill) {
            if (!isset($kill['id'])) {
                continue;
            }

            $killData = $kill['kill'] ?? [];

            $killStmt->execute([
                ':kill_id' => $kill['id'],
                ':expedition_id' => $expeditionId,
                ':user_id' => $userId,
                ':player_name' => $playerName,
                ':species_id' => $kill['species'] ?? null,
                ':species_name' => $kill['speciesName'] ?? null,
                ':weight' => $kill['weight'] ?? null,
                ':gender' => $kill['gender'] ?? null,
                ':texture' => $kill['texture'] ?? null,
                ':ethical' => $this->normalizeBooleanString($killData['ethical'] ?? null),
                ':wound_time' => $this->toNumeric($killData['wound_time'] ?? null),
                ':confirm_ts' => $this->normalizeUnixTimestamp($killData['confirmTs'] ?? null),
                ':confirm_at' => $this->unixToTimestamp($killData['confirmTs'] ?? null),
                ':harvest_value' => $this->toNumeric($killData['harvest_value'] ?? null),
                ':trophy_integrity' => $this->toNumeric($killData['trophy_integrity'] ?? null),
                ':score' => $this->toNumeric($killData['score'] ?? null),
                ':score_type' => $killData['scoreType'] ?? null,
                ':photo' => $killData['photo'] ?? null,
                ':raw_json' => json_encode($kill, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $hits = $kill['hits'] ?? [];

            foreach (array_values($hits) as $index => $hit) {
                $hitStmt->execute([
                    ':expedition_id' => $expeditionId,
                    ':kill_id' => $kill['id'],
                    ':hit_index' => $index + 1,
                    ':user_id' => $userId,
                    ':player_name' => $playerName,
                    ':distance' => $hit['distance'] ?? null,
                    ':weapon_id' => $hit['weapon_id'] ?? null,
                    ':ammo_id' => $hit['ammo_id'] ?? null,
                    ':organ' => $hit['organ'] ?? null,
                    ':raw_json' => json_encode($hit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }

    private function savePayload(int $userId, ?string $playerName, int $expeditionId, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.exp_payloads (expedition_id, user_id, player_name, payload_json)
             VALUES (:expedition_id, :user_id, :player_name, CAST(:payload_json AS JSONB))
             ON CONFLICT (expedition_id) DO UPDATE SET
                user_id = EXCLUDED.user_id,
                player_name = EXCLUDED.player_name,
                payload_json = EXCLUDED.payload_json'
        );

        $stmt->execute([
            ':expedition_id' => $expeditionId,
            ':user_id' => $userId,
            ':player_name' => $playerName,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function unixToTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return gmdate('Y-m-d H:i:sP', $this->normalizeUnixTimestamp($value));
    }

    private function normalizeUnixTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) floor((float) $value);
    }

    private function toNumeric(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function normalizeBooleanString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return ((bool) $value) ? 'true' : 'false';
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 't', 'yes', 'y'], true)) {
            return 'true';
        }

        if (in_array($normalized, ['0', 'false', 'f', 'no', 'n'], true)) {
            return 'false';
        }

        return null;
    }
}
