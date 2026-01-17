<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reverb Echo Demo</title>
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
        min-width: 0;
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
        width: 100%;
        min-width: 0;
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

    .mono {
        font-family: "JetBrains Mono", "SFMono-Regular", Menlo, Consolas, monospace;
        font-size: 12px;
        color: #7c6f61;
        background: #f5f1e8;
        border-radius: 10px;
        padding: 8px 10px;
        word-break: break-all;
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
<body>
<div class="page">
    <div class="hero">
        <div>
            <span class="eyebrow">Echo / Reverb</span>
            <h1>Reverb Echo Demo</h1>
            <p class="lead">使用 Laravel Echo 订阅频道与事件（登录态）。</p>
        </div>
        <div class="status" id="status" data-state="idle">初始化中</div>
    </div>

    <div class="grid">
        <section class="card">
            <h2>连接信息</h2>
            <div class="row">
                <div class="field">
                    <label>Host</label>
                    <input id="runtimeHost" type="text" value="-" disabled>
                </div>
                <div class="field">
                    <label>Port</label>
                    <input id="runtimePort" type="text" value="-" disabled>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label>Scheme</label>
                    <input id="runtimeScheme" type="text" value="-" disabled>
                </div>
                <div class="field">
                    <label>App Key</label>
                    <input id="runtimeKey" type="text" value="-" disabled>
                </div>
            </div>
            <div class="field">
                <label>Auth Endpoint</label>
                <div id="runtimeAuth" class="mono">-</div>
            </div>
            <div class="hint">基于 Echo 运行时配置读取。</div>
            <div class="actions">
                <button class="secondary" id="disconnectBtn" disabled>断开</button>
                <button class="primary" id="reconnectBtn" disabled>重连</button>
            </div>
        </section>

        <section class="card">
            <h2>订阅配置</h2>
            <div class="field">
                <label for="channelType">频道类型</label>
                <select id="channelType">
                    <option value="private" selected>private</option>
                    <option value="public">public</option>
                    <option value="presence">presence</option>
                </select>
            </div>
            <div class="field">
                <label for="channel">频道名（不含 private-/presence-）</label>
                <input id="channel" type="text" placeholder="gps.device.all 或 gps.device.终端ID">
                <div class="hint">输入带前缀也可，页面会自动去除。</div>
            </div>
            <div class="field">
                <label>事件名</label>
                <div class="mono">.GpsPositionUpdated</div>
                <div class="hint">固定监听该事件名（broadcastAs）。</div>
            </div>
            <div class="actions">
                <button class="primary" id="subscribeBtn" disabled>订阅</button>
                <button class="secondary" id="unsubscribeBtn" disabled>取消订阅</button>
                <button class="ghost" id="leaveAllBtn" disabled>清空订阅</button>
                <button class="ghost" id="clearLogBtn">清空日志</button>
            </div>
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

@vite(['resources/js/app.js'])

<script>
const state = {
    echoReady: false,
    pusher: null,
    subscriptions: new Map(),
};

const el = (id) => document.getElementById(id);
const statusEl = el('status');
const logEl = el('log');
const disconnectBtn = el('disconnectBtn');
const reconnectBtn = el('reconnectBtn');
const subscribeBtn = el('subscribeBtn');
const unsubscribeBtn = el('unsubscribeBtn');
const leaveAllBtn = el('leaveAllBtn');
const clearLogBtn = el('clearLogBtn');
const runtimeHostEl = el('runtimeHost');
const runtimePortEl = el('runtimePort');
const runtimeSchemeEl = el('runtimeScheme');
const runtimeKeyEl = el('runtimeKey');
const runtimeAuthEl = el('runtimeAuth');
const channelTypeInput = el('channelType');
const channelInput = el('channel');

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

const normalizeChannelName = (type, raw) => {
    let name = raw.trim();
    if (!name) {
        return '';
    }
    if (type === 'private' && name.startsWith('private-')) {
        name = name.slice('private-'.length);
    }
    if (type === 'presence' && name.startsWith('presence-')) {
        name = name.slice('presence-'.length);
    }
    return name;
};


const updateButtons = () => {
    disconnectBtn.disabled = !state.echoReady;
    reconnectBtn.disabled = !state.echoReady;
    subscribeBtn.disabled = !state.echoReady;
    unsubscribeBtn.disabled = !state.echoReady;
    leaveAllBtn.disabled = !state.echoReady || state.subscriptions.size === 0;
};

const waitForEcho = (maxAttempts = 30, interval = 200) => new Promise((resolve, reject) => {
    let attempts = 0;
    const timer = setInterval(() => {
        attempts += 1;
        if (window.Echo) {
            clearInterval(timer);
            resolve(window.Echo);
            return;
        }
        if (attempts >= maxAttempts) {
            clearInterval(timer);
            reject(new Error('Echo 初始化超时'));
        }
    }, interval);
});

const bindConnection = () => {
    const pusher = window.Echo && window.Echo.connector ? window.Echo.connector.pusher : null;
    const options = window.Echo && window.Echo.connector ? window.Echo.connector.options : null;
    if (!pusher) {
        addLog('错误', 'Echo 连接器未初始化');
        setStatus('连接不可用', 'warn');
        return;
    }
    state.pusher = pusher;
    if (options) {
        console.info(options);
        runtimeHostEl.value = options.wsHost || options.host || '-';
        runtimePortEl.value = options.wssPort || options.wsPort || '-';
        runtimeSchemeEl.value = options.forceTLS ? 'wss' : 'ws';
        runtimeKeyEl.value = options.key || '-';
        runtimeAuthEl.textContent = options.authEndpoint || '/broadcasting/auth';
    }

    const setByState = (stateName) => {
        const map = {
            connected: ['已连接', 'ok'],
            connecting: ['连接中', 'warn'],
            disconnected: ['已断开', 'warn'],
            unavailable: ['不可用', 'warn'],
            failed: ['失败', 'warn'],
            initialized: ['未连接', 'idle'],
        };
        const [label, stateClass] = map[stateName] || ['未知', 'warn'];
        setStatus(label, stateClass);
    };

    setByState(pusher.connection.state);

    pusher.connection.bind('state_change', (states) => {
        setByState(states.current);
    });

    pusher.connection.bind('error', (error) => {
        addLog('错误', '连接异常', error);
    });
};

const subscribe = () => {
    const type = channelTypeInput.value;
    const rawName = channelInput.value;
    const channelName = normalizeChannelName(type, rawName);
    if (!channelName) {
        addLog('错误', '频道不能为空');
        return;
    }
    const eventName = '.GpsPositionUpdated';
    const key = type + ':' + channelName;
    if (state.subscriptions.has(key)) {
        addLog('提示', '频道已订阅', { channel: channelName, type, event: eventName });
        return;
    }

    let channel;
    if (type === 'private') {
        channel = window.Echo.private(channelName);
    } else if (type === 'presence') {
        channel = window.Echo.join(channelName);
    } else {
        channel = window.Echo.channel(channelName);
    }

    channel.listen(eventName, (payload) => {
        addLog('接收', eventName, payload);
    });

    state.subscriptions.set(key, { channel, eventName, channelName, type });
    addLog('系统', '订阅成功', { channel: channelName, type, event: eventName });
    updateButtons();
};

const unsubscribe = () => {
    const type = channelTypeInput.value;
    const channelName = normalizeChannelName(type, channelInput.value);
    const key = type + ':' + channelName;
    const info = state.subscriptions.get(key);
    if (!info) {
        addLog('提示', '未找到订阅', { channel: channelName, type });
        return;
    }

    info.channel.stopListening(info.eventName);
    window.Echo.leave(channelName);
    state.subscriptions.delete(key);
    addLog('系统', '已取消订阅', { channel: channelName, type });
    updateButtons();
};

const leaveAll = () => {
    for (const info of state.subscriptions.values()) {
        window.Echo.leave(info.channelName);
    }
    state.subscriptions.clear();
    addLog('系统', '已清空订阅');
    updateButtons();
};

const connectEcho = () => {
    if (!state.pusher) {
        return;
    }
    state.pusher.connect();
};

const disconnectEcho = () => {
    if (!state.pusher) {
        return;
    }
    state.pusher.disconnect();
};

subscribeBtn.addEventListener('click', subscribe);
unsubscribeBtn.addEventListener('click', unsubscribe);
leaveAllBtn.addEventListener('click', leaveAll);
clearLogBtn.addEventListener('click', () => {
    logEl.textContent = '';
});
disconnectBtn.addEventListener('click', disconnectEcho);
reconnectBtn.addEventListener('click', connectEcho);

waitForEcho()
    .then(() => {
        state.echoReady = true;
        bindConnection();
        updateButtons();
        addLog('系统', 'Echo 已就绪');
    })
    .catch((error) => {
        addLog('错误', error.message || 'Echo 初始化失败');
        setStatus('初始化失败', 'warn');
        updateButtons();
    });
</script>
</body>
</html>
