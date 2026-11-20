import './echo';

const messages = document.getElementById('chat-messages');
const form = document.getElementById('chat-form');
const input = document.getElementById('chat-body');
const error = document.getElementById('chat-error');
const empty = document.getElementById('chat-empty');
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const currentUserId = document.body?.dataset.userId ?? null;
const appendedIds = new Set();

const THUMB_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 012.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 00.322-1.672V3a.75.75 0 01.75-.75 2.25 2.25 0 012.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 01-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 00-1.423-.23H6.633z" /></svg>';

function initials(name) {
    return String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');
}

function formatTime(iso) {
    const date = iso ? new Date(iso) : new Date();
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function setLikeState(button, liked, likes) {
    button.classList.toggle('bg-indigo-100', liked);
    button.classList.toggle('text-indigo-600', liked);
    button.classList.toggle('text-gray-400', !liked);
    const icon = button.querySelector('svg');
    if (icon) {
        icon.classList.toggle('fill-current', liked);
    }
    const count = button.querySelector('.like-count');
    if (count) {
        count.textContent = likes;
    }
}

function updateLikeCount(messageId, likes) {
    const button = messages?.querySelector(`.like-button[data-message-id="${messageId}"]`);
    if (button) {
        const count = button.querySelector('.like-count');
        if (count) {
            count.textContent = likes;
        }
    }
}

async function toggleLike(button) {
    const messageId = button.dataset.messageId;
    if (!messageId) {
        return;
    }

    button.disabled = true;
    try {
        const response = await fetch(`/chat/messages/${messageId}/react`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        });
        const data = await response.json().catch(() => null);
        if (response.ok && data) {
            setLikeState(button, data.liked, data.likes);
        }
    } finally {
        button.disabled = false;
    }
}

function appendMessage(payload) {
    const id = payload?.id;
    if (id == null || appendedIds.has(id)) {
        return;
    }
    appendedIds.add(id);

    if (empty) {
        empty.remove();
    }

    const isOwn = currentUserId != null && String(payload?.author?.id) === String(currentUserId);

    const item = document.createElement('li');
    item.className = `flex items-end gap-2.5 ${isOwn ? 'flex-row-reverse' : ''}`;
    item.dataset.messageId = id;

    const avatar = document.createElement('div');
    avatar.className = `flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white ${isOwn ? 'bg-gradient-to-br from-indigo-500 to-fuchsia-500' : 'bg-gradient-to-br from-slate-500 to-slate-700'}`;
    avatar.textContent = initials(payload?.author?.name);

    const column = document.createElement('div');
    column.className = `flex max-w-[75%] flex-col ${isOwn ? 'items-end' : 'items-start'}`;

    const meta = document.createElement('div');
    meta.className = `mb-1 flex items-baseline gap-2 px-1 ${isOwn ? 'flex-row-reverse' : ''}`;

    const name = document.createElement('span');
    name.className = 'text-xs font-semibold text-gray-700';
    name.textContent = isOwn ? 'You' : (payload?.author?.name ?? '');

    const time = document.createElement('span');
    time.className = 'text-[11px] text-gray-400';
    time.textContent = formatTime(payload?.created_at);

    meta.append(name, time);

    if (!isOwn && payload?.author?.role) {
        const badge = document.createElement('span');
        badge.className = 'rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700';
        badge.textContent = payload.author.role;
        meta.append(badge);
    }

    const bubble = document.createElement('div');
    bubble.className = `rounded-2xl px-4 py-2.5 text-sm shadow-sm ${isOwn ? 'rounded-br-md bg-gradient-to-br from-indigo-500 to-violet-600 text-white' : 'rounded-bl-md border border-gray-200 bg-white text-gray-800'}`;

    const text = document.createElement('p');
    text.className = 'whitespace-pre-line';
    text.textContent = payload?.body ?? '';
    bubble.append(text);

    const likeButton = document.createElement('button');
    likeButton.type = 'button';
    likeButton.className = 'like-button mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs transition text-gray-400 hover:bg-gray-200/70 hover:text-gray-600';
    likeButton.dataset.messageId = id;
    likeButton.setAttribute('aria-label', 'Like message');
    likeButton.innerHTML = `${THUMB_SVG}<span class="like-count font-medium">${payload?.likes ?? 0}</span>`;

    column.append(meta, bubble, likeButton);
    item.append(avatar, column);
    messages.append(item);

    messages.scrollTop = messages.scrollHeight;
}

if (messages) {
    messages.addEventListener('click', (event) => {
        const button = event.target.closest('.like-button');
        if (button) {
            toggleLike(button);
        }
    });
}

// Team presence roster: app.js joins the "online" presence channel on every
// page and broadcasts the connected user ids here.
const staffList = document.getElementById('staff-list');

function renderPresence(onlineIds) {
    if (!staffList) {
        return;
    }

    const online = new Set((onlineIds ?? []).map(String));

    staffList.querySelectorAll('[data-user-id]').forEach((item) => {
        const dot = item.querySelector('[data-status-dot]');
        const isOnline = online.has(String(item.dataset.userId));

        dot.classList.toggle('bg-green-500', isOnline);
        dot.classList.toggle('bg-red-500', !isOnline);
        dot.title = isOnline ? 'Online' : 'Offline';
    });
}

window.addEventListener('app:presence', (event) => renderPresence(event.detail));

if (window.Echo && messages && form && input) {
    window.Echo.private('chat')
        .listen('.message.sent', (payload) => appendMessage(payload))
        .listen('.message.liked', (payload) => updateLikeCount(payload?.message_id, payload?.likes));

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
