// 崩溃兜底入口：先装异常钩子，再动态加载主模块。
// 为什么不直接在 main 里静态 import：入口模块的静态 import 解析失败是 Node 的
// "致命错误"，不触发 uncaughtException——只有动态 import 的失败能被捕获并落盘。
import './utils/crashGuard.js';
import {reportCrash} from './utils/crashGuard.js';

// main.js 同时兼容两种宿主：
//  - CLI/壳子：模块加载即自行 start()（见 main.js 末尾的 VERCEL 条件）
//  - Vercel/serverless：不监听端口，由下方转发的 default handler 处理请求
let handler = null;
try {
    const mod = await import('./main.js');
    handler = mod.default;
} catch (err) {
    reportCrash('bootstrap-failed', err);
    process.exit(1);
}
export default handler;
