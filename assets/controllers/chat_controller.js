import { Controller } from '@hotwired/stimulus';

/**
 * Chat IA flottant — widget persistant sur toutes les pages.
 * Targets:
 *   - panel        : le panneau visible/caché
 *   - messages     : la zone de scroll des messages
 *   - input        : le champ texte
 *   - sendBtn      : bouton envoi
 *   - typingIndicator : "..." en cours de frappe IA
 */
export default class extends Controller {
    static targets = ['panel', 'messages', 'input', 'sendBtn', 'typingIndicator'];
    static values  = { open: Boolean };

    connect() {
        this.loadHistory();
    }

    // ─── Toggle panel ─────────────────────────────────────────────────────────

    toggle() {
        this.openValue = !this.openValue;
        this.panelTarget.classList.toggle('hidden', !this.openValue);
        if (this.openValue) {
            this.inputTarget.focus();
            this.scrollToBottom();
        }
    }

    // ─── Chargement historique ─────────────────────────────────────────────────

    async loadHistory() {
        try {
            const res  = await fetch('/chat/history');
            const msgs = await res.json();
            msgs.forEach(m => this.appendMessage(m.role, m.content, m.createdAt, false));
            this.scrollToBottom();
        } catch (_) {
            // silencieux si pas connecté
        }
    }

    // ─── Envoi message ─────────────────────────────────────────────────────────

    async send() {
        const message = this.inputTarget.value.trim();
        if (!message) return;

        // Affiche message user immédiatement
        const now = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        this.appendMessage('user', message, now);
        this.inputTarget.value = '';
        this.sendBtnTarget.disabled = true;

        // Indicateur IA en cours
        this.typingIndicatorTarget.classList.remove('hidden');
        this.scrollToBottom();

        try {
            const res = await fetch('/chat/send', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ message }),
            });

            const data = await res.json();
            this.typingIndicatorTarget.classList.add('hidden');

            if (data.error) {
                this.appendMessage('model', '⚠️ ' + data.error, now);
            } else {
                this.appendMessage('model', data.content, data.createdAt);
            }
        } catch (_) {
            this.typingIndicatorTarget.classList.add('hidden');
            this.appendMessage('model', '⚠️ Erreur réseau, réessaie.', now);
        }

        this.sendBtnTarget.disabled = false;
        this.inputTarget.focus();
    }

    // ─── Touche Entrée ─────────────────────────────────────────────────────────

    onKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.send();
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    appendMessage(role, content, time = '', animate = true) {
        const isUser  = role === 'user';
        const wrapper = document.createElement('div');
        wrapper.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-3`;

        // Convertit les bullet points et sauts de ligne simples en HTML
        const htmlContent = content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/• /g, '<span class="text-primary">•</span> ')
            .replace(/\n/g, '<br>');

        wrapper.innerHTML = `
            <div class="max-w-[80%] ${isUser
                ? 'bg-primary text-primary-content rounded-t-2xl rounded-bl-2xl'
                : 'bg-base-300 text-base-content rounded-t-2xl rounded-br-2xl'
            } px-4 py-2 text-sm shadow-sm ${animate ? 'animate-fade-in' : ''}">
                <div class="leading-relaxed">${htmlContent}</div>
                ${time ? `<div class="text-xs opacity-50 mt-1 text-right">${time}</div>` : ''}
            </div>
        `;

        this.messagesTarget.appendChild(wrapper);
        this.scrollToBottom();
    }

    scrollToBottom() {
        requestAnimationFrame(() => {
            this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
        });
    }
}
