<?php

declare(strict_types=1);

final class CompetitionJoiner
{
    private array $config;
    private PDO $pdo;

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
     * @return array{player:string,total:int,joined:int,already:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function joinAllAvailable(string $playerName, bool $skipAttempted = false): array
    {
        $playerName = trim($playerName);
        if ($playerName === '') {
            throw new InvalidArgumentException('El jugador es obligatorio.');
        }

        $cookie = $this->resolveCookieForPlayer($playerName);
        if ($cookie === '') {
            throw new RuntimeException(
                'No se encontro cookie de sesion de theHunter. Define THC_THEHUNTER_COOKIE o THC_THEHUNTER_COOKIE_' . $this->envSuffix($playerName)
            );
        }

        $items = $this->fetchCompetitions();
        $details = [];
        $joined = 0;
        $already = 0;
        $failed = 0;

        foreach ($items as $item) {
            $competitionId = isset($item['id']) ? (int) $item['id'] : 0;
            if ($competitionId <= 0) {
                continue;
            }

            if ($skipAttempted && $this->hasPreviousAttempt($playerName, $competitionId)) {
                $details[] = [
                    'competition_id' => $competitionId,
                    'competition_name' => (string) (($item['type']['name'] ?? '') ?: ''),
                    'status' => 'skipped',
                    'method' => '-',
                    'param' => '-',
                    'response' => 'Saltada por intento previo',
                ];
                continue;
            }

            $typeName = (string) (($item['type']['name'] ?? '') ?: '');
            $eligibility = $this->resolveCompetitionEligibility($playerName, $item);
            if ($eligibility !== null && ($eligibility['eligible'] ?? false) !== true) {
                $result = [
                    'competition_id' => $competitionId,
                    'competition_name' => $typeName,
                    'status' => 'ineligible',
                    'method' => '-',
                    'param' => '-',
                    'response' => $eligibility['reason'],
                ];
                $details[] = $result;
                $this->storeResult($playerName, $result);
                $failed++;
                continue;
            }

            $result = $this->joinCompetition($competitionId, $cookie);
            $result['competition_id'] = $competitionId;
            $result['competition_name'] = $typeName;
            $details[] = $result;
            $this->storeResult($playerName, $result);

            if (($result['status'] ?? '') === 'joined') {
                $joined++;
            } elseif (($result['status'] ?? '') === 'already_joined') {
                $already++;
            } else {
                $failed++;
            }

            if (($result['status'] ?? '') === 'auth_error') {
                break;
            }
        }

        return [
            'player' => $playerName,
            'total' => count($details),
            'joined' => $joined,
            'already' => $already,
            'failed' => $failed,
            'details' => $details,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchCompetitions(): array
    {
        $json = $this->httpRequest('GET', 'https://api.thehunter.com/v1/Page_content/list_competitions', [], '');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('La API de competiciones devolvio un JSON invalido.');
        }
        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function joinCompetition(int $competitionId, string $cookie): array
    {
        $oauthToken = $this->extractOauthAccessToken($cookie);
        if ($oauthToken === '') {
            return [
                'status' => 'auth_error',
                'method' => 'POST',
                'param' => 'id+oauth_access_token',
                'response' => 'No se pudo extraer oauth_access_token desde la cookie hunter',
            ];
        }

        $headers = [
            'Cookie: ' . $cookie,
            'Accept: application/json, text/plain, */*',
            'Origin: https://www.thehunter.com',
            'Referer: https://www.thehunter.com/#competitions',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $body = 'id=' . rawurlencode((string) $competitionId) . '&oauth_access_token=' . rawurlencode($oauthToken);
        $response = $this->httpRequest('POST', 'https://api.thehunter.com/v1/Competition/join', $headers, $body);
        $normalized = $this->normalizeJoinResponse($response);

        return [
            'status' => $normalized['status'],
            'method' => 'POST',
            'param' => 'id+oauth_access_token',
            'response' => $response,
        ];
    }

    /**
     * @return array{status:string}
     */
    private function normalizeJoinResponse(string $response): array
    {
        $trimmed = strtolower(trim($response));
        if ($trimmed === 'true') {
            return ['status' => 'joined'];
        }
        if ($trimmed === 'false') {
            return ['status' => 'failed'];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['status' => 'failed'];
        }

        $message = strtolower(trim((string) ($data['message'] ?? $data['code'] ?? $data['errorMessage'] ?? '')));
        $errorCode = (string) ($data['errorCode'] ?? '');
        $error = (string) ($data['error'] ?? '');

        if ($errorCode === '11' || str_contains($message, 'access denied')) {
            return ['status' => 'auth_error'];
        }
        if (str_contains($message, 'already') || str_contains($message, 'exists') || str_contains($message, 'joined')) {
            return ['status' => 'already_joined'];
        }
        if (($data['success'] ?? false) === true || ($data['ok'] ?? false) === true) {
            return ['status' => 'joined'];
        }
        if ($errorCode === '' && $error === '' && !str_contains($message, 'handlererror')) {
            return ['status' => 'joined'];
        }

        return ['status' => 'failed'];
    }

    private function hasPreviousAttempt(string $playerName, int $competitionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
               FROM gpt.comp_join_results
              WHERE LOWER(player_name) = LOWER(:player_name)
                AND competition_id = :competition_id
              LIMIT 1'
        );
        $stmt->execute([
            ':player_name' => $playerName,
            ':competition_id' => $competitionId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string,mixed> $item
     * @return array{eligible:bool,reason:string}|null
     */
    private function resolveCompetitionEligibility(string $playerName, array $item): ?array
    {
        $tier = $this->detectCompetitionTier((string) (($item['type']['name'] ?? '') ?: ''));
        if ($tier === null) {
            return null;
        }

        $speciesId = $this->detectCompetitionSpeciesId($item);
        if ($speciesId === null || $speciesId <= 0) {
            return null;
        }

        $kills = $this->fetchPlayerSpeciesKills($playerName, $speciesId);
        $speciesName = $this->fetchSpeciesName($speciesId);

        if ($tier === 'starter') {
            $eligible = $kills <= 50;
            $rangeText = '0-50';
        } elseif ($tier === 'intermediate') {
            $eligible = $kills > 50 && $kills <= 500;
            $rangeText = '51-500';
        } else {
            $eligible = $kills > 500;
            $rangeText = 'mas de 500';
        }

        if ($eligible) {
            return [
                'eligible' => true,
                'reason' => sprintf('Elegible: %s (%d muertes en %s)', $tier, $kills, $speciesName),
            ];
        }

        return [
            'eligible' => false,
            'reason' => sprintf(
                'No elegible: %s requiere %s muertes en %s y el jugador tiene %d',
                $tier,
                $rangeText,
                $speciesName,
                $kills
            ),
        ];
    }

    private function detectCompetitionTier(string $typeName): ?string
    {
        $normalized = strtolower(trim($typeName));
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'starter')) {
            return 'starter';
        }
        if (str_contains($normalized, 'intermediate')) {
            return 'intermediate';
        }
        if (str_contains($normalized, 'elite')) {
            return 'elite';
        }

        return null;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function detectCompetitionSpeciesId(array $item): ?int
    {
        $speciesList = $item['type']['species'] ?? null;
        if (is_array($speciesList) && isset($speciesList[0])) {
            $speciesId = (int) $speciesList[0];
            return $speciesId > 0 ? $speciesId : null;
        }

        $competitionId = isset($item['id']) ? (int) $item['id'] : 0;
        if ($competitionId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ts.species_id
               FROM gpt.comp_competitions c
               JOIN gpt.comp_type_species ts ON ts.competition_type_id = c.competition_type_id
              WHERE c.competition_id = :competition_id
              ORDER BY ts.species_id
              LIMIT 1'
        );
        $stmt->execute([':competition_id' => $competitionId]);
        $speciesId = $stmt->fetchColumn();
        if ($speciesId === false) {
            return null;
        }

        $value = (int) $speciesId;
        return $value > 0 ? $value : null;
    }

    private function fetchPlayerSpeciesKills(string $playerName, int $speciesId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(a.kills, 0)
               FROM gpt.est_animal_stats a
              WHERE LOWER(a.player_name) = LOWER(:player_name)
                AND a.species_id = :species_id
              LIMIT 1'
        );
        $stmt->execute([
            ':player_name' => $playerName,
            ':species_id' => $speciesId,
        ]);

        $value = $stmt->fetchColumn();
        return $value === false ? 0 : (int) $value;
    }

    private function fetchSpeciesName(int $speciesId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(NULLIF(especie_es, \'\'), NULLIF(especie, \'\'), :fallback)
               FROM gpt.tab_especies
              WHERE id_especie = :species_id
              LIMIT 1'
        );
        $fallback = 'species_id ' . $speciesId;
        $stmt->execute([
            ':species_id' => $speciesId,
            ':fallback' => $fallback,
        ]);

        $value = $stmt->fetchColumn();
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function resolveCookieForPlayer(string $playerName): string
    {
        $specific = getenv('THC_THEHUNTER_COOKIE_' . $this->envSuffix($playerName));
        if (is_string($specific) && trim($specific) !== '') {
            return trim($specific);
        }

        $stored = $this->loadStoredCookieForPlayer($playerName);
        if ($stored !== '') {
            return $stored;
        }

        $generic = getenv('THC_THEHUNTER_COOKIE');
        if (is_string($generic) && trim($generic) !== '') {
            return trim($generic);
        }

        return '';
    }

    private function envSuffix(string $playerName): string
    {
        $suffix = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $playerName) ?? '');
        return trim($suffix, '_');
    }

    private function extractOauthAccessToken(string $cookie): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(';', $cookie)), static fn(string $part): bool => $part !== ''));
        foreach ($parts as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            if (trim($name) !== 'hunter') {
                continue;
            }

            $decoded = urldecode(trim($value));
            $json = json_decode($decoded, true);
            if (is_array($json) && is_string($json['a'] ?? null) && trim((string) $json['a']) !== '') {
                return trim((string) $json['a']);
            }
        }

        return '';
    }

    private function loadStoredCookieForPlayer(string $playerName): string
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'thehunter_cookies.json';
        if (!is_file($file)) {
            return '';
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return '';
        }

        $key = mb_strtolower($playerName, 'UTF-8');
        $value = $data[$key] ?? null;
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string,mixed> $result
     */
    private function storeResult(string $playerName, array $result): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gpt.comp_join_results (
                player_name,
                competition_id,
                competition_name,
                status,
                request_method,
                request_param,
                response_body,
                created_at
            ) VALUES (
                :player_name,
                :competition_id,
                :competition_name,
                :status,
                :request_method,
                :request_param,
                :response_body,
                NOW()
            )'
        );

        $stmt->execute([
            ':player_name' => $playerName,
            ':competition_id' => $result['competition_id'] ?? null,
            ':competition_name' => $result['competition_name'] ?? null,
            ':status' => $result['status'] ?? null,
            ':request_method' => $result['method'] ?? null,
            ':request_param' => $result['param'] ?? null,
            ':response_body' => $result['response'] ?? null,
        ]);
    }

    /**
     * @param array<int,string> $headers
     */
    private function httpRequest(string $method, string $url, array $headers, string $body): string
    {
        $cmd = [
            'curl.exe',
            '-s',
            '-L',
            '-A',
            (string) ($this->config['api']['user_agent'] ?? 'Mozilla/5.0'),
            '-X',
            $method,
        ];

        foreach ($headers as $header) {
            $cmd[] = '-H';
            $cmd[] = $header;
        }

        if ($body !== '') {
            $cmd[] = '--data';
            $cmd[] = $body;
        }

        $cmd[] = $url;

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
        if (!is_resource($process)) {
            throw new RuntimeException('No se pudo iniciar curl.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Error HTTP curl: ' . trim((string) $stderr));
        }

        return trim((string) $stdout);
    }
}
