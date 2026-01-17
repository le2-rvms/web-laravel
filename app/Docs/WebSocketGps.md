# WebSocket GPS 监控设计（租户隔离版）

## 目标与范围
- 实时监控当前定位（不做轨迹回放），延迟期望 1-3 秒。
- 支持两类画面：全图监控（租户全量设备）、单设备监控。
- 仅使用 WebSocket 订阅；单实例部署，不依赖 Redis。

## 数据链路
1) 首屏数据：`GET /api-admin/gps-data/latest` 从 `pgsql-iot.gps_device_last_positions` 拉取（可传 `device_ids[]` 过滤；`COMPANY_ID` 存在时按 `tenant_id` 限制）。  
2) 实时增量：命令 `php artisan app:iot:gps-listen` 监听 `device_last_position_${COMPANY_ID}`（租户隔离），NOTIFY 负载含设备 ID、坐标、时间等，直接解析广播，无需再查库。示例负载：
```json
{"tenant_id":"rc-local","terminal_id":"013912345681","gps_time":"2026-01-12T12:45:57Z","latitude_gcj":30.487180213845548,"longitude_gcj":104.07431018102561}
```
3) 广播：推送 `GpsPositionUpdated` 事件到私有通道 `gps.device.{terminal_id}` 与 `gps.device.all`，`coord_sys=GCJ02`，`source=pgsql_listen`。  
4) 推送模式：事件使用 `ShouldBroadcastNow` 同步广播（当前不走队列；如需异步再改为队列广播）。

## 通道与订阅
- 通道名：`gps.device.{terminal_id}`（单设备）、`gps.device.all`（全设备），均为 PrivateChannel。
  - Echo 订阅：`Echo.private('gps.device.all')` / `Echo.private('gps.device.{terminal_id}')`。
  - 原生 Reverb/Pusher：频道名为 `private-gps.device.all` / `private-gps.device.{terminal_id}`（自动加 `private-` 前缀）。
- 订阅流程：订阅 → `/broadcasting/auth` 鉴权通过 → 后续实时增量；首屏快照来自 HTTP 接口。

## 认证与权限
- 鉴权：`auth:sanctum` 用于 `/broadcasting/auth`。
- 通道授权（在 `routes/channels.php` 中定义，由 `BroadcastServiceProvider` 加载）：
  - 需具备“轨迹数据”读取权限（`GpsData::read`）。
  - 设备通道：校验终端号在 `pgsql-iot.gps_devices` 中存在（可选按 `COMPANY_ID` 过滤 `tenant_id`）。
  - 全量通道：仅校验权限即可订阅。
  - 失败即拒绝订阅，并记录原因（脱敏）。

## 前端接入流程
- 全图监控：进入页面先调用初始位置 API（`GET /api-admin/gps-data/latest`，`device_ids[]` 为空=全量），渲染 marker；然后订阅 `gps.device.all` 接收增量。
- 单设备监控：进入设备详情先拉取该设备初始位置（`device_ids[]` 传单个终端号）；只订阅目标设备通道。
- 断线重连：提示状态，重连后重新订阅需监控的设备通道。

## 事件格式
说明：Reverb/Pusher 消息外层的 `event` 为 `GpsPositionUpdated`，`data` 解码后的结构如下：
```json
{
  "terminal_id": "013912345681",
  "ts": "2025-01-01T12:00:00+00:00",
  "latitude": 31.2304,
  "longitude": 121.4737,
  "speed": 30.5,
  "direction": 180,
  "altitude": 12.3,
  "coord_sys": "GCJ02",
 "source": "pgsql_listen",
  "meta": {
    "trace_id": "uuid"
  }
}
```

说明：
- 坐标系统一为 GCJ02。
- `ts` 为 ISO8601；实时推送会将 `gps_time` 按 UTC 解析后转换为应用时区（解析失败则原样透传）。
- `source` 取值：`pgsql_listen`（实时）；HTTP 首屏快照为 `snapshot`。
- 首屏接口返回字段与事件一致，但不包含 `speed/direction/altitude`。

## 任务拆分（建议）
1. 配置 Broadcasting + Reverb（`/broadcasting/auth` 走 Sanctum）。
2. 通道授权实现：`GpsData::read` + `gps_devices` 终端校验 + `COMPANY_ID` 过滤。
3. LISTEN worker：运行 `app:iot:gps-listen` 监听 `device_last_position_${COMPANY_ID}`，解析 NOTIFY 并广播。
4. 首屏快照：通过 `GET /api-admin/gps-data/latest` 获取（当前不做快照广播）。
5. 前端：初始位置 API 调用 + 通道订阅/重连处理。

## 待确认/风险
- NOTIFY 负载的字段格式与大小（需样例确认）。
- 单连接可订阅的设备数量上限；全量监控是否需要分批或多连接。
- COMPANY_ID 的取值与命名规范（大小写、前缀）以匹配 LISTEN 通道名。
- Reverb 不可达或慢时的降级策略（同步广播会阻塞监听进程）。
- 单连接订阅上限：不做分批/多连接拆分，默认可一次订阅全量设备（注意客户端/服务器资源占用）。
- COMPANY_ID 命名：普通字符串，直接拼接 LISTEN 通道名 `device_last_position_${COMPANY_ID}`。
- Reverb 不可达/慢：不做降级或重试（sync 模式下会阻塞/报错）。
