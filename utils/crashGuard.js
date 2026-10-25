/**
 * 最早崩溃兜底（crash guard）
 *
 * 目标：进程"秒退但无任何日志"时，至少在磁盘上留下一份带堆栈的崩溃报告。
 * 触发场景：入口静态 import 失败（存储层抽风/文件缺失）、初始化期未捕获异常等。
 *
 * 设计约束（勿破坏）：
 *  1. 本文件只允许依赖 node 内置模块——它必须在"项目其他部分全坏"时仍能工作；
 *  2. 全部使用同步写（appendFileSync/writeSync），崩溃路径上不能有异步；
 *  3. 多落点：项目 logs/ → 系统 tmpdir → stderr(fd 2)。Android 上项目目录可能
 *     位于 FUSE 存储层，恰好是最容易间歇失效的介质，必须要有降级去处；
 *  4. 只装 uncaughtException 一个钩子：node≥15 的默认 rejection 策略(throw)
 *     会把 unhandledRejection 汇入 uncaughtException，多装反而改变进程语义；
 *  5. 记录后 process.exit(1)，保持服务管理器观察到的退出码不变。
 *
 * 接入方式：index.js 的第一行 import（见该文件），确保它先于其他任何 import 执行。
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PRIMARY_DIR = path.join(__dirname, '..', 'logs');
const FALLBACK_DIR = os.tmpdir();

function stamp() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

export function reportCrash(kind, err) {
    const body = (err && (err.stack || err.message)) ? (err.stack || String(err.message)) : String(err);
    const text = `\n==== ${kind} @ ${new Date().toISOString()} ====\n` +
        `pid=${process.pid} node=${process.version} cwd=${process.cwd()}\n` +
        `argv=${JSON.stringify(process.argv)}\n` +
        `${body}\n`;
    const name = `drpy-crash-${stamp()}.log`;
    try {
        fs.mkdirSync(PRIMARY_DIR, {recursive: true});
        fs.appendFileSync(path.join(PRIMARY_DIR, name), text, 'utf8');
    } catch {
        try {
            fs.appendFileSync(path.join(FALLBACK_DIR, name), text, 'utf8');
        } catch {
            try { fs.writeSync(2, text); } catch { /* 连 stderr 都没了：放弃 */ }
        }
        try { fs.writeSync(2, text); } catch { /* 同上 */ }
    }
}

process.on('uncaughtException', (err) => {
    reportCrash('uncaughtException', err);
    process.exit(1);
});
