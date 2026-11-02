

import Alpine from 'alpinejs';

const themeKey = 'it-helpdesk-theme';

function setTheme(theme, persist = false) {
	const selectedTheme = theme === 'dark' ? 'dark' : 'light';

	document.documentElement.dataset.theme = selectedTheme;
	document.documentElement.classList.toggle('dark', selectedTheme === 'dark');
	const favicon = document.getElementById('app-favicon');

	if (favicon) {
		favicon.href = selectedTheme === 'dark' ? favicon.dataset.darkHref : favicon.dataset.lightHref;
	}

	if (persist) {
		try {
			localStorage.setItem(themeKey, selectedTheme);
		} catch {
			// The theme still applies for this page when storage is unavailable.
		}
	}

	document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
		const isDark = selectedTheme === 'dark';
		const label = isDark ? 'Switch to light theme' : 'Switch to dark theme';
		button.setAttribute('aria-label', label);
		button.setAttribute('title', label);
		button.setAttribute('aria-pressed', String(isDark));
		button.querySelector('[data-theme-icon="sun"]')?.toggleAttribute('hidden', !isDark);
		button.querySelector('[data-theme-icon="moon"]')?.toggleAttribute('hidden', isDark);
	});
}

try {
	setTheme(localStorage.getItem(themeKey));
} catch {
	setTheme('light');
}

document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-theme-toggle]');

	if (button) {
		setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark', true);
	}
});

window.addEventListener('storage', (event) => {
	if (event.key === themeKey) {
		setTheme(event.newValue);
	}
});

window.Alpine = Alpine;

Alpine.start();
