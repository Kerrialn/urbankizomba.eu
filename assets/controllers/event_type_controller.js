import { Controller } from '@hotwired/stimulus';

/**
 * Shows the date fields for a dated event and the schedule field for a
 * social, according to which type pill is selected.
 *
 * Presentation only. Both groups are in the markup and both are submitted;
 * the server decides which one is required for the chosen type, and a browser
 * without JavaScript sees both groups and is told which to fill in.
 */
export default class extends Controller {
    static targets = ['type', 'dated', 'recurring'];

    connect() {
        this.update();
    }

    update() {
        const selected = this.typeTargets.find((input) => input.checked);
        const recurring = selected?.dataset.eventTypeRecurring === '1';

        this.datedTargets.forEach((el) => { el.hidden = recurring; });
        this.recurringTargets.forEach((el) => { el.hidden = !recurring; });
    }
}
