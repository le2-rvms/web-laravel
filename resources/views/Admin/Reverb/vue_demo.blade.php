<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reverb WebSocket Token Demo（Vue）</title>
    <style>
        :root {
            --bg: #f6f1e8;
            --card: #ffffff;
            --ink: #1f2937;
            --muted: #6b7280;
            --accent: #0f766e;
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
            background: radial-gradient(1200px 620px at 95% -10%, rgba(245, 158, 11, 0.18), transparent 60%),
                        radial-gradient(880px 520px at -10% 8%, rgba(15, 118, 110, 0.16), transparent 55%),
                        var(--bg);
        }

        [v-cloak] {
            display: none;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.12);
            color: var(--accent);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 12px 0 10px;
            font-size: 28px;
            letter-spacing: 0.02em;
        }

        .lead {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .meta {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .pill {
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(107, 114, 128, 0.15);
            color: var(--muted);
            font-size: 12px;
            min-width: 84px;
            text-align: center;
        }

        .pill[data-state="ok"] {
            background: rgba(15, 118, 110, 0.16);
            color: var(--accent);
        }

        .pill[data-state="warn"] {
            background: rgba(245, 158, 11, 0.18);
            color: #b45309;
        }

        .pill[data-state="error"] {
            background: rgba(220, 38, 38, 0.12);
            color: #b91c1c;
        }

        .socket {
            font-size: 12px;
            color: var(--muted);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 18px;
            margin-top: 20px;
            align-items: start;
        }

        .card {
            background: var(--card);
            border-radius: 18px;
            padding: 18px 18px 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(231, 222, 208, 0.8);
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

        .field :is(input, select) {
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid var(--line);
            font-size: 14px;
            outline: none;
            background: #fffdf9;
            width: 100%;
            min-width: 0;
        }

        .field :is(input, select):focus {
            border-color: rgba(15, 118, 110, 0.4);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
        }

        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .row--compact {
            grid-template-columns: minmax(0, 1fr) 140px;
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
            margin-top: 12px;
        }

        button {
            border: none;
            border-radius: 999px;
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 10px 20px rgba(15, 118, 110, 0.3);
        }

        .secondary {
            background: #f0f4f3;
            color: var(--accent);
            border: 1px solid rgba(15, 118, 110, 0.2);
        }

        .ghost {
            background: transparent;
            color: var(--muted);
            border: 1px dashed rgba(107, 114, 128, 0.3);
        }

        .log-card {
            display: flex;
            flex-direction: column;
        }

        .log {
            margin: 0;
            padding: 12px;
            background: #111827;
            color: #e5e7eb;
            font-size: 12px;
            border-radius: 12px;
            height: 320px;
            flex: 1;
            min-height: 0;
            overflow: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
<div id="reverb-vue-app" class="page" v-cloak>
    <header class="header">
        <div>
            <span class="eyebrow">Reverb · Token</span>
            <h1>Reverb WebSocket 订阅调试台</h1>
            <p class="lead">无需登录，使用 Bearer Token 为私有频道鉴权。</p>
            <div class="meta">
                <span class="pill" :data-state="statusState" v-text="statusText"></span>
                <span class="socket" v-text="socketLabel"></span>
            </div>
        </div>
        <div class="actions">
            <button class="primary" @click="connect" :disabled="connected">连接</button>
            <button class="secondary" @click="disconnect" :disabled="!connected">断开</button>
            <button class="ghost" @click="clearLogs">清空日志</button>
        </div>
    </header>

    <section class="grid">
        <div class="card">
            <h2>连接配置</h2>
            <div class="field">
                <label>Reverb Host</label>
                <input v-model.trim="form.host" type="text" placeholder="reverb-xx 或 127.0.0.1">
            </div>
            <div class="row">
                <div class="field">
                    <label>端口</label>
                    <input v-model.trim="form.port" type="text" placeholder="6001">
                </div>
                <div class="field">
                    <label>协议</label>
                    <select v-model="form.scheme">
                        <option value="ws">ws</option>
                        <option value="wss">wss</option>
                    </select>
                </div>
            </div>
            <div class="row row--compact">
                <div class="field">
                    <label>App Key</label>
                    <input v-model.trim="form.appKey" type="text" placeholder="REVERB_APP_KEY">
                </div>
                <div class="field">
                    <label>Path</label>
                    <input v-model.trim="form.path" type="text" placeholder="/reverb">
                </div>
            </div>
            <div class="field">
                <label>WebSocket URL 预览</label>
                <div class="ws-url" v-text="wsUrl || '请先完善 Host 与 App Key'"></div>
            </div>
            <div class="hint">连接成功后会返回 socket_id，订阅私有频道需携带该值。</div>
        </div>

        <div class="card">
            <h2>订阅与鉴权</h2>
            <div class="field">
                <label>频道</label>
                <input v-model.trim="form.channel" type="text" placeholder="private-gps.device.all 或 public-demo">
                <div class="hint">私有频道需带 private- 前缀，例如 private-gps.device.all。</div>
            </div>
            <div class="field">
                <label>Bearer Token</label>
                <input v-model.trim="form.token" type="password" placeholder="粘贴管理员 token">
                <div class="hint">可用 /api-admin/no-auth/login 获取 token。</div>
            </div>
            <div class="field">
                <label>鉴权接口</label>
                <input v-model.trim="form.authEndpoint" type="text" placeholder="/api-admin/broadcasting/auth">
            </div>
            <div class="actions">
                <button class="primary" @click="subscribeChannel" :disabled="!canSubscribe">订阅</button>
                <button class="secondary" @click="unsubscribeChannel" :disabled="!canSubscribe">取消订阅</button>
            </div>
            <div class="hint">私有频道未鉴权会被拒绝，日志会提示错误原因。</div>
        </div>

        <div class="card log-card">
            <h2>消息日志</h2>
            <pre class="log" v-text="logText"></pre>
        </div>
    </section>
</div>

<script>
    window.__REVERB_VUE_DEMO__ = @json($defaults, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
</script>
@vite(['resources/js/reverb_vue_demo.js'])
</body>
</html>
