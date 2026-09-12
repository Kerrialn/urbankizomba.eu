import { Controller } from '@hotwired/stimulus';
import { createDrawable, createTimeline, utils } from 'animejs';

/**
 * Draws the logo in with anime.js once, when the page loads: the disc
 * springs up, then the U is drawn as one stroke and the K's three strokes
 * follow. About a second and a half, then it holds.
 *
 * Turbo reconnects the controller on every page visit, so it plays on each
 * page. Anyone who has asked their OS for less motion sees the finished mark
 * straight away.
 */
export default class extends Controller {
    static targets = ['disc', 'stroke'];

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.timeline = this.#build();
        this.timeline.play();
    }

    disconnect() {
        this.timeline?.pause();
        this.timeline = null;
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
}
