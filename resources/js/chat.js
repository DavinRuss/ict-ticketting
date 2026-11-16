import './echo';

const messages = document.getElementById('chat-messages');
const form = document.getElementById('chat-form');
const input = document.getElementById('chat-body');
const error = document.getElementById('chat-error');
const empty = document.getElementById('chat-empty');
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const appendedIds = new Set();

function appendMessage(payload) {
    const id = payload?.id;
    if (id == null || appendedIds.has(id)) {
        return;
    }
    appendedIds.add(id);

    if (empty) {
        empty.remove();
    }

    const item = document.createElement('li');
    item.className = 'flex items-start gap-3';

    const body = document.createElement('div');
    body.className = 'flex-1';

    const meta = document.createElement('p');
    meta.className = 'text-xs text-gray-500';

    const author = document.createElement('span');
    author.className = 'font-medium text-gray-700';
    author.textContent = payload?.author?.name ?? '';

    const time = document.createElement('span');
    time.className = 'text-gray-400';
    const date = payload?.created_at ? new Date(payload.created_at) : new Date();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    time.textContent = ` · ${hours}:${minutes}`;

    meta.append(author, time);

    const text = document.createElement('p');
    text.className = 'mt-1 text-sm text-gray-900 whitespace-pre-line';
    text.textContent = payload?.body ?? '';

    body.append(meta, text);
    item.append(body);
    messages.append(item);

    messages.scrollTop = messages.scrollHeight;
}

if (window.Echo && messages && form && input) {
    window.Echo.private('chat').listen('.message.sent', (payload) => appendMessage(payload));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = input.value.trim();
        if (!body) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        if (error) error.hidden = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Could not send the message.');
            }

            input.value = '';

            // Show our own message right away; the WebSocket echo dedupes by id.
            const data = await response.json().catch(() => null);
            if (data?.message) {
                appendMessage(data.message);
            }
        } catch (err) {
            if (error) {
                error.textContent = err.message;
                error.hidden = false;
            }
        } finally {
            if (button) button.disabled = false;
            input.focus();
        }
    });
}
