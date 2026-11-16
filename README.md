# ict-ticketting

## Hosting on the local network

The app is configured to be reached on the LAN at `http://10.100.10.20:8000`
(`APP_URL` in `.env`).

Requirements on the host machine:

1. Give the machine the static IP `10.100.10.20` (Settings > Network, or a
   DHCP reservation on the router).
2. Allow inbound traffic on port 8000 in Windows Firewall (run as admin):

   ```
   netsh advfirewall firewall add rule name="ICT Ticketing - HTTP 8000" dir=in action=allow protocol=TCP localport=8000
   ```

   If you also run the Vite dev server for hot-reload (`npm run dev`), allow
   port 5173 as well.

3. Build assets and start the server on all interfaces:

   ```
   npm run build
   php artisan serve --host=0.0.0.0 --port=8000
   ```

Other machines on the network then browse to `http://10.100.10.20:8000`.

## Team chat (real-time)

Technicians and admins share a real-time chat room (`/chat`) backed by
Laravel Reverb (WebSockets).

The Reverb server must run alongside the app, and port `8080` must be
reachable from the LAN. Start it with:

```
php artisan reverb:start
```

Allow WebSocket traffic through Windows Firewall (run as admin):

```
netsh advfirewall firewall add rule name="ICT Ticketing - Reverb 8080" dir=in action=allow protocol=TCP localport=8080
```

`REVERB_HOST` in `.env` is the address browsers use to reach the WebSocket
server. It defaults to this machine's LAN IP (`10.100.10.20`); change it to
`localhost` only if the chat is used from this machine alone. Messages are
stored in the `chat_messages` table and are only visible to technicians and
admins.
