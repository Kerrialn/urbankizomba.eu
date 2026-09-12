import { Controller } from '@hotwired/stimulus';
import { createDrawable, createTimeline, utils } from 'animejs';

/**
 * Draws the logo in with anime.js: the disc springs up, then the U is drawn
 * as one stroke and the K's three strokes follow.
 *
 * Plays once per browser session on load (Turbo reconnects the controller on
 * every page visit, and a logo that redraws itself on each click is a
 * distraction), again whenever the mark is hovered, and on the page a click on
 * it leads to. Anyone who has asked their OS for less motion never sees it
 * animate.
 */
export default class extends Controller {
    static targets = ['disc', 'stroke'];

    static SESSION_KEY = 'logo-drawn';

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        // Nothing is built until it is about to play: building sets the parts
        // to their hidden starting state, and on a page where the mark has
        // already played it must simply be there.
        if (this.#alreadyPlayed()) {
            return;
        }

        this.#remember();
        this.timeline = this.#build();
        this.timeline.play();
    }

    disconnect() {
        this.timeline?.pause();
        this.timeline = null;
    }

    /** Hover: redraw from the start, unless it is mid-draw already. */
    replay() {
        if (this.timeline && !this.timeline.completed) {
            return;
        }

        this.timeline ??= this.#build();
        this.timeline.restart();
    }

    /**
     * Click: the logo is a link home, and Turbo swaps the page before an
     * animation started here could finish. Forget that it has played, so the
     * mark draws itself in on the page that arrives instead.
     */
    replayOnNextPage() {
        try {
            sessionStorage.removeItem(this.constructor.SESSION_KEY);
        } catch {
            // Nothing to forget; it plays every time anyway.
        }
    }

    #build() {
        const strokes = createDrawable(this.strokeTargets);

        utils.set(this.discTarget, { scale: 0, transformOrigin: '50% 50%' });
        utils.set(strokes, { draw: '0 0' });

        const timeline = createTimeline({ defaults: { ease: 'outExpo' }, autoplay: false });

        timeline.add(this.discTarget, {
            scale: [0, 1],
            duration: 700,
            ease: 'outElastic(1, .55)',
        });

        strokes.forEach((stroke, index) => {
            timeline.add(stroke, {
                draw: '0 1',
                duration: index === 0 ? 550 : 260,
            }, index === 0 ? '-=520' : '-=120');
        });

        return timeline;
    }

    #alreadyPlayed() {
        try {
            return sessionStorage.getItem(this.constructor.SESSION_KEY) === '1';
        } catch {
            return false;
        }
    }

    #remember() {
        try {
            sessionStorage.setItem(this.constructor.SESSION_KEY, '1');
        } catch {
            // Private mode or storage blocked: it just plays every time.
        }
    }
}
