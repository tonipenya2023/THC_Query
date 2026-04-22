-- Vistas modulares con todos los campos de tablas relacionadas
-- Nota: se exponen como JSONB para evitar colisiones de nombre de columna
-- y mantener la vista estable ante cambios de esquema.

CREATE OR REPLACE VIEW gpt.v_exp_expediciones AS
SELECT
    e.expedition_id,
    e.user_id,
    e.player_name,
    e.reserve_id,
    e.reserve_name,
    e.start_at,
    e.end_at,
    to_jsonb(e) AS expedition_row,
    to_jsonb(ep) AS expedition_payload_row,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(s) ORDER BY s.created_at DESC)
            FROM gpt.exp_stats s
            WHERE s.expedition_id = e.expedition_id
        ),
        '[]'::jsonb
    ) AS stats_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(a) ORDER BY a.species_id)
            FROM gpt.exp_animal_stats a
            WHERE a.expedition_id = e.expedition_id
        ),
        '[]'::jsonb
    ) AS animal_stats_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(w) ORDER BY w.weapon_id, w.ammo_id)
            FROM gpt.exp_weapon_stats w
            WHERE w.expedition_id = e.expedition_id
        ),
        '[]'::jsonb
    ) AS weapon_stats_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(c) ORDER BY c.collectable_type)
            FROM gpt.exp_collectables c
            WHERE c.expedition_id = e.expedition_id
        ),
        '[]'::jsonb
    ) AS collectables_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(ac) ORDER BY ac.collectable_type)
            FROM gpt.exp_antler_collectables ac
            WHERE ac.expedition_id = e.expedition_id
        ),
        '[]'::jsonb
    ) AS antler_collectables_rows,
    COALESCE(
        (
            SELECT jsonb_agg(
                jsonb_build_object(
                    'kill', to_jsonb(k),
                    'species', to_jsonb(sp),
                    'hits', COALESCE(
                        (
                            SELECT jsonb_agg(to_jsonb(h) ORDER BY h.hit_index)
                            FROM gpt.exp_hits h
                            WHERE h.kill_id = k.kill_id
                        ),
                        '[]'::jsonb
                    )
                )
                ORDER BY k.kill_id
            )
            FROM gpt.exp_kills k
            LEFT JOIN gpt.tab_especies sp ON sp.id_especie = k.species_id
            WHERE k.expedition_id = e.expedition_id
        ),
        '[]'::jsonb
    ) AS kills_rows
FROM gpt.exp_expeditions e
LEFT JOIN gpt.exp_payloads ep ON ep.expedition_id = e.expedition_id;


DROP VIEW IF EXISTS gpt.v_user_trophies_summary;

CREATE VIEW gpt.v_user_trophies_summary AS
WITH trophy_counts AS (
    SELECT
        ut.user_id,
        COUNT(*) FILTER (WHERE LOWER(COALESCE(ut.trophy_name, '')) LIKE '%gold%') AS gold_count,
        COUNT(*) FILTER (WHERE LOWER(COALESCE(ut.trophy_name, '')) LIKE '%silver%') AS silver_count,
        COUNT(*) FILTER (WHERE LOWER(COALESCE(ut.trophy_name, '')) LIKE '%bronze%') AS bronze_count
    FROM gpt.user_trophies ut
    GROUP BY ut.user_id
)
SELECT
    u.user_id,
    u.player_name,
    COALESCE(tc.gold_count, 0) AS gold_count,
    COALESCE(tc.silver_count, 0) AS silver_count,
    COALESCE(tc.bronze_count, 0) AS bronze_count,
    (COALESCE(tc.gold_count, 0) + COALESCE(tc.silver_count, 0) + COALESCE(tc.bronze_count, 0)) AS total_trophies
FROM gpt.tab_usuarios u
LEFT JOIN trophy_counts tc ON tc.user_id = u.user_id;


CREATE OR REPLACE VIEW gpt.v_best_records AS
SELECT
    b.user_id,
    b.species_id,
    b.player_name,
    b.species_name_es,
    b.best_score_value,
    b.best_distance_m,
    to_jsonb(b) AS best_record_row,
    to_jsonb(sp) AS species_row,
    to_jsonb(p) AS profile_row,
    to_jsonb(ups) AS user_public_row,
    to_jsonb(u) AS users_row
FROM gpt.best_personal_records b
LEFT JOIN gpt.tab_especies sp ON sp.id_especie = b.species_id
LEFT JOIN gpt.est_profiles p ON p.user_id = b.user_id
LEFT JOIN gpt.user_public_stats ups ON ups.user_id = b.user_id
LEFT JOIN gpt.tab_usuarios u ON u.user_id = b.user_id;


CREATE OR REPLACE VIEW gpt.v_est_publicas AS
SELECT
    p.user_id,
    p.player_name,
    p.global_rank,
    p.hunter_score,
    to_jsonb(p) AS profile_row,
    to_jsonb(ups) AS user_public_row,
    to_jsonb(ep) AS payload_row,
    COALESCE(
        (
            SELECT jsonb_agg(
                jsonb_build_object(
                    'animal', to_jsonb(a),
                    'species', to_jsonb(sp)
                )
                ORDER BY a.species_id
            )
            FROM gpt.est_animal_stats a
            LEFT JOIN gpt.tab_especies sp ON sp.id_especie = a.species_id
            WHERE a.user_id = p.user_id
        ),
        '[]'::jsonb
    ) AS animal_stats_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(w) ORDER BY w.weapon_id, w.ammo_id)
            FROM gpt.est_weapon_stats w
            WHERE w.user_id = p.user_id
        ),
        '[]'::jsonb
    ) AS weapon_stats_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(c) ORDER BY c.collectable_id)
            FROM gpt.est_collectables c
            WHERE c.user_id = p.user_id
        ),
        '[]'::jsonb
    ) AS collectables_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(m) ORDER BY m.mission_id)
            FROM gpt.est_daily_missions m
            WHERE m.user_id = p.user_id
        ),
        '[]'::jsonb
    ) AS daily_missions_rows
FROM gpt.est_profiles p
LEFT JOIN gpt.user_public_stats ups ON ups.user_id = p.user_id
LEFT JOIN gpt.est_payloads ep ON ep.user_id = p.user_id;


CREATE OR REPLACE VIEW gpt.v_comp_competiciones AS
SELECT
    c.competition_id,
    c.competition_type_id,
    c.start_at,
    c.end_at,
    c.finished,
    c.entrants,
    to_jsonb(c) AS competition_row,
    to_jsonb(cp) AS competition_payload_row,
    to_jsonb(t) AS competition_type_row,
    COALESCE(
        (
            SELECT jsonb_agg(
                jsonb_build_object(
                    'type_species', to_jsonb(ts),
                    'species', to_jsonb(sp)
                )
                ORDER BY ts.species_id
            )
            FROM gpt.comp_type_species ts
            LEFT JOIN gpt.tab_especies sp ON sp.id_especie = ts.species_id
            WHERE ts.competition_type_id = c.competition_type_id
        ),
        '[]'::jsonb
    ) AS species_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(pr) ORDER BY pr.prize_position)
            FROM gpt.comp_type_prizes pr
            WHERE pr.competition_type_id = c.competition_type_id
        ),
        '[]'::jsonb
    ) AS prizes_rows,
    COALESCE(
        (
            SELECT jsonb_agg(to_jsonb(rw) ORDER BY rw.prize_position, rw.reward_position)
            FROM gpt.comp_type_rewards rw
            WHERE rw.competition_type_id = c.competition_type_id
        ),
        '[]'::jsonb
    ) AS rewards_rows
FROM gpt.comp_competitions c
LEFT JOIN gpt.comp_payloads cp ON cp.competition_id = c.competition_id
LEFT JOIN gpt.comp_types t ON t.competition_type_id = c.competition_type_id;


CREATE OR REPLACE VIEW gpt.v_clas_latest AS
SELECT
    l.leaderboard_type,
    l.species_id,
    l.rank_pos,
    l.user_id,
    l.player_name,
    l.snapshot_at,
    to_jsonb(l) AS ranking_row,
    to_jsonb(sp) AS species_row,
    to_jsonb(u) AS users_row,
    to_jsonb(ups) AS user_public_row
FROM gpt.clas_rankings_latest l
LEFT JOIN gpt.tab_especies sp ON sp.id_especie = l.species_id
LEFT JOIN gpt.tab_usuarios u ON u.user_id = l.user_id
LEFT JOIN gpt.user_public_stats ups ON ups.user_id = l.user_id;


CREATE OR REPLACE VIEW gpt.v_clas_historico AS
SELECT
    h.snapshot_at,
    h.leaderboard_type,
    h.species_id,
    h.rank_pos,
    h.user_id,
    h.player_name,
    to_jsonb(h) AS ranking_row,
    to_jsonb(sp) AS species_row,
    to_jsonb(u) AS users_row,
    to_jsonb(ups) AS user_public_row
FROM gpt.clas_rankings_history h
LEFT JOIN gpt.tab_especies sp ON sp.id_especie = h.species_id
LEFT JOIN gpt.tab_usuarios u ON u.user_id = h.user_id
LEFT JOIN gpt.user_public_stats ups ON ups.user_id = h.user_id;


CREATE OR REPLACE VIEW gpt.v_exp_cheat_risk AS
WITH kill_events AS (
    SELECT
        k.user_id,
        COALESCE(NULLIF(k.player_name, ''), NULLIF(u.player_name, ''), NULLIF(ups.player_name, ''), ('user_' || k.user_id::text)) AS player_name,
        k.expedition_id,
        COALESCE(k.confirm_at, k.created_at) AS kill_at,
        k.trophy_integrity,
        e.start_at,
        e.end_at
    FROM gpt.exp_kills k
    LEFT JOIN gpt.exp_expeditions e ON e.expedition_id = k.expedition_id
    LEFT JOIN gpt.tab_usuarios u ON u.user_id = k.user_id
    LEFT JOIN gpt.user_public_stats ups ON ups.user_id = k.user_id
    WHERE k.user_id IS NOT NULL
), hit_stats AS (
    SELECT
        k.user_id,
        MAX(h.distance) / 1000.0 AS max_hit_distance_m
    FROM gpt.exp_kills k
    LEFT JOIN gpt.exp_hits h ON h.kill_id = k.kill_id
    WHERE k.user_id IS NOT NULL
    GROUP BY k.user_id
), exp_rates AS (
    SELECT
        e.user_id,
        e.expedition_id,
        COUNT(k.kill_id) AS exp_kills,
        EXTRACT(EPOCH FROM (COALESCE(e.end_at, e.start_at) - e.start_at)) AS exp_duration_sec
    FROM gpt.exp_expeditions e
    LEFT JOIN gpt.exp_kills k ON k.expedition_id = e.expedition_id
    WHERE e.user_id IS NOT NULL
      AND e.start_at IS NOT NULL
      AND e.end_at IS NOT NULL
      AND EXTRACT(EPOCH FROM (COALESCE(e.end_at, e.start_at) - e.start_at)) >= 600
    GROUP BY e.user_id, e.expedition_id, e.start_at, e.end_at
), user_rates AS (
    SELECT
        user_id,
        MAX((exp_kills::numeric * 3600.0) / exp_duration_sec) AS max_kills_per_hour
    FROM exp_rates
    GROUP BY user_id
), kill_gaps AS (
    SELECT
        user_id,
        EXTRACT(EPOCH FROM (kill_at - LAG(kill_at) OVER (PARTITION BY user_id ORDER BY kill_at))) AS gap_sec
    FROM kill_events
    WHERE kill_at IS NOT NULL
), gap_stats AS (
    SELECT
        user_id,
        MIN(gap_sec) FILTER (WHERE gap_sec > 0) AS min_gap_sec,
        AVG(gap_sec) FILTER (WHERE gap_sec > 0) AS avg_gap_sec
    FROM kill_gaps
    GROUP BY user_id
), user_core AS (
    SELECT
        ke.user_id,
        MAX(ke.player_name) AS player_name,
        COUNT(*) AS total_kills,
        COUNT(*) FILTER (
            WHERE ke.start_at IS NOT NULL
              AND ke.end_at IS NOT NULL
              AND (ke.kill_at < ke.start_at - INTERVAL '5 minutes' OR ke.kill_at > ke.end_at + INTERVAL '5 minutes')
        ) AS kills_outside_window,
        COUNT(*) FILTER (WHERE ke.trophy_integrity >= 99.9) AS perfect_integrity_kills
    FROM kill_events ke
    GROUP BY ke.user_id
), scored AS (
    SELECT
        c.user_id,
        c.player_name,
        c.total_kills,
        c.kills_outside_window,
        c.perfect_integrity_kills,
        ROUND(COALESCE(h.max_hit_distance_m, 0), 2) AS max_hit_distance_m,
        ROUND(COALESCE(r.max_kills_per_hour, 0), 2) AS max_kills_per_hour,
        ROUND(COALESCE(g.min_gap_sec, 0), 2) AS min_gap_sec,
        ROUND(COALESCE(g.avg_gap_sec, 0), 2) AS avg_gap_sec,
        (
            CASE
                WHEN COALESCE(r.max_kills_per_hour, 0) >= 120 THEN 25
                WHEN COALESCE(r.max_kills_per_hour, 0) >= 80 THEN 15
                ELSE 0
              END
            + CASE
                WHEN COALESCE(h.max_hit_distance_m, 0) >= 1000 THEN 25
                WHEN COALESCE(h.max_hit_distance_m, 0) >= 700 THEN 15
                ELSE 0
              END
            + CASE
                WHEN c.total_kills >= 50 AND (c.perfect_integrity_kills::numeric / NULLIF(c.total_kills, 0)) >= 0.95 THEN 15
                WHEN c.total_kills >= 30 AND (c.perfect_integrity_kills::numeric / NULLIF(c.total_kills, 0)) >= 0.90 THEN 8
                ELSE 0
              END
        )::int AS risk_score,
        false AS signal_outside_window,
        (COALESCE(r.max_kills_per_hour, 0) >= 80) AS signal_speedrun,
        (COALESCE(h.max_hit_distance_m, 0) >= 700) AS signal_extreme_distance,
        false AS signal_dense_kills,
        ((c.total_kills >= 30 AND (c.perfect_integrity_kills::numeric / NULLIF(c.total_kills, 0)) >= 0.90)) AS signal_perfect_integrity
    FROM user_core c
    LEFT JOIN hit_stats h ON h.user_id = c.user_id
    LEFT JOIN user_rates r ON r.user_id = c.user_id
    LEFT JOIN gap_stats g ON g.user_id = c.user_id
)
SELECT
    s.user_id,
    s.player_name,
    s.total_kills,
    s.kills_outside_window,
    s.perfect_integrity_kills,
    ROUND((s.perfect_integrity_kills::numeric / NULLIF(s.total_kills, 0)) * 100.0, 2) AS integrity_ratio_pct,
    s.max_hit_distance_m,
    s.max_kills_per_hour,
    s.min_gap_sec,
    s.avg_gap_sec,
    LEAST(100, s.risk_score) AS risk_score,
    CASE
        WHEN LEAST(100, s.risk_score) >= 60 THEN 'alto'
        WHEN LEAST(100, s.risk_score) >= 30 THEN 'medio'
        ELSE 'bajo'
    END AS risk_level,
    (
        (CASE WHEN s.signal_speedrun THEN 1 ELSE 0 END)
        + (CASE WHEN s.signal_extreme_distance THEN 1 ELSE 0 END)
        + (CASE WHEN s.signal_perfect_integrity THEN 1 ELSE 0 END)
    )::int AS signal_count,
    array_to_string(
        array_remove(
            ARRAY[
                CASE WHEN s.signal_speedrun THEN 'Cadencia extrema' END,
                CASE WHEN s.signal_extreme_distance THEN 'Distancia extrema' END,
                CASE WHEN s.signal_perfect_integrity THEN 'Integridad casi perfecta' END
            ],
            NULL
        ),
        ', '
    ) AS signal_list,
    s.signal_outside_window,
    s.signal_speedrun,
    s.signal_extreme_distance,
    s.signal_dense_kills,
    s.signal_perfect_integrity
FROM scored s;


CREATE OR REPLACE VIEW gpt.v_exp_cheat_signals AS
SELECT
    r.user_id,
    r.player_name,
    x.signal_code,
    x.signal_label,
    x.signal_value,
    x.signal_threshold,
    x.signal_weight
FROM gpt.v_exp_cheat_risk r
CROSS JOIN LATERAL (
    VALUES
        (
            'speedrun',
            'Cadencia extrema (kills/hora)',
            r.max_kills_per_hour::text,
            '>= 80',
            CASE WHEN r.max_kills_per_hour >= 120 THEN 25 WHEN r.max_kills_per_hour >= 80 THEN 15 ELSE 0 END
        ),
        (
            'extreme_distance',
            'Distancia extrema (m)',
            r.max_hit_distance_m::text,
            '>= 700',
            CASE WHEN r.max_hit_distance_m >= 1000 THEN 25 WHEN r.max_hit_distance_m >= 700 THEN 15 ELSE 0 END
        ),
        (
            'perfect_integrity',
            'Integridad casi perfecta (%)',
            r.integrity_ratio_pct::text,
            '>= 90',
            CASE WHEN r.total_kills >= 50 AND r.integrity_ratio_pct >= 95 THEN 15 WHEN r.total_kills >= 30 AND r.integrity_ratio_pct >= 90 THEN 8 ELSE 0 END
        )
) AS x(signal_code, signal_label, signal_value, signal_threshold, signal_weight)
WHERE x.signal_weight > 0;


CREATE OR REPLACE VIEW gpt.v_exp_cheat_signal_expeditions AS
WITH speedrun AS (
    SELECT
        e.user_id,
        e.expedition_id,
        ((COUNT(k.kill_id)::numeric * 3600.0) / EXTRACT(EPOCH FROM (COALESCE(e.end_at, e.start_at) - e.start_at))) AS signal_value
    FROM gpt.exp_expeditions e
    LEFT JOIN gpt.exp_kills k ON k.expedition_id = e.expedition_id
    WHERE e.user_id IS NOT NULL
      AND e.start_at IS NOT NULL
      AND e.end_at IS NOT NULL
      AND EXTRACT(EPOCH FROM (COALESCE(e.end_at, e.start_at) - e.start_at)) >= 600
    GROUP BY e.user_id, e.expedition_id, e.start_at, e.end_at
    HAVING ((COUNT(k.kill_id)::numeric * 3600.0) / EXTRACT(EPOCH FROM (COALESCE(e.end_at, e.start_at) - e.start_at))) >= 80
), extreme_distance AS (
    SELECT
        e.user_id,
        e.expedition_id,
        (MAX(h.distance)::numeric / 1000.0) AS signal_value
    FROM gpt.exp_expeditions e
    JOIN gpt.exp_kills k ON k.expedition_id = e.expedition_id
    JOIN gpt.exp_hits h ON h.kill_id = k.kill_id
    WHERE e.user_id IS NOT NULL
    GROUP BY e.user_id, e.expedition_id
    HAVING (MAX(h.distance)::numeric / 1000.0) >= 700
), perfect_integrity AS (
    SELECT
        e.user_id,
        e.expedition_id,
        ((COUNT(*) FILTER (WHERE k.trophy_integrity >= 99.9))::numeric * 100.0 / NULLIF(COUNT(*), 0)) AS signal_value
    FROM gpt.exp_expeditions e
    JOIN gpt.exp_kills k ON k.expedition_id = e.expedition_id
    WHERE e.user_id IS NOT NULL
    GROUP BY e.user_id, e.expedition_id
    HAVING COUNT(*) >= 30
       AND ((COUNT(*) FILTER (WHERE k.trophy_integrity >= 99.9))::numeric * 100.0 / NULLIF(COUNT(*), 0)) >= 90
)
SELECT
    s.user_id,
    s.expedition_id,
    'speedrun' AS signal_code,
    'Cadencia extrema (kills/hora)' AS signal_label,
    ROUND(s.signal_value, 2) AS signal_value,
    '>= 80' AS signal_threshold,
    CASE WHEN s.signal_value >= 120 THEN 25 ELSE 15 END AS signal_weight
FROM speedrun s
UNION ALL
SELECT
    d.user_id,
    d.expedition_id,
    'extreme_distance' AS signal_code,
    'Distancia extrema (m)' AS signal_label,
    ROUND(d.signal_value, 3) AS signal_value,
    '>= 700' AS signal_threshold,
    CASE WHEN d.signal_value >= 1000 THEN 25 ELSE 15 END AS signal_weight
FROM extreme_distance d
UNION ALL
SELECT
    p.user_id,
    p.expedition_id,
    'perfect_integrity' AS signal_code,
    'Integridad casi perfecta (%)' AS signal_label,
    ROUND(p.signal_value, 2) AS signal_value,
    '>= 90' AS signal_threshold,
    CASE WHEN p.signal_value >= 95 THEN 15 ELSE 8 END AS signal_weight
FROM perfect_integrity p;

