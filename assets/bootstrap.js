import { Application } from '@hotwired/stimulus';

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
