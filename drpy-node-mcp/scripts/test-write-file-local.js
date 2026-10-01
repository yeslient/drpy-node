import fs from 'fs-extra';
import path from 'path';
import assert from 'node:assert/strict';

process.env.ROOT = path.resolve('E:/gitwork/drpy-node');

const { write_file } = await import('../tools/fsTools.js');
const { resolvePath } = await import('../utils/pathHelper.js');

async function main() {
  const relPath = 'spider/js/__mcp_write_file_test__.js';
  const absPath = resolvePath(relPath);

  await fs.ensureDir(path.dirname(absPath));
  const content = [
    'var rule = {',
    "    title: 'write-file-test',",
    "    searchUrl: '/search.asp?page=fypage&searchword=**&searchtype=-1',",
    "    encoding: 'gb18030',",
    "    搜索编码: 'gb18030',",
    '};',
    ''
  ].join('\n');

  const ret = await write_file({ path: relPath, content });
  const obj = JSON.parse(ret.content[0].text);
  assert.equal(obj.writeVerification.passed, true, 'write_file verification should pass');
  assert.equal(obj.writeVerification.expectedLength, content.length);
  assert.equal(obj.writeVerification.actualLength, content.length);
  assert(obj.humanSummary.message.includes('Verified write-back succeeded'));

  const finalText = await fs.readFile(absPath, 'utf8');
  assert.equal(finalText, content, 'final file should exactly match input content');

  await fs.remove(absPath);
  console.log(JSON.stringify({
    ok: true,
    tested: ['write_file verification', 'write_file exact readback match'],
    file: relPath
  }, null, 2));
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
