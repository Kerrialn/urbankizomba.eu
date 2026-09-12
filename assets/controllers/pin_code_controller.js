import { Controller } from '@hotwired/stimulus';

/**
 * Row of single-character boxes backing one hidden field.
 *
 * The boxes are presentation only — the joined value is written to the hidden
 * input, which is what the form actually submits.
 */
export default class extends Controller {
    static targets = ['input', 'hidden'];
    static values = { length: Number };

    connect() {
        this.inputTargets[0]?.focus();
    }

    onInput(event) {
        const input = event.target;
        input.value = input.value.replace(/\D/g, '').slice(-1);

        if (input.value !== '') {
            this.#next(input)?.focus();
        }

        this.#sync();
    }

    onKeydown(event) {
        if (event.key !== 'Backspace' || event.target.value !== '') return;

        const previous = this.#previous(event.target);
        if (previous) {
            previous.value = '';
            previous.focus();
            event.preventDefault();
            this.#sync();
        }
    }

    /** Let the whole code be pasted, or filled by an SMS/email autofill hint. */
    onPaste(event) {
        event.preventDefault();

        const digits = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');

        this.inputTargets.forEach((input, index) => {
            input.value = digits[index] ?? '';
        });

        const nextEmpty = this.inputTargets.findIndex((input) => input.value === '');
        this.inputTargets[nextEmpty === -1 ? this.inputTargets.length - 1 : nextEmpty].focus();

        this.#sync();
    }

    onFocus(event) {
        event.target.select();
    }

    #sync() {
        this.hiddenTarget.value = this.inputTargets.map((input) => input.value).join('');
    }

    #next(input) {
        return this.inputTargets[this.inputTargets.indexOf(input) + 1];
    }

    #previous(input) {
        return this.inputTargets[this.inputTargets.indexOf(input) - 1];
    }
}
