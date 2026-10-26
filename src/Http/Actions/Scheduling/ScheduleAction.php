<?php

namespace HughCube\Laravel\Knight\Http\Actions\Scheduling;

use HughCube\Laravel\Knight\Events\ScheduledTasksExecuted;
use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Scheduling\ScheduleRunner;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 统一调度触发网关 Action (/devops/schedule).
 *
 * 推荐路由配置：
 * Route::any('/schedule', ScheduleAction::class)->middleware([VerifyScheduleToken::class]);
 *
 * 推荐中间件组合：
 * - 专属中间件：[VerifyScheduleToken::class]
 *   1. 阿里云 FC 环境 (KNIGHT_IS_FC=true)：
 *      - 仅允许 FC 平台内部控制路径调用 (校验 x-fc-control-path，严禁 /http-invoke 公网触发器)；
 *      - 内部定时触发器免密放行。
 *   2. 常规环境 (ECS / Docker / K8s / 本地)：
 *      - 强制从 Query 或 Header 中比对 X-Devops-Token 是否与环境变量 KNIGHT_DEVOPS_TOKEN 一致。
 */
class ScheduleAction extends Controller
{
    /**
     * @var array|string|null 专属日志通道 (null 时自动探测 'schedule' 或回退默认通道)
     */
    protected $logChannel = null;

    /**
     * 获取调度日志通道.
     *
     * @return array|string|null
     */
    protected function getLogChannel()
    {
        if (null !== $this->logChannel) {
            return $this->logChannel;
        }

        if (array_key_exists('schedule', config('logging.channels', []))) {
            return 'schedule';
        }

        return null;
    }

    /**
     * @return Response
     *
     * @throws Throwable
     */
    protected function action(): Response
    {
        $runner = new ScheduleRunner($this->getContainer());
        $report = $runner->run(true);

        $this->logReport($report);

        $this->getEventsDispatcher()->dispatch(new ScheduledTasksExecuted($report));

        return $this->asSuccess($report);
    }

    /**
     * 记录调度执行日志 (左侧保持官方原生视觉，右侧扩展延迟、执行通道与 Job ID).
     *
     * @param  array  $report
     * @return void
     */
    protected function logReport(array $report): void
    {
        $count = $report['count'] ?? 0;
        $triggeredAt = $report['triggered_at'] ?? '';
        $tasks = $report['tasks'] ?? [];

        $logger = Log::channel($this->getLogChannel());

        if ($count <= 0) {
            $logger->info('No scheduled commands are ready to run.');

            return;
        }

        foreach ($tasks as $task) {
            $taskName = strval($task['task'] ?? '');
            $isBackground = ($task['defined_mode'] ?? '') === 'background';
            $duration = floatval($task['duration'] ?? 0.0);
            $delays = floatval($task['delays'] ?? 0.0);
            $mode = strval($task['mode'] ?? 'sync');
            $status = strval($task['status'] ?? 'done');
            $skipReason = strval($task['skip_reason'] ?? '');
            $queue = strval($task['queue'] ?? '');
            $jobId = isset($task['job_id']) ? strval($task['job_id']) : null;

            // 左侧保持 100% 官方原生格式
            $left = sprintf(
                '%s Running [%s]%s',
                $triggeredAt,
                $taskName,
                $isBackground ? ' in background' : ''
            );

            // 右侧核心状态
            if ($status === 'skipped') {
                $mainStatus = 'SKIPPED';
            } elseif ($status === 'fail') {
                $mainStatus = sprintf('%.2fms FAIL', $duration);
            } elseif ($status === 'pushed') {
                $mainStatus = sprintf('%.2fms PUSHED', $duration);
            } else {
                $mainStatus = sprintf('%.2fms DONE', $duration);
            }

            // 扩展元信息括号 (delays: 127.50ms, ...)
            $meta = [sprintf('delays: %.2fms', $delays)];

            if ($status === 'skipped' && ! empty($skipReason)) {
                $meta[] = $skipReason;
            }

            if ($mode === 'queue') {
                $queueName = $queue ?: 'default';
                if (null !== $jobId && '' !== $jobId) {
                    $meta[] = sprintf('queue: %s@%s', $queueName, $jobId);
                } else {
                    $meta[] = sprintf('queue: %s', $queueName);
                }
            } elseif (! empty($mode)) {
                $meta[] = $mode;
            }

            $right = sprintf('%s (%s)', $mainStatus, implode(', ', $meta));

            // 两列点号自动对齐 (基准宽度 100 字符，中间至少保留 2 个点号)
            $dotsCount = max(2, 100 - mb_strwidth($left) - mb_strwidth($right) - 2);
            $line = sprintf('%s %s %s', $left, str_repeat('.', $dotsCount), $right);

            $logger->info($line);
        }
    }
}
