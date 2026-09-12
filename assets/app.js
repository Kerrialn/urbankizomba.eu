import '@hotwired/turbo';
import './app.css';
import './bootstrap.js';
import { initFlowbite } from 'flowbite';

import '../vendor/kerrialnewham/autocomplete/assets/styles/autocomplete.css';

document.addEventListener('DOMContentLoaded', initFlowbite);
document.addEventListener('turbo:load', initFlowbite);
