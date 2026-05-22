import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["timerDisplay", "timerContainer", "setRow", "finishButton"];
    static values = {
        restTime: Number // Temps de repos en secondes
    };

    connect() {
        this.timerInterval = null;
        this.currentSeconds = 0;
        this.isResting = false;
        this.wakeLock = null;
        
        // Tente de garder l'écran allumé pendant la séance
        this.requestWakeLock();
    }
    
    disconnect() {
        this.stopTimer();
        this.releaseWakeLock();
    }

    async requestWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
            } catch (err) {
                console.warn(`${err.name}, ${err.message}`);
            }
        }
    }

    releaseWakeLock() {
        if (this.wakeLock !== null) {
            this.wakeLock.release()
                .then(() => {
                    this.wakeLock = null;
                });
        }
    }

    toggleSet(event) {
        const checkbox = event.currentTarget;
        const row = checkbox.closest('.set-row');
        
        if (checkbox.checked) {
            // Série terminée
            row.classList.add('opacity-50', 'bg-base-200');
            row.classList.remove('bg-base-100');
            
            // Lancer le timer de repos
            const defaultRest = parseInt(checkbox.dataset.rest || "90", 10);
            this.startTimer(defaultRest);
        } else {
            // Série décochée (annulation)
            row.classList.remove('opacity-50', 'bg-base-200');
            row.classList.add('bg-base-100');
            
            // Si c'était la dernière cochée, on peut arrêter le chrono (optionnel)
        }
        
        this.checkAllSetsDone();
    }

    startTimer(seconds) {
        this.stopTimer();
        this.currentSeconds = seconds;
        this.isResting = true;
        this.updateTimerDisplay();
        
        if (this.hasTimerContainerTarget) {
            this.timerContainerTarget.classList.remove('hidden');
            this.timerContainerTarget.classList.add('flex');
            
            // Animation pulse
            this.timerContainerTarget.classList.add('animate-pulse');
            setTimeout(() => this.timerContainerTarget.classList.remove('animate-pulse'), 1000);
        }

        this.timerInterval = setInterval(() => {
            this.currentSeconds--;
            this.updateTimerDisplay();

            if (this.currentSeconds <= 0) {
                this.timerFinished();
            }
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
        this.isResting = false;
        
        if (this.hasTimerContainerTarget) {
            this.timerContainerTarget.classList.add('hidden');
            this.timerContainerTarget.classList.remove('flex');
        }
    }
    
    skipTimer() {
        this.stopTimer();
    }

    timerFinished() {
        this.stopTimer();
        
        // Jouer un petit son (beep)
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(1, ctx.currentTime + 0.1);
            gain.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.5);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.5);
        } catch(e) {
            console.log("Audio not supported or blocked");
        }
        
        // Vibreur mobile
        if ("vibrate" in navigator) {
            navigator.vibrate([200, 100, 200]);
        }
    }

    updateTimerDisplay() {
        if (!this.hasTimerDisplayTarget) return;
        
        const m = Math.floor(this.currentSeconds / 60);
        const s = this.currentSeconds % 60;
        this.timerDisplayTarget.textContent = `${m}:${s.toString().padStart(2, '0')}`;
        
        if (this.currentSeconds <= 10) {
            this.timerDisplayTarget.classList.add('text-error');
        } else {
            this.timerDisplayTarget.classList.remove('text-error');
        }
    }

    checkAllSetsDone() {
        if (!this.hasSetRowTarget || !this.hasFinishButtonTarget) return;
        
        const allChecked = this.setRowTargets.every(row => {
            const checkbox = row.querySelector('input[type="checkbox"]');
            return checkbox && checkbox.checked;
        });
        
        if (allChecked) {
            this.finishButtonTarget.classList.remove('btn-outline');
            this.finishButtonTarget.classList.add('btn-primary', 'animate-bounce');
        } else {
            this.finishButtonTarget.classList.add('btn-outline');
            this.finishButtonTarget.classList.remove('btn-primary', 'animate-bounce');
        }
    }
}
