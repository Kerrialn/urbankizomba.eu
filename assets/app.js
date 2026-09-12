import '@hotwired/turbo';
import './app.css';
import './bootstrap.js';
import { initFlowbite } from 'flowbite';

document.addEventListener('DOMContentLoaded', initFlowbite);
document.addEventListener('turbo:load', initFlowbite);
