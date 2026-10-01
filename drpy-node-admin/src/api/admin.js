/**
 * Admin API 统一调用接口
 * 所有后台管理 API 都通过这个模块调用
 */

import client from './client';

export const adminApi = {
    // ==================== 系统 ====================
    async getHealth() {
        return client.get('/api/admin/health');
    },

    async restartService() {
        return client.post('/api/admin/restart');
    },

    // ==================== 日志 ====================
    async getLogs(lines = 50) {
        return client.get('/api/admin/logs', { params: { lines } });
    },

    // WebSocket 连接在组件中直接使用

    // ==================== 配置 ====================
    async getConfig(key) {
        return client.get('/api/admin/config', { params: { key } });
    },

    async updateConfig(key, value) {
        return client.post('/api/admin/config', { key, value });
    },

    async getEnv() {
        return client.get('/api/admin/env');
    },

    // ==================== 源管理 ====================
    async listSources() {
        return client.get('/api/admin/sources');
    },

    async validateSource(path) {
        return client.post('/api/admin/sources/validate', { path });
    },

    async checkSyntax(path) {
        return client.post('/api/admin/sources/syntax', { path });
    },

    async verifySourceFlow(path, options = {}) {
        return client.post('/api/admin/sources/verify-flow', { path, options }, { timeout: 180000 });
    },

    async getTemplate() {
        return client.get('/api/admin/sources/template');
    },

    async getLibsInfo() {
        return client.get('/api/admin/sources/libs');
    },

    async uploadSource(engine, filename, content, overwrite = false) {
        return client.post('/api/admin/sources/upload', { engine, filename, content, overwrite });
    },

    async deleteSource(path) {
        return client.post('/api/admin/sources/delete', { path });
    },

    async setSourcesEnabled(paths, enabled) {
        return client.post('/api/admin/sources/enabled', { paths, enabled });
    },

    // ==================== 文件管理 ====================
    async listDirectory(path) {
        return client.get('/api/admin/files/list', { params: { path } });
    },

    async readFile(path) {
        return client.get('/api/admin/files/read', { params: { path } });
    },

    async writeFile(path, content) {
        return client.post('/api/admin/files/write', { path, content });
    },

    async deleteFile(path) {
        return client.delete('/api/admin/files/delete', { params: { path } });
    },

    // ==================== 数据库 ====================
    async executeQuery(sql) {
        return client.post('/api/admin/db/query', { sql });
    },

    async getTables() {
        return client.get('/api/admin/db/tables');
    },

    async getTableSchema(table) {
        return client.get(`/api/admin/db/tables/${table}/schema`);
    },

    // ==================== 路由信息 ====================
    async getRoutes() {
        return client.get('/api/admin/routes');
    },

    async getApiDocs() {
        return client.get('/api/admin/docs');
    },

    // ==================== 更新日志 ====================
    async getChangelog() {
        return client.get('/api/admin/changelog');
    },

    // ==================== 订阅管理 ====================
    async getSubFiles() {
        return client.get('/api/admin/sub/files');
    },

    async getSubFile(name) {
        return client.get('/api/admin/sub/file', { params: { name } });
    },

    async saveSubFile(name, content) {
        return client.post('/api/admin/sub/file', { name, content });
    },

    // ==================== 插件管理 ====================
    async getPlugins() {
        return client.get('/api/admin/plugins');
    },

    async savePlugins(plugins) {
        return client.post('/api/admin/plugins', { plugins });
    },

    async restorePlugins() {
        return client.post('/api/admin/plugins/restore');
    },

    async getPluginStatus() {
        return client.get('/api/admin/plugins/status');
    },

    async startPlugin(name) {
        return client.post('/api/admin/plugins/start', { name });
    },

    async stopPlugin(name) {
        return client.post('/api/admin/plugins/stop', { name });
    },

    async restartPlugin(name) {
        return client.post('/api/admin/plugins/restart', { name });
    },

    // ==================== 插件市场 ====================
    async getMarketList(refresh = false) {
        return client.get('/api/admin/market/list', { params: refresh ? { refresh: 1 } : {} });
    },

    async getMarketSources() {
        return client.get('/api/admin/market/sources');
    },

    async saveMarketSources(sources, ghProxy) {
        return client.post('/api/admin/market/sources', { sources, ghProxy });
    },

    async installMarketPlugin(name, { active = true, start = true, version } = {}) {
        return client.post('/api/admin/market/install', { name, active, start, version });
    },

    async getInstallStatus() {
        return client.get('/api/admin/market/install/status');
    },

    async updateMarketPlugin(name) {
        return client.post('/api/admin/market/update', { name });
    },

    async uninstallMarketPlugin(name) {
        return client.post('/api/admin/market/uninstall', { name });
    },

    // 上传插件 zip 安装（multipart 流式上传，上限 500MB；timeout 与后端下载上限对齐放宽到 10min）
    async uploadPlugin(file, { active = false, start = false } = {}) {
        const form = new FormData();
        form.append('file', file);
        if (active) form.append('active', 'true');
        if (start) form.append('start', 'true');
        return client.post('/api/admin/plugins/upload', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            timeout: 600000,
        });
    },

    // ==================== 备份恢复 ====================
    async getBackupConfig() {
        return client.get('/api/admin/backup/config');
    },

    async updateBackupConfig(paths) {
        return client.post('/api/admin/backup/config', { paths });
    },

    async resetBackupConfig() {
        return client.post('/api/admin/backup/config/reset');
    },

    async createBackup() {
        // plugins 目录可达 GB 级（插件二进制/依赖），后端已并发拷贝提速；60s 覆盖常规磁盘冷热态
        return client.post('/api/admin/backup/create', {}, { timeout: 60000 });
    },

    async restoreBackup() {
        return client.post('/api/admin/backup/restore', {}, { timeout: 60000 });
    }
};
