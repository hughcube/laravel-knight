# AGENTS.md

> **本项目唯一权威主规范（Single Source of Truth）**  
> 所有 AI 编程助手（Claude Code、Codex CLI、AGY、CodeBuddy、WorkBuddy、Cursor、Windsurf、GitHub Copilot、Cline/Roo Code 等）在本项目工作区执行任何任务时，必须以本规范为最高准则。

---

## 1. 项目概述与时间基准

Laravel Knight 是一个强大的 Laravel 扩展包，提供增强的 Eloquent 模型、查询构建器、控制器工具、多级缓存机制、乐观锁、中间件集合以及 PostgreSQL 数组/WAL 支持等功能。

- **核心包命名空间**：`HughCube\Laravel\Knight\`
- **代码架构模式**：采用 Trait 组合模式，服务提供者自动注册 Mixin 与命令行，零配置路由设计。
- **时间与生态基准**：
  - 本项目当前时间节点为 **2026 年**；
  - 框架与环境生态已全面适配至 **PHP 8.5** 与 **Laravel 13**；
  - CI 测试矩阵覆盖 **PHP 7.4 ~ 8.5** 以及 **Laravel 8 ~ 13**，支持 Linux、Windows、macOS 跨平台环境。

---

## 2. 运行环境与 PHP 版本向下兼容硬约束

**本项目源码必须严格支持 PHP 7.4+（包含 PHP 7.4）**。

虽然测试环境和依赖支持至 PHP 8.5 与 Laravel 13，但在编写、重构或修改 `src/` 下的核心代码时，必须遵守以下向下兼容硬约束：
1. **禁止在核心代码中使用 PHP 8.0+ 的专属语法与特性**：
   - 严禁使用命名参数（Named Arguments）；
   - 严禁使用 `match` 表达式；
   - 严禁使用联合类型声明（Union Types，如 `int|string`）；
   - 严禁使用 Nullsafe 操作符（`?->`）；
   - 严禁使用构造函数属性提升（Constructor Property Promotion）；
   - 严禁使用 WeakMap、Stringable 接口等 PHP 8 特有内置类/接口。
2. **测试用例注解规范**：
   - 严禁使用 PHPUnit 8.0+ 原生属性语法（如 `#[DataProvider()]` 或 `#[Test]`）；
   - 统一使用 PHPDoc 注解（如 `@dataProvider`、`@test` 等）。
3. **类属性与闭包语法**：
   - 类属性类型声明需谨慎（PHP 7.4 虽然支持基本类型提示，但务必确保与上下游兼容）；
   - 允许使用短闭包/箭头函数 `fn(...) => ...`（PHP 7.4 原生支持）。

---

## 3. 开发命令与工具链指南

### 3.1 Windows 环境重要提示
**在 Windows 环境下，所有 `vendor/bin/` 下的 CLI 命令都必须显式使用 `.bat` 后缀**：

```bash
# ✅ 正确 - Windows 下必须带 .bat 后缀
./vendor/bin/phpunit.bat
./vendor/bin/phpstan.bat
./vendor/bin/phpcs.bat
./vendor/bin/phpcbf.bat
./vendor/bin/testbench.bat
./vendor/bin/psysh.bat

# ❌ 错误 - 不带 .bat 后缀在 Windows 下会调用失败
./vendor/bin/phpunit
./vendor/bin/phpstan
```

### 3.2 测试执行（统一通过 Composer）
**所有单元测试和集成测试，统一通过 `composer run` 执行**：

```bash
# 运行全部测试
composer run test

# 运行单个测试文件
composer run test -- tests/Database/Eloquent/ModelTest.php

# 运行特定测试方法（带过滤参数）
composer run test -- --filter testMethodName tests/SomeTest.php
```

### 3.3 代码质量与风格检查
```bash
# 运行 PHPStan 静态分析 (Linux/macOS)
composer phpstan
# 或
./vendor/bin/phpstan analyse -vvv --memory-limit=-1

# 运行 PHPStan 静态分析 (Windows)
./vendor/bin/phpstan.bat analyse --memory-limit=-1

# 检查代码风格 (PSR-2 规范)
composer check-style
# 或
./vendor/bin/phpcs -p --standard=PSR2 src/ -v

# 自动修复代码风格
composer fix-style
# 或
./vendor/bin/phpcbf -p --standard=PSR2 src/ -v
```

### 3.4 依赖管理
```bash
composer install
```

---

## 4. 架构设计与核心模式

### 4.1 核心设计模式
1. **Trait 组合模式（Trait Composition）**：
   - 项目高度倡导使用 Trait 组合功能，避免深层次的类继承；
   - 核心抽象基类（`Model`、`Job`、`Action`、`Controller` 等）均由多个小职责 Trait 拼装而成。
2. **服务提供者扩展（ServiceProvider）**：
   - `ServiceProvider` 统一负责将 Mixin 注入到 Laravel 核心类（`Collection`、`Builder`、`Grammar`、`Carbon`、`Str` 等）；
   - 负责命令行工具注册（Database, Model Cache, Wal, OPcache, Scheduling 等）；
   - 负责配置自定义的 Auth User Provider；
   - 注册并监听 Eloquent 变更事件刷新缓存（`onKnightModelChanged` / `deleteRowCache`）。
3. **分层缓存设计（Multi-level Cache）**：
   - Model 必须通过 `getCache()` 方法返回 PSR SimpleCache 实例；
   - 提供 `findById()`、`findByIds()` 等高效模型缓存查询方法；
   - 自动监听 Eloquent 模型事件（`created` / `updated` / `deleted` / `restored`）即时清空对应缓存键。

### 4.2 主要模块全景

#### Database / Eloquent
- **Model**：扩展 Laravel Eloquent Model，添加行级缓存、乐观锁等增强功能；
- **Builder**：扩展 Eloquent 查询构建器，封装通用扩展方法（`whereLike`、`whereLeftLike`、`whereRange` 等）；
- **Traits/KnightModelTrait**：核心模型 Trait，赋能缓存与查询优化；
- **Traits/OptimisticLock**：乐观锁机制实现，基于数据版本号字段 `data_version` 控制并发；
- **PostgreSQL 扩展**：支持原生数组检索与 PostgreSQL WAL 逻辑复制变更捕获（`WalEventDispatchCommand`、`WalChangeRecord`）。

#### Queue
- **Job**：抽象基类，预装 `Dispatchable`、`Queueable`、`InteractsWithQueue` 等 traits；
- **FlowJobDescribe**：Job 流程描述与执行链路追踪；
- 内置开箱即用 Jobs：`PingJob`、`CleanFilesJob`、`ScheduleJob` 等。

#### Routing & Http
- **Action trait**：为控制器提供统一的参数校验、缓存响应、事件分发等能力；
- **Controller**：扩展 Laravel 原生 Controller，混入 `Action` trait；
- **Http/Actions 模块**：统一放置在 `HughCube\Laravel\Knight\Http\Actions\` 命名空间，采用零配置路由设计，由宿主业务在 `routes/*.php` 中显式引入并配置中间件：
  - *Devops*：`HealthcheckAction` (探活), `StatusAction` (DB/Redis连通看板), `VersionAction` (版本), `SystemAction` (系统/内存), `ClientInfoAction` (客户端/UA识别), `NowAction` (时钟探针), `RequestShowAction` (报文回显与按需记录), `QueueDepthAction` (队列深度), `PhpInfoAction` (原生HTML环境诊断看板)
  - *OPcache*：`StatesAction` (OPcache状态与脚本列表), `ResetAction` (OPcache刷新与重置)
  - *Scheduling*：`ScheduleAction` (统一调度触发网关)
- **Http/Middleware 模块**：
  - *运维鉴权*：`VerifyDevopsToken`（严格从原生环境变量 `KNIGHT_DEVOPS_TOKEN` 动态鉴权，仅支持 Query 与 Header 携带 `X-Devops-Token`，非 Schedule 端点全环境强制校验）；`VerifyScheduleToken`（`ScheduleAction` 专属中间件，阿里云 FC 环境免密放行，非 FC 环境强制校验 `KNIGHT_DEVOPS_TOKEN`）
  - *认证保障*：`Authenticate`、`CheckAuthUserInstanceOf`、`CheckAuthUserIsAvailable`
  - *环境准入*：`OnlyLocalGuard`、`OnlyProdEnvGuard`、`OnlyTestEnvGuard`
  - *网络边界*：`OnlyIpGuard`、`OnlyPrivateIpGuard`、`OnlyPublicIpGuard`
  - *报文与协议*：`RequestSignatureValidate`、`LogRequest`、`HandleAllPathCors`

#### OPcache 管理
- 提供 OPcache 脚本预热、编译、重置与统计功能；
- Artisan Commands：`CompileFilesCommand`、`ClearCliCacheCommand`、`CreatePreloadCommand`；
- `CompileFilesCommand` 默认预热编译 `app`、`config`、`routes`、`database` 目录及所有 Composer PSR-4 自动加载文件。

#### 核心通用 Traits
- `GetOrSet`：提供缓存读取、设置与淘汰三合一门面（get / set / forget）；
- `Validation`：集成 Laravel 原生 Validation 验证能力；
- `ParameterBag`：参数载荷统一管理与清洗；
- `Container`：便捷访问 Laravel IoC 容器；
- `Logger`：标准化日志记入与上下文提取。

#### 配置文件与环境变量
- `config/knight.php` 保持轻量，为空或仅保留可选的基础配置；
- 所有 Action 端点去除黑盒路由隐式注册；Devops 密钥完全从操作系统原生环境变量动态注入，**严禁在配置文件或代码中硬编码任何明文密钥**。

#### Mixin 扩展
- **CollectionMixin**：扩展 `Illuminate\Support\Collection`
- **BuilderMixin**：扩展 Eloquent 查询构建器
- **GrammarMixin**：扩展 SQL 语法生成器
- **CarbonMixin**：扩展 Carbon 日期时间处理
- **StrMixin**：扩展 Str 辅助类（提供 `isCnCarLicensePlate()` 等特定算法与格式验证）

#### 测试规范与策略
- 使用 Orchestra Testbench 搭建独立包开发测试环境；
- 测试用例集中于 `tests/` 目录，目录结构与命名空间完整镜像 `src/`；
- PHPUnit 配置文件为 `phpunit.xml.dist`；
- 测试执行内存上限设定为 `2048M`，采用本地独立 `testing` 数据库连接。

---

## 5. Git 协同规范

- **提交信息规范（Conventional Commits）**：
  - 严禁在提交信息中出现 AI 标识：不得出现 `AI`、`Claude`、`GPT`、`Copilot`、`Gemini` 等任何大模型或工具名称；
  - 提交信息必须简洁明了地描述业务改动与代码变更内容，严格遵循语义化规范（如 `feat(...)`、`fix(...)`、`chore(...)`、`test(...)`、`ci(...)` 等）。
