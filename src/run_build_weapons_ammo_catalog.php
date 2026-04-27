<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS gpt.tab_weapons (
        weapon_id INTEGER PRIMARY KEY,
        weapon_text TEXT NOT NULL,
        sample_count INTEGER NOT NULL DEFAULT 0,
        first_seen_at TIMESTAMPTZ NULL,
        last_seen_at TIMESTAMPTZ NULL,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )"
);
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tab_weapons_text ON gpt.tab_weapons(weapon_text)");

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS gpt.tab_ammo (
        ammo_id INTEGER PRIMARY KEY,
        ammo_text TEXT NOT NULL,
        weapon_id INTEGER NULL,
        sample_count INTEGER NOT NULL DEFAULT 0,
        first_seen_at TIMESTAMPTZ NULL,
        last_seen_at TIMESTAMPTZ NULL,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )"
);
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tab_ammo_text ON gpt.tab_ammo(ammo_text)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tab_ammo_weapon_id ON gpt.tab_ammo(weapon_id)");

$sql = <<<'SQL'
WITH latest_scrapes AS (
    SELECT DISTINCT ON (k.player_name, k.kill_id)
        k.kill_id,
        k.scraped_at,
        k.kill_data_json
    FROM gpt.kill_detail_scrapes k
    WHERE k.kill_id IS NOT NULL
      AND k.kill_data_json IS NOT NULL
    ORDER BY k.player_name, k.kill_id, k.scraped_at DESC, k.scrape_id DESC
),
parsed_hits AS (
    SELECT
        ls.kill_id,
        ls.scraped_at,
        COALESCE(
            NULLIF(hit.value->>'hit_index', ''),
            (hit.ordinality)::text
        )::INTEGER AS hit_index,
        NULLIF(BTRIM(hit.value->>'weapon_text'), '') AS weapon_text,
        NULLIF(BTRIM(hit.value->>'ammo_text'), '') AS ammo_text
    FROM latest_scrapes ls
    CROSS JOIN LATERAL jsonb_array_elements(
        COALESCE(ls.kill_data_json->'parsed_hits', '[]'::jsonb)
    ) WITH ORDINALITY AS hit(value, ordinality)
),
joined AS (
    SELECT
        h.weapon_id,
        h.ammo_id,
        p.weapon_text,
        p.ammo_text,
        p.scraped_at
    FROM gpt.exp_hits h
    JOIN parsed_hits p
      ON p.kill_id = h.kill_id
     AND p.hit_index = h.hit_index
    WHERE (h.weapon_id IS NOT NULL AND p.weapon_text IS NOT NULL)
       OR (h.ammo_id IS NOT NULL AND p.ammo_text IS NOT NULL)
),
weapon_ranked AS (
    SELECT
        weapon_id,
        weapon_text,
        COUNT(*) AS sample_count,
        MIN(scraped_at) AS first_seen_at,
        MAX(scraped_at) AS last_seen_at,
        ROW_NUMBER() OVER (
            PARTITION BY weapon_id
            ORDER BY COUNT(*) DESC, MAX(scraped_at) DESC, weapon_text ASC
        ) AS rn
    FROM joined
    WHERE weapon_id IS NOT NULL
      AND weapon_text IS NOT NULL
    GROUP BY weapon_id, weapon_text
),
ammo_ranked AS (
    SELECT
        ammo_id,
        ammo_text,
        weapon_id,
        COUNT(*) AS sample_count,
        MIN(scraped_at) AS first_seen_at,
        MAX(scraped_at) AS last_seen_at,
        ROW_NUMBER() OVER (
            PARTITION BY ammo_id
            ORDER BY COUNT(*) DESC, MAX(scraped_at) DESC, ammo_text ASC
        ) AS rn
    FROM joined
    WHERE ammo_id IS NOT NULL
      AND ammo_text IS NOT NULL
    GROUP BY ammo_id, ammo_text, weapon_id
)
SELECT
    'weapon' AS entity_type,
    weapon_id::INTEGER AS item_id,
    weapon_text AS item_text,
    NULL::INTEGER AS parent_weapon_id,
    sample_count::INTEGER AS sample_count,
    first_seen_at,
    last_seen_at
FROM weapon_ranked
WHERE rn = 1

UNION ALL

SELECT
    'ammo' AS entity_type,
    ammo_id::INTEGER AS item_id,
    ammo_text AS item_text,
    weapon_id::INTEGER AS parent_weapon_id,
    sample_count::INTEGER AS sample_count,
    first_seen_at,
    last_seen_at
FROM ammo_ranked
WHERE rn = 1

ORDER BY entity_type, item_id
SQL;

$rows = $pdo->query($sql)->fetchAll();

$pdo->beginTransaction();
try {
    $pdo->exec('TRUNCATE TABLE gpt.tab_ammo');
    $pdo->exec('TRUNCATE TABLE gpt.tab_weapons');

    $insertWeapon = $pdo->prepare(
        'INSERT INTO gpt.tab_weapons (
            weapon_id, weapon_text, sample_count, first_seen_at, last_seen_at, updated_at
         ) VALUES (
            :weapon_id, :weapon_text, :sample_count, :first_seen_at, :last_seen_at, NOW()
         )'
    );

    $insertAmmo = $pdo->prepare(
        'INSERT INTO gpt.tab_ammo (
            ammo_id, ammo_text, weapon_id, sample_count, first_seen_at, last_seen_at, updated_at
         ) VALUES (
            :ammo_id, :ammo_text, :weapon_id, :sample_count, :first_seen_at, :last_seen_at, NOW()
         )'
    );

    $weaponCount = 0;
    $ammoCount = 0;

    foreach ($rows as $row) {
        $type = (string) ($row['entity_type'] ?? '');
        if ($type === 'weapon') {
            $insertWeapon->execute([
                ':weapon_id' => (int) $row['item_id'],
                ':weapon_text' => (string) $row['item_text'],
                ':sample_count' => (int) $row['sample_count'],
                ':first_seen_at' => $row['first_seen_at'],
                ':last_seen_at' => $row['last_seen_at'],
            ]);
            $weaponCount++;
            continue;
        }

        if ($type === 'ammo') {
            $insertAmmo->execute([
                ':ammo_id' => (int) $row['item_id'],
                ':ammo_text' => (string) $row['item_text'],
                ':weapon_id' => $row['parent_weapon_id'] !== null ? (int) $row['parent_weapon_id'] : null,
                ':sample_count' => (int) $row['sample_count'],
                ':first_seen_at' => $row['first_seen_at'],
                ':last_seen_at' => $row['last_seen_at'],
            ]);
            $ammoCount++;
        }
    }

    $pdo->commit();
    fwrite(STDOUT, "Catalogo reconstruido. Weapons: {$weaponCount}. Ammo: {$ammoCount}." . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error reconstruyendo catalogo: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
