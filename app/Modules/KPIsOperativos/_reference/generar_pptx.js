// =====================================================
//  GENERADOR DE PRESENTACIÓN KPI — SERVICE DESK
//  Uso: node generar_pptx.js [kpi_data.json]
// =====================================================
const pptxgen = require("pptxgenjs");
const fs = require("fs");
const path = require("path");

// ── PALETA "MIDNIGHT EXECUTIVE" ───────────────────────────────────────────
const C = {
  navy:    "0F1C3F",   // fondo oscuro
  navyMid: "1A2E5A",   // fondo medio
  navyLt:  "1E3A6E",   // fondo claro
  cyan:    "00C8E0",   // acento principal
  violet:  "7B61FF",   // acento secundario
  mint:    "2DD4BF",   // acento terciario
  gold:    "F59E0B",   // alerta/atención
  red:     "EF4444",   // crítico
  green:   "22C55E",   // positivo
  white:   "FFFFFF",
  offwhite:"E2E8F0",
  gray:    "94A3B8",
  grayDk:  "334155",
};

const CHART_COLORS = ["00C8E0","7B61FF","2DD4BF","F59E0B","EF4444","22C55E","F97316","EC4899"];

// ── HELPERS ───────────────────────────────────────────────────────────────
function bg(slide, color = C.navy) {
  slide.background = { color };
}

function title(slide, text, y = 0.3, color = C.cyan, size = 22) {
  slide.addText(text.toUpperCase(), {
    x: 0.5, y, w: 9, h: 0.5,
    fontSize: size, bold: true, color,
    fontFace: "Calibri", charSpacing: 2, margin: 0,
  });
}

function subtitle(slide, text, y = 0.78) {
  slide.addText(text, {
    x: 0.5, y, w: 9, h: 0.25,
    fontSize: 11, color: C.gray, fontFace: "Calibri", margin: 0,
  });
}

function divider(slide, y = 0.72) {
  slide.addShape("rect", {
    x: 0.5, y, w: 9, h: 0.015,
    fill: { color: C.cyan, transparency: 60 }, line: { color: C.cyan, transparency: 60 },
  });
}

function makeShadow() {
  return { type: "outer", color: "000000", blur: 8, offset: 3, angle: 135, opacity: 0.25 };
}

// KPI card: big number with label and sublabel
function kpiCard(slide, x, y, w, h, value, label, sub, accentColor, bgColor) {
  bgColor = bgColor || C.navyMid;
  // card background
  slide.addShape("rect", {
    x, y, w, h,
    fill: { color: bgColor },
    line: { color: accentColor, width: 0.5 },
    shadow: makeShadow(),
  });
  // top accent bar
  slide.addShape("rect", {
    x, y, w: w, h: 0.06,
    fill: { color: accentColor },
    line: { color: accentColor },
  });
  // big value
  slide.addText(String(value), {
    x: x + 0.1, y: y + 0.12, w: w - 0.2, h: h * 0.5,
    fontSize: 34, bold: true, color: C.white, fontFace: "Calibri",
    align: "center", valign: "middle", margin: 0,
  });
  // label
  slide.addText(label, {
    x: x + 0.08, y: y + h * 0.62, w: w - 0.16, h: 0.22,
    fontSize: 10, bold: true, color: accentColor, fontFace: "Calibri",
    align: "center", charSpacing: 1, margin: 0,
  });
  // sub
  if (sub) {
    slide.addText(sub, {
      x: x + 0.08, y: y + h * 0.82, w: w - 0.16, h: 0.18,
      fontSize: 9, color: C.gray, fontFace: "Calibri",
      align: "center", margin: 0,
    });
  }
}

// Section label (small pill)
function sectionLabel(slide, text, x, y, color) {
  slide.addText(text.toUpperCase(), {
    x, y, w: 3, h: 0.22,
    fontSize: 8, bold: true, color, fontFace: "Calibri",
    charSpacing: 2, margin: 0,
  });
}

// ── LOAD DATA ─────────────────────────────────────────────────────────────
const dataFile = process.argv[2] || path.join(__dirname, "kpi_data.json");
if (!fs.existsSync(dataFile)) {
  console.error(`❌ No se encontró: ${dataFile}`);
  console.error("   Primero corre: python generar_reporte_pptx.py tu_archivo.csv");
  process.exit(1);
}
const D = JSON.parse(fs.readFileSync(dataFile, "utf8"));
const fecha = new Date().toLocaleDateString("es-MX", { day:"2-digit", month:"long", year:"numeric" });

// ── BUILD PRESENTATION ───────────────────────────────────────────────────
async function build() {
  const pres = new pptxgen();
  pres.layout = "LAYOUT_16x9";
  pres.title = "Dashboard KPI — Service Desk";
  pres.author = "Service Desk Analytics";

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 1 — PORTADA
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);

    // Large left accent block
    s.addShape("rect", { x: 0, y: 0, w: 0.18, h: 5.625, fill: { color: C.cyan }, line: { color: C.cyan } });

    // Decorative circles
    s.addShape("ellipse", { x: 7.5, y: -0.8, w: 4, h: 4, fill: { color: C.navyLt }, line: { color: C.navyLt } });
    s.addShape("ellipse", { x: 8.2, y: 3.2, w: 2.5, h: 2.5, fill: { color: C.violet, transparency: 80 }, line: { color: C.violet, transparency: 80 } });

    // Main title
    s.addText("DASHBOARD KPI", {
      x: 0.55, y: 1.3, w: 7, h: 0.9,
      fontSize: 42, bold: true, color: C.white, fontFace: "Calibri",
      charSpacing: 4, margin: 0,
    });
    s.addText("SERVICE DESK", {
      x: 0.55, y: 2.15, w: 7, h: 0.65,
      fontSize: 32, bold: false, color: C.cyan, fontFace: "Calibri",
      charSpacing: 6, margin: 0,
    });
    s.addText("Reporte de Indicadores Operativos", {
      x: 0.55, y: 2.85, w: 7, h: 0.35,
      fontSize: 15, color: C.gray, fontFace: "Calibri", margin: 0,
    });

    // Stats row at bottom
    const stats = [
      { v: D.total,        l: "Tickets Totales" },
      { v: D.tasa_cierre + "%", l: "Tasa de Cierre" },
      { v: D.sla_pct + "%",    l: "SLA < 24h" },
      { v: D.env_total,    l: "Control Envíos" },
    ];
    stats.forEach((st, i) => {
      const x = 0.55 + i * 2.3;
      s.addShape("rect", { x, y: 4.05, w: 2.1, h: 1.0, fill: { color: C.navyMid }, line: { color: C.cyan, width: 0.5 } });
      s.addText(String(st.v), { x, y: 4.08, w: 2.1, h: 0.52, fontSize: 26, bold: true, color: C.cyan, fontFace: "Calibri", align: "center", valign: "middle", margin: 0 });
      s.addText(st.l, { x, y: 4.6, w: 2.1, h: 0.3, fontSize: 9, color: C.gray, fontFace: "Calibri", align: "center", charSpacing: 1, margin: 0 });
    });

    s.addText(fecha, { x: 0.55, y: 5.2, w: 5, h: 0.2, fontSize: 9, color: C.grayDk, fontFace: "Calibri", margin: 0 });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 2 — RESUMEN EJECUTIVO (KPI cards 2x3)
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Resumen Ejecutivo");
    divider(s);
    subtitle(s, `Total: ${D.total} tickets analizados · ${fecha}`);

    const cards = [
      { v: D.total,            l: "TOTAL TICKETS",     sub: "Período analizado",          color: C.cyan },
      { v: D.en_curso,         l: "EN CURSO",          sub: `${100-D.tasa_cierre}% del total`, color: C.gold },
      { v: D.cerrados,         l: "CERRADOS",          sub: `${D.tasa_cierre}% tasa cierre`, color: C.green },
      { v: D.sla_pct + "%",    l: "SLA < 24H",         sub: "Tickets resueltos a tiempo",  color: C.violet },
      { v: D.prom_h + "h",     l: "TIEMPO PROMEDIO",   sub: "Resolución de tickets",       color: C.mint },
      { v: D.env_total,        l: "CONTROL ENVÍOS",    sub: `${D.env_pend} pendientes`,    color: C.red },
    ];

    const cols = [0.4, 3.55, 6.7];
    const rows = [1.1, 3.2];
    cards.forEach((c, i) => {
      kpiCard(s, cols[i % 3], rows[Math.floor(i / 3)], 2.85, 1.85, c.v, c.l, c.sub, c.color);
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 3 — ESTADO DE TICKETS (donut + bars)
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Estado de Tickets");
    divider(s);
    subtitle(s, "Distribución por estado de atención");

    // Donut chart left — single series, multiple labels/values
    const pieColors = ["F59E0B", "22C55E", "00C8E0", "7B61FF", "EF4444"];
    s.addChart(pres.charts.DOUGHNUT, [{
      name: "Tickets",
      labels: D.estados_ticket.map(([k]) => k),
      values: D.estados_ticket.map(([, v]) => v),
    }], {
      x: 0.4, y: 0.95, w: 4.2, h: 4.3,
      chartColors: pieColors,
      chartArea: { fill: { color: C.navy } },
      showLegend: true, legendPos: "b", legendColor: C.offwhite, legendFontSize: 9,
      showPercent: true, dataLabelColor: C.white, dataLabelFontSize: 10,
      holeSize: 55,
    });

    // Stats column right
    sectionLabel(s, "Detalle por estado", 4.9, 0.95, C.cyan);
    D.estados_ticket.forEach(([estado, cnt], i) => {
      const y = 1.25 + i * 0.72;
      const pct = Math.round(cnt / D.total * 100);
      const color = pieColors[i] || "94A3B8";
      s.addShape("rect", { x: 4.9, y, w: 4.7, h: 0.58, fill: { color: C.navyMid }, line: { color, width: 0.5 } });
      s.addShape("rect", { x: 4.9, y, w: 0.06, h: 0.58, fill: { color }, line: { color } });
      s.addText(estado, { x: 5.05, y: y + 0.04, w: 2.8, h: 0.22, fontSize: 10, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });
      s.addText(`${pct}% del total`, { x: 5.05, y: y + 0.3, w: 2.8, h: 0.18, fontSize: 9, color: C.gray, fontFace: "Calibri", margin: 0 });
      s.addText(String(cnt), { x: 8.8, y: y + 0.1, w: 0.7, h: 0.35, fontSize: 16, bold: true, color, fontFace: "Calibri", align: "right", margin: 0 });
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 4 — DIVISIÓN TERRITORIAL
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "División Territorial — Por Regional");
    divider(s);
    subtitle(s, "Volumen de tickets por zona geográfica");

    const labels = D.reg_top.map(([k]) => k);
    const values = D.reg_top.map(([, v]) => v);

    s.addChart(pres.charts.BAR, [{ name: "Tickets", labels, values }], {
      x: 0.4, y: 0.95, w: 6.1, h: 4.35,
      barDir: "bar",
      chartColors: [C.cyan],
      chartArea: { fill: { color: C.navyMid }, roundedCorners: false },
      catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 10,
      valAxisLabelColor: C.gray,     valAxisLabelFontSize: 9,
      valGridLine: { color: C.grayDk, size: 0.5 },
      catGridLine: { style: "none" },
      showValue: true, dataLabelColor: C.white, dataLabelFontSize: 9,
      showLegend: false,
    });

    // Alert box right
    s.addShape("rect", { x: 6.8, y: 0.95, w: 2.8, h: 1.4, fill: { color: C.navyMid }, line: { color: C.red, width: 0.5 } });
    s.addShape("rect", { x: 6.8, y: 0.95, w: 2.8, h: 0.06, fill: { color: C.red }, line: { color: C.red } });
    s.addText("⚠️  ALERTA", { x: 6.9, y: 1.05, w: 2.5, h: 0.22, fontSize: 9, bold: true, color: C.red, fontFace: "Calibri", charSpacing: 1, margin: 0 });
    s.addText(`${D.sin_reg} tickets`, { x: 6.9, y: 1.27, w: 2.5, h: 0.38, fontSize: 22, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });
    s.addText("Sin regional asignada", { x: 6.9, y: 1.65, w: 2.5, h: 0.2, fontSize: 9, color: C.gray, fontFace: "Calibri", margin: 0 });
    s.addText(`${Math.round(D.sin_reg / D.total * 100)}% del total`, { x: 6.9, y: 1.85, w: 2.5, h: 0.18, fontSize: 9, color: C.gold, fontFace: "Calibri", margin: 0 });

    // Top 3 regionales
    sectionLabel(s, "Top Regionales", 6.8, 2.55, C.cyan);
    D.reg_top.slice(0, 3).forEach(([reg, cnt], i) => {
      const y = 2.82 + i * 0.78;
      const pct = Math.round(cnt / D.total * 100);
      s.addShape("rect", { x: 6.8, y, w: 2.8, h: 0.65, fill: { color: C.navyMid }, line: { color: CHART_COLORS[i], width: 0.5 } });
      s.addText(`#${i + 1}  ${reg}`, { x: 6.9, y: y + 0.05, w: 2.5, h: 0.22, fontSize: 9, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });
      s.addText(`${cnt} tickets · ${pct}%`, { x: 6.9, y: y + 0.3, w: 2.5, h: 0.2, fontSize: 9, color: C.gray, fontFace: "Calibri", margin: 0 });
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 5 — COORDINACIÓN
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Distribución por Coordinación");
    divider(s);
    subtitle(s, "Tickets asignados por coordinador y gerencia");

    const coordEntries = Object.entries(D.coord_tickets).sort((a, b) => b[1] - a[1]);
    const coordLabels = coordEntries.map(([zona]) => D.coord_info[zona].coord);
    const coordValues = coordEntries.map(([, v]) => v);

    // Chart left
    s.addChart(pres.charts.BAR, [{ name: "Tickets", labels: coordLabels, values: coordValues }], {
      x: 0.4, y: 0.95, w: 5.4, h: 4.35,
      barDir: "bar",
      chartColors: CHART_COLORS,
      chartArea: { fill: { color: C.navyMid } },
      catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 10,
      valAxisLabelColor: C.gray,
      valGridLine: { color: C.grayDk, size: 0.5 }, catGridLine: { style: "none" },
      showValue: true, dataLabelColor: C.white, dataLabelFontSize: 9,
      showLegend: false,
    });

    // Coord cards right — top 6 para no desbordar el slide
    const cardEntries = coordEntries.slice(0, 6);
    sectionLabel(s, `Detalle Coordinadores (Top ${cardEntries.length} de ${coordEntries.length})`, 6.1, 0.95, C.violet);
    cardEntries.forEach(([zona, cnt], i) => {
      const info = D.coord_info[zona];
      const y = 1.22 + i * 0.73;
      const color = CHART_COLORS[i] || C.gray;
      s.addShape("rect", { x: 6.1, y, w: 3.5, h: 0.6, fill: { color: C.navyMid }, line: { color, width: 0.5 } });
      s.addShape("rect", { x: 6.1, y, w: 0.06, h: 0.6, fill: { color }, line: { color } });
      s.addText(info.coord, { x: 6.22, y: y + 0.04, w: 2.3, h: 0.2, fontSize: 10, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });
      s.addText(zona, { x: 6.22, y: y + 0.26, w: 2, h: 0.16, fontSize: 8, color: color, fontFace: "Calibri", margin: 0 });
      s.addText(String(cnt), { x: 8.8, y: y + 0.1, w: 0.65, h: 0.35, fontSize: 18, bold: true, color, fontFace: "Calibri", align: "right", margin: 0 });
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 6 — POR ESTADO GEOGRÁFICO
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Tickets por Estado");
    divider(s);
    subtitle(s, "Top 8 estados con mayor volumen de tickets");

    const labels = D.est_top.map(([k]) => k);
    const values = D.est_top.map(([, v]) => v);

    s.addChart(pres.charts.BAR, [{ name: "Tickets", labels, values }], {
      x: 0.4, y: 0.95, w: 9.2, h: 4.4,
      barDir: "col",
      chartColors: CHART_COLORS,
      chartArea: { fill: { color: C.navyMid }, roundedCorners: false },
      catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 9,
      valAxisLabelColor: C.gray,
      valGridLine: { color: C.grayDk, size: 0.5 }, catGridLine: { style: "none" },
      showValue: true, dataLabelColor: C.white, dataLabelFontSize: 10, dataLabelPosition: "outEnd",
      showLegend: false,
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 7 — POR IDC
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Ranking por Técnico IDC");
    divider(s);
    subtitle(s, "Top 6 IDCs por volumen de tickets atendidos");

    const labels = D.idc_top.map(([k]) => k.split(" ").slice(0, 2).join(" "));
    const values = D.idc_top.map(([, v]) => v);

    // Bar chart left
    s.addChart(pres.charts.BAR, [{ name: "Tickets", labels, values }], {
      x: 0.4, y: 0.95, w: 6.2, h: 4.35,
      barDir: "bar",
      chartColors: [C.mint],
      chartArea: { fill: { color: C.navyMid } },
      catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 10,
      valAxisLabelColor: C.gray,
      valGridLine: { color: C.grayDk, size: 0.5 }, catGridLine: { style: "none" },
      showValue: true, dataLabelColor: C.white, dataLabelFontSize: 10,
      showLegend: false,
    });

    // Alert sin asignar
    s.addShape("rect", { x: 6.9, y: 0.95, w: 2.7, h: 1.7, fill: { color: C.navyMid }, line: { color: C.red, width: 1 } });
    s.addShape("rect", { x: 6.9, y: 0.95, w: 2.7, h: 0.07, fill: { color: C.red }, line: { color: C.red } });
    s.addText("⚠️  SIN IDC ASIGNADO", { x: 7.0, y: 1.07, w: 2.4, h: 0.2, fontSize: 8, bold: true, color: C.red, fontFace: "Calibri", charSpacing: 1, margin: 0 });
    s.addText(String(D.sin_idc), { x: 7.0, y: 1.28, w: 2.4, h: 0.55, fontSize: 34, bold: true, color: C.white, fontFace: "Calibri", align: "center", margin: 0 });
    s.addText("tickets sin asignar", { x: 7.0, y: 1.84, w: 2.4, h: 0.2, fontSize: 9, color: C.gray, fontFace: "Calibri", align: "center", margin: 0 });
    s.addText(`${Math.round(D.sin_idc / D.total * 100)}% del total · Impacta trazabilidad`, { x: 7.0, y: 2.05, w: 2.4, h: 0.2, fontSize: 8, color: C.gold, fontFace: "Calibri", align: "center", margin: 0 });

    // Top 3 IDC cards
    sectionLabel(s, "Top IDCs", 6.9, 2.5, C.mint);
    D.idc_top.slice(0, 3).forEach(([nombre, cnt], i) => {
      const y = 2.77 + i * 0.72;
      const color = CHART_COLORS[i];
      s.addShape("rect", { x: 6.9, y, w: 2.7, h: 0.6, fill: { color: C.navyMid }, line: { color, width: 0.5 } });
      s.addText(`#${i + 1}`, { x: 6.95, y: y + 0.15, w: 0.3, h: 0.3, fontSize: 11, bold: true, color, fontFace: "Calibri", margin: 0 });
      s.addText(nombre.split(" ").slice(0, 3).join(" "), { x: 7.28, y: y + 0.04, w: 1.8, h: 0.26, fontSize: 9, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });
      s.addText(`${cnt} tickets`, { x: 7.28, y: y + 0.32, w: 1.8, h: 0.18, fontSize: 9, color: C.gray, fontFace: "Calibri", margin: 0 });
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 8 — TÉCNICOS CON MENOR CARGA
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Ranking por Técnico IDC — Menor Carga", 0.3, C.gold);
    divider(s);
    subtitle(s, "Bottom 6 IDCs por volumen de tickets atendidos");

    const labels = D.idc_bottom.map(([k]) => k.split(" ").slice(0, 2).join(" "));
    const values = D.idc_bottom.map(([, v]) => v);

    // Bar chart left
    s.addChart(pres.charts.BAR, [{ name: "Tickets", labels, values }], {
      x: 0.4, y: 0.95, w: 6.2, h: 4.35,
      barDir: "bar",
      chartColors: [C.gold],
      chartArea: { fill: { color: C.navyMid } },
      catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 10,
      valAxisLabelColor: C.gray,
      valGridLine: { color: C.grayDk, size: 0.5 }, catGridLine: { style: "none" },
      showValue: true, dataLabelColor: C.white, dataLabelFontSize: 10,
      showLegend: false,
    });

    // Alert sin asignar
    s.addShape("rect", { x: 6.9, y: 0.95, w: 2.7, h: 1.7, fill: { color: C.navyMid }, line: { color: C.red, width: 1 } });
    s.addShape("rect", { x: 6.9, y: 0.95, w: 2.7, h: 0.07, fill: { color: C.red }, line: { color: C.red } });
    s.addText("⚠️  SIN IDC ASIGNADO", { x: 7.0, y: 1.07, w: 2.4, h: 0.2, fontSize: 8, bold: true, color: C.red, fontFace: "Calibri", charSpacing: 1, margin: 0 });
    s.addText(String(D.sin_idc), { x: 7.0, y: 1.28, w: 2.4, h: 0.55, fontSize: 34, bold: true, color: C.white, fontFace: "Calibri", align: "center", margin: 0 });
    s.addText("tickets sin asignar", { x: 7.0, y: 1.84, w: 2.4, h: 0.2, fontSize: 9, color: C.gray, fontFace: "Calibri", align: "center", margin: 0 });
    s.addText(`${Math.round(D.sin_idc / D.total * 100)}% del total · Impacta trazabilidad`, { x: 7.0, y: 2.05, w: 2.4, h: 0.2, fontSize: 8, color: C.gold, fontFace: "Calibri", align: "center", margin: 0 });

    // Bottom 3 IDC cards
    sectionLabel(s, "Bottom IDCs", 6.9, 2.5, C.gold);
    D.idc_bottom.slice(0, 3).forEach(([nombre, cnt], i) => {
      const y = 2.77 + i * 0.72;
      const color = CHART_COLORS[i];
      s.addShape("rect", { x: 6.9, y, w: 2.7, h: 0.6, fill: { color: C.navyMid }, line: { color, width: 0.5 } });
      s.addText(`#${i + 1}`, { x: 6.95, y: y + 0.15, w: 0.3, h: 0.3, fontSize: 11, bold: true, color, fontFace: "Calibri", margin: 0 });
      s.addText(nombre.split(" ").slice(0, 3).join(" "), { x: 7.28, y: y + 0.04, w: 1.8, h: 0.26, fontSize: 9, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });
      s.addText(`${cnt} tickets`, { x: 7.28, y: y + 0.32, w: 1.8, h: 0.18, fontSize: 9, color: C.gray, fontFace: "Calibri", margin: 0 });
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 9 — CATEGORÍAS Y PROYECTOS
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Categorías y Proyectos");
    divider(s);
    subtitle(s, "Distribución por cliente/categoría y tipo de proyecto");

    // Bar categorías
    s.addText("CATEGORÍA / CLIENTE", { x: 0.4, y: 0.9, w: 4.5, h: 0.22, fontSize: 8, bold: true, color: C.cyan, fontFace: "Calibri", charSpacing: 2, margin: 0 });
    s.addChart(pres.charts.BAR,
      [{ name: "Tickets", labels: D.cat_top.map(([k]) => k), values: D.cat_top.map(([, v]) => v) }],
      {
        x: 0.4, y: 1.1, w: 4.5, h: 4.1,
        barDir: "bar",
        chartColors: CHART_COLORS,
        chartArea: { fill: { color: C.navyMid } },
        catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 9,
        valAxisLabelColor: C.gray,
        valGridLine: { color: C.grayDk, size: 0.5 }, catGridLine: { style: "none" },
        showValue: true, dataLabelColor: C.white, dataLabelFontSize: 9,
        showLegend: false,
      }
    );

    // Bar proyectos
    s.addText("TIPO DE PROYECTO", { x: 5.2, y: 0.9, w: 4.5, h: 0.22, fontSize: 8, bold: true, color: C.violet, fontFace: "Calibri", charSpacing: 2, margin: 0 });
    s.addChart(pres.charts.BAR,
      [{ name: "Tickets", labels: D.proy_top.map(([k]) => k), values: D.proy_top.map(([, v]) => v) }],
      {
        x: 5.2, y: 1.1, w: 4.4, h: 4.1,
        barDir: "bar",
        chartColors: [C.violet],
        chartArea: { fill: { color: C.navyMid } },
        catAxisLabelColor: C.offwhite, catAxisLabelFontSize: 10,
        valAxisLabelColor: C.gray,
        valGridLine: { color: C.grayDk, size: 0.5 }, catGridLine: { style: "none" },
        showValue: true, dataLabelColor: C.white, dataLabelFontSize: 10,
        showLegend: false,
      }
    );
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 10 — CONTROL DE ENVÍOS
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navy);
    title(s, "Control de Envíos", 0.3, C.gold);
    divider(s, 0.72);
    subtitle(s, "Tickets de la categoría Control de Envíos");

    // KPI cards top
    [
      { v: D.env_total,            l: "TOTAL ENVÍOS",   sub: "Registrados",              color: C.gold },
      { v: D.env_pend,             l: "PENDIENTES",     sub: "En curso sin cerrar",      color: C.red },
      { v: D.env_cerr,             l: "CERRADOS",       sub: "Finalizados",              color: C.green },
      { v: D.env_pct + "%",        l: "TASA CIERRE",    sub: "% resueltos",              color: C.violet },
    ].forEach((c, i) => {
      kpiCard(s, 0.4 + i * 2.3, 1.05, 2.1, 1.7, c.v, c.l, c.sub, c.color);
    });

    // Donut chart
    s.addChart(pres.charts.DOUGHNUT,
      [{ name: "Envíos", labels: ["Pendientes", "Cerrados"], values: [D.env_pend, D.env_cerr] }],
      {
        x: 0.4, y: 2.95, w: 4.2, h: 2.5,
        chartColors: [C.red, C.green],
        chartArea: { fill: { color: C.navyMid } },
        showLegend: true, legendPos: "b", legendColor: C.offwhite, legendFontSize: 10,
        showPercent: true, dataLabelFontSize: 11,
        holeSize: 50,
      }
    );

    // Alert
    const alertColor = D.env_pct < 30 ? C.red : D.env_pct < 70 ? C.gold : C.green;
    const alertTxt = D.env_pct < 30 ? "CRÍTICO" : D.env_pct < 70 ? "ATENCIÓN" : "NORMAL";
    s.addShape("rect", { x: 4.9, y: 2.95, w: 4.7, h: 2.5, fill: { color: C.navyMid }, line: { color: alertColor, width: 1 } });
    s.addShape("rect", { x: 4.9, y: 2.95, w: 4.7, h: 0.07, fill: { color: alertColor }, line: { color: alertColor } });
    s.addText(`🚨 ESTADO: ${alertTxt}`, { x: 5.0, y: 3.08, w: 4.4, h: 0.25, fontSize: 10, bold: true, color: alertColor, fontFace: "Calibri", charSpacing: 1, margin: 0 });
    s.addText(`${D.env_pend} de ${D.env_total} envíos permanecen abiertos (${100 - D.env_pct}%)`, { x: 5.0, y: 3.38, w: 4.4, h: 0.3, fontSize: 12, color: C.white, fontFace: "Calibri", margin: 0 });

    const recs = [
      "Priorizar revisión de envíos en curso",
      "Implementar alerta automática >48h sin actualización",
      "Generar reporte semanal dedicado a envíos",
    ];
    recs.forEach((r, i) => {
      s.addShape("ellipse", { x: 5.0, y: 3.85 + i * 0.45, w: 0.18, h: 0.18, fill: { color: alertColor }, line: { color: alertColor } });
      s.addText(r, { x: 5.26, y: 3.83 + i * 0.45, w: 4.2, h: 0.22, fontSize: 10, color: C.offwhite, fontFace: "Calibri", margin: 0 });
    });
  }

  // ────────────────────────────────────────────────────────────────────────
  // SLIDE 11 — CIERRE / CONCLUSIONES
  // ────────────────────────────────────────────────────────────────────────
  {
    const s = pres.addSlide();
    bg(s, C.navyMid);

    s.addShape("rect", { x: 0, y: 0, w: 0.18, h: 5.625, fill: { color: C.violet }, line: { color: C.violet } });
    s.addShape("ellipse", { x: 7, y: -1, w: 5, h: 5, fill: { color: C.navyLt }, line: { color: C.navyLt } });
    s.addShape("ellipse", { x: 8.5, y: 3.5, w: 3, h: 3, fill: { color: C.violet, transparency: 85 }, line: { color: C.violet, transparency: 85 } });

    s.addText("PUNTOS CLAVE", { x: 0.55, y: 0.8, w: 5, h: 0.3, fontSize: 11, bold: true, color: C.violet, fontFace: "Calibri", charSpacing: 3, margin: 0 });
    s.addText("Conclusiones\nOperativas", { x: 0.55, y: 1.12, w: 6, h: 1.2, fontSize: 34, bold: true, color: C.white, fontFace: "Calibri", margin: 0 });

    const puntos = [
      { icon: "📊", txt: `${D.tasa_cierre}% de tasa de cierre — ${D.cerrados} tickets resueltos de ${D.total}` },
      { icon: "⏱️", txt: `SLA ${D.sla_pct}% dentro de 24h · Tiempo promedio: ${D.prom_h}h` },
      { icon: "⚠️", txt: `${D.sin_reg} tickets sin regional y ${D.sin_idc} sin IDC asignado — requieren acción` },
      { icon: "📦", txt: `Control de Envíos: ${D.env_pend} pendientes (${100 - D.env_pct}%) — prioridad alta` },
    ];

    puntos.forEach((p, i) => {
      const y = 2.55 + i * 0.65;
      s.addShape("rect", { x: 0.55, y, w: 6.0, h: 0.52, fill: { color: C.navy }, line: { color: C.violet, width: 0.4 } });
      s.addText(p.icon + "  " + p.txt, { x: 0.7, y: y + 0.1, w: 5.7, h: 0.32, fontSize: 11, color: C.offwhite, fontFace: "Calibri", margin: 0 });
    });

    s.addText(fecha, { x: 0.55, y: 5.2, w: 5, h: 0.2, fontSize: 9, color: C.grayDk, fontFace: "Calibri", margin: 0 });
  }

  // ── SAVE ─────────────────────────────────────────────────────────────────
  const outFile = path.join(__dirname, "reporte_kpi_servicedesk.pptx");
  await pres.writeFile({ fileName: outFile });
  console.log(`\n✅ Presentación generada: ${outFile}`);
  console.log(`   11 slides · Tema Midnight Executive`);
}

build().catch(e => { console.error("❌ Error:", e.message); process.exit(1); });
