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
              【简单任务 (未染色)】              【复杂任务 (runInQueue)】             TaskRunner
            QueuedCommandJob 就地同步           QueuedCommandJob 异步队列            (CLI 独立沙箱)
              (耗时 < 50ms, 留存连接)             (Redis / FC / DB 削峰)
```

---

## 二、四大核心设计支柱

### 1. DSL 流式“染色”机制 (`EventMixin` & `EventDyeManager`)

业务开发者 100% 使用 Laravel 原生的 `routes/console.php` 语法定义任务，通过流畅的链式方法对需要异步消费的复杂任务进行“染色”：

```php
use Illuminate\Support\Facades\Schedule;

// A. 简单任务（未染色）：就地同步执行，毫秒级跑完，不卡 Web 请求
Schedule::command('proxy:ping')->everyMinute()->withoutOverlapping();

// B. 复杂任务（染色 runInQueue）：自动推入异步队列，由队列 Worker 削峰
Schedule::command('order:sync')->everyFiveMinutes()->withoutOverlapping()->runInQueue();

// C. 复杂任务精细化分配（指定队列与连接）
Schedule::command('report:generate')->dailyAt('02:00')->withoutOverlapping()->runInQueue('reports', 'redis');
```

* **WeakMap 内存安全**：底层通过 PHP 8 的 `WeakMap` 实现对象级动态属性绑定，随 Event 对象析构自动释放，**绝对零内存泄漏**；
* **老项目零代码侵入兼容**：如果不想改动历史老项目代码，直接在 `config/knight.php` 中配置通配符即可：
  ```php
  'schedule' => [
      'heavy_commands' => [
          'order:*',
          'sync:*',
          'report:generate',
      ],
  ],
  ```

---

### 2. 唯一执行内核 (`QueuedCommandJob`)

`QueuedCommandJob` 作为调度任务在全平台的**唯一执行单元**：无论是同步直驱、还是队列消费，所有执行逻辑 100% 收拢在此。

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
  * 遍历到期任务并包装为 `QueuedCommandJob`；
  * 染了 `runInQueue` 的任务执行 `dispatch($job)`；
  * 未染色的简单任务直接调用 `$job->action()` 就地同步执行。

---

### 4. 触发网关与全链路安全防御 (`DevopsScheduleAction`)

对外挂载的 HTTP 触发端点 `/devops/schedule` 拥有 3 重硬核防线：

1. **随机 Token 校验**：比对环境变量 `KNIGHT_DEVOPS_SCHEDULE_TOKEN`，支持 `X-Devops-Token`、Bearer Token、或 Query 参数；
2. **内网 IP 白名单**：仅放行 `127.0.0.1` 及 Docker 内网网段（`172.x`、`10.x`、`192.168.x`）；
3. **分钟级幂等锁**：使用 Cache 锁定当前分钟 `YmdHi`（TTL 55 秒），任意一分钟内无论受到多少次心跳打入，绝对只执行一次，彻底免疫重放与并发攻击。

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

| 运行环境 | 触发方式 | 简单任务（未染色） | 复杂任务（`runInQueue`） | 业务代码改动 |
| :--- | :--- | :--- | :--- | :--- |
| **Docker Compose** | 伴生 `pulse` 请求 `POST /devops/schedule` | Web 进程内毫秒级同步执行 | 推入标准 Laravel 队列消费 | 0 改动 |
| **阿里云 FC** | FC 定时触发器调用 `/invoke` | FC 实例内就地执行 | 推入 `alifc` 队列异步并发削峰 | 0 改动 |
| **独立 CLI 容器** | 容器常驻 `schedule:daemon` 运行 | CLI 进程直驱执行 | 推入标准 Laravel 队列消费 | 0 改动 |
