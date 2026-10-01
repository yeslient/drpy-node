# MCP 文件工具本地测试说明

位置：`drpy-node-mcp/scripts/`

## 已提供测试

### 1. drpy_edit_file 成功路径
```bash
cd /d E:\gitwork\drpy-node\drpy-node-mcp
node scripts\test-edit-file-local.js
```
验证点：
- insert_lines 返回元信息
- drpy_edit_file 写后回读校验通过
- replace_text 写后回读校验通过

### 2. drpy_edit_file 失败路径（防误报成功）
```bash
cd /d E:\gitwork\drpy-node\drpy-node-mcp
node scripts\test-edit-file-write-verification-fail.js
```
验证点：
- 模拟写后读取结果被篡改
- 工具返回 `WRITE_VERIFICATION_FAILED`
- 不会假装成功

### 3. drpy_write_file 成功路径
```bash
cd /d E:\gitwork\drpy-node\drpy-node-mcp
node scripts\test-write-file-local.js
```
验证点：
- drpy_write_file 写后回读校验通过
- 最终文件内容与输入完全一致

## 说明
这些测试都直接 import 本地源码，不依赖重启 MCP 服务。
因此：
- 在你重启服务前，可以先用这些脚本验证源码级行为
- 你重启服务后，MCP 才会真正对外提供这些新行为
