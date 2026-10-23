<?php
require_once __DIR__ . '/lib/spider.php';

#[AllowDynamicProperties]
class Spider extends BaseSpider {
    /** 🔥 站点基础配置（无改动） */
    private const HOST = 'http://wap.xbiquzw.net';
    private const COOKIE_PATH = './novel_cookie.txt';
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';
    
    /** 🔥 编码属性，避免未定义警告 */
    private $pageCharset = 'GBK';
    
    /** 🔥 类属性（无改动） */
    private static $bookNameToIdMap = [];
    public $vod_id = '';
    public $currentBookName = '';
    public $startPage = 1;

    /**
     * 🔥 构造函数（无改动）
     */
    public function __construct() {
        parent::__construct();
        if (!file_exists(self::COOKIE_PATH)) {
            @touch(self::COOKIE_PATH);
            @chmod(self::COOKIE_PATH, 0777);
        }
        error_reporting(E_ALL & ~E_DEPRECATED);
    }

    /**
     * 🔥 书籍名称清洗（无改动）
     */
    private function cleanBookName($name) {
        $name = trim($name);
        $name = preg_replace('/\s+/', '', $name);
        $name = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]/u', '', $name);
        return $name;
    }

    /** 🔥 首页分类（完全无改动） */
    public function homeContent($filter = []) {
        return [
            'class' => [
                ['type_name' => '玄幻小说', 'type_id' => '1'],
                ['type_name' => '修真小说', 'type_id' => '2'],
                ['type_name' => '都市小说', 'type_id' => '3'],
                ['type_name' => '穿越小说', 'type_id' => '4'],
                ['type_name' => '网游小说', 'type_id' => '5'],
                ['type_name' => '科幻小说', 'type_id' => '6']
            ],
            'filters' => (object)[]
        ];
    }

    /** 🔥 首页推荐（完全无改动） */
    public function homeVideoContent() {
        return [
            'list' => [
                [
                    'vod_id' => '93_93817*book',
                    'vod_name' => '七世神盘',
                    'vod_pic' => self::HOST . '/files/article/image/93/93817/93817s.jpg',
                    'vod_remarks' => '连载中'
                ],
                [
                    'vod_id' => '182_182188*book',
                    'vod_name' => '天地无殇',
                    'vod_pic' => self::HOST . '/files/article/image/182/182188/182188s.jpg',
                    'vod_remarks' => '连载中'
                ]
            ]
        ];
    }

    /** 🔥 分类页（完全无改动） */
    public function categoryContent($tid, $pg = 1, $filter = [], $extend = []) {
        $urlMap = [
            '1' => "list/1_{$pg}.html", 
            '2' => "list/2_{$pg}.html",
            '3' => "list/3_{$pg}.html", 
            '4' => "list/4_{$pg}.html",
            '5' => "list/5_{$pg}.html", 
            '6' => "list/6_{$pg}.html"
        ];
        
        $url = self::HOST . '/' . ($urlMap[$tid] ?? "list/2_{$pg}.html");
        $html = $this->fetch($url);
        
        $videos = [];
        if ($html) {
            $items = $this->pdfa($html, 'ul.xbk&&li');
            
            for ($i = 0; $i < count($items) && count($videos) < 15; $i += 2) {
                $imgItem = $items[$i] ?? '';
                $infoItem = $items[$i + 1] ?? '';
                
                $cover = $this->pd($imgItem, 'img&&src') ?: self::HOST . '/modules/article/images/nocover.jpg';
                $name = trim($this->pdfh($infoItem, '.xsm&&a&&Text') ?: $this->pdfh($imgItem, 'a&&Text'));
                $link = $this->pd($infoItem, '.xsm&&a&&href') ?: $this->pd($imgItem, 'a&&href');
                
                if (empty($name) || empty($link)) continue;
                
                $bookId = $this->extractBookId($link);
                $vodId = $bookId . '*book';
                $cleanName = $this->cleanBookName($name);
                self::$bookNameToIdMap[$cleanName] = $bookId;
                
                $videos[] = [
                    'vod_id' => $vodId,
                    'vod_name' => $name,
                    'vod_pic' => $cover,
                    'vod_remarks' => trim($this->pdfh($infoItem, 'span&&Text')) ?: '笔趣阁'
                ];
            }
        }
        
        return $this->pageResult($videos, $pg, 99999 * 15, 15);
    }

    /** 🔥 详情页（核心修改：强制显示所有章节，删除分页逻辑） */
    public function detailContent($ids) {
        $rawId = $ids[0] ?? '';
        $parts = explode('*', $rawId);
        $bookId = $parts[0] ?? '';

        if (empty($bookId) || preg_match('#^[a-f0-9]{8,12}$#', $bookId)) {
            return ['list' => []];
        }

        $detailUrl = rtrim(self::HOST, '/') . '/' . ltrim($bookId . '/', '/');
        $html = $this->fetch($detailUrl, ['headers' => ['User-Agent' => self::UA]]);
        if (!$html || strpos(strtolower($html), '404') !== false) {
            return ['list' => []];
        }

        $vod_name = trim($this->pdfh($html, 'h1&&Text') ?: '未知书籍');
        $this->currentBookName = $this->cleanBookName($vod_name);
        self::$bookNameToIdMap[$this->currentBookName] = $bookId;
        $this->vod_id = $bookId . '*book';

        $vod_pic = $this->pd($html, '.block_img2 img&&src') ?: self::HOST . '/modules/article/images/nocover.jpg';
        $vod_pic = strpos($vod_pic, 'http') === 0 ? $vod_pic : self::HOST . '/' . ltrim($vod_pic, '/');

        $vod_director = trim(str_replace(['作者：', '作者'], '', $this->pdfh($html, '.block_txt2 p:nth-of-type(2)&&Text') ?: '未知作者'));
        $vod_actor = trim($this->pdfh($html, '.block_txt2 p:nth-of-type(3)&&Text') ?: '玄幻小说');
        $vod_year = trim(str_replace(['状态：', '状态'], '', $this->pdfh($html, '.block_txt2 p:nth-of-type(4)&&Text') ?: '连载中'));
        $vod_content = trim(strip_tags($this->pdfh($html, '.intro&&Text') ?: '暂无简介'));
        $vod_content = preg_replace(['#https?://[^\s]+#i', '/\s+/'], ['', ' '], $vod_content);

        $chapters = [];
        $chapterIds = [];
        if (preg_match_all('~<ul class="chapter">.*?</ul>~is', $html, $ulMatches)) {
            $pattern = '~<li><a href="(/' . preg_quote($bookId, '~') . '/(\d+(_\d+)?)\.html)"[^>]*>([^<]+)</a></li>~is';
            foreach ($ulMatches[0] as $ulHtml) {
                if (preg_match_all($pattern, $ulHtml, $aMatches, PREG_SET_ORDER)) {
                    foreach ($aMatches as $match) {
                        $chapterId = $match[2];
                        $chapterTitle = trim($match[4]);
                        if (!in_array($chapterId, $chapterIds)) {
                            $chapterIds[] = $chapterId;
                            $chapters[] = $chapterTitle . '$' . $bookId . '|' . $chapterId . '@' . $chapterTitle;
                        }
                    }
                }
            }
        }

        // 章节排序：按章节ID升序
        usort($chapters, function($a, $b) {
            preg_match('/\$(\d+(_\d+)?)\|(\d+)/', $a, $ma);
            preg_match('/\$(\d+(_\d+)?)\|(\d+)/', $b, $mb);
            return intval($ma[3] ?? 0) - intval($mb[3] ?? 0);
        });

        $totalChapters = count($chapters);
        
        // 🔥 核心修改：强制显示所有章节，无分页、无最新章节筛选
        $finalChapters = $chapters;
        $vod_play_url = implode('#', $finalChapters);

        // 仅显示总章数
        $vod_remarks = $totalChapters . '章';

        return [
            'list' => [[
                'vod_id'        => $this->vod_id,
                'vod_name'      => $vod_name,
                'vod_pic'       => $vod_pic,
                'vod_director'  => $vod_director,
                'vod_actor'     => $vod_actor,
                'vod_year'      => $vod_year,
                'vod_remarks'   => $vod_remarks,
                'vod_content'   => $vod_content,
                'vod_play_from' => '笔趣阁',
                'vod_play_url'  => $vod_play_url
            ]]
        ];
    }

    /** 🔥 搜索页（完全无改动） */
    public function searchContent($key, $quick = false, $pg = 1) {
        $url = self::HOST . '/search.php?sou=1&keywords=' . urlencode($key);
        $html = $this->fetch($url);
        
        $videos = [];
        if ($html) {
            $items = $this->pdfa($html, 'ul.xbk&&li');
            
            for ($i = 0; $i < count($items) && count($videos) < 15; $i += 2) {
                $imgItem = $items[$i] ?? '';
                $infoItem = $items[$i + 1] ?? '';
                
                $cover = $this->pd($imgItem, 'img&&src') ?: self::HOST . '/modules/article/images/nocover.jpg';
                $name = trim($this->pdfh($infoItem, '.xsm&&a&&Text') ?: $this->pdfh($imgItem, 'a&&Text'));
                $link = $this->pd($infoItem, '.xsm&&a&&href') ?: $this->pd($imgItem, 'a&&href');
                
                if (empty($name) || empty($link)) continue;
                
                $bookId = $this->extractBookId($link);
                $vodId = $bookId . '*book';
                $cleanName = $this->cleanBookName($name);
                self::$bookNameToIdMap[$cleanName] = $bookId;
                
                $videos[] = [
                    'vod_id' => $vodId,
                    'vod_name' => $name,
                    'vod_pic' => $cover,
                    'vod_remarks' => trim($this->pdfh($infoItem, 'span&&Text')) ?: '笔趣阁'
                ];
            }
        }
        
        return $this->pageResult($videos, $pg, 99999 * 15, 15);
    }

    /** 🔥 🔥 核心优化：阅读页（无改动） */
    public function playerContent($flag, $id, $vipFlags = []) {
        $parts = explode('@', $id);
        $title = $parts[1] ?? '章节内容';
        $content = '章节内容加载中...';
        $bookId = '';
        $chapterId = '';

        // 从$id提取bookId和chapterId
        if (!empty($parts[0])) {
            $idParts = explode('|', $parts[0]);
            if (count($idParts) === 2) {
                $extractedBookId = preg_replace('/[^0-9_]/', '', $idParts[0]);
                if (preg_match('#^\d+(_\d+)?$#', $extractedBookId)) {
                    $bookId = $extractedBookId;
                }
                $extractedChapterId = preg_replace('/[^0-9]/', '', $idParts[1]);
                if (!empty($extractedChapterId)) {
                    $chapterId = $extractedChapterId;
                }
            }
        }

        // 实例属性优先级
        if (empty($bookId) && !empty($this->vod_id)) {
            $vodParts = explode('*', $this->vod_id);
            if (count($vodParts) === 2 && $vodParts[1] === 'book') {
                $extractedBookId = preg_replace('/[^0-9_]/', '', $vodParts[0]);
                if (preg_match('#^\d+(_\d+)?$#', $extractedBookId)) {
                    $bookId = $extractedBookId;
                }
            }
        }

        // 书名映射兜底
        if (empty($bookId) && !empty($this->currentBookName) && isset(self::$bookNameToIdMap[$this->currentBookName])) {
            $extractedBookId = preg_replace('/[^0-9_]/', '', self::$bookNameToIdMap[$this->currentBookName]);
            if (preg_match('#^\d+(_\d+)?$#', $extractedBookId)) {
                $bookId = $extractedBookId;
            }
        }

        // 补充chapterId
        if (empty($chapterId)) {
            $chapterId = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
        }

        // 最终校验
        if (empty($bookId)) {
            $content = "[bookId获取失败] 未从章节标识中提取到有效bookId | 原始ID:{$parts[0]} | 请从详情页进入章节";
            goto returnResult;
        }
        if (empty($chapterId)) {
            $content = "[chapterId无效] 原始ID:{$parts[0]} | 仅支持纯数字格式";
            goto returnResult;
        }

        // 同步更新实例属性
        $this->vod_id = $bookId . '*book';
        $this->currentBookName = isset(self::$bookNameToIdMap[$bookId]) ? array_search($bookId, self::$bookNameToIdMap) : $title;

        // 精准拼接URL
        $url = rtrim(self::HOST, '/') . "/{$bookId}/{$chapterId}.html";
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Referer: ' . rtrim(self::HOST, '/') . "/{$bookId}/"
        ];

        // 发起请求（保留原始响应，不提前转换编码）
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => self::COOKIE_PATH,
            CURLOPT_COOKIEFILE => self::COOKIE_PATH,
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_BINARYTRANSFER => true // 二进制模式获取，避免编码丢失
        ]);
        $rawHtml = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // 响应校验
        if ($httpCode !== 200 || strlen($rawHtml) < 1000) {
            $content = "[链接无效] 请求URL:{$url} | 状态码:{$httpCode} | bookId:{$bookId} | chapterId:{$chapterId} | 章节可能不存在";
            goto returnResult;
        }

        // 🔥 核心优化1：增强编码自动检测与转换（解决乱码根源）
        $html = '';
        // 优先从Content-Type提取编码
        $charset = 'GBK';
        if (preg_match('/charset=([a-zA-Z0-9\-]+)/i', $contentType, $m)) {
            $charset = strtoupper($m[1]);
        }
        // 支持的中文编码列表（覆盖笔趣阁所有变体）
        $supportedCharsets = ['GBK', 'GB2312', 'GB18030', 'UTF-8', 'ISO-8859-1'];
        foreach ($supportedCharsets as $cs) {
            $tempHtml = mb_convert_encoding($rawHtml, 'UTF-8', $cs);
            // 验证转换有效性（中文占比>30%则视为有效）
            $chineseRatio = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $tempHtml, $matches) / strlen($tempHtml);
            if ($chineseRatio > 0.3) {
                $html = $tempHtml;
                $this->pageCharset = $cs;
                break;
            }
        }
        // 兜底转换（防止编码检测失败）
        if (empty($html)) {
            $html = mb_convert_encoding($rawHtml, 'UTF-8', 'auto');
        }

        // 🔥 核心优化2：清理乱码字符和无效字节（保留有效内容）
        $html = preg_replace([
            '~<!--.*?-->~is',
            '~<script.*?</script>~is',
            '~<style.*?</style>~is',
            '~<div class="nr_page|center_tip|ad|footer|header|nav|skip|notice">.*?</div>~is',
            '~<div id="ad|footer|header|skip">.*?</div>~is',
            '/[\x00-\x1F\x7F]/', // 移除控制字符
            '/[^\x{4e00}-\x{9fa5}\x{3000}-\x{303f}\x{ff00}-\x{ffef}\w\s\p{P}<>\/=]/u' // 仅保留有效字符
        ], '', $html);
        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html); // 移除BOM头
        $html = trim($html);

        // 反爬检测（转换后再检测，避免乱码干扰）
        $antiCrawlKeywords = ['验证码', '访问受限', '请登录', '频繁访问'];
        foreach ($antiCrawlKeywords as $keyword) {
            if (strpos($html, $keyword) !== false) {
                $content = "[反爬拦截] 页面包含关键词:{$keyword} | 请求URL:{$url}";
                goto returnResult;
            }
        }

        // 🔥 核心优化3：精准选择器（优先匹配高频容器，忽略乱码干扰）
        $contentHtml = '';
        $selectors = [
            // 笔趣阁最高频容器（优先匹配）
            '~<div[^>]*id="content"[^>]*>(.*?)</div>~is',
            '~<div[^>]*class="content"[^>]*>(.*?)</div>~is',
            '~<div[^>]*id="nr"[^>]*>(.*?)</div>~is',
            // 常见变体容器
            '~<article[^>]*id="nr"[^>]*>(.*?)</article>~is',
            '~<div[^>]*class="nr_content|content_nr|readbox|article-content"[^>]*>(.*?)</div>~is',
            // 兜底：匹配包含大量中文的连续内容（忽略标签结构）
            '~([\x{4e00}-\x{9fa5}\s\p{P}]{200,})~ius'
        ];

        foreach ($selectors as $selector) {
            if (preg_match($selector, $html, $matches)) {
                $contentHtml = $matches[1];
                // 验证内容有效性（排除过短片段）
                if (strlen(trim(preg_replace('/\s+/', '', $contentHtml))) > 100) {
                    break;
                }
            }
        }

        // 最终错误处理（显示有效内容片段，方便调试）
        if (empty($contentHtml)) {
            $validHtml = preg_replace('/<[^>]+>/', '', $html);
            $content = "[提取失败] 未匹配到内容容器 | 请求URL:{$url} | 编码:{$this->pageCharset} | 有效文本片段:".mb_substr($validHtml, 0, 300, 'UTF-8');
            goto returnResult;
        }

        // 内容清洗排版（保留正常段落结构）
        $contentHtml = preg_replace([
            '#&nbsp;&nbsp;&nbsp;&nbsp;#is',
            '#<br\s*/?>#is',
            '#（本章未完，请点击下一页继续阅读）#is',
            '#&nbsp;#is',
            '#\(第\d+/\d+页\)#is',
            '#<br\s*/?>\s*<br\s*/?>#is'
        ], [
            '    ',
            PHP_EOL,
            '',
            ' ',
            '',
            PHP_EOL
        ], $contentHtml);

        $content = html_entity_decode(strip_tags(trim($contentHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace(['/\t+/', '/　+/', '/' . PHP_EOL . '{3,}/'], [' ', ' ', PHP_EOL . PHP_EOL], $content);
        $content = $content ?: '章节内容为空';

        returnResult:
        return [
            'parse' => 0,
            'url' => 'novel://' . json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE)
        ];
    }

    /** 🔥 bookId提取（完全无改动） */
    private function extractBookId($link) {
        if (preg_match('#/(\d+_\d+)/#', $link, $m)) {
            return $m[1];
        }
        if (preg_match('#/(\d+)/#', $link, $m)) {
            return $m[1];
        }
        return '';
    }
}

(new Spider())->run();