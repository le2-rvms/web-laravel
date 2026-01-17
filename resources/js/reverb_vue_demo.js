import { createApp, reactive, ref, computed } from 'vue/dist/vue.esm-bundler.js';

const defaults = window.__REVERB_VUE_DEMO__ || {};

createApp({
    setup() {
        const form = reactive({
            host: defaults.host || '',
            port: defaults.port || '',
            scheme: defaults.scheme || 'wss',
            appKey: defaults.appKey || '',
            path: defaults.path || '',
            channel: defaults.channel || '',
            authEndpoint: defaults.authEndpoint || '/api-admin/broadcasting/auth',
            token: '',
        });

        const ws = ref(null);
        const socketOpen = ref(false);
        const socketId = ref('');
        const status = ref('idle');
        const logs = ref([]);
        const maxLogs = 200;

        const wsUrl = computed(() => {
            const host = form.host.trim();
            const port = form.port.trim();
            const scheme = form.scheme.trim() || 'ws';
            const appKey = form.appKey.trim();
            let path = form.path.trim();
            if (path && !path.startsWith('/')) {
                path = '/' + path;
            }
            if (!host || !appKey) {
                return '';
            }
            const portPart = port ? ':' + port : '';
            return `${scheme}://${host}${portPart}${path}/app/${appKey}?protocol=7&client=js&version=7.6.0&flash=false`;
        });

        const connected = computed(() => socketOpen.value);
        const canSubscribe = computed(() => connected.value && socketId.value && form.channel.trim());
        const statusText = computed(() => {
            if (status.value === 'connected') {
                return '已连接';
            }
            if (status.value === 'connecting') {
                return '连接中';
            }
            if (status.value === 'error') {
                return '连接错误';
            }
            return '未连接';
        });
        const statusState = computed(() => {
            if (status.value === 'connected') {
                return 'ok';
            }
            if (status.value === 'error') {
                return 'error';
            }
            return 'warn';
        });
        const socketLabel = computed(() => `socket_id：${socketId.value || '-'}`);
        const logText = computed(() => logs.value.join('\n'));

        const addLog = (type, message, payload) => {
            const ts = new Date().toLocaleTimeString('zh-CN', { hour12: false });
            let line = `[${ts}] [${type}] ${message}`;
            if (payload !== undefined) {
                const text = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
                line += `\n${text}`;
            }
            logs.value.push(line);
            if (logs.value.length > maxLogs) {
                logs.value.splice(0, logs.value.length - maxLogs);
            }
        };

        const parseJson = (value) => {
            if (typeof value !== 'string') {
                return value;
            }
            try {
                return JSON.parse(value);
            } catch {
                return value;
            }
        };

        const sendWs = (payload) => {
            if (!ws.value || ws.value.readyState !== WebSocket.OPEN) {
                addLog('错误', 'WebSocket 未连接');
                return false;
            }
            ws.value.send(JSON.stringify(payload));
            addLog('发送', payload.event, payload.data);
            return true;
        };

        const isPrivateChannel = (channelName) => channelName.startsWith('private-') || channelName.startsWith('presence-');

        const requestAuth = async (channelName) => {
            if (!socketId.value) {
                addLog('错误', '尚未拿到 socket_id，无法鉴权');
                return null;
            }
            const token = form.token.trim();
            if (!token) {
                addLog('错误', '请先填写 Bearer Token');
                return null;
            }
            const endpoint = form.authEndpoint.trim() || '/api-admin/broadcasting/auth';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    Authorization: 'Bearer ' + token,
                },
                body: JSON.stringify({
                    socket_id: socketId.value,
                    channel_name: channelName,
                }),
            });
            if (!response.ok) {
                const text = await response.text();
                addLog('错误', '鉴权接口返回异常', text);
                return null;
            }
            return await response.json();
        };

        const subscribeChannel = async () => {
            const channelName = form.channel.trim();
            if (!channelName) {
                addLog('错误', '频道不能为空');
                return;
            }
            const data = { channel: channelName };
            if (isPrivateChannel(channelName)) {
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

        const unsubscribeChannel = () => {
            const channelName = form.channel.trim();
            if (!channelName) {
                addLog('错误', '频道不能为空');
                return;
            }
            sendWs({ event: 'pusher:unsubscribe', data: { channel: channelName } });
        };

        const connect = () => {
            const url = wsUrl.value;
            if (!url) {
                addLog('错误', 'URL 无效，请检查 Host 与 App Key');
                return;
            }
            if (connected.value) {
                return;
            }
            status.value = 'connecting';
            socketOpen.value = false;
            socketId.value = '';
            addLog('系统', '开始连接', url);
            try {
                ws.value = new WebSocket(url);
            } catch (error) {
                status.value = 'error';
                addLog('错误', '连接失败', String(error));
                return;
            }

            ws.value.onopen = () => {
                socketOpen.value = true;
                addLog('系统', 'WebSocket 已建立');
            };
            ws.value.onerror = () => {
                status.value = 'error';
                addLog('错误', 'WebSocket 发生错误');
            };
            ws.value.onclose = () => {
                status.value = 'idle';
                socketOpen.value = false;
                socketId.value = '';
                addLog('系统', '连接已关闭');
            };
            ws.value.onmessage = (event) => {
                const message = parseJson(event.data);
                if (!message || typeof message !== 'object') {
                    addLog('接收', '原始消息', event.data);
                    return;
                }
                const data = parseJson(message.data);
                addLog('接收', message.event || 'unknown', data);
                if (message.event === 'pusher:connection_established') {
                    const payload = typeof data === 'string' ? parseJson(data) : data;
                    socketId.value = payload && payload.socket_id ? payload.socket_id : '';
                    status.value = 'connected';
                }
                if (message.event === 'pusher:ping') {
                    sendWs({ event: 'pusher:pong', data: {} });
                }
            };
        };

        const disconnect = () => {
            if (ws.value) {
                ws.value.close(1000, 'client close');
            }
        };

        const clearLogs = () => {
            logs.value = [];
        };

        return {
            form,
            wsUrl,
            connected,
            canSubscribe,
            statusText,
            statusState,
            socketLabel,
            logText,
            connect,
            disconnect,
            subscribeChannel,
            unsubscribeChannel,
            clearLogs,
        };
    },
}).mount('#reverb-vue-app');
