<?php

declare(strict_types=1);

final class UserPublicStatsImporter
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

    public function importByHostname(string $hostname): void
    {
        $hostname = trim($hostname);
        if ($hostname === '') {
            throw new RuntimeException('Hostname vacio.');
        }

        $url = 'https://api.thehunter.com/v1/Public_user/getByHostname?hostname=' . urlencode($hostname);
        $data = $this->fetchJson($url);

        $userId = $this->getUserIdByPlayerName($hostname);
        if ($userId === null) {
            throw new RuntimeException("No existe user_id en gpt.tab_usuarios para {$hostname}.");
        }

        $profileStmt = $this->pdo->prepare(
            'INSERT INTO gpt.user_public_stats (
                user_id, player_name, hostname, handle, membership, avatar_url, online,
                global_rank, hunter_score, duration, distance, raw_json, updated_at
             ) VALUES (
                :user_id, :player_name, :hostname, :handle, :membership, :avatar_url, CAST(NULLIF(:online, \'\') AS BOOLEAN),
                :global_rank, :hunter_score, :duration, :distance, CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (user_id) DO UPDATE SET
                player_name = EXCLUDED.player_name,
                hostname = EXCLUDED.hostname,
                handle = EXCLUDED.handle,
                membership = EXCLUDED.membership,
                avatar_url = EXCLUDED.avatar_url,
                online = EXCLUDED.online,
                global_rank = EXCLUDED.global_rank,
                hunter_score = EXCLUDED.hunter_score,
                duration = EXCLUDED.duration,
                distance = EXCLUDED.distance,
                raw_json = EXCLUDED.raw_json,
                updated_at = NOW()'
        );

        $estProfileStmt = $this->pdo->prepare(
            'INSERT INTO gpt.est_profiles (
                user_id, player_name, hostname, handle, membership, avatar_url, online,
                global_rank, hunter_score, duration, distance, updated_at
             ) VALUES (
                :user_id, :player_name, :hostname, :handle, :membership, :avatar_url, CAST(NULLIF(:online, \'\') AS BOOLEAN),
                :global_rank, :hunter_score, :duration, :distance, NOW()
             )
             ON CONFLICT (user_id) DO UPDATE SET
                player_name = EXCLUDED.player_name,
                hostname = EXCLUDED.hostname,
                handle = EXCLUDED.handle,
                membership = EXCLUDED.membership,
                avatar_url = EXCLUDED.avatar_url,
                online = EXCLUDED.online,
                global_rank = EXCLUDED.global_rank,
                hunter_score = EXCLUDED.hunter_score,
                duration = EXCLUDED.duration,
                distance = EXCLUDED.distance,
                updated_at = NOW()'
        );

        $payloadStmt = $this->pdo->prepare(
            'INSERT INTO gpt.est_payloads (user_id, player_name, payload_json, updated_at)
             VALUES (:user_id, :player_name, CAST(:payload_json AS JSONB), NOW())
             ON CONFLICT (user_id) DO UPDATE SET
                player_name = EXCLUDED.player_name,
                payload_json = EXCLUDED.payload_json,
                updated_at = NOW()'
        );

        $deleteCollectables = $this->pdo->prepare('DELETE FROM gpt.est_collectables WHERE user_id = :user_id');
        $insertCollectable = $this->pdo->prepare(
            'INSERT INTO gpt.est_collectables (
                user_id, player_name, collectable_id, collected, max_value, sum_value, max_id, raw_json
             ) VALUES (
                :user_id, :player_name, :collectable_id, :collected, :max_value, :sum_value, :max_id, CAST(:raw_json AS JSONB)
             )'
        );

        $deleteWeapons = $this->pdo->prepare('DELETE FROM gpt.est_weapon_stats WHERE user_id = :user_id');
        $insertWeapon = $this->pdo->prepare(
            'INSERT INTO gpt.est_weapon_stats (
                user_id, player_name, weapon_id, ammo_id, tracks, hits, kills, misses, score, raw_json
             ) VALUES (
                :user_id, :player_name, :weapon_id, :ammo_id, :tracks, :hits, :kills, :misses, :score, CAST(:raw_json AS JSONB)
             )'
        );

        $deleteAnimals = $this->pdo->prepare('DELETE FROM gpt.est_animal_stats WHERE user_id = :user_id');
        $insertAnimal = $this->pdo->prepare(
            'INSERT INTO gpt.est_animal_stats (
                user_id, player_name, species_id, tracks, spots, kills, ethical_kills, raw_json
             ) VALUES (
                :user_id, :player_name, :species_id, :tracks, :spots, :kills, :ethical_kills, CAST(:raw_json AS JSONB)
             )'
        );

        $deleteMissions = $this->pdo->prepare('DELETE FROM gpt.est_daily_missions WHERE user_id = :user_id');
        $insertMission = $this->pdo->prepare(
            'INSERT INTO gpt.est_daily_missions (
                user_id, player_name, mission_id, mission_value, raw_json
             ) VALUES (
                :user_id, :player_name, :mission_id, :mission_value, CAST(:raw_json AS JSONB)
             )'
        );

        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $profileParams = [
            ':user_id' => $userId,
            ':player_name' => $hostname,
            ':hostname' => $hostname,
            ':handle' => $data['handle'] ?? null,
            ':membership' => $data['membership'] ?? null,
            ':avatar_url' => $data['avatar'] ?? null,
            ':online' => $this->normalizeBooleanString($data['online'] ?? null),
            ':global_rank' => $data['rank'] ?? null,
            ':hunter_score' => $data['hunterscore'] ?? null,
            ':duration' => $data['stats']['duration'] ?? null,
            ':distance' => $data['stats']['distance'] ?? null,
            ':raw_json' => $payloadJson,
        ];

        $this->pdo->beginTransaction();
        try {
            $profileStmt->execute($profileParams);
            $estProfileStmt->execute([
                ':user_id' => $userId,
                ':player_name' => $hostname,
                ':hostname' => $hostname,
                ':handle' => $data['handle'] ?? null,
                ':membership' => $data['membership'] ?? null,
                ':avatar_url' => $data['avatar'] ?? null,
                ':online' => $this->normalizeBooleanString($data['online'] ?? null),
                ':global_rank' => $data['rank'] ?? null,
                ':hunter_score' => $data['hunterscore'] ?? null,
                ':duration' => $data['stats']['duration'] ?? null,
                ':distance' => $data['stats']['distance'] ?? null,
            ]);
            $payloadStmt->execute([
                ':user_id' => $userId,
                ':player_name' => $hostname,
                ':payload_json' => $payloadJson,
            ]);

            $statsData = $data['stats']['data'] ?? [];

            $deleteCollectables->execute([':user_id' => $userId]);
            foreach (($statsData['collectables'] ?? []) as $collectableId => $item) {
                $insertCollectable->execute([
                    ':user_id' => $userId,
                    ':player_name' => $hostname,
                    ':collectable_id' => (int) $collectableId,
                    ':collected' => $item['collected'] ?? null,
                    ':max_value' => $this->toNumeric($item['max'] ?? null),
                    ':sum_value' => $this->toNumeric($item['sum'] ?? null),
                    ':max_id' => $item['max_id'] ?? null,
                    ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $deleteWeapons->execute([':user_id' => $userId]);
            foreach (($statsData['weapon'] ?? []) as $weaponId => $ammoList) {
                foreach ($ammoList as $ammoId => $item) {
                    $insertWeapon->execute([
                        ':user_id' => $userId,
                        ':player_name' => $hostname,
                        ':weapon_id' => (int) $weaponId,
                        ':ammo_id' => (int) $ammoId,
                        ':tracks' => $item['tracks'] ?? null,
                        ':hits' => $item['hits'] ?? null,
                        ':kills' => $item['kills'] ?? null,
                        ':misses' => $item['misses'] ?? null,
                        ':score' => $this->toNumeric($item['score'] ?? null),
                        ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }

            $deleteAnimals->execute([':user_id' => $userId]);
            foreach (($statsData['animal'] ?? []) as $speciesId => $item) {
                $insertAnimal->execute([
                    ':user_id' => $userId,
                    ':player_name' => $hostname,
                    ':species_id' => (int) $speciesId,
                    ':tracks' => $item['tracks'] ?? null,
                    ':spots' => $item['spots'] ?? null,
                    ':kills' => $item['kills'] ?? null,
                    ':ethical_kills' => $item['ethical_kills'] ?? null,
                    ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $deleteMissions->execute([':user_id' => $userId]);
            foreach (($statsData['daily_missions'] ?? []) as $missionId => $item) {
                $value = is_array($item) ? reset($item) : $item;
                $insertMission->execute([
                    ':user_id' => $userId,
                    ':player_name' => $hostname,
                    ':mission_id' => (int) $missionId,
                    ':mission_value' => $value === false ? null : $value,
                    ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$this->config['api']['user_agent']}\r\n",
                'ignore_errors' => true,
                'timeout' => $this->config['api']['timeout'],
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            throw new RuntimeException('No se pudo obtener respuesta de la API pública.');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('La API pública devolvio un JSON invalido.');
        }

        return $data;
    }

    private function getUserIdByPlayerName(string $playerName): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM gpt.tab_usuarios WHERE player_name = :player_name LIMIT 1'
        );
        $stmt->execute([':player_name' => $playerName]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    private function normalizeBooleanString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
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

    private function toNumeric(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
