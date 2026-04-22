<?php

declare(strict_types=1);

final class GalleryImporter
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
            throw new RuntimeException('user_id invalido para importar galeria.');
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
            $payload = $this->fetchGalleryPage($userId, $offset, $pageSize);
            $rows = $payload['photos'] ?? null;

            if (!is_array($rows)) {
                throw new RuntimeException('La API de galeria no devolvio photos valida.');
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

        $deleteStmt = $this->pdo->prepare('DELETE FROM gpt.user_gallery WHERE user_id = :user_id');
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO gpt.user_gallery (
                gallery_entry_id, user_id, player_name, label, photo_url, thumbnail_url,
                photo_type, animal_id, species_id, species_name, score_type, score_value,
                raw_json, updated_at
             ) VALUES (
                :gallery_entry_id, :user_id, :player_name, :label, :photo_url, :thumbnail_url,
                :photo_type, :animal_id, :species_id, :species_name, :score_type, :score_value,
                CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (gallery_entry_id) DO UPDATE SET
                user_id = EXCLUDED.user_id,
                player_name = EXCLUDED.player_name,
                label = EXCLUDED.label,
                photo_url = EXCLUDED.photo_url,
                thumbnail_url = EXCLUDED.thumbnail_url,
                photo_type = EXCLUDED.photo_type,
                animal_id = EXCLUDED.animal_id,
                species_id = EXCLUDED.species_id,
                species_name = EXCLUDED.species_name,
                score_type = EXCLUDED.score_type,
                score_value = EXCLUDED.score_value,
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

                $animal = is_array($row['animal'] ?? null) ? $row['animal'] : [];

                $insertStmt->execute([
                    ':gallery_entry_id' => $entryId,
                    ':user_id' => $userId,
                    ':player_name' => $playerName,
                    ':label' => $this->normalizeNullableString($row['label'] ?? null),
                    ':photo_url' => $this->normalizeNullableString($row['url'] ?? null),
                    ':thumbnail_url' => $this->normalizeNullableString($row['thumbnail'] ?? null),
                    ':photo_type' => isset($row['type']) ? (int) $row['type'] : null,
                    ':animal_id' => isset($animal['id']) ? (int) $animal['id'] : null,
                    ':species_id' => isset($animal['species_id']) ? (int) $animal['species_id'] : null,
                    ':species_name' => $this->normalizeNullableString($animal['species'] ?? null),
                    ':score_type' => isset($animal['scoreType']) ? (int) $animal['scoreType'] : null,
                    ':score_value' => $this->normalizeNullableNumeric($animal['score'] ?? null),
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

    private function fetchGalleryPage(int $userId, int $offset, int $limit): array
    {
        $query = http_build_query([
            'user_id' => $userId,
            'offset' => max(0, $offset),
            'limit' => max(1, $limit),
        ]);

        $url = 'https://api.thehunter.com/v1/Gallery/list?' . $query;
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
            throw new RuntimeException('No se pudo obtener respuesta de la API de galeria.');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('La API de galeria devolvio un JSON invalido.');
        }

        if (($data['error'] ?? null) !== null) {
            $code = (string) ($data['code'] ?? 'unknown');
            throw new RuntimeException('La API de galeria devolvio error: ' . $code);
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

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeNullableNumeric(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric((string) $value) ? (string) $value : null;
    }
}
