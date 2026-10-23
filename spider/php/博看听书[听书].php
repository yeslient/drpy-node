<?php
require_once __DIR__ . '/lib/spider.php';

class Spider extends BaseSpider
{
    // 站点核心配置
    private $HOST = 'https://api.bookan.com.cn';
    private $SEARCH_HOST = 'https://es.bookan.com.cn';
    private $INSTANCE_ID = '25304';
    private $UA = 'Mozilla/5.0 (Linux; Android 10; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

    // 分类映射（class_name 与 class_url 对应）
    private $CLASS_MAP = [
        '少年读物' => '1305',
        '儿童文学' => '1304',
        '国学经典' => '1320',
        '文艺少年' => '1306',
        '育儿心经' => '1309',
        '心理哲学' => '1310',
        '青春励志' => '1307',
        '历史小说' => '1312',
        '故事会'    => '1303',
        '音乐戏剧' => '1317',
        '相声评书' => '1319'
    ];

    /**
     * 构造请求头
     */
    protected function getHeaders($isJson = true)
    {
        $headers = [
            'User-Agent: ' . $this->UA,
            'Origin: ' . $this->HOST,
            'Referer: ' . $this->HOST . '/',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Connection: keep-alive'
        ];
        if ($isJson) {
            $headers[] = 'Content-Type: application/json';
        }
        return $headers;
    }

    /**
     * 首页分类数据解析
     */
    public function homeContent($filter = [])
    {
        $classes = [];
        foreach ($this->CLASS_MAP as $className => $classId) {
            $classes[] = [
                'type_id' => $classId,
                'type_name' => $className
            ];
        }

        // 默认抓取少年读物分类首页数据
        $defaultData = $this->categoryContent('1305', 1);
        return [
            'class' => $classes,
            'list' => $defaultData['list'] ?? [],
            'filters' => (object)[]
        ];
    }

    /**
     * 分类数据抓取
     */
    public function categoryContent($tid, $pg = 1, $filter = [], $extend = [])
    {
        $pg = max(1, intval($pg));
        $tid = empty($tid) ? '1305' : $tid;

        // 构造分类请求URL
        $requestUrl = $this->HOST . '/voice/book/list?instance_id=' . $this->INSTANCE_ID . '&page=' . $pg . '&category_id=' . $tid . '&num=24';
        $response = $this->fetch($requestUrl, [
            CURLOPT_HTTPHEADER => $this->getHeaders(true),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $list = [];
        if (!empty($response)) {
            $data = json_decode($response, true) ?? [];
            foreach ($data['data']['list'] ?? [] as $item) {
                $vodId = $item['id'] ?? '';
                if (empty($vodId)) continue;

                $list[] = [
                    'vod_id' => $vodId,
                    'vod_name' => $item['name'] ?? '未知书名',
                    'vod_pic' => $item['cover'] ?? '',
                    'vod_remarks' => '博看听书',
                    'vod_actor' => $item['extra']['author'] ?? '未知作者'
                ];
            }
        }

        return $this->pageResult($list, $pg, count($list) * 10, 24);
    }

    /**
     * 详情页集数抓取（核心逻辑）
     */
    public function detailContent($ids)
    {
        $id = is_array($ids) ? ($ids[0] ?? '') : $ids;
        if (empty($id)) return ['list' => []];

        $vod = [];
        $playlists = [];
        $page = 1;
        $pageSize = 200;

        do {
            // 构造详情请求URL
            $requestUrl = $this->HOST . '/voice/album/units?album_id=' . $id . '&page=' . $page . '&num=' . $pageSize . '&order=1';
            $response = $this->fetch($requestUrl, [
                CURLOPT_HTTPHEADER => $this->getHeaders(true),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20
            ]);

            if (empty($response)) break;

            $data = json_decode($response, true) ?? [];
            $currentList = $data['data']['list'] ?? [];
            if (empty($currentList)) break;

            // 合并集数列表
            $playlists = array_merge($playlists, $currentList);
            $total = $data['data']['total'] ?? 0;
            $page++;
        } while (count($playlists) < $total);

        if (!empty($playlists)) {
            $firstItem = $playlists[0];
            $playUrlArr = [];

            foreach ($playlists as $item) {
                $title = $item['title'] ?? '未知章节';
                $fileUrl = $item['file'] ?? '';
                if (!empty($fileUrl)) {
                    $playUrlArr[] = $title . '$' . $fileUrl;
                }
            }

            $vod = [
                'vod_id' => $id,
                'vod_name' => $firstItem['id'] ?? $id,
                'vod_actor' => '▶️创建于' . ($firstItem['created_at'] ?? ''),
                'vod_year' => !empty($firstItem['created_at']) ? explode('-', $firstItem['created_at'])[0] : '',
                'vod_director' => '▶️更新于' . ($firstItem['updated_at'] ?? ''),
                'vod_play_from' => 'bookan',
                'vod_play_url' => implode('#', $playUrlArr),
                'vod_url' => $playlists[0]['file'] ?? '' // 封面跳转第一集
            ];
        }

        return ['list' => [$vod]];
    }

    /**
     * 搜索功能实现
     */
    public function searchContent($key, $quick = false, $pg = 1)
    {
        if (empty($key)) return $this->pageResult([], $pg, 0, 20);

        $pg = max(1, intval($pg));
        $requestUrl = $this->SEARCH_HOST . '/api/v3/voice/book?instanceId=' . $this->INSTANCE_ID . '&keyword=' . urlencode($key) . '&pageNum=' . $pg . '&limitNum=20';
        $response = $this->fetch($requestUrl, [
            CURLOPT_HTTPHEADER => $this->getHeaders(true),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $list = [];
        if (!empty($response)) {
            $data = json_decode($response, true) ?? [];
            foreach ($data['data']['list'] ?? [] as $item) {
                $vodId = $item['id'] ?? '';
                if (empty($vodId)) continue;

                $list[] = [
                    'vod_id' => $vodId,
                    'vod_name' => $item['name'] ?? '未知书名',
                    'vod_pic' => $item['cover'] ?? '',
                    'vod_remarks' => '博看听书',
                    'vod_actor' => $item['author'] ?? '未知作者'
                ];
            }
        }

        return $this->pageResult($list, $pg, count($list) * 5, 20);
    }

    /**
     * 播放地址解析
     */
    public function playerContent($flag, $id, $vipFlags = [])
    {
        return [
            'jx' => 0,
            'parse' => 0,
            'url' => $id,
            'header' => $this->getHeaders(true)
        ];
    }

    /**
     * 工具函数：格式化链接
     */
    private function formatUrl($url)
    {
        if (empty($url)) return '';
        if (strpos($url, 'http') === 0) return $url;
        return rtrim($this->HOST, '/') . '/' . ltrim($url, '/');
    }
}

(new Spider())->run();
