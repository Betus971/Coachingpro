import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

// ─── Palette CoachPro ─────────────────────────────────────────────────────────
const C = {
  fat:     '#ff6b6b',  // coral  — masse grasse
  lean:    '#9b6dff',  // purple — poids total / maigre
  muscle:  '#00c9a7',  // teal   — masse musculaire
  bone:    '#9898b0',  // gris   — ossature & autres
  bg:      '#1c1c28',
  border:  '#2a2a3e',
  text:    '#eeeef5',
  subtext: '#9898b0',
  grid:    '#2a2a3e',
};

/**
 * Controller Stimulus — page Historique Poids.
 *
 * data-weight-composition-logs-value : JSON des pesées
 * [{date, weightKg, fatKg, muscleKg, fatPercent, waterPercent, bmi}] récent -> ancien.
 */
export default class extends Controller {
  static values = { logs: Array };

  static targets = [
    'kpiWeight', 'kpiFat', 'kpiMuscle', 'kpiBmi',
    'kpiWeightDelta', 'kpiFatDelta', 'kpiMuscleDelta',
    'composition', 'compositionEmpty',
    'evolution',
  ];

  evolutionChart = null;

  connect() {
    const logs = this.logsValue;
    if (!logs || logs.length === 0) return;

    this.renderKpis(logs);
    this.renderComposition(logs[0]); // composition la plus récente
    this.renderEvolution(logs);
  }

  disconnect() {
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

    this._renderDelta(this.hasKpiWeightDeltaTarget ? this.kpiWeightDeltaTarget : null, delta(cur, prev, 'weightKg'),   'kg', false);
    this._renderDelta(this.hasKpiFatDeltaTarget    ? this.kpiFatDeltaTarget    : null, delta(cur, prev, 'fatPercent'), '%',  false);
    this._renderDelta(this.hasKpiMuscleDeltaTarget ? this.kpiMuscleDeltaTarget : null, delta(cur, prev, 'muscleKg'),   'kg', true);
  }

  /** positiveIsGood=true → hausse en vert ; false → hausse en rouge */
  _renderDelta(el, value, unit, positiveIsGood) {
    if (!el || value == null) return;
    const v = parseFloat(value);
    const sign = v > 0 ? '+' : '';
    let color = C.subtext;
    if (v > 0) color = positiveIsGood ? '#3dd68c' : '#ff6b6b';
    if (v < 0) color = positiveIsGood ? '#ff6b6b' : '#3dd68c';
    el.textContent = `${sign}${value} ${unit}`;
    el.style.color = color;
  }

  // ─── Composition corporelle (barre empilée + lignes) ────────────────────────

  renderComposition(log) {
    if (!this.hasCompositionTarget) return;

    const weight = parseFloat(log.weightKg);
    const fat    = parseFloat(log.fatKg ?? 0);
    const muscle = parseFloat(log.muscleKg ?? 0);

    // Pas de données de composition → message vide
    if (!fat && !muscle) {
      this.compositionTarget.classList.add('hidden');
      if (this.hasCompositionEmptyTarget) this.compositionEmptyTarget.classList.remove('hidden');
      return;
    }
    this.compositionTarget.classList.remove('hidden');
    if (this.hasCompositionEmptyTarget) this.compositionEmptyTarget.classList.add('hidden');

    const rest = Math.max(0, weight - fat - muscle); // ossature, eau structurelle, organes…
    const pct  = (v) => (weight > 0 ? (v / weight) * 100 : 0);
    const fmt  = (v) => v.toFixed(1);

    const items = [
      { label: 'Masse grasse',      kg: fat,    pct: pct(fat),    color: C.fat },
      { label: 'Masse musculaire',  kg: muscle, pct: pct(muscle), color: C.muscle },
      { label: 'Ossature & autres', kg: rest,   pct: pct(rest),   color: C.bone },
    ].filter((i) => i.kg > 0);

    const bar = items
      .map((i) => `<div style="width:${i.pct}%;background:${i.color}"></div>`)
      .join('');

    const rows = items
      .map((i) => `
        <div class="flex items-center justify-between py-1.5 border-b border-base-200/50 last:border-0">
          <span class="flex items-center gap-2 text-sm">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:${i.color}"></span>${i.label}
          </span>
          <span class="text-sm"><strong>${fmt(i.kg)} kg</strong> <span class="opacity-40 ml-1">${i.pct.toFixed(0)}%</span></span>
        </div>`)
      .join('');

    this.compositionTarget.innerHTML = `
      <div class="flex justify-between items-baseline mb-3">
        <span class="text-sm opacity-50">Poids total</span>
        <span class="font-display text-2xl" style="color:${C.lean}">${fmt(weight)} kg</span>
      </div>
      <div class="flex w-full h-6 rounded-lg overflow-hidden mb-4">${bar}</div>
      <div>${rows}</div>`;
  }

  // ─── Évolution temporelle ──────────────────────────────────────────────────

  renderEvolution(logs) {
    if (!this.hasEvolutionTarget) return;

    const ordered = [...logs].reverse();
    const labels  = ordered.map((l) => {
      const d = new Date(l.date);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: '2-digit' });
    });

    const weights   = ordered.map((l) => parseFloat(l.weightKg));
    const fats      = ordered.map((l) => (l.fatKg ? parseFloat(l.fatKg) : null));
    const muscles   = ordered.map((l) => (l.muscleKg ? parseFloat(l.muscleKg) : null));
    const hasFat    = fats.some((v) => v != null);
    const hasMuscle = muscles.some((v) => v != null);

    const makeDataset = (label, data, color, hidden = false) => ({
      label, data,
      borderColor: color,
      backgroundColor: color + '22',
      fill: false, tension: 0.4,
      pointRadius: 5, pointHoverRadius: 7, borderWidth: 2.5,
      spanGaps: true, hidden,
    });

    const datasets = [makeDataset('Poids total (kg)', weights, C.lean)];
    if (hasFat)    datasets.push(makeDataset('Masse grasse (kg)', fats, C.fat));
    if (hasMuscle) datasets.push(makeDataset('Masse musculaire (kg)', muscles, C.muscle));

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
            labels: { color: C.subtext, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, font: { size: 11 } },
          },
          tooltip: {
            backgroundColor: C.bg, borderColor: C.border, borderWidth: 1,
            titleColor: C.text, bodyColor: C.subtext,
            callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y != null ? ctx.parsed.y.toFixed(1) + ' kg' : '—'}` },
          },
        },
        scales: {
          x: { ticks: { color: C.subtext, font: { size: 10 } }, grid: { color: C.grid } },
          y: { ticks: { color: C.subtext, font: { size: 10 }, callback: (v) => `${v} kg` }, grid: { color: C.grid } },
        },
      },
    });
  }
}
