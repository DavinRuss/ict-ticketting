// Shows new ticket-comment notifications as OS (taskbar) notifications while the app is open in a tab.
// Browsers only allow this on https:// or http://localhost, and after the user clicks "Allow".

const POLL_INTERVAL_MS = 15000;
const SHOWN_KEY = 'helpdesk.shownNotificationIds';
const DISMISSED_KEY = 'helpdesk.desktopNotificationsDismissed';
const MAX_REMEMBERED = 50;

function readShown() {
    try {
        const ids = JSON.parse(localStorage.getItem(SHOWN_KEY));
        return Array.isArray(ids) ? ids : null;
    } catch {
        return null;
    }
}

function writeShown(ids) {
    localStorage.setItem(SHOWN_KEY, JSON.stringify(ids.slice(-MAX_REMEMBERED)));
}

function updateBadges(count) {
    document.querySelectorAll('[data-notification-count]').forEach((badge) => {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    });
}

function showDesktopNotification(item) {
    const notification = new Notification(item.title, { body: item.body, tag: item.id });

    notification.onclick = () => {
        window.focus();
        window.location.href = item.url;
        notification.close();
    };
}

function setUpPermissionPrompt(root, supported) {
    if (!supported || Notification.permission !== 'default' || localStorage.getItem(DISMISSED_KEY)) {
        return;
    }

    root.classList.remove('hidden');

    root.querySelector('[data-enable-notifications]')?.addEventListener('click', async () => {
        await Notification.requestPermission();
        root.classList.add('hidden');
    });

    root.querySelector('[data-dismiss-notifications]')?.addEventListener('click', () => {
        localStorage.setItem(DISMISSED_KEY, '1');
        root.classList.add('hidden');
    });
}

export function startDesktopNotifications() {
    const root = document.getElementById('desktop-notifications');

    if (!root) {
        return;
    }

    const supported = 'Notification' in window && window.isSecureContext;
    let timer = null;

    setUpPermissionPrompt(root, supported);

    async function poll() {
        let data;

        try {
            const response = await fetch(root.dataset.feedUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            // Signed out or deactivated: stop polling until the next page load.
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                clearInterval(timer);
                return;
            }

            data = await response.json();
        } catch {
            return;
        }

        updateBadges(data.unread_count);

        // Re-read on every poll so several open tabs don't show the same notification twice.
        const shown = readShown();
        const firstRun = shown === null;
        const ids = shown ?? [];

        // On the very first run only remember what is already there; the bell shows it.
        [...data.notifications].reverse().forEach((item) => {
            if (ids.includes(item.id)) {
                return;
            }

            ids.push(item.id);

            if (!firstRun && supported && Notification.permission === 'granted') {
                showDesktopNotification(item);
            }
        });

        writeShown(ids);
    }

    poll();
    timer = setInterval(poll, POLL_INTERVAL_MS);
}
