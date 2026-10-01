import fs from 'fs-extra';
import path from 'path';
import assert from 'node:assert/strict';

process.env.ROOT = path.resolve('E:/gitwork/drpy-node');

const fsExtra = await import('fs-extra');
const originalWriteFile = fsExtra.default.writeFile.bind(fsExtra.default);
const originalReadFile = fsExtra.default.readFile.bind(fsExtra.default);

let targetAbsPath = '';
let writeTriggered = false;
let normalReadCount = 0;
fsExtra.default.writeFile = async (...args) => {
  await originalWriteFile(...args);
  writeTriggered = true;
};
fsExtra.default.readFile = async (...args) => {
  const ret = await originalReadFile(...args);
  const file = String(args[0] || '');
  if (file === targetAbsPath && writeTriggered) {
    normalReadCount += 1;
    if (normalReadCount >= 2) {
      return String(ret).replace("title: 'changed'", "title: 'tampered'");
    }
  }
  return ret;
};

const { edit_file } = await import('../tools/fsTools.js');
const { resolvePath } = await import('../utils/pathHelper.js');

async function main() {
  const relPath = 'spider/js/__mcp_edit_file_fail_test__.js';
  const absPath = resolvePath(relPath);
  targetAbsPath = absPath;

  await fs.ensureDir(path.dirname(absPath));
  await fs.writeFile(absPath, [
    'var rule = {',
    "    title: 'test',",
    '};',
    ''
  ].join('\n'), 'utf8');

  const ret = await edit_file({
    path: relPath,
    operation: 'replace_text',
    search: "    title: 'test',",
    replacement: "    title: 'changed',"
  });

  assert.equal(ret.isError, true, 'tampered write should return isError=true');
  const obj = JSON.parse(ret.content[0].text);
  assert.equal(obj.error, 'WRITE_VERIFICATION_FAILED');

  await fs.remove(absPath);
  console.log(JSON.stringify({
    ok: true,
    tested: ['tampered post-write readback triggers WRITE_VERIFICATION_FAILED']
  }, null, 2));
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
