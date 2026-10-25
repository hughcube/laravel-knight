# Laravel Knight 大一统调度架构规范 (Unified Scheduling Architecture)

## 一、架构背景与设计哲学

在现代容器化与微服务架构中，特别是在中小规格宿主机（如 2 核 8GB 内存）运行 20+ 个 Laravel Octane 应用时，传统的调度方案面临尖锐矛盾：

* **传统方案的资源困局**：若为每个项目独立运行 `schedule:daemon` 或 `schedule:work` 容器，20 个项目将产生 20 个常驻 PHP 进程，白白霸占 **1.2GB ~ 1.6GB** 物理内存；
* **历史 FC 模式的绑定困局**：早期为了免常驻进程，通过函数计算 (FC) 定时触发，但往往导致任务定义与 FC SDK 深度锁死，无法无缝切回标准模式；
* **时序与协程死锁困局**：在常驻内存中若盲目使用协程并发执行调度任务，极易引发底层数据库连接池竞争 (`Socket already bound to another coroutine`) 或 Web 请求超时。

为了彻底解决上述痛点，`laravel-knight` 确立了**「大一统调度契约（Unified Scheduling Contract）」**：
> **“触发机制走心跳/网关，任务定义走原生标准，执行通道依染色两级分流，生命周期与排他互斥绝对自闭环。”**

```
                     ┌──────────────────────────────────────────────┐
                     │          业务定义层 (100% 官方标准)           │
                     │             routes/console.php               │
                     │  Schedule::command() / exec() / call() ...   │
                     └──────────────────────┬───────────────────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │          调度分发器: ScheduleJob              │
                     │     (读取 routes/console.php 解析 dueEvents)  │
                     └───────┬──────────────────────────────┬───────┘
                             │                              │
         ┌───────────────────┴──────────┐        ┌──────────┴──────────────────┐
         ▼                              ▼        ▼                             ▼
【形态 A: Docker Compose】     【形态 B: 阿里云 FC】     【形态 C: 原生 CLI】     【形态 D: 独立容器】
HTTP /devops/schedule          FC 统一 /invoke          crontab / daemon        schedule:work
(随机 Token + 内网白名单)        (FC 定时触发器)         (本地定时任务)           (独立部署)
         │                              │                       │                      │
         └──────────────────────────────┼───────────────────────┘                      │
                                        │                                              │
                      ┌─────────────────┴─────────────────┐                            │
                      ▼                                   ▼                            ▼
              【简单短任务 (无标记)】            【复杂耗时任务 (runInBackground)】       ScheduleRunner
            CommandJob 就地同步                 CommandJob 异步队列 (Action环境下)       (CLI 进程极速直驱)
              (耗时 < 50ms, 留存连接)             (Redis / FC / 队列削峰防 HTTP 超时)
```

---

## 二、四大核心设计支柱

### 1. 100% 官方原生语法与双端自适应 (`runInBackground`)

业务开发者 100% 使用 Laravel 原生的 `routes/console.php` 语法定义任务，无需记忆任何私有方言：

```php
use Illuminate\Support\Facades\Schedule;

// A. 简单短任务（未标记）：就地同步执行，毫秒级跑完，不卡 Web 请求
Schedule::command('proxy:ping')->everyMinute()->withoutOverlapping();

// B. 复杂耗时任务（官方原生 runInBackground）：
//    1. 在原生 CLI / 守护进程模式下：本地后台独立运行，零队列开销；
//    2. 在 HTTP Action / 网关模式下：自动升阶为推入异步队列 (To Queue)，100% 免疫 HTTP 超时！
Schedule::command('order:sync')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
```

* **零侵入与零心智负担**：完全采用 Laravel 官方自带的 `$event->runInBackground` 属性，彻底告别额外的弱引用与元数据开销；
* **双端环境自适应**：原生守护下直驱后台进程，HTTP 网关下自动入队削峰。

---

### 2. 唯一执行内核 (`CommandJob`)

`CommandJob` 作为调度任务在全平台的**唯一执行单元**：无论是同步直驱、还是队列消费，所有执行逻辑 100% 收拢在此。

* **互斥锁完整自闭环**：
  * 执行前通过 `$event->mutex->exists($event)` 检查；若已锁定，派发 `ScheduledTaskSkipped` 并安全退出；
  * 执行中抢占锁并在 `finally` 块中 **100% 保证释放锁**，彻底杜绝死锁与两阶段时序竞争；
* **官方生命周期 4 大事件 1:1 派发**：
  * `ScheduledTaskStarting`、`ScheduledTaskFinished`、`ScheduledTaskFailed`、`ScheduledTaskSkipped` 严格对齐；
* **输出捕获与日志重定向**：
  * 自动拦截输出并写入 `sendOutputTo()` / `appendOutputTo()` 指定的文件；
* **连接复用纪律（严禁无脑 `disconnect()`）**：
  * 因为在就地同步或队列中均为单任务顺序执行，不存在跨协程 Socket 碰撞，**绝对不调用 `disconnect()` 或 `setPdo(null)`**；
  * 仅在任务异常退出且留下未提交事务时回滚事务 (`rollBack()`)，完整捍卫 Octane / Swoole 预热好的 MySQL/Redis 长连接。

---

### 3. 大一统调度分发器 (`ScheduleJob`)

* **无环境感知**：`ScheduleJob` 没有任何 `isAliFC()` 或 `isSwooleTask()` 之类的特化分支代码；
* **纯粹的分发器职责**：
  * 遍历到期任务；
  * 耗时任务（`runInBackground`）执行 `dispatch(new CommandJob($event))`；
  * 未标的简单任务直接调用 `$job->action()` 就地同步执行。

---

### 4. 触发网关与职责解耦 (`ScheduleAction`)

对外挂载的 HTTP/路由触发端点 `ScheduleAction` 坚持极简与单一职责原则：

1. **标准总线驱动**：内部统一采用 `$this->getDispatcher()->dispatchSync(new ScheduleJob())` 驱动调度，与框架标准总线/事件系统无缝契合；
2. **纯粹执行职责**：移除内置鉴权与内置分钟幂等锁，将安全鉴权（Token/HMAC）、IP 白名单防护与时序幂等控制彻底解耦，完整交由业务 HTTP 中间件流水线灵活管控；
3. **结构化轻量响应**：执行完毕后返回包含状态与毫秒耗时的结构化 JSON 响应。

---

## 三、Docker Compose 伴生 Pulse 编排最佳实践

在 Docker Compose 密集部署场景下，推荐为每个项目配置一个极简的伴生 `pulse` 服务：

```yaml
services:
  app:
    image: my-app:latest
    init: true
    environment:
      KNIGHT_DEVOPS_SCHEDULE_TOKEN: "${KNIGHT_DEVOPS_SCHEDULE_TOKEN:-secret_token_zy}"
    ports:
      - "9090:80"

  pulse:
    image: curlimages/curl:latest
    restart: unless-stopped
    init: true
    command: >
      sh -c '
        while true; do
          sec=$$(date +%S);
          sleep_time=$$((60 - 10#$$sec));
          sleep $$sleep_time;

          curl -s -m 5 -X POST \
            -H "X-Devops-Token: ${KNIGHT_DEVOPS_SCHEDULE_TOKEN:-secret_token_zy}" \
            http://app/devops/schedule >/dev/null 2>&1;
        done
      '
    depends_on:
      - app
```

### 为什么这是最佳落地形态？
1. **真实资源消耗极低**：单个 `pulse` 仅消耗 **~1.5MB 内存**，20 个项目总计仅 **~30MB**（相比 20 个 CLI 容器省下 **1.2GB 以上内存**）；
2. **动态时钟差值对齐**：`sleep $((60 - 10#$sec))` 保证永远在系统时钟的 `00 秒` 整点发起，运行数月也零累积漂移；
3. **免疫蓝绿发布端口漂移**：直连 Docker 内部网络 `http://app/devops/schedule`，发版切端口对它完全透明。发版过渡期两个容器并存时，依靠 Redis 分钟锁保证幂等。

---

## 四、全运行环境表现一致性矩阵

| 运行环境 | 触发方式 | 简单短任务（未标记） | 耗时任务（`runInBackground`） | 业务代码改动 |
| :--- | :--- | :--- | :--- | :--- |
| **Docker Compose** | 伴生 `pulse` 请求 `POST /devops/schedule` | Web 进程内毫秒级同步执行 | 自动推入标准 Laravel 队列消费 (防 HTTP 超时) | 0 改动 |
| **阿里云 FC** | FC 定时触发器调用 `/invoke` | FC 实例内就地执行 | 自动推入队列异步并发削峰 | 0 改动 |
| **独立 CLI 容器** | 容器常驻 `schedule:daemon` 运行 | CLI 进程直驱执行 | 本地后台独立运行 (子进程) | 0 改动 |
