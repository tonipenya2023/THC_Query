CREATE SCHEMA IF NOT EXISTS gpt;

CREATE TABLE IF NOT EXISTS gpt.exp_expeditions (
    expedition_id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    reserve_id INTEGER NULL,
    reserve_name TEXT NULL,
    map_id INTEGER NULL,
    start_ts BIGINT NULL,
    end_ts BIGINT NULL,
    start_at TIMESTAMPTZ NULL,
    end_at TIMESTAMPTZ NULL,
    x NUMERIC(18,6) NULL,
    y NUMERIC(18,6) NULL,
    z NUMERIC(18,6) NULL,
    location_id BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.exp_stats (
    expedition_id BIGINT PRIMARY KEY REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    duration INTEGER NULL,
    distance INTEGER NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.exp_animal_stats (
    expedition_id BIGINT NOT NULL REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    species_id INTEGER NOT NULL,
    tracks INTEGER NULL,
    spots INTEGER NULL,
    kills INTEGER NULL,
    ethical_kills INTEGER NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (expedition_id, species_id)
);

CREATE TABLE IF NOT EXISTS gpt.exp_weapon_stats (
    expedition_id BIGINT NOT NULL REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    weapon_id INTEGER NOT NULL,
    ammo_id INTEGER NOT NULL,
    ethical_kills INTEGER NULL,
    hits INTEGER NULL,
    misses INTEGER NULL,
    kills INTEGER NULL,
    distance BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (expedition_id, weapon_id, ammo_id)
);

CREATE TABLE IF NOT EXISTS gpt.exp_collectables (
    expedition_id BIGINT NOT NULL REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    collectable_type TEXT NOT NULL,
    collected INTEGER NULL,
    max_value NUMERIC(12,5) NULL,
    sum_value NUMERIC(12,5) NULL,
    max_id BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (expedition_id, collectable_type)
);

CREATE TABLE IF NOT EXISTS gpt.exp_antler_collectables (
    antler_collectable_id BIGINT PRIMARY KEY,
    expedition_id BIGINT NOT NULL REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    species_id INTEGER NULL,
    score NUMERIC(12,5) NULL,
    collectable_type TEXT NULL,
    collected_at TIMESTAMPTZ NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.exp_kills (
    kill_id BIGINT PRIMARY KEY,
    expedition_id BIGINT NOT NULL REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    species_id INTEGER NULL,
    species_name TEXT NULL,
    weight BIGINT NULL,
    gender INTEGER NULL,
    texture INTEGER NULL,
    ethical BOOLEAN NULL,
    wound_time NUMERIC(12,5) NULL,
    confirm_ts BIGINT NULL,
    confirm_at TIMESTAMPTZ NULL,
    harvest_value NUMERIC(12,5) NULL,
    trophy_integrity NUMERIC(12,5) NULL,
    score NUMERIC(12,5) NULL,
    score_type TEXT NULL,
    photo TEXT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.exp_hits (
    expedition_id BIGINT NOT NULL REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    kill_id BIGINT NOT NULL REFERENCES gpt.exp_kills(kill_id) ON DELETE CASCADE,
    hit_index INTEGER NOT NULL,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    distance BIGINT NULL,
    weapon_id INTEGER NULL,
    ammo_id INTEGER NULL,
    organ BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (kill_id, hit_index)
);

CREATE TABLE IF NOT EXISTS gpt.exp_payloads (
    expedition_id BIGINT PRIMARY KEY REFERENCES gpt.exp_expeditions(expedition_id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    payload_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.best_personal_records (
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    species_id INTEGER NOT NULL,
    species_name TEXT NULL,
    species_name_es TEXT NULL,
    best_distance_raw BIGINT NULL,
    best_distance_m NUMERIC(12,3) NULL,
    best_distance_score NUMERIC(12,5) NULL,
    best_distance_weapon_id INTEGER NULL,
    best_distance_animal_id BIGINT NULL,
    best_distance_gender INTEGER NULL,
    best_distance_texture INTEGER NULL,
    best_distance_confirm_ts BIGINT NULL,
    best_distance_confirm_at TIMESTAMPTZ NULL,
    best_score_value NUMERIC(12,5) NULL,
    best_score_distance_raw BIGINT NULL,
    best_score_distance_m NUMERIC(12,3) NULL,
    best_score_weapon_id INTEGER NULL,
    best_score_animal_id BIGINT NULL,
    best_score_gender INTEGER NULL,
    best_score_texture INTEGER NULL,
    best_score_confirm_ts BIGINT NULL,
    best_score_confirm_at TIMESTAMPTZ NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, species_id)
);

CREATE TABLE IF NOT EXISTS gpt.user_public_stats (
    user_id BIGINT PRIMARY KEY,
    player_name TEXT NULL,
    hostname TEXT NULL,
    handle TEXT NULL,
    membership TEXT NULL,
    avatar_url TEXT NULL,
    online BOOLEAN NULL,
    global_rank INTEGER NULL,
    hunter_score INTEGER NULL,
    duration BIGINT NULL,
    distance BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.user_trophies (
    trophy_entry_id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    trophy_id BIGINT NULL,
    trophy_name TEXT NULL,
    competition_id BIGINT NULL,
    competition_name TEXT NULL,
    image_url TEXT NULL,
    trophy_ts BIGINT NULL,
    trophy_at TIMESTAMPTZ NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.est_profiles (
    user_id BIGINT PRIMARY KEY,
    player_name TEXT NULL,
    hostname TEXT NULL,
    handle TEXT NULL,
    membership TEXT NULL,
    avatar_url TEXT NULL,
    online BOOLEAN NULL,
    global_rank INTEGER NULL,
    hunter_score INTEGER NULL,
    duration BIGINT NULL,
    distance BIGINT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.est_collectables (
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    collectable_id INTEGER NOT NULL,
    collected INTEGER NULL,
    max_value NUMERIC(18,5) NULL,
    sum_value NUMERIC(18,5) NULL,
    max_id BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, collectable_id)
);

CREATE TABLE IF NOT EXISTS gpt.est_weapon_stats (
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    weapon_id INTEGER NOT NULL,
    ammo_id INTEGER NOT NULL,
    tracks INTEGER NULL,
    hits INTEGER NULL,
    kills INTEGER NULL,
    misses INTEGER NULL,
    score NUMERIC(18,5) NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, weapon_id, ammo_id)
);

CREATE TABLE IF NOT EXISTS gpt.est_animal_stats (
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    species_id INTEGER NOT NULL,
    tracks INTEGER NULL,
    spots INTEGER NULL,
    kills INTEGER NULL,
    ethical_kills INTEGER NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, species_id)
);

CREATE TABLE IF NOT EXISTS gpt.est_daily_missions (
    user_id BIGINT NOT NULL,
    player_name TEXT NULL,
    mission_id INTEGER NOT NULL,
    mission_value BIGINT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, mission_id)
);

CREATE TABLE IF NOT EXISTS gpt.est_payloads (
    user_id BIGINT PRIMARY KEY,
    player_name TEXT NULL,
    payload_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.comp_types (
    competition_type_id BIGINT PRIMARY KEY,
    type_name TEXT NULL,
    description_short TEXT NULL,
    description_es TEXT NULL,
    rules_html TEXT NULL,
    singleplayer BOOLEAN NULL,
    entrant_rules BOOLEAN NULL,
    attempts INTEGER NULL,
    point_type INTEGER NULL,
    image_full_url TEXT NULL,
    image_class TEXT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.comp_competitions (
    competition_id BIGINT PRIMARY KEY,
    competition_type_id BIGINT NULL REFERENCES gpt.comp_types(competition_type_id),
    start_ts BIGINT NULL,
    end_ts BIGINT NULL,
    start_at TIMESTAMPTZ NULL,
    end_at TIMESTAMPTZ NULL,
    entrants INTEGER NULL,
    finished BOOLEAN NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gpt.comp_type_species (
    competition_type_id BIGINT NOT NULL REFERENCES gpt.comp_types(competition_type_id) ON DELETE CASCADE,
    species_id INTEGER NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (competition_type_id, species_id)
);

CREATE TABLE IF NOT EXISTS gpt.comp_type_prizes (
    competition_type_id BIGINT NOT NULL REFERENCES gpt.comp_types(competition_type_id) ON DELETE CASCADE,
    prize_position INTEGER NOT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (competition_type_id, prize_position)
);

CREATE TABLE IF NOT EXISTS gpt.comp_type_rewards (
    competition_type_id BIGINT NOT NULL REFERENCES gpt.comp_types(competition_type_id) ON DELETE CASCADE,
    prize_position INTEGER NOT NULL,
    reward_position INTEGER NOT NULL,
    reward_type TEXT NULL,
    reward_define TEXT NULL,
    reward_amount NUMERIC(18,5) NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (competition_type_id, prize_position, reward_position)
);

CREATE TABLE IF NOT EXISTS gpt.comp_payloads (
    competition_id BIGINT PRIMARY KEY REFERENCES gpt.comp_competitions(competition_id) ON DELETE CASCADE,
    payload_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_exp_expeditions_user_id ON gpt.exp_expeditions(user_id);
CREATE INDEX IF NOT EXISTS idx_exp_kills_expedition_id ON gpt.exp_kills(expedition_id);
CREATE INDEX IF NOT EXISTS idx_exp_hits_expedition_id ON gpt.exp_hits(expedition_id);
CREATE INDEX IF NOT EXISTS idx_best_personal_records_species_id ON gpt.best_personal_records(species_id);
CREATE INDEX IF NOT EXISTS idx_best_personal_records_player_name ON gpt.best_personal_records(player_name);
CREATE INDEX IF NOT EXISTS idx_user_public_stats_global_rank ON gpt.user_public_stats(global_rank);
CREATE INDEX IF NOT EXISTS idx_est_profiles_global_rank ON gpt.est_profiles(global_rank);
CREATE INDEX IF NOT EXISTS idx_comp_competitions_type_id ON gpt.comp_competitions(competition_type_id);

ALTER TABLE gpt.exp_expeditions
    ALTER COLUMN x TYPE NUMERIC(18,6) USING x::NUMERIC,
    ALTER COLUMN y TYPE NUMERIC(18,6) USING y::NUMERIC,
    ALTER COLUMN z TYPE NUMERIC(18,6) USING z::NUMERIC;

ALTER TABLE gpt.exp_expeditions
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS reserve_name TEXT NULL;

ALTER TABLE gpt.exp_stats
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_animal_stats
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_weapon_stats
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_collectables
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_antler_collectables
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_kills
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_hits
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.exp_payloads
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL;

ALTER TABLE gpt.best_personal_records
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS species_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS species_name_es TEXT NULL,
    ADD COLUMN IF NOT EXISTS best_distance_raw BIGINT NULL,
    ADD COLUMN IF NOT EXISTS best_distance_m NUMERIC(12,3) NULL,
    ADD COLUMN IF NOT EXISTS best_distance_score NUMERIC(12,5) NULL,
    ADD COLUMN IF NOT EXISTS best_distance_weapon_id INTEGER NULL,
    ADD COLUMN IF NOT EXISTS best_distance_animal_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS best_distance_gender INTEGER NULL,
    ADD COLUMN IF NOT EXISTS best_distance_texture INTEGER NULL,
    ADD COLUMN IF NOT EXISTS best_distance_confirm_ts BIGINT NULL,
    ADD COLUMN IF NOT EXISTS best_distance_confirm_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS best_score_value NUMERIC(12,5) NULL,
    ADD COLUMN IF NOT EXISTS best_score_distance_raw BIGINT NULL,
    ADD COLUMN IF NOT EXISTS best_score_distance_m NUMERIC(12,3) NULL,
    ADD COLUMN IF NOT EXISTS best_score_weapon_id INTEGER NULL,
    ADD COLUMN IF NOT EXISTS best_score_animal_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS best_score_gender INTEGER NULL,
    ADD COLUMN IF NOT EXISTS best_score_texture INTEGER NULL,
    ADD COLUMN IF NOT EXISTS best_score_confirm_ts BIGINT NULL,
    ADD COLUMN IF NOT EXISTS best_score_confirm_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

ALTER TABLE gpt.user_public_stats
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS hostname TEXT NULL,
    ADD COLUMN IF NOT EXISTS handle TEXT NULL,
    ADD COLUMN IF NOT EXISTS membership TEXT NULL,
    ADD COLUMN IF NOT EXISTS avatar_url TEXT NULL,
    ADD COLUMN IF NOT EXISTS online BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS global_rank INTEGER NULL,
    ADD COLUMN IF NOT EXISTS hunter_score INTEGER NULL,
    ADD COLUMN IF NOT EXISTS duration BIGINT NULL,
    ADD COLUMN IF NOT EXISTS distance BIGINT NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

ALTER TABLE gpt.est_profiles
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS hostname TEXT NULL,
    ADD COLUMN IF NOT EXISTS handle TEXT NULL,
    ADD COLUMN IF NOT EXISTS membership TEXT NULL,
    ADD COLUMN IF NOT EXISTS avatar_url TEXT NULL,
    ADD COLUMN IF NOT EXISTS online BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS global_rank INTEGER NULL,
    ADD COLUMN IF NOT EXISTS hunter_score INTEGER NULL,
    ADD COLUMN IF NOT EXISTS duration BIGINT NULL,
    ADD COLUMN IF NOT EXISTS distance BIGINT NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

ALTER TABLE gpt.est_collectables
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS collected INTEGER NULL,
    ADD COLUMN IF NOT EXISTS max_value NUMERIC(18,5) NULL,
    ADD COLUMN IF NOT EXISTS sum_value NUMERIC(18,5) NULL,
    ADD COLUMN IF NOT EXISTS max_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL;

ALTER TABLE gpt.est_weapon_stats
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS tracks INTEGER NULL,
    ADD COLUMN IF NOT EXISTS hits INTEGER NULL,
    ADD COLUMN IF NOT EXISTS kills INTEGER NULL,
    ADD COLUMN IF NOT EXISTS misses INTEGER NULL,
    ADD COLUMN IF NOT EXISTS score NUMERIC(18,5) NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL;

ALTER TABLE gpt.est_animal_stats
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS tracks INTEGER NULL,
    ADD COLUMN IF NOT EXISTS spots INTEGER NULL,
    ADD COLUMN IF NOT EXISTS kills INTEGER NULL,
    ADD COLUMN IF NOT EXISTS ethical_kills INTEGER NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL;

ALTER TABLE gpt.est_daily_missions
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS mission_value BIGINT NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL;

ALTER TABLE gpt.est_payloads
    ADD COLUMN IF NOT EXISTS player_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS payload_json JSONB NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

ALTER TABLE gpt.comp_types
    ADD COLUMN IF NOT EXISTS type_name TEXT NULL,
    ADD COLUMN IF NOT EXISTS description_short TEXT NULL,
    ADD COLUMN IF NOT EXISTS description_es TEXT NULL,
    ADD COLUMN IF NOT EXISTS rules_html TEXT NULL,
    ADD COLUMN IF NOT EXISTS singleplayer BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS entrant_rules BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS attempts INTEGER NULL,
    ADD COLUMN IF NOT EXISTS point_type INTEGER NULL,
    ADD COLUMN IF NOT EXISTS image_full_url TEXT NULL,
    ADD COLUMN IF NOT EXISTS image_class TEXT NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

ALTER TABLE gpt.comp_competitions
    ADD COLUMN IF NOT EXISTS competition_type_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS start_ts BIGINT NULL,
    ADD COLUMN IF NOT EXISTS end_ts BIGINT NULL,
    ADD COLUMN IF NOT EXISTS start_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS end_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS entrants INTEGER NULL,
    ADD COLUMN IF NOT EXISTS finished BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

ALTER TABLE gpt.comp_type_prizes
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL;

ALTER TABLE gpt.comp_type_rewards
    ADD COLUMN IF NOT EXISTS reward_type TEXT NULL,
    ADD COLUMN IF NOT EXISTS reward_define TEXT NULL,
    ADD COLUMN IF NOT EXISTS reward_amount NUMERIC(18,5) NULL,
    ADD COLUMN IF NOT EXISTS raw_json JSONB NULL;

ALTER TABLE gpt.comp_payloads
    ADD COLUMN IF NOT EXISTS payload_json JSONB NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

CREATE TABLE IF NOT EXISTS gpt.clas_rankings_history (
    snapshot_at TIMESTAMPTZ NOT NULL,
    leaderboard_type TEXT NOT NULL,
    species_id INTEGER NOT NULL,
    species_name TEXT NULL,
    species_name_es TEXT NULL,
    rank_pos INTEGER NOT NULL,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    value_numeric NUMERIC(18,5) NULL,
    distance_m NUMERIC(18,3) NULL,
    animal_id BIGINT NULL,
    weapon_id INTEGER NULL,
    gender INTEGER NULL,
    texture INTEGER NULL,
    confirm_ts BIGINT NULL,
    confirm_at TIMESTAMPTZ NULL,
    leaderboard_url TEXT NULL,
    mark_url TEXT NULL,
    raw_json JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (snapshot_at, leaderboard_type, species_id, rank_pos)
);

CREATE TABLE IF NOT EXISTS gpt.clas_rankings_latest (
    leaderboard_type TEXT NOT NULL,
    species_id INTEGER NOT NULL,
    species_name TEXT NULL,
    species_name_es TEXT NULL,
    rank_pos INTEGER NOT NULL,
    user_id BIGINT NULL,
    player_name TEXT NULL,
    value_numeric NUMERIC(18,5) NULL,
    distance_m NUMERIC(18,3) NULL,
    animal_id BIGINT NULL,
    weapon_id INTEGER NULL,
    gender INTEGER NULL,
    texture INTEGER NULL,
    confirm_ts BIGINT NULL,
    confirm_at TIMESTAMPTZ NULL,
    leaderboard_url TEXT NULL,
    mark_url TEXT NULL,
    snapshot_at TIMESTAMPTZ NOT NULL,
    raw_json JSONB NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (leaderboard_type, species_id, rank_pos)
);

CREATE INDEX IF NOT EXISTS idx_clas_rankings_latest_species
    ON gpt.clas_rankings_latest(leaderboard_type, species_id);

CREATE INDEX IF NOT EXISTS idx_clas_rankings_history_species
    ON gpt.clas_rankings_history(leaderboard_type, species_id, snapshot_at DESC);

ALTER TABLE gpt.clas_rankings_history
    ADD COLUMN IF NOT EXISTS leaderboard_url TEXT NULL,
    ADD COLUMN IF NOT EXISTS mark_url TEXT NULL;

ALTER TABLE gpt.clas_rankings_latest
    ADD COLUMN IF NOT EXISTS leaderboard_url TEXT NULL,
    ADD COLUMN IF NOT EXISTS mark_url TEXT NULL;

-- Alias tab_* requested for catalogs
DO $$
BEGIN
    IF to_regclass('gpt.species') IS NOT NULL THEN
        EXECUTE 'CREATE OR REPLACE VIEW gpt.tab_especies AS SELECT * FROM gpt.species';
    END IF;

    IF to_regclass('gpt.users') IS NOT NULL THEN
        EXECUTE 'CREATE OR REPLACE VIEW gpt.tab_usuarios AS SELECT * FROM gpt.users';
    END IF;

    IF to_regclass('gpt.reservas') IS NOT NULL THEN
        EXECUTE 'CREATE OR REPLACE VIEW gpt.tab_reservas AS SELECT * FROM gpt.reservas';
    END IF;
END
$$;
