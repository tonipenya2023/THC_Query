from __future__ import annotations

import argparse
import datetime as dt
import os
from collections import defaultdict
from decimal import Decimal
from typing import Iterable
from xml.sax.saxutils import escape

import psycopg2


DB_CONFIG = {
    "host": "localhost",
    "dbname": "test",
    "user": "postgres",
    "password": "system",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Exporta comparativas de mejores marcas a un XML compatible con Excel."
    )
    parser.add_argument("--player", help="Filtra por player_name exacto")
    parser.add_argument("--user-id", type=int, help="Filtra por user_id")
    parser.add_argument(
        "--out",
        default=os.path.join("out", "best_personal_records.xml"),
        help="Ruta de salida del fichero XML",
    )
    return parser.parse_args()


def fetch_rows(player: str | None, user_id: int | None) -> list[dict]:
    sql = """
        SELECT
            bpr.user_id,
            bpr.player_name,
            bpr.species_id,
            COALESCE(bpr.species_name_es, bpr.species_name, bpr.species_id::text) AS species_label,
            bpr.best_score_value,
            bpr.best_score_distance_m,
            bpr.best_distance_m,
            bpr.best_distance_score,
            COALESCE(ep.global_rank, ups.global_rank) AS global_rank,
            COALESCE(ep.hunter_score, ups.hunter_score) AS hunter_score
        FROM gpt.best_personal_records
        bpr
        LEFT JOIN gpt.est_profiles ep
          ON ep.user_id = bpr.user_id
        LEFT JOIN gpt.user_public_stats ups
          ON ups.user_id = bpr.user_id
    """
    where = []
    params: list[object] = []

    if player:
        where.append("player_name = %s")
        params.append(player)

    if user_id:
        where.append("user_id = %s")
        params.append(user_id)

    if where:
        sql += " WHERE " + " AND ".join(where)

    sql += " ORDER BY species_label, COALESCE(ups.global_rank, 999999), bpr.player_name, bpr.user_id"

    with psycopg2.connect(**DB_CONFIG) as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            columns = [desc[0] for desc in cur.description]
            return [dict(zip(columns, row)) for row in cur.fetchall()]


def decimal_to_float(value: object) -> float | None:
    if value is None:
        return None
    if isinstance(value, Decimal):
        return float(value)
    return float(value)


def build_matrix(rows: list[dict], metric_key: str) -> tuple[list[str], list[tuple[str, dict[str, float | None]]]]:
    player_meta: dict[str, tuple[int, int, str]] = {}
    for row in rows:
        player = row["player_name"] or str(row["user_id"])
        rank = int(row["global_rank"]) if row.get("global_rank") is not None else 999999
        hunter_score = int(row["hunter_score"]) if row.get("hunter_score") is not None else -1
        player_meta[player] = (rank, -hunter_score, player)

    players = [player for player, _ in sorted(player_meta.items(), key=lambda item: item[1])]
    species_order: list[str] = []
    matrix: dict[str, dict[str, float | None]] = defaultdict(dict)

    for row in rows:
        species = str(row["species_label"])
        player = row["player_name"] or str(row["user_id"])
        if species not in matrix:
            species_order.append(species)
        matrix[species][player] = decimal_to_float(row[metric_key])

    ordered_rows = [(species, matrix[species]) for species in species_order]
    return players, ordered_rows


def style_for_value(value: float | None, row_values: Iterable[float | None]) -> str:
    if value is None:
        return "CellEmpty"

    values = [v for v in row_values if v is not None]
    if len(values) <= 1:
        return "CellTop"

    max_v = max(values)
    if value == max_v:
        return "CellTop"

    non_top_values = [v for v in values if v != max_v]
    if not non_top_values:
        return "CellHigh"

    min_v = min(non_top_values)
    max_non_top = max(non_top_values)
    if max_non_top == min_v:
        return "CellHigh"

    ratio = (value - min_v) / (max_non_top - min_v)
    if ratio >= 0.75:
        return "CellHigh"
    if ratio >= 0.65:
        return "CellHigh"
    if ratio >= 0.45:
        return "CellMid"
    if ratio >= 0.25:
        return "CellLow"
    return "CellBottom"


def xml_cell(value: object, style_id: str = "DefaultCell", data_type: str = "String") -> str:
    if value is None:
        return f'<Cell ss:StyleID="{style_id}"/>'

    if data_type == "String":
        text = escape(str(value))
    else:
        text = str(value)

    return (
        f'<Cell ss:StyleID="{style_id}"><Data ss:Type="{data_type}">{text}</Data></Cell>'
    )


def worksheet_table(title: str, rows: list[dict], metric_key: str, metric_label: str) -> str:
    players, matrix_rows = build_matrix(rows, metric_key)
    current_date = dt.datetime.now().strftime("%d/%m/%Y")

    xml_rows = []
    xml_rows.append(
        "<Row>"
        + xml_cell(current_date, "DateCell")
        + "".join(xml_cell(None) for _ in players)
        + "</Row>"
    )
    xml_rows.append(
        "<Row>"
        + xml_cell(metric_label, "HeaderLeft")
        + "".join(xml_cell(player, "HeaderPlayer") for player in players)
        + "</Row>"
    )

    for species, values in matrix_rows:
        row_values = [values.get(player) for player in players]
        row_xml = [xml_cell(species, "SpeciesCell")]
        for player in players:
            value = values.get(player)
            if value is None:
                row_xml.append(xml_cell(None, "CellEmpty"))
                continue
            style_id = style_for_value(value, row_values)
            row_xml.append(xml_cell(f"{value:.2f}", style_id))
        xml_rows.append("<Row>" + "".join(row_xml) + "</Row>")

    return (
        f'<Worksheet ss:Name="{escape(title)}">'
        "<Table>"
        + "".join(xml_rows)
        + "</Table>"
        "</Worksheet>"
    )


def worksheet_raw(rows: list[dict]) -> str:
    headers = [
        "user_id",
        "player_name",
        "species_id",
        "species_label",
        "best_score_value",
        "best_score_distance_m",
        "best_distance_m",
        "best_distance_score",
    ]

    xml_rows = ["<Row>" + "".join(xml_cell(header, "HeaderPlayer") for header in headers) + "</Row>"]

    for row in rows:
        xml_rows.append(
            "<Row>"
            + xml_cell(row["user_id"], "DefaultCell", "Number")
            + xml_cell(row["player_name"], "DefaultCell")
            + xml_cell(row["species_id"], "DefaultCell", "Number")
            + xml_cell(row["species_label"], "DefaultCell")
            + xml_cell("" if row["best_score_value"] is None else row["best_score_value"], "DefaultCell")
            + xml_cell("" if row["best_score_distance_m"] is None else row["best_score_distance_m"], "DefaultCell")
            + xml_cell("" if row["best_distance_m"] is None else row["best_distance_m"], "DefaultCell")
            + xml_cell("" if row["best_distance_score"] is None else row["best_distance_score"], "DefaultCell")
            + "</Row>"
        )

    return '<Worksheet ss:Name="Raw"><Table>' + "".join(xml_rows) + "</Table></Worksheet>"


def workbook_xml(rows: list[dict]) -> str:
    return f"""<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
<Styles>
 <Style ss:ID="DefaultCell">
  <Alignment ss:Vertical="Center"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
   <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
  </Borders>
  <Font ss:FontName="Calibri" ss:Size="10"/>
 </Style>
 <Style ss:ID="DateCell" ss:Parent="DefaultCell">
  <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1"/>
 </Style>
 <Style ss:ID="HeaderLeft" ss:Parent="DefaultCell">
  <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/>
  <Interior ss:Color="#9FD4F2" ss:Pattern="Solid"/>
 </Style>
 <Style ss:ID="HeaderPlayer" ss:Parent="DefaultCell">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Interior ss:Color="#CDEBF8" ss:Pattern="Solid"/>
 </Style>
 <Style ss:ID="SpeciesCell" ss:Parent="DefaultCell">
  <Font ss:FontName="Calibri" ss:Size="10"/>
 </Style>
 <Style ss:ID="CellTop" ss:Parent="DefaultCell">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Interior ss:Color="#4F81BD" ss:Pattern="Solid"/>
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
 </Style>
 <Style ss:ID="CellHigh" ss:Parent="DefaultCell">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Interior ss:Color="#92D050" ss:Pattern="Solid"/>
  <Font ss:FontName="Calibri" ss:Size="10"/>
 </Style>
 <Style ss:ID="CellMid" ss:Parent="DefaultCell">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Interior ss:Color="#FFF2CC" ss:Pattern="Solid"/>
  <Font ss:FontName="Calibri" ss:Size="10"/>
 </Style>
 <Style ss:ID="CellLow" ss:Parent="DefaultCell">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Interior ss:Color="#F4B183" ss:Pattern="Solid"/>
  <Font ss:FontName="Calibri" ss:Size="10"/>
 </Style>
 <Style ss:ID="CellBottom" ss:Parent="DefaultCell">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Interior ss:Color="#F8696B" ss:Pattern="Solid"/>
  <Font ss:FontName="Calibri" ss:Size="10"/>
 </Style>
 <Style ss:ID="CellEmpty" ss:Parent="DefaultCell">
  <Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/>
 </Style>
</Styles>
{worksheet_table("Best Score", rows, "best_score_value", "MEJORES MARCAS PUNTUACIÓN")}
{worksheet_table("Best Distance", rows, "best_distance_m", "MEJORES MARCAS DISTANCIA")}
{worksheet_raw(rows)}
</Workbook>
"""


def main() -> None:
    args = parse_args()
    rows = fetch_rows(args.player, args.user_id)

    if not rows:
        raise SystemExit("No hay datos en gpt.best_personal_records para ese filtro.")

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    xml = workbook_xml(rows)

    with open(args.out, "w", encoding="utf-8-sig") as fh:
        fh.write(xml)

    print(f"Excel XML generado: {args.out}")
    print(f"Filas base: {len(rows)}")


if __name__ == "__main__":
    main()
