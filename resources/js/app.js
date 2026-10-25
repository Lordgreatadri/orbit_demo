import Alpine from 'alpinejs';
import { theme } from './theme';

window.Alpine = Alpine;

Alpine.data('themeToggle', theme);

Alpine.start();
