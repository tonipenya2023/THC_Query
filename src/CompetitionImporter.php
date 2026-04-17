<?php

declare(strict_types=1);

final class CompetitionImporter
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

    public function importAll(): int
    {
        $items = $this->fetchCompetitions();

        $typeStmt = $this->pdo->prepare(
            'INSERT INTO gpt.comp_types (
                competition_type_id, type_name, description_short, rules_html, singleplayer, entrant_rules,
                attempts, point_type, image_full_url, image_class, raw_json, updated_at
             ) VALUES (
                :competition_type_id, :type_name, :description_short, :rules_html,
                CAST(NULLIF(:singleplayer, \'\') AS BOOLEAN), CAST(NULLIF(:entrant_rules, \'\') AS BOOLEAN),
                :attempts, :point_type, :image_full_url, :image_class, CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (competition_type_id) DO UPDATE SET
                type_name = EXCLUDED.type_name,
                description_short = EXCLUDED.description_short,
                rules_html = EXCLUDED.rules_html,
                singleplayer = EXCLUDED.singleplayer,
                entrant_rules = EXCLUDED.entrant_rules,
                attempts = EXCLUDED.attempts,
                point_type = EXCLUDED.point_type,
                image_full_url = EXCLUDED.image_full_url,
                image_class = EXCLUDED.image_class,
                raw_json = EXCLUDED.raw_json,
                updated_at = NOW()'
        );

        $competitionStmt = $this->pdo->prepare(
            'INSERT INTO gpt.comp_competitions (
                competition_id, competition_type_id, start_ts, end_ts, start_at, end_at, entrants, finished, raw_json, updated_at
             ) VALUES (
                :competition_id, :competition_type_id, :start_ts, :end_ts, :start_at, :end_at, :entrants,
                CAST(NULLIF(:finished, \'\') AS BOOLEAN), CAST(:raw_json AS JSONB), NOW()
             )
             ON CONFLICT (competition_id) DO UPDATE SET
                competition_type_id = EXCLUDED.competition_type_id,
                start_ts = EXCLUDED.start_ts,
                end_ts = EXCLUDED.end_ts,
                start_at = EXCLUDED.start_at,
                end_at = EXCLUDED.end_at,
                entrants = EXCLUDED.entrants,
                finished = EXCLUDED.finished,
                raw_json = EXCLUDED.raw_json,
                updated_at = NOW()'
        );

        $payloadStmt = $this->pdo->prepare(
            'INSERT INTO gpt.comp_payloads (competition_id, payload_json, updated_at)
             VALUES (:competition_id, CAST(:payload_json AS JSONB), NOW())
             ON CONFLICT (competition_id) DO UPDATE SET
                payload_json = EXCLUDED.payload_json,
                updated_at = NOW()'
        );

        $deleteSpecies = $this->pdo->prepare('DELETE FROM gpt.comp_type_species WHERE competition_type_id = :competition_type_id');
        $insertSpecies = $this->pdo->prepare(
            'INSERT INTO gpt.comp_type_species (competition_type_id, species_id)
             VALUES (:competition_type_id, :species_id)
             ON CONFLICT (competition_type_id, species_id) DO NOTHING'
        );

        $deletePrizes = $this->pdo->prepare('DELETE FROM gpt.comp_type_prizes WHERE competition_type_id = :competition_type_id');
        $insertPrize = $this->pdo->prepare(
            'INSERT INTO gpt.comp_type_prizes (competition_type_id, prize_position, raw_json)
             VALUES (:competition_type_id, :prize_position, CAST(:raw_json AS JSONB))'
        );

        $deleteRewards = $this->pdo->prepare('DELETE FROM gpt.comp_type_rewards WHERE competition_type_id = :competition_type_id');
        $insertReward = $this->pdo->prepare(
            'INSERT INTO gpt.comp_type_rewards (
                competition_type_id, prize_position, reward_position, reward_type, reward_define, reward_amount, raw_json
             ) VALUES (
                :competition_type_id, :prize_position, :reward_position, :reward_type, :reward_define, :reward_amount, CAST(:raw_json AS JSONB)
             )'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $type = is_array($item['type'] ?? null) ? $item['type'] : [];
                $typeId = isset($type['id']) ? (int) $type['id'] : null;
                $payloadJson = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $typeJson = json_encode($type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($typeId !== null) {
                    $typeStmt->execute([
                        ':competition_type_id' => $typeId,
                        ':type_name' => $type['name'] ?? null,
                        ':description_short' => $type['descriptionShort'] ?? null,
                        ':rules_html' => $type['rules'] ?? null,
                        ':singleplayer' => $this->normalizeBooleanString($type['singleplayer'] ?? null),
                        ':entrant_rules' => $this->normalizeBooleanString($type['entrantRules'] ?? null),
                        ':attempts' => $type['attempts'] ?? null,
                        ':point_type' => $type['pointType'] ?? null,
                        ':image_full_url' => $type['image']['full'] ?? null,
                        ':image_class' => $type['image']['class'] ?? null,
                        ':raw_json' => $typeJson,
                    ]);

                    $deleteSpecies->execute([':competition_type_id' => $typeId]);
                    foreach (($type['species'] ?? []) as $speciesId) {
                        $insertSpecies->execute([
                            ':competition_type_id' => $typeId,
                            ':species_id' => (int) $speciesId,
                        ]);
                    }

                    $deletePrizes->execute([':competition_type_id' => $typeId]);
                    $deleteRewards->execute([':competition_type_id' => $typeId]);
                    foreach (array_values($type['prizes'] ?? []) as $prizeIndex => $prize) {
                        $prizePosition = $prizeIndex + 1;
                        $insertPrize->execute([
                            ':competition_type_id' => $typeId,
                            ':prize_position' => $prizePosition,
                            ':raw_json' => json_encode($prize, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);

                        foreach (array_values($prize['rewards'] ?? []) as $rewardIndex => $reward) {
                            $insertReward->execute([
                                ':competition_type_id' => $typeId,
                                ':prize_position' => $prizePosition,
                                ':reward_position' => $rewardIndex + 1,
                                ':reward_type' => $reward['type'] ?? null,
                                ':reward_define' => $reward['define'] ?? null,
                                ':reward_amount' => $this->toNumeric($reward['amount'] ?? null),
                                ':raw_json' => json_encode($reward, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ]);
                        }
                    }
                }

                $competitionStmt->execute([
                    ':competition_id' => $item['id'] ?? null,
                    ':competition_type_id' => $typeId,
                    ':start_ts' => $this->normalizeUnixTimestamp($item['start'] ?? null),
                    ':end_ts' => $this->normalizeUnixTimestamp($item['end'] ?? null),
                    ':start_at' => $this->unixToTimestamp($item['start'] ?? null),
                    ':end_at' => $this->unixToTimestamp($item['end'] ?? null),
                    ':entrants' => $item['entrants'] ?? null,
                    ':finished' => $this->normalizeBooleanString($item['finished'] ?? null),
                    ':raw_json' => $payloadJson,
                ]);

                $payloadStmt->execute([
                    ':competition_id' => $item['id'] ?? null,
                    ':payload_json' => $payloadJson,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return count($items);
    }

    private function fetchCompetitions(): array
    {
        $url = 'https://api.thehunter.com/v1/Page_content/list_competitions';
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
            throw new RuntimeException('No se pudo obtener la lista de competiciones.');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('La API de competiciones devolvio un JSON invalido.');
        }

        return $data;
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
}
