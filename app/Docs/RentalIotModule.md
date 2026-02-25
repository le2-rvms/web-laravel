# 租车车载设备 IoT 模块（Laravel API 规划）

## 目标与范围
- 面向“租车业务”的车载终端接入与能力封装：GPS 定位、车门锁控制（开/关）、鸣笛找车（以及后续可扩展的寻车灯/断电等）。
- 对外提供稳定、可审计的 API（管理端/客户端），对内对接 EMQX/MQTT 与数据落库。
- 实时性要求：
  - GPS：首屏 0~3s 内可用；增量延迟期望 1~3s。
  - 车钥匙类命令：端到端可追踪（下发/回执/超时/失败原因）。
- 租户隔离：按 `COMPANY_ID/tenant_id` 过滤设备与数据（后台与实时通道均一致）。

## 角色与入口（建议边界）
- 管理后台（`api-admin/*`）：设备资产管理、绑定关系、调试、全量监控、运维排障、命令审计。
- 客户端（`api-customer/*`）：租车用户仅能操作“当前订单/当前车辆”的 IoT 能力（查看定位、开关锁、鸣笛）。
- IoT 内部接口（`api-iot/*`）：EMQX 鉴权、Webhook（上行消息/事件）、命令回执上报等，仅对内网或签名访问。
- 公共初始化（`api-base/no-auth/init`）：下发前端所需的 WS 配置（host/port/app_key/path/auth_endpoint）。

## 核心对象（数据视角）
- 终端（Device/Terminal）：`terminal_id`（设备唯一标识，通常与 MQTT client_id/账号关联）。
- 车辆（Vehicle）：`ve_id`，车牌 `plate_no`。
- 绑定关系（Binding）：终端在某段时间绑定到某辆车（安装/拆卸/更换）。
- 遥控命令（Command）：锁车/解锁/鸣笛等，必须具备状态机、超时、回执与审计字段。
- 遥测数据（Telemetry）：
  - GPS：最近位置（last position）+ 历史轨迹（history，若需要）。
  - 设备状态：在线/离线、信号、电量、门锁状态等（按硬件支持逐步扩展）。

## Laravel API 要做什么（按前缀拆分）

### 1) 管理端 API（`api-admin`）
#### 1.1 现有能力（仓库内已存在）
- 设备绑定：`Route::resource('iot-device-bindings', ...)`（绑定/查询/审计处理人）。
- GPS 数据：
  - `GET /api-admin/gps-data/latest`：首屏快照（支持 `terminal_ids[]` 过滤）。
  - `GET /api-admin/gps-data/history-vehicle` / `history-device`：轨迹查询（窗口受限）。
- 广播鉴权：`POST /api-admin/broadcasting/auth`（Bearer Token，给 Reverb/Echo 私有频道鉴权）。

#### 1.2 建议补齐的后台能力（规划）
- 设备资产（可选，取决于是否由外部系统维护）：
  - `GET /api-admin/iot-devices`：设备列表（按租户/在线状态/关键字过滤）。
  - `GET /api-admin/iot-devices/{terminal_id}`：设备详情（最近上报/当前绑定车辆/能力集合）。
- 设备状态：
  - `GET /api-admin/iot-devices/{terminal_id}/status`：在线状态 + 关键状态字段（锁状态/电量/信号等）。
- 遥控命令（统一入口，避免为每个动作散落多个 controller）：
  - `POST /api-admin/iot-commands`：创建命令（`terminal_id` 或 `ve_id` + `action` + `client_request_id`）。
  - `GET /api-admin/iot-commands/{command_id}`：查询单条命令状态（sent/acked/timeout/failed + reason）。
  - `GET /api-admin/iot-commands`：命令列表（按设备/车辆/动作/状态/时间过滤）。

### 2) 客户端 API（`api-customer`）
#### 2.1 “我的车辆/订单”的 IoT 视图
- `GET /api-customer/iot/my-vehicle`：
  - 返回当前订单车辆、绑定终端、能力集合（是否支持锁/鸣笛等）、以及基础状态摘要。
- `GET /api-customer/iot/my-vehicle/gps/latest`：
  - 返回当前车辆实时定位首屏快照（仅当前订单可见）。

#### 2.2 “车钥匙”命令（异步为主）
建议统一走命令模型返回 `command_id`，客户端可轮询或 WS 接收回执：
- `POST /api-customer/iot/my-vehicle/commands/lock`
- `POST /api-customer/iot/my-vehicle/commands/unlock`
- `POST /api-customer/iot/my-vehicle/commands/horn`

返回建议：
- `data.command_id`：命令唯一 ID
- `data.status`：`queued|sent|acked|failed|timeout`
- `data.estimated_timeout_sec`：前端倒计时提示

#### 2.3 客户端实时订阅（可选）
若客户侧需要实时定位/状态变化：
- 需提供 `POST /api-customer/broadcasting/auth`（与 `api-admin` 分离，使用 Customer guard/TemporaryCustomer）。
- 频道授权规则需按“当前订单车辆绑定终端”校验（不能复用后台 `Admin` 授权类）。

### 3) IoT 内部接口（`api-iot`）
#### 3.1 现有能力
- `POST /api-iot/emqx/auth`：EMQX 认证（username/password）。

#### 3.2 建议新增（取决于上行方案）
如果采用 EMQX Rule Engine / Webhook 把 MQTT 消息转 HTTP：
- `POST /api-iot/emqx/webhook/telemetry`：
  - 上报 GPS/状态等数据（服务端做校验、落库、触发实时广播/NOTIFY）。
- `POST /api-iot/emqx/webhook/events`：
  - connect/disconnect/subscribe 等事件，用于维护设备在线状态。
- `POST /api-iot/emqx/webhook/command-ack`：
  - 设备执行结果回执（更新命令状态，触发通知/WS）。

安全建议：
- 仅允许内网访问或对请求做签名校验（shared secret / HMAC）。
- 上行落库与状态更新需具备幂等键，避免重复投递造成状态抖动。

## 协议与状态机建议（落地需要统一）
### 1) GPS 字段
- 建议统一字段：`terminal_id`、`datetime`（ISO8601）、`latitude`、`longitude`、`coord_sys`、`source`。
- 坐标系建议统一 GCJ02（与现有实现一致）。

### 2) 遥控命令字段与状态机
- 命令字段建议：
  - `command_id`（UUID/雪花）
  - `action`：`LOCK|UNLOCK|HORN|...`
  - `params`：动作参数（如鸣笛时长）
  - `status`：`queued -> sent -> acked|failed|timeout`
  - `requested_by`（admin/customer）、`requested_at`、`sent_at`、`acked_at`
  - `error_code/error_message`（失败原因，便于客服/运维）
  - `client_request_id`（幂等：同一请求重复提交返回同一 `command_id`）
- 超时策略：
  - 客户端命令建议 10~30s 超时（按设备能力与网络实际调整）。
  - 超时后仍可接受迟到回执，但状态需定义清晰（例如 `timeout` 后到达 `acked` 是否允许翻转）。

## 待确认（建议先定下来再写代码）
- 设备协议：MQTT topic 约定、payload 字段（锁/鸣笛回执格式、错误码）。
- 设备在线判定：心跳/上报周期，online/offline 阈值。
- 命令下发通道：Laravel 直接调用 EMQX HTTP API 发布？还是写 outbox + worker 用 MQTT client 发布？
- 多租户：`tenant_id` 的来源与一致性（设备表/绑定表/上行数据）。
- 权限边界：客户侧“可控范围”以订单为准还是以车辆为准（历史订单是否可见定位/轨迹）。
