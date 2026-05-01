<?php

declare(strict_types=1);

final class TrophyImporter
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

    public function importUser(int $userId, ?string $playerName = null, int $pageSize = 24): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('user_id invalido para importar trofeos.');
        }

        if ($pageSize <= 0) {
            $pageSize = 24;
        }

        $playerName = $playerName !== null && trim($playerName) !== ''
            ? trim($playerName)
            : $this->getPlayerName($userId);

        $offset = 0;
        $allRows = [];

        while (true) {
            $payload = $this->fetchTrophiesPage($userId, $offset, $pageSize);
            $rows = $payload['trophies'] ?? null;

            if (!is_array($rows)) {
                throw new RuntimeException('La API de trofeos no devolvio trophies valida.');
            }

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $allRows[] = $row;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }

            $offset += $pageSize;
        }

        $deleteStmt = $this->pdo->prepare('DELETE FROM gpt.user_trophies WHERE user_id = :user_id');
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO gpt.user_trophies (
                trophy_entry_id, user_id, player_name, trophy_id, trophy_name,
                competition_id, competition_name, image_url, trophy_ts, trophy_at, raw_json, updated_at
             ) VALUES (
                :trophy_entry_id, :user_id, :player_name, :trophy_id, :trophy_name,
                :competition_id, :competition_name, :image_url, :trophy_ts, :trophy_at, CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (trophy_entry_id) DO UPDATE SET
                user_id = EXCLUDED.user_id,
                player_name = EXCLUDED.player_name,
                trophy_id = EXCLUDED.trophy_id,
                trophy_name = EXCLUDED.trophy_name,
                competition_id = EXCLUDED.competition_id,
                competition_name = EXCLUDED.competition_name,
                image_url = EXCLUDED.image_url,
                trophy_ts = EXCLUDED.trophy_ts,
                trophy_at = EXCLUDED.trophy_at,
                raw_json = EXCLUDED.raw_json,
                updated_at = NOW()'
        );

        $this->pdo->beginTransaction();
        try {
            $deleteStmt->execute([':user_id' => $userId]);

            foreach ($allRows as $row) {
                $entryId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($entryId <= 0) {
                    continue;
                }

                $insertStmt->execute([
                    ':trophy_entry_id' => $entryId,
                    ':user_id' => $userId,
                    ':player_name' => $playerName,
                    ':trophy_id' => isset($row['trophy_id']) ? (int) $row['trophy_id'] : null,
                    ':trophy_name' => $row['name'] ?? null,
                    ':competition_id' => isset($row['competition_id']) ? (int) $row['competition_id'] : null,
                    ':competition_name' => $row['competition_name'] ?? null,
                    ':image_url' => $row['image'] ?? null,
                    ':trophy_ts' => $this->normalizeUnixTimestamp($row['ts'] ?? null),
                    ':trophy_at' => $this->unixToTimestamp($row['ts'] ?? null),
                    ':raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return count($allRows);
    }

    private function fetchTrophiesPage(int $userId, int $offset, int $limit): array
    {
        $query = http_build_query([
            'user_id' => $userId,
            'offset' => max(0, $offset),
            'limit' => max(1, $limit),
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
                'ignore_errors' => true,
                'timeout' => $this->config['api']['timeout'],
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            throw new RuntimeException('No se pudo obtener respuesta de la API de trofeos.');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('La API de trofeos devolvio un JSON invalido.');
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

    private function normalizeUnixTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) floor((float) $value);
    }

    private function unixToTimestamp(mixed $value): ?string
    {
        $ts = $this->normalizeUnixTimestamp($value);
        if ($ts === null) {
            return null;
        }

        return gmdate('Y-m-d H:i:sP', $ts);
    }
}
