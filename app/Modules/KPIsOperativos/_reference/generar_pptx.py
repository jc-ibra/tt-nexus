#!/usr/bin/env python3
"""
======================================================
  GENERADOR DE PRESENTACIÓN KPI — SERVICE DESK
  Uso:
    python generar_pptx.py [archivo.csv o archivo.xlsx]
    python generar_pptx.py archivo.csv --desde 2026-04-01 --hasta 2026-04-30
    python generar_pptx.py archivo.csv --mes 2026-04
  Genera: reporte_kpi_servicedesk.pptx
======================================================
"""
import sys, os, json, subprocess, argparse, calendar
from datetime import datetime
from collections import Counter
from pathlib import Path

try:
    import pandas as pd
except ImportError:
    print("❌ pip install pandas openpyxl")
    sys.exit(1)

COL = {
    "estado":     "Estado",
    "fecha_ap":   "Fecha de apertura",
    "fecha_ci":   "Fecha de cierre",
    "regional":   "Complementos - Datos Cliente - Regional",
    "estado_geo": "Complementos - Datos Cliente - Estado",
    "proyecto":   "Complementos - Datos Cliente - Proyecto",
    "categoria":  "Categoría",
    "solicitud":  "Complementos - Datos Cliente - Solicitud",
    "idc":        "Complementos - Datos Cliente - Nombre IDC",
}

COORDINADORES = {
    "DTN - ZONA 1": ("Emmanuel Ocampo",  "Luis E. Hernández"),
    "DTN - ZONA 2": ("Alejo Vaquero",    "Luis E. Hernández"),
    "DTN - ZONA 3": ("Itzel Espinoza",   "Luis E. Hernández"),
    "DTS - ZONA 1": ("Jesús Chulín",     "Sócrates Hernández"),
    "DTS - ZONA 2": ("Jorge González",   "Sócrates Hernández"),
    "DTS - ZONA 3": ("Erick Sandoval",   "Sócrates Hernández"),
}

def cargar(ruta):
    ext = Path(ruta).suffix.lower()
    if ext in [".xlsx", ".xls"]:
        df = pd.read_excel(ruta, dtype=str)
    else:
        for enc in ["utf-8-sig", "utf-8", "cp1252", "latin-1"]:
            try:
                df = pd.read_csv(ruta, dtype=str, encoding=enc); break
            except Exception: continue
    df.columns = [c.strip() for c in df.columns]
    return df.fillna("")

def get(df, key):
    c = COL.get(key, key)
    return df[c].str.strip() if c in df.columns else pd.Series([""] * len(df))

def parse_fechas_serie(series):
    """Multi-format date parser — matches generar_reporte.py logic exactly."""
    for fmt in ('%d/%m/%y %H:%M', '%d/%m/%y %H:%M:%S', '%Y-%m-%d %H:%M', '%Y-%m-%d %H:%M:%S'):
        parsed = pd.to_datetime(series, format=fmt, errors='coerce')
        if parsed.notna().any():
            mask = parsed.isna() & series.notna() & (series.astype(str).str.strip() != '')
            if mask.any():
                parsed[mask] = pd.to_datetime(series[mask], errors='coerce')
            return parsed
    return pd.to_datetime(series, errors='coerce')

def filtrar(df, desde=None, hasta=None):
    col_fecha = COL["fecha_ap"]
    if col_fecha not in df.columns:
        return df
    df = df.copy()
    df["_fa"] = parse_fechas_serie(df[col_fecha])
    mask = pd.Series([True] * len(df), index=df.index)
    if desde: mask &= df["_fa"] >= pd.Timestamp(desde)
    if hasta: mask &= df["_fa"] <= pd.Timestamp(hasta + " 23:59:59")
    return df[mask].copy()

def extraer_kpis(df):
    total = len(df)
    est_cnt = Counter(get(df, "estado"))
    cerrados = est_cnt.get("Cerrado", 0) + est_cnt.get("Resuelto", 0)

    # SLA
    col_ap, col_ci = COL["fecha_ap"], COL["fecha_ci"]
    df_c = df[get(df, "estado").isin(["Cerrado", "Resuelto"])].copy()
    df_c["_fa"] = parse_fechas_serie(df_c[col_ap]) if col_ap in df_c.columns else pd.NaT
    df_c["_fc"] = parse_fechas_serie(df_c[col_ci]) if col_ci in df_c.columns else pd.NaT
    df_v = df_c.dropna(subset=["_fa", "_fc"])
    df_v = df_v[df_v["_fc"] >= df_v["_fa"]]
    tiempos = (df_v["_fc"] - df_v["_fa"]).dt.total_seconds().div(3600).tolist()

    # Regional
    reg_cnt = Counter(v for v in get(df, "regional") if v)
    sin_reg = sum(1 for v in get(df, "regional") if not v)

    # Coordinación — dinámico: cuenta todos los valores presentes en el CSV
    coord_t = Counter(v for v in get(df, "regional") if v)

    coord_info_out = {}
    for zona in coord_t:
        matched = next(
            (k for k in COORDINADORES if k.upper().replace(" ", "") == zona.upper().replace(" ", "")),
            None,
        )
        if matched:
            coord_info_out[zona] = {"coord": COORDINADORES[matched][0], "gte": COORDINADORES[matched][1]}
        else:
            coord_info_out[zona] = {"coord": zona, "gte": "—"}

    # IDC
    idc_cnt = Counter(v for v in get(df, "idc") if v and v != "SIN ASIGNAR")
    sin_idc = sum(1 for v in get(df, "idc") if not v or v == "SIN ASIGNAR")

    # Envíos
    env = df[get(df, "categoria").str.upper().str.contains("ENVI", na=False)]
    env_cerr = sum(1 for v in get(env, "estado") if v in ["Cerrado", "Resuelto"])

    return {
        "total": total,
        "cerrados": cerrados,
        "en_curso": total - cerrados,
        "tasa_cierre": round(cerrados / total * 100) if total else 0,
        "sla_pct": round(sum(1 for t in tiempos if t <= 24) / len(tiempos) * 100) if tiempos else 0,
        "prom_h": round(sum(tiempos) / len(tiempos), 1) if tiempos else 0,
        "sin_reg": sin_reg,
        "sin_idc": sin_idc,
        "reg_top": reg_cnt.most_common(8),
        "est_top": Counter(v for v in get(df, "estado_geo") if v).most_common(8),
        "idc_top": idc_cnt.most_common(6),
        "idc_bottom": list(reversed(idc_cnt.most_common()))[:6],
        "cat_top": Counter(v for v in get(df, "categoria") if v).most_common(7),
        "proy_top": Counter(v for v in get(df, "proyecto") if v).most_common(5),
        "estados_ticket": list(est_cnt.most_common()),
        "env_total": len(env),
        "env_cerr": env_cerr,
        "env_pend": len(env) - env_cerr,
        "env_pct": round(env_cerr / len(env) * 100) if len(env) else 0,
        "coord_tickets": dict(coord_t),
        "coord_info": coord_info_out,
    }

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("archivo", nargs="?")
    parser.add_argument("--desde", default=None)
    parser.add_argument("--hasta", default=None)
    parser.add_argument("--mes", default=None)
    args = parser.parse_args()

    if args.mes:
        y, m = args.mes.split("-")
        args.desde = f"{args.mes}-01"
        args.hasta = f"{args.mes}-{calendar.monthrange(int(y), int(m))[1]:02d}"

    # Buscar archivo
    ruta = args.archivo
    if not ruta:
        for ext in [".csv", ".xlsx", ".xls"]:
            found = list(Path(".").glob(f"*{ext}"))
            if found: ruta = str(found[0]); break
    if not ruta or not os.path.exists(ruta):
        print("❌ No se encontró archivo. Uso: python generar_pptx.py mi_archivo.csv")
        sys.exit(1)

    print(f"📂 Cargando: {Path(ruta).name}")
    df = cargar(ruta)
    print(f"✅ {len(df)} registros, {len(df.columns)} columnas")

    if args.desde or args.hasta:
        df = filtrar(df, args.desde, args.hasta)
        print(f"🔍 Filtro: {args.desde or 'inicio'} → {args.hasta or 'hoy'} → {len(df)} registros")

    print("⚙️  Calculando KPIs...")
    kpis = extraer_kpis(df)

    # Guardar JSON para el JS
    script_dir = Path(__file__).parent
    json_path = script_dir / "kpi_data.json"
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(kpis, f, ensure_ascii=False, indent=2)

    print("🎨 Generando PPTX...")
    js_path = script_dir / "generar_pptx.js"
    result = subprocess.run(["node", str(js_path), str(json_path)], capture_output=True, text=True)
    print(result.stdout)
    if result.returncode != 0:
        print("❌ Error en Node.js:", result.stderr)
        sys.exit(1)

    # Mover PPTX al directorio actual si generó en script_dir
    pptx_src = script_dir / "reporte_kpi_servicedesk.pptx"
    pptx_dst = Path(".") / "reporte_kpi_servicedesk.pptx"
    if pptx_src.exists() and str(pptx_src.resolve()) != str(pptx_dst.resolve()):
        import shutil
        shutil.move(str(pptx_src), str(pptx_dst))

    print(f"\n📊 Resumen:")
    print(f"   Total:    {kpis['total']} tickets")
    print(f"   En curso: {kpis['en_curso']}")
    print(f"   Cerrados: {kpis['cerrados']} ({kpis['tasa_cierre']}%)")
    print(f"   SLA<24h:  {kpis['sla_pct']}%")
    print(f"   Envíos:   {kpis['env_total']} total, {kpis['env_pend']} pendientes")
    print(f"\n✅ Listo → reporte_kpi_servicedesk.pptx")

if __name__ == "__main__":
    main()
