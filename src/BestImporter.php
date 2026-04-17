<?php

declare(strict_types=1);

final class BestImporter
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

    public function importUser(int $userId, int $seasonNo = 0): int
    {
        $payload = $this->fetchPersonalLeaderboard($userId, $seasonNo);
        $rows = $payload['data'] ?? null;

        if (!is_array($rows)) {
            throw new RuntimeException('La API de mejores marcas no devolvio data valida.');
        }

        $playerName = $this->getPlayerName($userId);
        $this->pdo->beginTransaction();

        try {
            foreach ($rows as $row) {
                $speciesId = isset($row['specie']) ? (int) $row['specie'] : null;
                if ($speciesId === null) {
                    continue;
                }

                [$speciesName, $speciesNameEs] = $this->getSpeciesNames($speciesId);
                $this->saveRecord($userId, $playerName, $speciesId, $speciesName, $speciesNameEs, $row);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return count($rows);
    }

    private function fetchPersonalLeaderboard(int $userId, int $seasonNo): array
    {
        $query = http_build_query([
            'user_id' => $userId,
            'season_no' => $seasonNo,
        ]);

        $getUrl = 'https://api.thehunter.com/v1/Leaderboard/personal?' . $query;
        return $this->fetchJson($getUrl);
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
            throw new RuntimeException('No se pudo obtener respuesta de la API de mejores marcas.');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('La API de mejores marcas devolvio un JSON invalido.');
        }

        return $data;
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

    private function getSpeciesNames(int $speciesId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT especie, especie_es FROM gpt.tab_especies WHERE id_especie = :species_id LIMIT 1'
        );
        $stmt->execute([':species_id' => $speciesId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return [null, null];
        }

        return [
            $row['especie'] ?? null,
            $row['especie_es'] ?? null,
        ];
    }

    private function saveRecord(
        int $userId,
        ?string $playerName,
        int $speciesId,
        ?string $speciesName,
        ?string $speciesNameEs,
        array $row
    ): void {
        $distance = is_array($row['distance'] ?? null) ? $row['distance'] : [];
        $score = is_array($row['score'] ?? null) ? $row['score'] : [];

        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.best_personal_records (
                user_id, player_name, species_id, species_name, species_name_es,
                best_distance_raw, best_distance_m, best_distance_score, best_distance_weapon_id,
                best_distance_animal_id, best_distance_gender, best_distance_texture,
                best_distance_confirm_ts, best_distance_confirm_at,
                best_score_value, best_score_distance_raw, best_score_distance_m, best_score_weapon_id,
                best_score_animal_id, best_score_gender, best_score_texture,
                best_score_confirm_ts, best_score_confirm_at, raw_json, updated_at
             ) VALUES (
                :user_id, :player_name, :species_id, :species_name, :species_name_es,
                :best_distance_raw, :best_distance_m, :best_distance_score, :best_distance_weapon_id,
                :best_distance_animal_id, :best_distance_gender, :best_distance_texture,
                :best_distance_confirm_ts, :best_distance_confirm_at,
                :best_score_value, :best_score_distance_raw, :best_score_distance_m, :best_score_weapon_id,
                :best_score_animal_id, :best_score_gender, :best_score_texture,
                :best_score_confirm_ts, :best_score_confirm_at, CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (user_id, species_id) DO UPDATE SET
                player_name = EXCLUDED.player_name,
                species_name = EXCLUDED.species_name,
                species_name_es = EXCLUDED.species_name_es,
                best_distance_raw = EXCLUDED.best_distance_raw,
                best_distance_m = EXCLUDED.best_distance_m,
                best_distance_score = EXCLUDED.best_distance_score,
                best_distance_weapon_id = EXCLUDED.best_distance_weapon_id,
                best_distance_animal_id = EXCLUDED.best_distance_animal_id,
                best_distance_gender = EXCLUDED.best_distance_gender,
                best_distance_texture = EXCLUDED.best_distance_texture,
                best_distance_confirm_ts = EXCLUDED.best_distance_confirm_ts,
                best_distance_confirm_at = EXCLUDED.best_distance_confirm_at,
                best_score_value = EXCLUDED.best_score_value,
                best_score_distance_raw = EXCLUDED.best_score_distance_raw,
                best_score_distance_m = EXCLUDED.best_score_distance_m,
                best_score_weapon_id = EXCLUDED.best_score_weapon_id,
                best_score_animal_id = EXCLUDED.best_score_animal_id,
                best_score_gender = EXCLUDED.best_score_gender,
                best_score_texture = EXCLUDED.best_score_texture,
                best_score_confirm_ts = EXCLUDED.best_score_confirm_ts,
                best_score_confirm_at = EXCLUDED.best_score_confirm_at,
                raw_json = EXCLUDED.raw_json,
                updated_at = NOW()'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':player_name' => $playerName,
            ':species_id' => $speciesId,
            ':species_name' => $speciesName,
            ':species_name_es' => $speciesNameEs,
            ':best_distance_raw' => $distance['distance'] ?? null,
            ':best_distance_m' => $this->distanceToMeters($distance['distance'] ?? null),
            ':best_distance_score' => $this->toNumeric($distance['score'] ?? null),
            ':best_distance_weapon_id' => $distance['weapon_id'] ?? null,
            ':best_distance_animal_id' => $distance['animal_id'] ?? null,
            ':best_distance_gender' => $distance['gender'] ?? null,
            ':best_distance_texture' => $distance['texture'] ?? null,
            ':best_distance_confirm_ts' => $this->normalizeUnixTimestamp($distance['confirm_ts'] ?? null),
            ':best_distance_confirm_at' => $this->unixToTimestamp($distance['confirm_ts'] ?? null),
            ':best_score_value' => $this->toNumeric($score['score'] ?? null),
            ':best_score_distance_raw' => $score['distance'] ?? null,
            ':best_score_distance_m' => $this->distanceToMeters($score['distance'] ?? null),
            ':best_score_weapon_id' => $score['weapon_id'] ?? null,
            ':best_score_animal_id' => $score['animal_id'] ?? null,
            ':best_score_gender' => $score['gender'] ?? null,
            ':best_score_texture' => $score['texture'] ?? null,
            ':best_score_confirm_ts' => $this->normalizeUnixTimestamp($score['confirm_ts'] ?? null),
            ':best_score_confirm_at' => $this->unixToTimestamp($score['confirm_ts'] ?? null),
            ':raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function normalizeUnixTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) floor((float) $value);
    }

    private function unixToTimestamp(mixed $value): ?string
    {
        $timestamp = $this->normalizeUnixTimestamp($value);
        if ($timestamp === null) {
            return null;
        }

        return gmdate('Y-m-d H:i:sP', $timestamp);
    }

    private function toNumeric(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function distanceToMeters(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format(((float) $value) / 1000, 3, '.', '');
    }
}
