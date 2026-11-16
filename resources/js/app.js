

import './echo';

import Alpine from 'alpinejs';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const userId = document.body?.dataset.userId;

Alpine.data('notificationCenter', (initialUnread = 0) => ({
	open: false,
	loading: false,
	unread: initialUnread,
	items: [],

	init() {
		this.refresh();
	},

	async toggle() {
		this.open = !this.open;
		if (this.open) {
			await this.refresh();
		}
	},

	async refresh() {
		this.loading = true;
		try {
			const response = await fetch('/notifications', {
				headers: { Accept: 'application/json' },
			});
			if (response.ok) {
				const data = await response.json();
				this.unread = data.unread_count ?? 0;
				this.items = data.notifications ?? [];
			}
		} finally {
			this.loading = false;
		}
	},

	async openNotification(notification) {
		this.open = false;
		await this.markRead(notification);
		if (notification.url) {
			window.location.href = notification.url;
		}
	},

	async markRead(notification) {
		notification.read_at = notification.read_at ?? new Date().toISOString();
		this.unread = Math.max(0, this.unread - 1);
		try {
			await fetch(`/notifications/${encodeURIComponent(notification.id)}/read`, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': csrfToken,
				},
			});
		} catch {
			// The notification stays unread server-side; the next refresh resyncs.
		}
	},

	async markAllRead() {
		this.items.forEach((n) => { n.read_at = n.read_at ?? new Date().toISOString(); });
		this.unread = 0;
		try {
			await fetch('/notifications/read-all', {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': csrfToken,
				},
			});
		} catch {
			// Same as markRead: resynced on next refresh.
		}
	},

	onPush(payload) {
		this.unread += 1;
		if (this.open) {
			this.refresh();
		}
	},

	formatTime(iso) {
		if (!iso) return '';
		const date = new Date(iso);
		return date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
	},
}));

// WebAudio bell — no audio asset needed. The context is created lazily and
// unlocked on the first user gesture because browsers block autoplay audio.
let audioContext = null;

function unlockAudio() {
	if (!audioContext) {
		const Ctor = window.AudioContext ?? window.webkitAudioContext;
		if (Ctor) {
			audioContext = new Ctor();
		}
	}
	if (audioContext?.state === 'suspended') {
		audioContext.resume();
	}
}

['pointerdown', 'keydown'].forEach((event) => {
	window.addEventListener(event, unlockAudio);
});

function playNotificationBell() {
	unlockAudio();
	if (!audioContext || audioContext.state !== 'running') {
		return;
	}

	const now = audioContext.currentTime;
	// Inharmonic partials approximate a struck bell; layered for a loud chime.
	const partials = [
		[1046.5, 0.5, 1.6],
		[1568.0, 0.32, 1.2],
		[2093.0, 0.2, 0.9],
		[2637.0, 0.12, 0.6],
	];

	partials.forEach(([frequency, volume, decay]) => {
		const oscillator = audioContext.createOscillator();
		const gain = audioContext.createGain();

		oscillator.type = 'sine';
		oscillator.frequency.value = frequency;
		gain.gain.setValueAtTime(volume, now);
		gain.gain.exponentialRampToValueAtTime(0.001, now + decay);

		oscillator.connect(gain).connect(audioContext.destination);
		oscillator.start(now);
		oscillator.stop(now + decay);
	});
}

let toastStack = null;

function showNotificationToast(payload) {
	if (!toastStack) {
		toastStack = document.createElement('div');
		toastStack.className = 'fixed right-4 top-4 z-[70] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2 sm:w-96';
		document.body.append(toastStack);
	}

	const toast = document.createElement('button');
	toast.type = 'button';
	toast.className = 'flex w-full items-start gap-3 rounded-lg border border-indigo-100 bg-white p-4 text-left shadow-lg transition hover:shadow-xl';

	const dot = document.createElement('span');
	dot.className = 'mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500';

	const text = document.createElement('span');
	text.className = 'min-w-0 flex-1';

	const title = document.createElement('span');
	title.className = 'block text-sm font-semibold text-gray-900';
	title.textContent = payload?.title ?? '';

	const message = document.createElement('span');
	message.className = 'mt-0.5 block text-sm text-gray-600 line-clamp-2';
	message.textContent = payload?.message ?? '';

	text.append(title, message);
	toast.append(dot, text);

	toast.addEventListener('click', () => {
		toast.remove();
		if (payload?.id) {
			fetch(`/notifications/${encodeURIComponent(payload.id)}/read`, {
				method: 'POST',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
			}).catch(() => {});
		}
		if (payload?.url) {
			window.location.href = payload.url;
		}
	});

	toastStack.append(toast);
	setTimeout(() => toast.remove(), 8000);
}

if (window.Echo && userId) {
	window.Echo.private(`App.Models.User.${userId}`).listen('.notification.received', (payload) => {
		window.dispatchEvent(new CustomEvent('app:notification', { detail: payload }));
		showNotificationToast(payload);
		playNotificationBell();
	});
}

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
