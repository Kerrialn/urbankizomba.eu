import { Application } from '@hotwired/stimulus';

// Symfony UX controllers
import DropzoneController from '@symfony/ux-dropzone';
import LiveController from '@symfony/ux-live-component';

// 3rd party controllers — eager
import CharacterCounter from '@stimulus-components/character-counter';
import PasswordVisibility from '@stimulus-components/password-visibility';
import TextareaAutogrow from 'stimulus-textarea-autogrow';
import ReadMore from '@stimulus-components/read-more';
import AnimatedNumber from '@stimulus-components/animated-number';
import Clipboard from '@stimulus-components/clipboard';
import SsrAutocompleteController from 'kerrialnewham/autocomplete/controllers/ssr_autocomplete_controller.js';

export const app = Application.start();

// Auto-register every controller in assets/controllers/
const controllers = import.meta.glob('./controllers/*_controller.js', { eager: true });
for (const [path, module] of Object.entries(controllers)) {
    const name = path
        .replace('./controllers/', '')
        .replace('_controller.js', '')
        .replace(/_/g, '-');
    app.register(name, module.default);
}

// Symfony UX
app.register('symfony--ux-dropzone--dropzone', DropzoneController);
app.register('live', LiveController);

// 3rd party
app.register('character-counter', CharacterCounter);
app.register('textarea-autogrow', TextareaAutogrow);
app.register('password-visibility', PasswordVisibility);
app.register('read-more', ReadMore);
app.register('animated-number', AnimatedNumber);
app.register('clipboard', Clipboard);
app.register('kerrialnewham--autocomplete--ssr-autocomplete', SsrAutocompleteController);

// Accessibility: Flowbite marks closed off-canvas drawers `aria-hidden` while
// their links stay focusable (slid off-screen via `translate-x-full`, not
// display:none). Mirror the closed state to `inert` so a hidden drawer is
// removed from the tab order and the accessibility tree.
function syncDrawerInert() {
    document.querySelectorAll('[id$="-offcanvas"]').forEach((el) => {
        if (el.dataset.inertSynced) return;
        el.dataset.inertSynced = '1';
        const sync = () => { el.inert = el.classList.contains('translate-x-full'); };
        sync();
        new MutationObserver(sync).observe(el, { attributes: true, attributeFilter: ['class'] });
    });
}
document.addEventListener('turbo:load', syncDrawerInert);
document.addEventListener('DOMContentLoaded', syncDrawerInert);
