

import Alpine from 'alpinejs';
import { startDesktopNotifications } from './desktop-notifications';

window.Alpine = Alpine;

Alpine.start();

startDesktopNotifications();
