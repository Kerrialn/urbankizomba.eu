import { Controller } from '@hotwired/stimulus';
import { createDrawable, createTimeline, utils } from 'animejs';

/**
 * Draws the logo in with anime.js: the disc springs up, then the U is drawn
 * as one stroke and the K's three strokes follow.
 *
 * Plays once per browser session. Turbo re-connects the controller on every
 * page visit, and a logo that redraws itself on each click is a distraction;
 * after the first visit the mark is simply there. Anyone who has asked their
 * OS for less motion never sees it animate.
 */
export default class extends Controller {
    static targets = ['disc', 'stroke'];

    static SESSION_KEY = 'logo-drawn';

    connect() {
        if (this.#alreadyPlayed() || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.#remember();

        const strokes = createDrawable(this.strokeTargets);

        utils.set(this.discTarget, { scale: 0, transformOrigin: '50% 50%' });
        utils.set(strokes, { draw: '0 0' });

        const timeline = createTimeline({ defaults: { ease: 'outExpo' } });

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
