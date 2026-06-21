import { Controller } from '@hotwired/stimulus';

/**
 * Challenge — gère les check-ins quotidiens avec animations.
 * Targets: dayBtn, progressBar, progressText, streakCount, checkedCount, completionBanner
 * Values:  participationId (String)
 */
export default class extends Controller {
    static targets = ['dayBtn', 'progressBar', 'progressText', 'streakCount', 'checkedCount', 'completionBanner'];
    static values  = { participationId: String };

    async checkDay(event) {
        const btn = event.currentTarget;
        const day = parseInt(btn.dataset.day, 10);

        if (btn.disabled) return;
        btn.disabled = true;

        try {
            const res = await fetch(
                `/defis/participation/${this.participationIdValue}/check/${day}`,
                { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } },
            );

            if (!res.ok) { btn.disabled = false; return; }
            const data = await res.json();

            this._updateDayBtn(btn, day, data.checked);
            this._updateStats(data);

            if (data.checked) {
                if (data.isCompleted) {
                    this._celebrateCompletion();
                } else if (data.isMilestone) {
                    this._celebrateMilestone(day, btn);
                } else {
                    this._popBtn(btn);
                }
            }
        } catch (_) {
            // silent
        } finally {
            btn.disabled = false;
        }
    }

    // ─── UI updates ──────────────────────────────────────────────────────────

    _updateDayBtn(btn, day, checked) {
        btn.dataset.checked = checked ? '1' : '0';
        if (checked) {
            btn.classList.add('day-checked');
            btn.classList.remove('day-unchecked');
            btn.innerHTML = `<span class="day-num">${day}</span><span class="day-tick">✓</span>`;
        } else {
            btn.classList.remove('day-checked');
            btn.classList.add('day-unchecked');
            btn.innerHTML = `<span class="day-num">${day}</span>`;
        }
    }

    _updateStats(data) {
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${data.progress}%`;
        }
        if (this.hasProgressTextTarget) {
            this.progressTextTarget.textContent = `${data.progress}%`;
        }
        if (this.hasStreakCountTarget) {
            this.streakCountTarget.textContent = data.streak;
            this._animateEl(this.streakCountTarget, 'anim-streak-pop');
        }
        if (this.hasCheckedCountTarget) {
            this.checkedCountTarget.textContent = data.total;
        }
    }

    // ─── Animations ──────────────────────────────────────────────────────────

    _popBtn(btn) {
        this._animateEl(btn, 'anim-pop');
    }

    _celebrateMilestone(day, btn) {
        this._popBtn(btn);
        const messages = {
            7:  '🔥 1 semaine, t\'es chaud !',
            14: '⭐ Mi-parcours, ne lâche rien !',
            21: '💪 21 jours = nouvelle habitude !',
            30: '🏆 30 JOURS — LÉGENDAIRE !',
        };
        this._spawnParticles(12);
        this._showToast(messages[day] ?? '⭐ Milestone !' , 4000);
    }

    _celebrateCompletion() {
        if (this.hasCompletionBannerTarget) {
            this.completionBannerTarget.classList.remove('hidden');
            this.completionBannerTarget.classList.add('anim-pop');
        }
        for (let i = 0; i < 30; i++) {
            setTimeout(() => this._spawnParticles(3), i * 80);
        }
        this._showToast('🏆 DÉFI COMPLÉTÉ ! Tu es une LÉGENDE ! 🏆', 6000);
    }

    _spawnParticles(count) {
        const emojis = ['🎉', '⭐', '🔥', '💪', '🏆', '🎊', '✨', '🌟', '💯'];
        for (let i = 0; i < count; i++) {
            const el       = document.createElement('div');
            const dx       = (Math.random() - 0.5) * 300;
            const dy       = -(120 + Math.random() * 250);
            const size     = 1.2 + Math.random() * 1.2;
            el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
            el.style.cssText = [
                'position:fixed',
                `left:${10 + Math.random() * 80}vw`,
                'top:55vh',
                `font-size:${size}rem`,
                'pointer-events:none',
                'z-index:9999',
                `--dx:${dx}px`,
                `--dy:${dy}px`,
                'animation:challengeParticleFly 1.3s ease-out forwards',
            ].join(';');
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 1400);
        }
    }

    _showToast(message, duration = 3000) {
        const toast       = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = [
            'position:fixed',
            'bottom:5rem',
            'left:50%',
            'transform:translateX(-50%) translateY(16px)',
            'background:linear-gradient(135deg,#f59e0b,#ef4444)',
            'color:#fff',
            'padding:.85rem 1.8rem',
            'border-radius:1rem',
            'font-size:1.05rem',
            'font-weight:700',
            'z-index:9998',
            'opacity:0',
            'transition:opacity .25s,transform .25s',
            'text-align:center',
            'box-shadow:0 8px 24px rgba(0,0,0,.35)',
            'max-width:90vw',
        ].join(';');
        document.body.appendChild(toast);
        requestAnimationFrame(() => {
            toast.style.opacity    = '1';
            toast.style.transform  = 'translateX(-50%) translateY(0)';
        });
        setTimeout(() => {
            toast.style.opacity   = '0';
            toast.style.transform = 'translateX(-50%) translateY(16px)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    _animateEl(el, cls) {
        el.classList.remove(cls);
        void el.offsetWidth; // reflow
        el.classList.add(cls);
        el.addEventListener('animationend', () => el.classList.remove(cls), { once: true });
    }
}
