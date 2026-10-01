import fs from 'fs-extra';
import path from 'path';
import assert from 'node:assert/strict';

process.env.ROOT = path.resolve('E:/gitwork/drpy-node');

const { edit_file, write_file, read_file } = await import('../tools/fsTools.js');
const { resolvePath } = await import('../utils/pathHelper.js');

async function main() {
  const relPath = 'spider/js/__mcp_edit_file_test__.js';
  const absPath = resolvePath(relPath);

  await fs.ensureDir(path.dirname(absPath));
  await fs.writeFile(absPath, [
    'var rule = {',
    "    title: 'test',",
    "    searchUrl: '/search.asp?wd=**',",
    '    searchable: 2,',
    '};',
    ''
  ].join('\n'), 'utf8');

  const insertRet = await edit_file({
    path: relPath,
    operation: 'insert_lines',
    start_line: 3,
    content: "    encoding: 'gb18030',\n    搜索编码: 'gb18030',"
  });

  const insertObj = JSON.parse(insertRet.content[0].text);
  assert.equal(insertObj.writeVerification.passed, true, 'writeVerification should pass');
  assert.equal(insertObj.operationMeta.insert_mode, 'after_line');
  assert.equal(insertObj.operationMeta.anchor_line, 3);
  assert.equal(insertObj.operationMeta.actual_inserted_range.start_line, 4);
  assert.equal(insertObj.operationMeta.actual_inserted_range.end_line, 5);

  const finalText = await fs.readFile(absPath, 'utf8');
  assert(finalText.includes("    encoding: 'gb18030',"), 'final file should contain encoding');
  assert(finalText.includes("    搜索编码: 'gb18030',"), 'final file should contain search encoding');

  const replaceRet = await edit_file({
    path: relPath,
    operation: 'replace_text',
    search: "    searchUrl: '/search.asp?wd=**',",
    replacement: "    searchUrl: '/search.asp?page=fypage&searchword=**&searchtype=-1',"
  });
  const replaceObj = JSON.parse(replaceRet.content[0].text);
  assert.equal(replaceObj.writeVerification.passed, true, 'replace_text writeVerification should pass');

  const finalText2 = await fs.readFile(absPath, 'utf8');
  assert(finalText2.includes("page=fypage&searchword=**&searchtype=-1"), 'searchUrl should be replaced');

  await fs.remove(absPath);
  console.log(JSON.stringify({
    ok: true,
    tested: ['insert_lines metadata', 'post-write verification', 'replace_text verification'],
    file: relPath
  }, null, 2));
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
