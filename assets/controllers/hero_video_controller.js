import { Controller } from '@hotwired/stimulus';

/**
 * Play/pause and sound toggles for the landing-page clip. The clip starts
 * muted (browsers only autoplay silent video); the buttons let a visitor
 * stop it or hear it. Each button carries both icons and both labels, and
 * this swaps which one shows so the label always says what a press will do.
 *
 * Anyone who has asked their OS for less motion never sees the clip
 * (app.css), so it is paused here too rather than playing out of sight.
 */
export default class extends Controller {
    static targets = ['video', 'play', 'mute'];
    static values = {
        playLabel: String,
        pauseLabel: String,
        muteLabel: String,
        unmuteLabel: String,
    };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.videoTarget.pause();
        }

        this.render();
    }

    togglePlay() {
        if (this.videoTarget.paused) {
            this.videoTarget.play();
        } else {
            this.videoTarget.pause();
        }
    }

    toggleMute() {
        this.videoTarget.muted = !this.videoTarget.muted;
        // Asking for sound on a paused clip means "play it".
        if (!this.videoTarget.muted && this.videoTarget.paused) {
            this.videoTarget.play();
        }
        this.render();
    }

    // Also wired to the video's play/pause/volumechange events, so the
    // buttons follow the clip whatever changed it.
    render() {
        const { paused, muted } = this.videoTarget;

        this.#show(this.playTarget, paused ? 'play' : 'pause');
        this.playTarget.setAttribute('aria-label', paused ? this.playLabelValue : this.pauseLabelValue);

        this.#show(this.muteTarget, muted ? 'unmute' : 'mute');
        this.muteTarget.setAttribute('aria-label', muted ? this.unmuteLabelValue : this.muteLabelValue);
    }

    #show(button, icon) {
        button.querySelectorAll('[data-icon]').forEach((svg) => {
            svg.classList.toggle('hidden', svg.dataset.icon !== icon);
        });
    }
}
