<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reverb WS 连接 Demo</title>
<style>
    :root {
        --bg: #f6f1e8;
        --card: #ffffff;
        --ink: #1f2937;
        --muted: #6b7280;
        --accent: #0f766e;
        --accent-2: #f59e0b;
        --line: #e7ded0;
        --shadow: 0 18px 40px rgba(31, 41, 55, 0.12);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: "Noto Sans SC", "Source Han Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
        color: var(--ink);
        background: radial-gradient(1200px 600px at 90% -10%, rgba(245, 158, 11, 0.16), transparent 60%),
                    radial-gradient(900px 500px at -20% 10%, rgba(15, 118, 110, 0.18), transparent 55%),
                    var(--bg);
    }

    .page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 32px 20px 60px;
    }

    .hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
    }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(15, 118, 110, 0.12);
        color: var(--accent);
        font-size: 12px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    h1 {
        margin: 10px 0 8px;
        font-size: 30px;
        letter-spacing: 0.02em;
    }

    .lead {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.6;
    }

    .mode-session .token-only {
        display: none;
    }

    .mode-token .session-only {
        display: none;
    }

    .status {
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(107, 114, 128, 0.15);
        color: var(--muted);
        font-size: 13px;
        min-width: 120px;
        text-align: center;
    }

    .status[data-state="ok"] {
        background: rgba(15, 118, 110, 0.16);
        color: var(--accent);
    }

    .status[data-state="warn"] {
        background: rgba(245, 158, 11, 0.18);
        color: #b45309;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px;
    }

    .card {
        background: var(--card);
        border-radius: 18px;
        padding: 18px 18px 20px;
        box-shadow: var(--shadow);
        border: 1px solid rgba(231, 222, 208, 0.8);
        animation: rise 0.6s ease both;
    }

    .card:nth-child(2) {
        animation-delay: 0.08s;
    }

    .card:nth-child(3) {
        animation-delay: 0.16s;
    }

    @@keyframes rise {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @@media (prefers-reduced-motion: reduce) {
        .card {
            animation: none;
        }
    }

    .card h2 {
        margin: 0 0 12px;
        font-size: 18px;
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 12px;
    }

    .field label {
        font-size: 12px;
        color: var(--muted);
    }

    .field input,
    .field select {
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid var(--line);
        font-size: 14px;
        outline: none;
        background: #fffdf9;
    }

    .field input:focus,
    .field select:focus {
        border-color: rgba(15, 118, 110, 0.4);
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
    }

    .row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .hint {
        font-size: 12px;
        color: var(--muted);
        margin: 4px 0 0;
        line-height: 1.5;
    }

    .ws-url {
        background: #f5f1e8;
        border-radius: 10px;
        padding: 8px 10px;
        font-size: 12px;
        color: #7c6f61;
        word-break: break-all;
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 8px;
    }

    button {
        border: none;
        border-radius: 999px;
        padding: 8px 16px;
        font-size: 13px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    button.primary {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 8px 18px rgba(15, 118, 110, 0.25);
    }

    button.secondary {
        background: rgba(15, 118, 110, 0.12);
        color: var(--accent);
    }

    button.ghost {
        background: rgba(245, 158, 11, 0.12);
        color: #b45309;
    }

    button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
    }

    button:not(:disabled):hover {
        transform: translateY(-1px);
    }

    .toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--muted);
    }

    .log-card {
        grid-column: 1 / -1;
    }

    .log-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .log {
        background: #111827;
        color: #e5e7eb;
        border-radius: 12px;
        padding: 14px;
        min-height: 180px;
        max-height: 380px;
        overflow: auto;
        font-size: 12px;
        line-height: 1.6;
        white-space: pre-wrap;
        font-family: "JetBrains Mono", "SFMono-Regular", Menlo, Consolas, monospace;
    }

    .socket-id {
        font-size: 12px;
        color: var(--muted);
        margin-top: 6px;
    }

    @@media (max-width: 720px) {
        .hero {
            flex-direction: column;
        }

        .row {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body class="mode-{{ $mode ?? 'session' }}">
<div class="page">
    <div class="hero">
        <div>
            <span class="eyebrow">Reverb / Pusher 协议</span>
            <h1>Reverb WS 连接 Demo</h1>
            <p class="lead">
                @if(($mode ?? 'session') === 'token')
                    未登录场景，使用 Bearer Token 完成鉴权与订阅。
                @else
                    登录态场景，使用当前会话完成鉴权与订阅。
                @endif
            </p>
        </div>
        <div class="status" id="status" data-state="idle">未连接</div>
    </div>

    <div class="grid">
        <section class="card">
            <h2>连接配置</h2>
            <div class="row">
                <div class="field">
                    <label for="host">Host</label>
                    <input id="host" type="text" placeholder="reverb-xx 或 127.0.0.1">
                </div>
                <div class="field">
                    <label for="port">Port</label>
                    <input id="port" type="number" min="1" max="65535">
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="scheme">协议</label>
                    <select id="scheme">
                        <option value="ws">ws</option>
                        <option value="wss">wss</option>
                    </select>
                </div>
                <div class="field">
                    <label for="path">路径（可选）</label>
                    <input id="path" type="text" placeholder="/reverb">
                </div>
            </div>
            <div class="field">
                <label for="appKey">App Key</label>
                <input id="appKey" type="text" placeholder="REVERB_APP_KEY">
            </div>
            <div class="field">
                <label>WebSocket URL 预览</label>
                <div class="ws-url" id="wsUrl"></div>
            </div>
            <div class="actions">
                <button class="primary" id="connectBtn">连接</button>
                <button class="secondary" id="disconnectBtn" disabled>断开</button>
            </div>
            <div class="socket-id" id="socketId">socket_id：-</div>
        </section>

        <section class="card">
            <h2>订阅配置</h2>
            <div class="field token-only">
                <label for="token">Bearer Token</label>
                <input id="token" type="password" placeholder="输入管理员 token">
                <div class="hint">可用 /api-admin/no-auth/login 返回的 token。</div>
            </div>
            <div class="field">
                <label for="channel">频道</label>
                <input id="channel" type="text" placeholder="private-gps.device.终端ID 或 public-demo">
                <div class="hint">示例：private-gps.device.123456（私有频道需要鉴权）</div>
            </div>
            <div class="field">
                <label for="eventFilter">事件过滤（可选）</label>
                <input id="eventFilter" type="text" placeholder="GpsPositionUpdated">
            </div>
            <div class="field">
                <label for="authEndpoint">鉴权接口</label>
                <input id="authEndpoint" type="text" placeholder="/broadcasting/auth">
            </div>
            <label class="toggle">
                <input id="authEnabled" type="checkbox">
                使用 /broadcasting/auth 做频道鉴权
            </label>
            <div class="actions">
                <button class="primary" id="subscribeBtn" disabled>订阅</button>
                <button class="ghost" id="clearLogBtn">清空日志</button>
            </div>
            <div class="hint session-only">鉴权依赖当前登录态与频道授权规则（routes/channels.php）。</div>
            <div class="hint token-only">鉴权请求会携带 Authorization: Bearer {token}。</div>
        </section>

        <section class="card log-card">
            <div class="log-head">
                <h2>连接日志</h2>
                <div class="hint">仅展示最近会话的消息</div>
            </div>
            <pre id="log" class="log"></pre>
        </section>
    </div>
</div>

<script>
const defaults = @json($defaults, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
const mode = @json($mode ?? 'session');
const csrfToken = @json($defaults['csrfToken'] ?? null);
const isTokenMode = mode === 'token';

const state = {
    ws: null,
    socketId: null,
    ready: false,
};

const el = (id) => document.getElementById(id);
const hostInput = el('host');
const portInput = el('port');
const schemeInput = el('scheme');
const pathInput = el('path');
const appKeyInput = el('appKey');
const tokenInput = el('token');
const channelInput = el('channel');
const eventFilterInput = el('eventFilter');
const authEnabledInput = el('authEnabled');
const authEndpointInput = el('authEndpoint');
const wsUrlEl = el('wsUrl');
const logEl = el('log');
const statusEl = el('status');
const socketIdEl = el('socketId');
const connectBtn = el('connectBtn');
const disconnectBtn = el('disconnectBtn');
const subscribeBtn = el('subscribeBtn');
const clearLogBtn = el('clearLogBtn');

hostInput.value = defaults.host || '';
portInput.value = defaults.port || '';
schemeInput.value = defaults.scheme || 'ws';
pathInput.value = defaults.path || '';
appKeyInput.value = defaults.appKey || '';
channelInput.value = defaults.channel || '';
authEndpointInput.value = defaults.authEndpoint || '/broadcasting/auth';
authEnabledInput.checked = channelInput.value.startsWith('private-') || channelInput.value.startsWith('presence-');

const setStatus = (text, stateName) => {
    statusEl.textContent = text;
    statusEl.dataset.state = stateName || 'idle';
};

const addLog = (type, message, payload) => {
    const ts = new Date().toLocaleTimeString('zh-CN', { hour12: false });
    let line = '[' + ts + '] [' + type + '] ' + message;
    if (payload !== undefined) {
        const content = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
        line += '\n' + content;
    }
    logEl.textContent += line + '\n';
    logEl.scrollTop = logEl.scrollHeight;
};

const safeJsonParse = (value) => {
    if (typeof value !== 'string') {
        return value;
    }
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
};

const buildUrl = () => {
    const host = hostInput.value.trim();
    const port = portInput.value.trim();
    const scheme = schemeInput.value.trim() || 'ws';
    const appKey = appKeyInput.value.trim();
    let path = pathInput.value.trim();
    if (path && !path.startsWith('/')) {
        path = '/' + path;
    }
    const portPart = port ? ':' + port : '';
    return scheme + '://' + host + portPart + path + '/app/' + appKey + '?protocol=7&client=js&version=7.6.0&flash=false';
};

const refreshPreview = () => {
    wsUrlEl.textContent = buildUrl();
};

const updateButtons = () => {
    const connected = state.ws && state.ws.readyState === WebSocket.OPEN;
    connectBtn.disabled = connected;
    disconnectBtn.disabled = !connected;
    subscribeBtn.disabled = !state.ready;
};

const sendWs = (payload) => {
    if (!state.ws || state.ws.readyState !== WebSocket.OPEN) {
        addLog('错误', 'WebSocket 未连接');
        return false;
    }
    state.ws.send(JSON.stringify(payload));
    addLog('发送', payload.event, payload.data);
    return true;
};

const requestAuth = async (channelName) => {
    const socketId = state.socketId;
    if (!socketId) {
        addLog('错误', '尚未拿到 socket_id，无法鉴权');
        return null;
    }
    if (isTokenMode) {
        const token = tokenInput.value.trim();
        if (!token) {
            addLog('错误', '请先填写 Bearer Token');
            return null;
        }
    }
    const endpoint = authEndpointInput.value.trim() || '/broadcasting/auth';
    const body = {
        socket_id: socketId,
        channel_name: channelName,
    };
    const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    };
    if (isTokenMode) {
        headers.Authorization = 'Bearer ' + tokenInput.value.trim();
    } else if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    const response = await fetch(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        const text = await response.text();
        addLog('错误', '鉴权接口返回异常', text);
        return null;
    }
    return await response.json();
};

const subscribeChannel = async () => {
    const channelName = channelInput.value.trim();
    if (!channelName) {
        addLog('错误', '频道不能为空');
        return;
    }
    const mustAuth = authEnabledInput.checked || channelName.startsWith('private-') || channelName.startsWith('presence-');
    let data = { channel: channelName };
    if (mustAuth) {
        const authData = await requestAuth(channelName);
        if (!authData || !authData.auth) {
            addLog('错误', '鉴权失败，无法订阅', authData);
            return;
        }
        data.auth = authData.auth;
        if (authData.channel_data) {
            data.channel_data = authData.channel_data;
        }
    }
    sendWs({ event: 'pusher:subscribe', data });
};

const connect = () => {
    const url = buildUrl();
    if (!url.includes('/app/')) {
        addLog('错误', 'URL 无效，请检查 App Key');
        return;
    }
    state.ready = false;
    state.socketId = null;
    socketIdEl.textContent = 'socket_id：-';
    try {
        state.ws = new WebSocket(url);
    } catch (error) {
        addLog('错误', '连接失败', String(error));
        return;
    }
    setStatus('连接中', 'warn');
    addLog('系统', '开始连接', url);

    state.ws.onopen = () => {
        updateButtons();
    };
    state.ws.onerror = () => {
        setStatus('连接错误', 'warn');
        updateButtons();
        addLog('错误', 'WebSocket 发生错误');
    };
    state.ws.onclose = () => {
        setStatus('已断开', 'warn');
        state.ready = false;
        state.socketId = null;
        socketIdEl.textContent = 'socket_id：-';
        updateButtons();
        addLog('系统', '连接已关闭');
    };
    state.ws.onmessage = (event) => {
        const message = safeJsonParse(event.data);
        if (!message || typeof message !== 'object') {
            addLog('接收', '原始消息', event.data);
            return;
        }
        const data = safeJsonParse(message.data);
        const filter = eventFilterInput.value.trim();
        if (!filter || message.event === filter || (data && data.event === filter)) {
            addLog('接收', message.event || 'unknown', data);
        }
        if (message.event === 'pusher:connection_established') {
            const payload = typeof data === 'string' ? safeJsonParse(data) : data;
            state.socketId = payload && payload.socket_id ? payload.socket_id : null;
            socketIdEl.textContent = 'socket_id：' + (state.socketId || '-');
            state.ready = true;
            setStatus('已连接', 'ok');
            updateButtons();
        }
        if (message.event === 'pusher:ping') {
            sendWs({ event: 'pusher:pong', data: {} });
        }
    };
};

const disconnect = () => {
    if (state.ws) {
        state.ws.close(1000, 'client close');
    }
};

connectBtn.addEventListener('click', connect);
disconnectBtn.addEventListener('click', disconnect);
subscribeBtn.addEventListener('click', subscribeChannel);
clearLogBtn.addEventListener('click', () => {
    logEl.textContent = '';
});

[hostInput, portInput, schemeInput, pathInput, appKeyInput].forEach((input) => {
    input.addEventListener('input', refreshPreview);
});

channelInput.addEventListener('input', () => {
    if (channelInput.value.startsWith('private-') || channelInput.value.startsWith('presence-')) {
        authEnabledInput.checked = true;
    }
});

refreshPreview();
updateButtons();
</script>
</body>
</html>
