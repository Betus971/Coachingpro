import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';
import { SankeyController, Flow } from 'chartjs-chart-sankey';

Chart.register(...registerables, SankeyController, Flow);

// ─── Palette CoachPro (miroir de tokens.js mobile) ────────────────────────────
const C = {
  fat:     '#ff6b6b',  // coral  — masse grasse
  lean:    '#9b6dff',  // purple — masse maigre (intermédiaire)
  muscle:  '#00c9a7',  // teal   — masse musculaire
  bone:    '#9898b0',  // gris   — ossature & autres
  bg:      '#1c1c28',
  border:  '#2a2a3e',
  text:    '#eeeef5',
  subtext: '#9898b0',
  grid:    '#2a2a3e',
};

/**
 * Controller Stimulus — page Historique Poids
 *
 * Attend un data-attribute `data-weight-composition-logs-value` contenant
 * le JSON des pesées : [{date, weightKg, fatKg, muscleKg, fatPercent, waterPercent, bmi}]
 * trié du plus récent au plus ancien.
 */
export default class extends Controller {
  static values = { logs: Array };

  static targets = [
    // KPI header
    'kpiWeight', 'kpiFat', 'kpiMuscle', 'kpiBmi',
    'kpiWeightDelta', 'kpiFatDelta', 'kpiMuscleDelta',
    // Charts
    'sankey', 'sankeyEmpty',
    'evolution',
  ];

  sankeyChart   = null;
  evolutionChart = null;

  connect() {
    const logs = this.logsValue;
    if (!logs || logs.length === 0) return;

    this.renderKpis(logs);
    this.renderSankey(logs[0]);       // composition la plus récente
    this.renderEvolution(logs);
  }

  disconnect() {
    this.sankeyChart?.destroy();
    this.evolutionChart?.destroy();
  }

  // ─── KPI Header ────────────────────────────────────────────────────────────

  renderKpis(logs) {
    const cur  = logs[0];
    const prev = logs[1] ?? null;

    const fmt1 = (v) => v != null ? parseFloat(v).toFixed(1) : '—';
    const delta = (cur, prev, key) => {
      if (!prev || cur[key] == null || prev[key] == null) return null;
      return (parseFloat(cur[key]) - parseFloat(prev[key])).toFixed(1);
    };

    if (this.hasKpiWeightTarget)  this.kpiWeightTarget.textContent  = `${fmt1(cur.weightKg)} kg`;
    if (this.hasKpiFatTarget)     this.kpiFatTarget.textContent     = cur.fatPercent ? `${cur.fatPercent}%` : '—';
    if (this.hasKpiMuscleTarget)  this.kpiMuscleTarget.textContent  = cur.muscleKg ? `${fmt1(cur.muscleKg)} kg` : '—';
    if (this.hasKpiBmiTarget)     this.kpiBmiTarget.textContent     = cur.bmi ?? '—';

    // Deltas (flèche + valeur)
    this._renderDelta(this.hasKpiWeightDeltaTarget  ? this.kpiWeightDeltaTarget  : null, delta(cur, prev, 'weightKg'), 'kg', false);
    this._renderDelta(this.hasKpiFatDeltaTarget     ? this.kpiFatDeltaTarget     : null, delta(cur, prev, 'fatPercent'), '%', false);
    this._renderDelta(this.hasKpiMuscleDeltaTarget  ? this.kpiMuscleDeltaTarget  : null, delta(cur, prev, 'muscleKg'), 'kg', true);
  }

  /** positiveIsGood=true → hausse en vert ; false → hausse en rouge */
  _renderDelta(el, value, unit, positiveIsGood) {
    if (!el || value == null) return;
    const v = parseFloat(value);
    const sign = v > 0 ? '+' : '';
    const goodColor   = '#3dd68c';
    const badColor    = '#ff6b6b';
    const neutralColor = C.subtext;

    let color = neutralColor;
    if (v > 0) color = positiveIsGood ? goodColor : badColor;
    if (v < 0) color = positiveIsGood ? badColor  : goodColor;

    el.textContent = `${sign}${value} ${unit}`;
    el.style.color  = color;
  }

  // ─── Sankey composition ────────────────────────────────────────────────────

  renderSankey(log) {
    if (!this.hasSankeyTarget) return;

    const weight = parseFloat(log.weightKg);
    const fat    = parseFloat(log.fatKg ?? 0);
    const muscle = parseFloat(log.muscleKg ?? 0);

    // Si pas de données composition → message vide
    if (!fat && !muscle) {
      if (this.hasSankeyEmptyTarget) this.sankeyEmptyTarget.classList.remove('hidden');
      return;
    }
    if (this.hasSankeyEmptyTarget) this.sankeyEmptyTarget.classList.add('hidden');

    // Calculs dérivés
    const lean   = parseFloat((weight - fat).toFixed(2));
    const bone   = parseFloat(Math.max(0, lean - muscle).toFixed(2));

    /*
      Flux :
        Poids total → Masse grasse
        Poids total → Masse maigre
        Masse maigre → Masse musculaire
        Masse maigre → Ossature & autres
    */
    const flows = [
      { from: 'Poids total',   to: 'Masse grasse',       flow: fat    },
      { from: 'Poids total',   to: 'Masse maigre',        flow: lean   },
      { from: 'Masse maigre',  to: 'Masse musculaire',    flow: muscle },
    ];
    if (bone > 0) {
      flows.push({ from: 'Masse maigre', to: 'Ossature & autres', flow: bone });
    }

    const nodeColors = {
      'Poids total':       C.lean,
      'Masse grasse':      C.fat,
      'Masse maigre':      C.lean,
      'Masse musculaire':  C.muscle,
      'Ossature & autres': C.bone,
    };

    this.sankeyChart?.destroy();
    this.sankeyChart = new Chart(this.sankeyTarget, {
      type: 'sankey',
      data: {
        datasets: [{
          data: flows,
          colorFrom: (ctx) => nodeColors[ctx.raw?.from] ?? C.lean,
          colorTo:   (ctx) => nodeColors[ctx.raw?.to]   ?? C.subtext,
          colorMode: 'gradient',
          borderWidth: 0,
          nodeWidth: 16,
          nodePadding: 24,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 700 },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: C.bg,
            borderColor: C.border,
            borderWidth: 1,
            titleColor: C.text,
            bodyColor: C.subtext,
            callbacks: {
              title: () => '',
              label: (ctx) => {
                const { from, to, flow } = ctx.raw;
                const pct = ((flow / weight) * 100).toFixed(1);
                return [`${from} → ${to}`, `${flow.toFixed(1)} kg  (${pct}% du poids total)`];
              },
            },
          },
        },
      },
    });
  }

  // ─── Évolution temporelle ──────────────────────────────────────────────────

  renderEvolution(logs) {
    if (!this.hasEvolutionTarget) return;

    // Inverser pour ordre chronologique
    const ordered = [...logs].reverse();
    const labels  = ordered.map(l => {
      const d = new Date(l.date);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: '2-digit' });
    });

    const weights  = ordered.map(l => parseFloat(l.weightKg));
    const fats     = ordered.map(l => l.fatKg     ? parseFloat(l.fatKg)    : null);
    const muscles  = ordered.map(l => l.muscleKg  ? parseFloat(l.muscleKg) : null);
    const hasFat   = fats.some(v => v != null);
    const hasMuscle = muscles.some(v => v != null);

    const makeDataset = (label, data, color, hidden = false) => ({
      label,
      data,
      borderColor: color,
      backgroundColor: color + '22',
      fill: false,
      tension: 0.4,
      pointRadius: 5,
      pointHoverRadius: 7,
      borderWidth: 2.5,
      spanGaps: true,
      hidden,
    });

    const datasets = [makeDataset('Poids total (kg)', weights, C.lean)];
    if (hasFat)    datasets.push(makeDataset('Masse grasse (kg)', fats,    C.fat,    false));
    if (hasMuscle) datasets.push(makeDataset('Masse musculaire (kg)', muscles, C.muscle, false));

    this.evolutionChart?.destroy();
    this.evolutionChart = new Chart(this.evolutionTarget, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            labels: {
              color: C.subtext,
              usePointStyle: true,
              pointStyle: 'circle',
              boxWidth: 8,
              font: { size: 11 },
            },
          },
          tooltip: {
            backgroundColor: C.bg,
            borderColor: C.border,
            borderWidth: 1,
            titleColor: C.text,
            bodyColor: C.subtext,
            callbacks: {
              label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y != null ? ctx.parsed.y.toFixed(1) + ' kg' : '—'}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: C.subtext, font: { size: 10 } },
            grid:  { color: C.grid },
          },
          y: {
            ticks: {
              color: C.subtext,
              font: { size: 10 },
              callback: (v) => `${v} kg`,
            },
            grid: { color: C.grid },
          },
        },
      },
    });
  }
}
