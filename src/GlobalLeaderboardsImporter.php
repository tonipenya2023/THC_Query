<?php

declare(strict_types=1);

final class GlobalLeaderboardsImporter
{
    private PDO $pdo;
    private array $config;
    /** @var array<int, string> */
    private array $lastErrors = [];

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

    /**
     * @param array<string> $types
     * @return array<string, int|string>
     */
    public function importAll(
        array $types = ['score', 'range'],
        int $limit = 100,
        ?int $onlySpeciesId = null,
        string $speciesSource = 'auto'
    ): array
    {
        $types = array_values(array_filter($types, static fn (string $t): bool => in_array($t, ['score', 'range'], true)));
        if ($types === []) {
            throw new InvalidArgumentException('Debes indicar al menos un tipo: score o range.');
        }

        $source = strtolower(trim($speciesSource));
        if (!in_array($source, ['auto', 'summary', 'table'], true)) {
            $source = 'auto';
        }

        if ($source === 'summary') {
            $species = $this->loadSpeciesFromSummary();
            if ($species === []) {
                $species = $this->loadSpeciesFromDbFallback();
            }
        } elseif ($source === 'table') {
            $species = $this->loadSpeciesFromDbFallback();
        } else {
            $species = $this->loadSpeciesFromSummary();
            if ($species === []) {
                $species = $this->loadSpeciesFromDbFallback();
            }
        }
        if ($onlySpeciesId !== null) {
            $species = array_values(
                array_filter(
                    $species,
                    static fn (array $item): bool => (int) $item['species_id'] === $onlySpeciesId
                )
            );
        }

        if ($species === []) {
            throw new RuntimeException('No hay especies disponibles para importar leaderboard.');
        }

        $snapshotAt = gmdate('Y-m-d H:i:sP');
        $result = [
            'species' => count($species),
            'score_rows' => 0,
            'range_rows' => 0,
            'errors' => 0,
            'snapshot_at' => $snapshotAt,
            'error_samples' => [],
        ];
        $this->lastErrors = [];

        foreach ($types as $type) {
            foreach ($species as $item) {
                try {
                    $rows = $this->fetchSpeciesLeaderboard(
                        $type,
                        (int) $item['species_id'],
                        (int) $item['leaderboard_code'],
                        $limit
                    );
                    $normalized = $this->normalizeRows($rows, $type, $limit);
                    $this->saveSpeciesRows(
                        $snapshotAt,
                        $type,
                        (int) $item['species_id'],
                        $item['species_name'],
                        $item['species_name_es'],
                        $normalized
                    );
                    $key = $type . '_rows';
                    $result[$key] = (int) $result[$key] + count($normalized);
                } catch (Throwable $e) {
                    $result['errors'] = (int) $result['errors'] + 1;
                    $sample = "{$type} species_id={$item['species_id']}: {$e->getMessage()}";
                    if (count($this->lastErrors) < 30) {
                        $this->lastErrors[] = $sample;
                    }
                }
            }
        }

        $result['error_samples'] = $this->lastErrors;

        return $result;
    }

    private function summaryUrl(): string
    {
        $url = $this->config['leaderboards']['summary_url'] ?? null;
        return is_string($url) && $url !== '' ? $url : 'https://api.thehunter.com/v1/Page_content/leaderboards_all';
    }

    private function detailsUrl(): string
    {
        $url = $this->config['leaderboards']['details_url'] ?? null;
        return is_string($url) && $url !== '' ? $url : 'https://api.thehunter.com/v1/Page_content/leaderboard_details';
    }

    /**
     * @return array<int, array{species_id:int,leaderboard_code:int,species_name:?string,species_name_es:?string}>
     */
    private function loadSpeciesFromSummary(): array
    {
        $species = [];

        try {
            $payload = $this->fetchJsonGet($this->summaryUrl());
            [$rows, $matched] = $this->extractRows($payload);
            if (!$matched || $rows === []) {
                return [];
            }

            foreach ($rows as $row) {
                $code = $this->toInt(
                    $row['species_id']
                    ?? $row['specie']
                    ?? $row['species']
                    ?? $row['animal']
                    ?? $row['id']
                    ?? null
                );
                if ($code === null || $code <= 0) {
                    continue;
                }

                $species[] = [
                    'species_id' => $code,
                    'leaderboard_code' => $code,
                    'species_name' => $this->toString($row['species_name'] ?? $row['name'] ?? $row['specie_name'] ?? null),
                    'species_name_es' => $this->toString($row['species_name_es'] ?? $row['name_es'] ?? null),
                ];
            }
        } catch (Throwable) {
            return [];
        }

        $unique = [];
        foreach ($species as $item) {
            $unique[$item['species_id']] = $item;
        }
        ksort($unique);
        return array_values($unique);
    }

    /**
     * @return array<int, array{species_id:int,leaderboard_code:int,species_name:?string,species_name_es:?string}>
     */
    private function loadSpeciesFromDbFallback(): array
    {
        $species = [];

        try {
            $rows = $this->pdo->query(
                'SELECT id_especie, especie, especie_es
                 FROM gpt.tab_especies
                 ORDER BY id_especie'
            )->fetchAll();

            foreach ($rows as $row) {
                $species[] = [
                    'species_id' => (int) $row['id_especie'],
                    'leaderboard_code' => (int) $row['id_especie'],
                    'species_name' => isset($row['especie']) ? (string) $row['especie'] : null,
                    'species_name_es' => isset($row['especie_es']) ? (string) $row['especie_es'] : null,
                ];
            }
        } catch (Throwable) {
            // fallback abajo
        }

        if ($species !== []) {
            return $species;
        }

        $rows = $this->pdo->query(
            'SELECT DISTINCT species_id
             FROM gpt.exp_animal_stats
             WHERE species_id IS NOT NULL
             ORDER BY species_id'
        )->fetchAll();

        foreach ($rows as $row) {
            $species[] = [
                'species_id' => (int) $row['species_id'],
                'leaderboard_code' => (int) $row['species_id'],
                'species_name' => null,
                'species_name_es' => null,
            ];
        }

        return $species;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSpeciesLeaderboard(string $type, int $speciesId, int $leaderboardCode, int $limit): array
    {
        $errors = [];

        $detailsUrl = $this->detailsUrl();
        $payloadCandidates = $this->buildDetailPayloadCandidates($type, $leaderboardCode, $limit);
        foreach ($payloadCandidates as $candidate) {
            try {
                if (($candidate['__format'] ?? 'json') === 'form') {
                    $payload = $this->fetchJsonPostForm($detailsUrl, $candidate['payload']);
                } else {
                    $payload = $this->fetchJsonPostJson($detailsUrl, $candidate['payload']);
                }
                [$rows, $matched] = $this->extractRows($payload);
                if ($matched) {
                    return $rows;
                }

                $meta = [];
                foreach (['message', 'error', 'status', 'code'] as $k) {
                    if (isset($payload[$k])) {
                        $meta[] = $k . '=' . (is_scalar($payload[$k]) ? (string) $payload[$k] : '[obj]');
                    }
                }
                if ($meta !== []) {
                    $errors[] = 'payload sin filas (' . implode(', ', $meta) . ')';
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $templates = $this->config['leaderboards'][$type . '_templates'] ?? null;
        if (is_array($templates) && $templates !== []) {
            foreach ($templates as $template) {
                $url = str_replace(
                    ['{species_id}', '{limit}'],
                    [(string) $speciesId, (string) $limit],
                    (string) $template
                );

                try {
                    $payload = $this->fetchJsonGet($url);
                    [$rows, $matched] = $this->extractRows($payload);

                    if ($matched) {
                        return $rows;
                    }
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $suffix = $errors === [] ? '' : ' - ' . implode(' | ', array_slice($errors, 0, 3));
        throw new RuntimeException("No se pudo resolver endpoint para {$type} species_id={$speciesId}{$suffix}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDetailPayloadCandidates(string $type, int $code, int $limit): array
    {
        $mode = $type === 'range' ? 'range' : 'score';
        $typeCodePrimary = $type === 'score' ? 1 : 2;
        $typeCodeFallback = $type === 'score' ? 2 : 1;
        $typeCodeExtra = $type === 'score' ? 0 : 3;

        return [
            ['__format' => 'form', 'payload' => ['type' => (string) $typeCodePrimary, 'species' => (string) $code]],
            ['__format' => 'form', 'payload' => ['type' => (string) $typeCodeFallback, 'species' => (string) $code]],
            ['__format' => 'form', 'payload' => ['type' => (string) $typeCodeExtra, 'species' => (string) $code]],
            ['__format' => 'form', 'payload' => ['type' => $mode, 'species' => (string) $code]],
            ['__format' => 'form', 'payload' => ['type' => (string) $typeCodePrimary, 'specie' => (string) $code]],
            ['__format' => 'form', 'payload' => ['type' => (string) $typeCodePrimary, 'species_id' => (string) $code]],
            ['__format' => 'form', 'payload' => ['type' => (string) $typeCodePrimary, 'species' => (string) $code, 'limit' => (string) $limit, 'offset' => '0']],
            ['__format' => 'json', 'payload' => ['type' => $typeCodePrimary, 'species' => $code]],
            ['__format' => 'json', 'payload' => ['type' => $typeCodeExtra, 'species' => $code]],
            ['__format' => 'json', 'payload' => ['type' => $mode, 'species' => $code]],
            ['__format' => 'json', 'payload' => ['species_id' => $code, 'type' => $mode, 'limit' => $limit, 'offset' => 0]],
            ['__format' => 'json', 'payload' => ['specie' => $code, 'type' => $mode, 'limit' => $limit, 'offset' => 0]],
        ];
    }

    private function fetchJsonGet(string $url): array
    {
        return $this->fetchJson($url, 'GET', null, "User-Agent: {$this->config['api']['user_agent']}\r\n");
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function fetchJsonPostJson(string $url, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('No se pudo serializar payload de leaderboard_details.');
        }

        $headers = "User-Agent: {$this->config['api']['user_agent']}\r\n"
            . "Content-Type: application/json\r\n"
            . "Accept: application/json\r\n";

        return $this->fetchJson($url, 'POST', $json, $headers);
    }

    /**
     * @param array<string, scalar> $payload
     */
    private function fetchJsonPostForm(string $url, array $payload): array
    {
        $body = http_build_query($payload);
        $headers = "User-Agent: {$this->config['api']['user_agent']}\r\n"
            . "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n"
            . "Accept: application/json\r\n";

        return $this->fetchJson($url, 'POST', $body, $headers);
    }

    private function fetchJson(string $url, string $method, ?string $content, string $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $content ?? '',
                'ignore_errors' => true,
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

    /**
     * @param array<string,mixed> $payload
     * @return array{0: array<int, array<string,mixed>>, 1: bool}
     */
    private function extractRows(array $payload): array
    {
        if (array_is_list($payload) && $payload !== []) {
            $rows = array_values(array_filter($payload, 'is_array'));
            return [$rows, $rows !== []];
        }

        $candidates = ['data', 'items', 'entries', 'leaderboard', 'rankings', 'results', 'rows', 'records'];

        foreach ($candidates as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $rows = array_values(array_filter($value, 'is_array'));
                    return [$rows, true];
                }

                foreach ($candidates as $subKey) {
                    $sub = $value[$subKey] ?? null;
                    if (is_array($sub) && array_is_list($sub)) {
                        $rows = array_values(array_filter($sub, 'is_array'));
                        return [$rows, true];
                    }
                }
            }
        }

        return [[], false];
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows, string $type, int $limit): array
    {
        $out = [];
        foreach (array_values($rows) as $index => $row) {
            $rank = $this->toInt($row['rank'] ?? $row['position'] ?? $row['pos'] ?? ($index + 1));
            if ($rank === null || $rank < 1 || $rank > $limit) {
                continue;
            }

            $userBlock = is_array($row['user'] ?? null) ? $row['user'] : [];
            $scoreRaw = $this->firstNonNull($row, ['score', 'value', 'points']);
            $distanceRaw = $this->firstNonNull($row, ['distance', 'range', 'value', 'score']);
            $confirmTs = $this->toInt($row['confirm_ts'] ?? $row['confirmTs'] ?? $row['ts'] ?? null);

            $out[] = [
                'rank_pos' => $rank,
                'user_id' => $this->toInt($row['user_id'] ?? $row['userId'] ?? $row['id'] ?? ($userBlock['id'] ?? null)),
                'player_name' => $this->toString($row['player_name'] ?? $row['playerName'] ?? $row['hostname'] ?? $row['name'] ?? $row['player'] ?? ($userBlock['handle'] ?? $userBlock['name'] ?? null)),
                'value_numeric' => $type === 'score' ? $this->toNumeric($scoreRaw) : null,
                'distance_m' => $type === 'range' ? $this->normalizeDistance($distanceRaw) : null,
                'animal_id' => $this->toInt($row['animal_id'] ?? $row['animalId'] ?? null),
                'weapon_id' => $this->toInt($row['weapon_id'] ?? $row['weaponId'] ?? null),
                'gender' => $this->toInt($row['gender'] ?? null),
                'texture' => $this->toInt($row['texture'] ?? null),
                'confirm_ts' => $confirmTs,
                'confirm_at' => $confirmTs === null ? null : gmdate('Y-m-d H:i:sP', $confirmTs),
                'raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'mark_url' => $this->buildMarkUrl($row),
            ];
        }

        usort($out, static fn (array $a, array $b): int => (int) $a['rank_pos'] <=> (int) $b['rank_pos']);
        return $out;
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     */
    private function saveSpeciesRows(
        string $snapshotAt,
        string $type,
        int $speciesId,
        ?string $speciesName,
        ?string $speciesNameEs,
        array $rows
    ): void {
        $deleteLatest = $this->pdo->prepare(
            'DELETE FROM gpt.clas_rankings_latest WHERE leaderboard_type = :leaderboard_type AND species_id = :species_id'
        );

        $insertLatest = $this->pdo->prepare(
            'INSERT INTO gpt.clas_rankings_latest (
                leaderboard_type, species_id, species_name, species_name_es, rank_pos,
                user_id, player_name, value_numeric, distance_m, animal_id, weapon_id,
                gender, texture, confirm_ts, confirm_at, leaderboard_url, mark_url, snapshot_at, raw_json, updated_at
             ) VALUES (
                :leaderboard_type, :species_id, :species_name, :species_name_es, :rank_pos,
                :user_id, :player_name, :value_numeric, :distance_m, :animal_id, :weapon_id,
                :gender, :texture, :confirm_ts, :confirm_at, :leaderboard_url, :mark_url, :snapshot_at, CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (leaderboard_type, species_id, rank_pos) DO UPDATE SET
                species_name = EXCLUDED.species_name,
                species_name_es = EXCLUDED.species_name_es,
                user_id = EXCLUDED.user_id,
                player_name = EXCLUDED.player_name,
                value_numeric = EXCLUDED.value_numeric,
                distance_m = EXCLUDED.distance_m,
                animal_id = EXCLUDED.animal_id,
                weapon_id = EXCLUDED.weapon_id,
                gender = EXCLUDED.gender,
                texture = EXCLUDED.texture,
                confirm_ts = EXCLUDED.confirm_ts,
                confirm_at = EXCLUDED.confirm_at,
                leaderboard_url = EXCLUDED.leaderboard_url,
                mark_url = EXCLUDED.mark_url,
                snapshot_at = EXCLUDED.snapshot_at,
                raw_json = EXCLUDED.raw_json,
                updated_at = NOW()'
        );

        $insertHistory = $this->pdo->prepare(
            'INSERT INTO gpt.clas_rankings_history (
                snapshot_at, leaderboard_type, species_id, species_name, species_name_es, rank_pos,
                user_id, player_name, value_numeric, distance_m, animal_id, weapon_id,
                gender, texture, confirm_ts, confirm_at, leaderboard_url, mark_url, raw_json
             ) VALUES (
                :snapshot_at, :leaderboard_type, :species_id, :species_name, :species_name_es, :rank_pos,
                :user_id, :player_name, :value_numeric, :distance_m, :animal_id, :weapon_id,
                :gender, :texture, :confirm_ts, :confirm_at, :leaderboard_url, :mark_url, CAST(:raw_json AS JSONB)
             )
             ON CONFLICT (snapshot_at, leaderboard_type, species_id, rank_pos) DO NOTHING'
        );

        $this->pdo->beginTransaction();
        try {
            $deleteLatest->execute([
                ':leaderboard_type' => $type,
                ':species_id' => $speciesId,
            ]);

            foreach ($rows as $row) {
                $params = [
                    ':snapshot_at' => $snapshotAt,
                    ':leaderboard_type' => $type,
                    ':species_id' => $speciesId,
                    ':species_name' => $speciesName,
                    ':species_name_es' => $speciesNameEs,
                    ':rank_pos' => $row['rank_pos'],
                    ':user_id' => $row['user_id'],
                    ':player_name' => $row['player_name'],
                    ':value_numeric' => $row['value_numeric'],
                    ':distance_m' => $row['distance_m'],
                    ':animal_id' => $row['animal_id'],
                    ':weapon_id' => $row['weapon_id'],
                    ':gender' => $row['gender'],
                    ':texture' => $row['texture'],
                    ':confirm_ts' => $row['confirm_ts'],
                    ':confirm_at' => $row['confirm_at'],
                    ':leaderboard_url' => $this->buildLeaderboardUrl($type, $speciesId),
                    ':mark_url' => $row['mark_url'] ?? null,
                    ':raw_json' => $row['raw_json'],
                ];

                $insertLatest->execute($params);
                $insertHistory->execute($params);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private function firstNonNull(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric((string) $value)) {
            return null;
        }

        return (int) floor((float) $value);
    }

    private function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function toNumeric(mixed $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric((string) $value)) {
            return null;
        }

        return (string) $value;
    }

    private function buildLeaderboardUrl(string $type, int $speciesId): string
    {
        $kind = $type === 'range' ? 'range' : 'score';
        return 'https://www.thehunter.com/#leaderboards/' . $kind . '/' . $speciesId;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildMarkUrl(array $row): ?string
    {
        $direct = $this->toString($row['url'] ?? $row['link'] ?? $row['permalink'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $animalId = $this->toInt($row['animal_id'] ?? $row['animalId'] ?? $row['id'] ?? null);
        if ($animalId === null || $animalId <= 0) {
            return null;
        }

        return 'https://www.thehunter.com/#animal/' . $animalId;
    }

    private function normalizeDistance(mixed $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric((string) $value)) {
            return null;
        }

        $float = (float) $value;
        if ($float > 1000) {
            $float /= 1000;
        }

        return number_format($float, 3, '.', '');
    }
}
