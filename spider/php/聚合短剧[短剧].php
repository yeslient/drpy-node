<?php
require_once __DIR__ . '/lib/spider.php';

class Spider extends BaseSpider {
    private $host = '';
    private $config = [
        'keys' => 'd3dGiJc651gSQ8w1',
        'charMap' => [
            '+' => 'P', '/' => 'X', '0' => 'M', '1' => 'U', '2' => 'l', '3' => 'E', '4' => 'r', '5' => 'Y', '6' => 'W', '7' => 'b', '8' => 'd', '9' => 'J',
            'A' => '9', 'B' => 's', 'C' => 'a', 'D' => 'I', 'E' => '0', 'F' => 'o', 'G' => 'y', 'H' => '_', 'I' => 'H', 'J' => 'G', 'K' => 'i', 'L' => 't',
            'M' => 'g', 'N' => 'N', 'O' => 'A', 'P' => '8', 'Q' => 'F', 'R' => 'k', 'S' => '3', 'T' => 'h', 'U' => 'f', 'V' => 'R', 'W' => 'q', 'X' => 'C',
            'Y' => '4', 'Z' => 'p', 'a' => 'm', 'b' => 'B', 'c' => 'O', 'd' => 'u', 'e' => 'c', 'f' => '6', 'g' => 'K', 'h' => 'x', 'i' => '5', 'j' => 'T',
            'k' => '-', 'l' => '2', 'm' => 'z', 'n' => 'S', 'o' => 'Z', 'p' => '1', 'q' => 'V', 'r' => 'v', 's' => 'j', 't' => 'Q', 'u' => '7', 'v' => 'D',
            'w' => 'w', 'x' => 'n', 'y' => 'L', 'z' => 'e'
        ],
        'headers' => [
            'default' => ['User-Agent' => 'okhttp/3.12.11', 'content-type' => 'application/json; charset=utf-8']
        ],
        'platform' => [
            '百度' => ['host' => 'https://api.jkyai.top', 'url1' => '/API/bddjss.php?name=fyclass&page=fypage', 'url2' => '/API/bddjss.php?id=fyid', 'search' => '/API/bddjss.php?name=**&page=fypage'],
            '甜圈' => ['host' => 'https://mov.cenguigui.cn', 'url1' => '/duanju/api.php?classname', 'url2' => '/duanju/api.php?book_id', 'search' => '/duanju/api.php?name'],
            '锦鲤' => ['host' => 'https://api.jinlidj.com', 'search' => '/api/search', 'url2' => '/api/detail'],
            '番茄' => ['host' => 'https://reading.snssdk.com', 'url1' => '/reading/bookapi/bookmall/cell/change/v', 'url2' => 'https://fqgo.52dns.cc/catalog', 'search' => 'https://fqgo.52dns.cc/search'],
            '星芽' => ['host' => 'https://app.whjzjx.cn', 'url1' => '/cloud/v2/theater/home_page?theater_class_id', 'url2' => '/v2/theater_parent/detail', 'search' => '/v3/search', 'loginUrl' => 'https://u.shytkjgs.com/user/v1/account/login'],
            '西饭' => ['host' => 'https://xifan-api-cn.youlishipin.com', 'url1' => '/xifan/drama/portalPage', 'url2' => '/xifan/drama/getDuanjuInfo', 'search' => '/xifan/search/getSearchList'],
            '星星' => ['host' => 'http://read.api.duodutek.com', 'url1' => '/novel-api/app/pageModel/getResourceById', 'url2' => '/novel-api/basedata/book/getChapterList', 'search' => '/novel-api/app/pageModel/getResourceById', 'COMMON_PARAMS' => ["productId" => "2a8c14d1-72e7-498b-af23-381028eb47c0", "vestId" => "2be070e0-c824-4d0e-a67a-8f688890cadb", "channel" => "oppo19", "osType" => "android", "version" => "20", "token" => "202509271001001446030204698626"]],
            '河马' => ['host' => 'https://freevideo.zqqds.cn', 'url1' => '/free-video-portal/portal/1125', 'url2' => '/free-video-portal/portal/1131', 'search' => '/free-video-portal/portal/1803'],
            '围观' => ['host' => 'https://api.drama.9ddm.com', 'url1' => '/drama/home/shortVideoTags', 'url2' => '/drama/home/shortVideoDetail', 'search' => '/drama/home/search'],
            '红果' => ['host' => 'https://api.cenguigui.cn', 'url1' => '/api/duanju/api.php?name=fyclass&page=fypage', 'url2' => '/api/duanju/api.php?book_id=fyid', 'search' => '/api/duanju/api.php?name=**&page=fypage', 'homeUrl' => '/api/duanju/api.php?name=新剧'],
            '七猫' => ['host' => 'https://api-store.qmplaylet.com', 'host1' => 'https://api-read.qmplaylet.com', 'url1' => '/api/v1/playlet/index', 'url2' => '/api/v1/playlet/index', 'search' => '/api/v1/playlet/search']
        ],
        'platformList' => [
            ['name' => '河马短剧', 'id' => '河马'], ['name' => '番茄短剧', 'id' => '番茄'],
            ['name' => '星芽短剧', 'id' => '星芽'], ['name' => '甜圈短剧', 'id' => '甜圈'], ['name' => '锦鲤短剧', 'id' => '锦鲤'],
            ['name' => '西饭短剧', 'id' => '西饭'], ['name' => '星星短剧', 'id' => '星星'], ['name' => '围观短剧', 'id' => '围观'],
            ['name' => '七猫短剧', 'id' => '七猫']
        ],
        'search' => ['limit' => 30, 'timeout' => 6000]
    ];
    
    private $filterDef = [
        '百度' => ['area' => '逆袭'], '甜圈' => ['area' => '推荐榜'], '锦鲤' => ['area' => ''], '番茄' => ['area' => 'videoseries_hot'],
        '星芽' => ['area' => '1'], '西饭' => ['area' => ''], '星星' => ['area' => '1287'], '河马' => ['area' => '53@精选'],
        '围观' => ['area' => ''], '红果' => ['area' => '系统'], '七猫' => ['area' => '0']
    ];
    
    private $xingyaToken = '';
    
    // 工具函数
    private function guid() {
        $chars = '0123456789abcdef';
        $uuid = '';
        for ($i = 0; $i < 36; $i++) {
            if ($i == 8 || $i == 13 || $i == 18 || $i == 23) {
                $uuid .= '-';
            } else if ($i == 14) {
                $uuid .= '4';
            } else {
                $uuid .= $chars[rand(0, 15)];
            }
        }
        return $uuid;
    }
    
    // 河马短剧专用方法
    private function fetchApiData($portal, $body, $apiUrl) {
        $key = "ZHpramdmeXhnc2h5bGd6bQ==";
        $iv = "YXBpdXBkb3duZWRjcnlwdA==";
        $UA = "Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.91 Mobile Safari/537.36";
        
        try {
            $encryptedBody = $this->aesEncrypt($body, $key, $iv);
            if (empty($encryptedBody)) {
                return null;
            }
            $headers = $this->encryptData($key, $iv);
            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = "$k: $v";
            }
            $curlHeaders[] = "User-Agent: $UA";
            $curlHeaders[] = "Referer: $apiUrl";
            $response = $this->fetch($apiUrl . "/free-video-portal/portal/{$portal}", [
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $encryptedBody,
                CURLOPT_HTTPHEADER => $curlHeaders
            ]);
            if (!empty($response)) {
                $result = json_decode($response, true);
                if (isset($result['data'])) {
                    $decryptedData = $this->aesDecrypt($result['data'], $key, $iv);
                    if (!empty($decryptedData)) {
                        return json_decode($decryptedData, true);
                    }
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function aesEncrypt($text, $key, $iv) {
        try {
            $key = base64_decode($key);
            $iv = base64_decode($iv);
            $encrypted = openssl_encrypt($text, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return base64_encode($encrypted);
        } catch (Exception $e) {
            return "";
        }
    }
    
    private function aesDecrypt($encryptedData, $key, $iv) {
        try {
            $key = base64_decode($key);
            $iv = base64_decode($iv);
            $decrypted = openssl_decrypt(base64_decode($encryptedData), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $decrypted;
        } catch (Exception $e) {
            return "{}";
        }
    }
    
    private function encryptData($key, $iv) {
        $randomUuid = $this->generateUuid4();
        $data = [
            "version" => "2.1.0",
            "pname" => "com.dz.hmjc",
            "channelCode" => "HMJC1000002",
            "utdidTmp" => "A20250528195953036srZcvA",
            "token" => "",
            "utdid" => "d5d959973340bb1325b551cce488a191",
            "os" => "android",
            "osv" => 28,
            "brand" => "vivo",
            "model" => "V1938T",
            "manu" => "vivo",
            "userId" => "2484393607",
            "launch" => "shortcut",
            "mchid" => "HMJC1000002",
            "nchid" => "VHSE1000000",
            "session1" => $randomUuid,
            "session2" => $randomUuid,
            "startScene" => "shortcut",
            "recSwitch" => true,
            "installTime" => 1748433575024,
            "p" => 30
        ];
        $plaintext = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
        $encryptedB64 = $this->aesEncrypt($plaintext, $key, $iv);
        return [
            "alg" => "HG45LKBS",
            "datas" => $encryptedB64,
            "content-type" => "application/json; charset=utf-8",
            "user-agent" => "okhttp/4.10.0"
        ];
    }
    
    private function generateUuid4() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    private function getVideoUrlFromApi($bookId, $chapterId, $lastChapterId) {
        $key = "ZHpramdmeXhnc2h5bGd6bQ==";
        $iv = "YXBpdXBkb3duZWRjcnlwdA==";
        $apiUrl = "https://freevideo.zqqds.cn";
        
        try {
            $body = json_encode([
                "bookId" => $bookId,
                "chapterIds" => [$chapterId],
                "unClockType" => "load",
                "chapterId" => $lastChapterId,
                "resolutionRate" => "720P"
            ], JSON_UNESCAPED_UNICODE);
            $apiData = $this->fetchApiData("1139", $body, $apiUrl);
            if ($apiData && isset($apiData['chapterInfo']) && !empty($apiData['chapterInfo']) &&
                isset($apiData['chapterInfo'][0]['content']) &&
                isset($apiData['chapterInfo'][0]['content']['mp4SwitchUrl'])) {
                $mp4Urls = $apiData['chapterInfo'][0]['content']['mp4SwitchUrl'];
                if (!empty($mp4Urls)) {
                    return $mp4Urls[0];
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function getPlatformConfig($id) {
        return $this->config['platform'][$id];
    }
    
    // 初始化星芽token
    private function initXingyaToken() {
        if (empty($this->xingyaToken)) {
            $data = json_encode(['device' => '24250683a3bdb3f118dff25ba4b1cba1a']);
            $headers = [
                'User-Agent' => 'okhttp/4.10.0',
                'platform' => '1',
                'Content-Type' => 'application/json'
            ];
            
            $response = $this->fetch($this->config['platform']['星芽']['loginUrl'], [
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers)
            ]);
            
            $json = json_decode($response, true);
            $this->xingyaToken = $json['data']['token'] ?? $json['data']['data']['token'] ?? '';
        }
    }
    
    // 格式化请求头
    private function formatHeaders($headers) {
        $formatted = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $formatted[] = "$k: $v";
            }
        }
        return $formatted;
    }
    
    // MD5加密
    private function md5($str) {
        return md5($str);
    }
    
    // 七猫相关函数
    private function getQmParamsAndSign() {
        $sessionId = floor(microtime(true) * 1000);
        $data = [
            "static_score" => "0.8", "uuid" => "00000000-7fc7-08dc-0000-000000000000",
            "device-id" => "20250220125449b9b8cac84c2dd3d035c9052a2572f7dd0122edde3cc42a70",
            "mac" => "", "sourceuid" => "aa7de295aad621a6", "refresh-type" => "0", "model" => "22021211RC",
            "wlb-imei" => "", "client-id" => "aa7de295aad621a6", "brand" => "Redmi", "oaid" => "",
            "oaid-no-cache" => "", "sys-ver" => "12", "trusted-id" => "", "phone-level" => "H",
            "imei" => "", "wlb-uid" => "aa7de295aad621a6", "session-id" => (string)$sessionId
        ];
        
        $jsonStr = json_encode($data, JSON_UNESCAPED_UNICODE);
        $utf8Encoded = rawurlencode($jsonStr);
        $base64Str = base64_encode(urldecode($utf8Encoded));
        
        $qmParams = '';
        for ($i = 0; $i < strlen($base64Str); $i++) {
            $c = $base64Str[$i];
            $qmParams .= $this->config['charMap'][$c] ?? $c;
        }
        
        $paramsStr = "AUTHORIZATION=app-version=10001application-id=com.duoduo.readchannel=unknownis-white=net-env=5platform=androidqm-params={$qmParams}reg={$this->config['keys']}";
        $sign = $this->md5($paramsStr);
        
        return ['qmParams' => $qmParams, 'sign' => $sign];
    }

    private function getHeaderX() {
        $result = $this->getQmParamsAndSign();
        return [
            'net-env' => '5', 'reg' => '', 'channel' => 'unknown', 'is-white' => '', 'platform' => 'android',
            'application-id' => 'com.duoduo.read', 'authorization' => '', 'app-version' => '10001',
            'user-agent' => 'webviewversion/0', 'qm-params' => $result['qmParams'],
            'sign' => $result['sign']
        ];
    }
    
    // ================== 核心方法实现 ==================
    
    public function homeContent($filter) {
        $classes = [];
        foreach ($this->config['platformList'] as $platform) {
            $classes[] = [
                'type_id' => $platform['id'],
                'type_name' => $platform['name']
            ];
        }
        return ['class' => $classes];
    }
    
    public function homeVideoContent() {
        // 随机选择一个平台获取推荐内容
        $randomIndex = rand(0, count($this->config['platformList']) - 1);
        $randomPlat = $this->config['platformList'][$randomIndex];
        $platId = $randomPlat['id'];
        $defaultFilter = $this->filterDef[$platId] ?? [];
        
        // 调用categoryContent获取推荐内容
        $result = $this->categoryContent($platId, 1, [], $defaultFilter);
        return ['list' => $result['list']];
    }
    
    public function categoryContent($tid, $pg = 1, $filter = [], $extend = []) {
        $plat = $this->getPlatformConfig($tid);
        $videos = [];
        
        // 根据平台获取area值
        if ($tid === '星星') {
            // 星星短剧特殊处理：使用默认分类ID，甜宠
            $area = '1287';
        } else {
            $area = $extend['area'] ?? '';
        }
        
        try {
            switch ($tid) {
                case '百度':
                    $url = $plat['host'] . str_replace(['fyclass', 'fypage'], [$area, $pg], $plat['url1']);
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        foreach ($data['data'] as $item) {
                            $videos[] = [
                                'vod_id' => '百度@' . $item['id'],
                                'vod_name' => $item['title'],
                                'vod_pic' => $item['cover'],
                                'vod_remarks' => '更新至' . $item['totalChapterNum'] . '集'
                            ];
                        }
                    }
                    break;
                    
                case '甜圈':
                    $url = $plat['host'] . $plat['url1'] . '=' . $area . '&offset=' . $pg;
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        foreach ($data['data'] as $item) {
                            $videos[] = [
                                'vod_id' => '甜圈@' . $item['book_id'],
                                'vod_name' => $item['title'],
                                'vod_pic' => $item['cover'],
                                'vod_remarks' => $item['sub_title']
                            ];
                        }
                    }
                    break;
                    
                case '锦鲤':
                    $data = json_encode(['page' => $pg, 'limit' => 24, 'type_id' => $area, 'year' => '', 'keyword' => '']);
                    $response = $this->fetch($plat['host'] . $plat['search'], [
                        CURLOPT_POST => 1,
                        CURLOPT_POSTFIELDS => $data,
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $result = json_decode($response, true);
                    if (isset($result['data']['list'])) {
                        foreach ($result['data']['list'] as $item) {
                            $videos[] = [
                                'vod_id' => '锦鲤@' . $item['vod_id'],
                                'vod_name' => $item['vod_name'] ?? '',
                                'vod_pic' => $item['vod_pic'] ?? '',
                                'vod_remarks' => $item['vod_total'] . '集'
                            ];
                        }
                    }
                    break;
                    
                case '番茄':
                    $sessionId = substr(date('c'), 0, 16);
                    $sessionId = str_replace(['-', 'T', ':'], '', $sessionId);
                    $url = $plat['host'] . $plat['url1'] . '?change_type=0&selected_items=' . $area . '&tab_type=8&cell_id=6952850996422770718&version_tag=video_feed_refactor&device_id=1423244030195267&aid=1967&app_name=novelapp&ssmix=a&session_id=' . $sessionId;
                    if ($pg > 1) {
                        $url .= '&offset=' . (($pg - 1) * 12);
                    }
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data']['cell_view']['cell_data'])) {
                        foreach ($data['data']['cell_view']['cell_data'] as $item) {
                            $videoData = $item['video_data'][0] ?? $item;
                            $videos[] = [
                                'vod_id' => '番茄@' . ($videoData['series_id'] ?? $videoData['book_id'] ?? $videoData['id'] ?? ''),
                                'vod_name' => $videoData['title'] ?? '未知短剧',
                                'vod_pic' => $videoData['cover'] ?? $videoData['horiz_cover'] ?? '',
                                'vod_remarks' => $videoData['sub_title'] ?? $videoData['rec_text'] ?? ''
                            ];
                        }
                    }
                    break;
                    
                case '星芽':
                    $this->initXingyaToken();
                    $headers = $this->config['headers']['default'];
                    $headers['authorization'] = $this->xingyaToken;
                    
                    $url = $plat['host'] . $plat['url1'] . '=' . $area . '&type=1&class2_ids=0&page_num=' . $pg . '&page_size=24';
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($headers)
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data']['list'])) {
                        foreach ($data['data']['list'] as $item) {
                            $theater = $item['theater'];
                            $id = $plat['host'] . $plat['url2'] . '?theater_parent_id=' . $theater['id'];
                            $videos[] = [
                                'vod_id' => '星芽@' . $id,
                                'vod_name' => $theater['title'],
                                'vod_pic' => $theater['cover_url'],
                                'vod_remarks' => $theater['total'] . '集'
                            ];
                        }
                    }
                    break;
                    
                case '西饭':
                    $ts = time();
                    // 移除硬编码session，增加referer兼容
                    $url = $plat['host'] . $plat['url1'] . '?reqType=aggregationPage&offset=' . (($pg - 1) * 30) . '&categoryId=' . $area . '&quickEngineVersion=-1&density=1.5&version=2001001&androidVersionCode=28&requestId=' . $ts . '000&appId=drama';
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => [
                            'User-Agent: okhttp/4.10.0',
                            'Referer: ' . $plat['host'] . '/'
                        ]
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['result']['elements'])) {
                        foreach ($data['result']['elements'] as $soup) {
                            if (isset($soup['contents'])) {
                                foreach ($soup['contents'] as $vod) {
                                    $dj = $vod['duanjuVo'];
                                    $videos[] = [
                                        'vod_id' => '西饭@' . $dj['duanjuId'] . '#' . $dj['source'],
                                        'vod_name' => $dj['title'],
                                        'vod_pic' => $dj['coverImageUrl'],
                                        'vod_remarks' => $dj['total'] . '集'
                                    ];
                                }
                            }
                        }
                    }
                    break;
                    
                case '星星':
                    // 完全按照星星短剧_yjm.php实现
                    $apiUrl = $plat['host'] . '/novel-api/app/pageModel/getResourceById';
                    
                    $params = array_merge($plat['COMMON_PARAMS'], [
                        "resourceId" => $area,
                        "pageNum" => (string)$pg,
                        "pageSize" => "10"
                    ]);

                    $url = $apiUrl . '?' . http_build_query($params);
                    $jsonStr = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.87 Safari/537.36'])
                    ]);
                    
                    $jsonObj = json_decode($jsonStr, true);
                    
                    $list = [];
                    if ($jsonObj && isset($jsonObj['data']['datalist'])) {
                        foreach ($jsonObj['data']['datalist'] as $vod) {
                            $list[] = [
                                // 仿照原 Python：id@@name@@introduction 存储
                                'vod_id' => '星星@' . $vod['id'] . '@@' . $vod['name'] . '@@' . ($vod['introduction'] ?? ''),
                                'vod_name' => $vod['name'],
                                'vod_pic' => $vod['icon'],
                                'vod_remarks' => $vod['heat'] . '万播放'
                            ];
                        }
                    }

                    return $this->pageResult($list, $pg, 999, 10);
                    
                case '河马':
                    // 河马短剧逻辑
                    try {
                        $fenge = explode("@", $area);
                        $cid = $fenge[0];
                        $cname = isset($fenge[1]) ? $fenge[1] : "";
                        $body = json_encode([
                            "recSwitch" => true,
                            "storePageId" => 10002,
                            "channelGroupId" => "10",
                            "channelId" => intval($cid),
                            "channelName" => $cname,
                            "lastColumnStyle" => 3,
                            "fromColumnId" => "1",
                            "pageFlag" => strval($pg),
                            "theaterSubscriptSwitch" => true
                        ], JSON_UNESCAPED_UNICODE);
                        $apiData = $this->fetchApiData("1125", $body, $plat['host']);
                        if ($apiData && isset($apiData['columnData']) && !empty($apiData['columnData'])) {
                            $videoData = $apiData['columnData'][0]['videoData'] ?? [];
                            foreach ($videoData as $vod) {
                                $videos[] = [
                                    "vod_id" => '河马@' . ($vod['bookId'] ?? ''),
                                    "vod_name" => $vod['bookName'] ?? '',
                                    "vod_pic" => $vod['coverWap'] ?? '',
                                    "vod_remarks" => $vod['finishStatusCn'] ?? ''
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        // 忽略异常
                    }
                    break;
                    
                case '围观':
                    $data = json_encode(['audience' => '全部受众', 'page' => $pg, 'pageSize' => 30, 'searchWord' => '', 'subject' => '全部主题']);
                    $response = $this->fetch($plat['host'] . $plat['search'], [
                        CURLOPT_POST => 1,
                        CURLOPT_POSTFIELDS => $data,
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $result = json_decode($response, true);
                    if (isset($result['data'])) {
                        foreach ($result['data'] as $item) {
                            $videos[] = [
                                'vod_id' => '围观@' . $item['oneId'],
                                'vod_name' => $item['title'],
                                'vod_pic' => $item['vertPoster'],
                                'vod_remarks' => '集数:' . $item['episodeCount'] . ' 播放:' . $item['viewCount']
                            ];
                        }
                    }
                    break;
                    
                case '红果':
                    $url = $plat['host'] . str_replace(['fyclass', 'fypage'], [$area, $pg], $plat['url1']);
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        foreach ($data['data'] as $item) {
                            $videos[] = [
                                'vod_id' => '红果@' . $item['book_id'],
                                'vod_name' => $item['title'],
                                'vod_pic' => $item['cover'],
                                'vod_remarks' => $item['total'] ?? 0 . '集'
                            ];
                        }
                    }
                    break;
                    
                case '七猫':
                    $cid = $area ?: 0;
                    $page = $pg ?: 1;
                    
                    if ($page === 1) {
                        $signString = "operation=1playlet_privacy=1tag_id={$cid}{$this->config['keys']}";
                    } else {
                        $signString = "next_id={$page}operation=1playlet_privacy=1tag_id={$cid}{$this->config['keys']}";
                    }
                    $sign = $this->md5($signString);
                    
                    if ($page === 1) {
                        $url = "{$plat['host']}{$plat['url1']}?tag_id={$cid}&playlet_privacy=1&operation=1&sign={$sign}";
                    } else {
                        $url = "{$plat['host']}{$plat['url1']}?tag_id={$cid}&next_id={$page}&playlet_privacy=1&operation=1&sign={$sign}";
                    }
                    
                    $headers = array_merge($this->config['headers']['default'], $this->getHeaderX());
                    $html = $this->fetch($url, [], $headers);
                    if ($html !== false) {
                        $data = json_decode($html, true);
                        $data = is_array($data) ? $data : [];
                        
                        $responseData = isset($data['data']) ? $data['data'] : [];
                        $videoList = isset($responseData['list']) ? $responseData['list'] : [];
                        
                        foreach ($videoList as $vod) {
                            $playletId = isset($vod['playlet_id']) ? $vod['playlet_id'] : '';
                            $title = isset($vod['title']) ? $vod['title'] : '未知标题';
                            $imageLink = isset($vod['image_link']) ? $vod['image_link'] : '';
                            $tags = isset($vod['tags']) ? $vod['tags'] : '';
                            $totalEpisodeNum = isset($vod['total_episode_num']) ? $vod['total_episode_num'] : 0;
                            $hotValue = isset($vod['hot_value']) ? $vod['hot_value'] : '';
                            
                            $videos[] = [
                                'vod_id' => '七猫@' . urlencode($playletId),
                                'vod_name' => $title,
                                'vod_pic' => $imageLink,
                                'vod_remarks' => "{$tags} {$totalEpisodeNum}集 {$hotValue}"
                            ];
                        }
                    }
                    break;
            }
        } catch (Exception $e) {
            // 忽略错误，返回空列表
        }
        
        return $this->pageResult($videos, $pg, 0, 20);
    }
    
    public function detailContent($ids) {
        $id = is_array($ids) ? $ids[0] : $ids;
        $parts = explode('@', $id, 2);
        $platform = $parts[0];
        $itemId = $parts[1] ?? '';
        $plat = $this->getPlatformConfig($platform);
        $vod = [];
        
        try {
            switch ($platform) {
                case '百度':
                    $url = $plat['host'] . str_replace('fyid', $itemId, $plat['url2']);
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        $playUrls = [];
                        foreach ($data['data'] as $item) {
                            $playUrls[] = $item['title'] . '$' . $item['video_id'];
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $data['title'],
                            'vod_pic' => $data['data'][0]['cover'],
                            'vod_remarks' => '更新至' . $data['total'] . '集',
                            'vod_play_from' => '百度短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '甜圈':
                    $url = $plat['host'] . $plat['url2'] . '=' . $itemId;
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        $playUrls = [];
                        foreach ($data['data'] as $item) {
                            $playUrls[] = $item['title'] . '$' . $item['video_id'];
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $data['book_name'],
                            'vod_pic' => $data['book_pic'],
                            'vod_content' => $data['desc'],
                            'vod_remarks' => $data['duration'],
                            'vod_year' => '更新时间:' . $data['time'],
                            'vod_actor' => $data['author'],
                            'vod_play_from' => '甜圈短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '锦鲤':
                    $url = $plat['host'] . $plat['url2'] . '/' . $itemId;
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        $list = $data['data'];
                        $playUrls = [];
                        foreach ($list['player'] as $key => $value) {
                            $playUrls[] = $key . '$' . $value;
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $list['vod_name'] ?? '暂无名称',
                            'vod_pic' => $list['vod_pic'] ?? '暂无图片',
                            'vod_remarks' => $list['vod_remarks'] ?? '暂无备注',
                            'vod_year' => $list['vod_year'] ?? '暂无年份',
                            'vod_area' => $list['vod_area'] ?? '暂无地区',
                            'vod_actor' => $list['vod_actor'] ?? '暂无演员',
                            'vod_director' => $list['vod_director'] ?? '暂无导演',
                            'vod_content' => $list['vod_blurb'] ?? '暂无剧情',
                            'vod_play_from' => '锦鲤短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '番茄':
                    $url = $plat['url2'] . '?book_id=' . $itemId;
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        $bookInfo = $data['data']['book_info'];
                        $playUrls = [];
                        foreach ($data['data']['item_data_list'] as $item) {
                            $playUrls[] = $item['title'] . '$' . $item['item_id'];
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $bookInfo['book_name'],
                            'vod_type' => $bookInfo['tags'],
                            'vod_year' => $bookInfo['create_time'],
                            'vod_pic' => $bookInfo['thumb_url'] ?? $bookInfo['audio_thumb_uri'],
                            'vod_content' => $bookInfo['abstract'] ?? $bookInfo['book_abstract_v2'],
                            'vod_remarks' => $bookInfo['sub_info'] ?? '更新至' . count($data['data']['item_data_list']) . '集',
                            'vod_play_from' => '番茄短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '星芽':
                    $this->initXingyaToken();
                    $headers = $this->config['headers']['default'];
                    $headers['authorization'] = $this->xingyaToken;
                    
                    $response = $this->fetch($itemId, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($headers)
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        $playUrls = [];
                        foreach ($data['data']['theaters'] as $item) {
                            $playUrls[] = $item['num'] . '$' . $item['son_video_url'];
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $data['data']['title'],
                            'vod_pic' => $data['data']['cover_url'],
                            'vod_area' => '收藏' . $data['data']['collect_number'],
                            'vod_actor' => '点赞' . $data['data']['like_num'],
                            'vod_director' => '评分' . $data['data']['score'],
                            'vod_remarks' => implode(',', $data['data']['desc_tags']),
                            'vod_content' => $data['data']['introduction'],
                            'vod_play_from' => '星芽短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '西饭':
                    list($duanjuId, $source) = explode('#', $itemId);
                    $ts = time();
                    // 移除硬编码session，优化详情页URL
                    $url = $plat['host'] . $plat['url2'] . '?duanjuId=' . $duanjuId . '&source=' . $source . '&openFrom=homescreen&version=2001001&requestId=' . $ts . '000&appId=drama';
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => [
                            'User-Agent: okhttp/4.10.0',
                            'Referer: ' . $plat['host'] . '/'
                        ]
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['result'])) {
                        $playUrls = [];
                        foreach ($data['result']['episodeList'] as $ep) {
                            $playUrls[] = $ep['index'] . '$' . $ep['playUrl'];
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $data['result']['title'],
                            'vod_pic' => $data['result']['coverImageUrl'],
                            'vod_content' => $data['result']['desc'] ?? '未知',
                            'vod_remarks' => $data['result']['updateStatus'] === 'over' ? $data['result']['total'] . '集 已完结' : '更新' . $data['result']['total'] . '集',
                            'vod_play_from' => '西饭短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '星星':
                    // 完全按照星星短剧_yjm.php实现
                    $did = $itemId;
                    $parts = explode('@@', $did);
                    if (count($parts) >= 2) {
                        $bookId = $parts[0];
                        $bookName = $parts[1];
                        $intro = $parts[2] ?? '';
                    } else {
                        // 兼容旧格式 id@intro
                        $parts = explode('@', $did);
                        $bookId = $parts[0];
                        $bookName = '';
                        $intro = $parts[1] ?? '';
                    }

                    $apiUrl = $plat['host'] . '/novel-api/basedata/book/getChapterList';
                    $params = array_merge($plat['COMMON_PARAMS'], [
                        "bookId" => $bookId
                    ]);

                    $url = $apiUrl . '?' . http_build_query($params);
                    $jsonStr = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.87 Safari/537.36'])
                    ]);
                    $jsonObj = json_decode($jsonStr, true);

                    $playUrls = [];
                    if ($jsonObj && isset($jsonObj['data'])) {
                        $chapters = $jsonObj['data'];
                        foreach ($chapters as $index => $chapter) {
                            // 提取短剧播放地址
                            if (isset($chapter['shortPlayList'][0]['chapterShortPlayVoList'][0]['shortPlayUrl'])) {
                                $vUrl = $chapter['shortPlayList'][0]['chapterShortPlayVoList'][0]['shortPlayUrl'];
                                $epName = "第" . ($index + 1) . "集";
                                $playUrls[] = $epName . '$' . $vUrl;
                            }
                        }
                    }

                    $vod = [
                        'vod_id' => $id,
                        'vod_name' => $bookName, // 由列表页带入
                        'vod_pic' => '',
                        'vod_content' => $intro,
                        'vod_play_from' => '星星短剧',
                        'vod_play_url' => implode('#', $playUrls)
                    ];
                    break;
                    
                case '河马':
                    $bookId = $itemId;
                    try {
                        $body = json_encode([
                            "bookId" => $bookId,
                            "needNextChapter" => 0,
                            "isNeedAlias" => "",
                            "bookAlias" => "",
                            "resolutionRate" => "720P"
                        ], JSON_UNESCAPED_UNICODE);
                        $apiData = $this->fetchApiData("1131", $body, $plat['host']);
                        if ($apiData && isset($apiData['videoInfo'])) {
                            $videoInfo = $apiData['videoInfo'];
                            $chapterList = $apiData['chapterList'] ?? [];
                            $protagonist = $videoInfo['protagonist'] ?? [];
                            $vodActor = !empty($protagonist) ? implode(', ', $protagonist) : "";
                            $vodDirector = !empty($protagonist) ? $protagonist[0] : "";
                            $bookTags = $videoInfo['bookTags'] ?? [];
                            $bookTagsStr = !empty($bookTags) ? implode(', ', $bookTags) : "";
                            $vodContent = $videoInfo['introduction'] ?? '';
                            
                            $playUrls = [];
                            // 按chapterNum排序
                            usort($chapterList, function($a, $b) {
                                return ($a['chapterNum'] ?? 0) <=> ($b['chapterNum'] ?? 0);
                            });
                            $lastChapterId = !empty($chapterList) ? $chapterList[count($chapterList) - 1]['chapterId'] ?? '' : '';
                            foreach ($chapterList as $chapter) {
                                $chapterId = $chapter['chapterId'] ?? '';
                                $chapterName = $chapter['chapterName'] ?? '';
                                if (!empty($chapterId)) {
                                    $episodeUrl = "{$bookId}@{$chapterId}@{$lastChapterId}";
                                    $playUrls[] = $chapterName . '$' . $episodeUrl;
                                }
                            }
                            
                            $vod = [
                                "vod_id" => $id,
                                "vod_name" => $videoInfo['bookName'] ?? '',
                                "vod_pic" => $videoInfo['coverWap'] ?? '',
                                "vod_remarks" => $videoInfo['finishStatusCn'] ?? "" . " " . $bookTagsStr,
                                "vod_content" => $vodContent,
                                "vod_actor" => $vodActor,
                                "vod_director" => $vodDirector,
                                "vod_year" => $videoInfo['utime'] ?? '',
                                "vod_area" => "中国",
                                "vod_play_from" => "河马短剧",
                                "vod_play_url" => implode('#', $playUrls)
                            ];
                        }
                    } catch (Exception $e) {
                        // 忽略异常
                    }
                    break;
                    
                case '围观':
                    try {
                        $url = $plat['host'] . $plat['url2'] . '?oneId=' . $itemId . '&page=1&pageSize=1000';
                        $response = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                        ]);
                        $data = json_decode($response, true);
                        if (isset($data['data']) && !empty($data['data'])) {
                            $firstEpisode = $data['data'][0];
                            $playUrls = [];
                            foreach ($data['data'] as $episode) {
                                // 确保playSetting是有效的JSON格式
                                $playSetting = $episode['playSetting'];
                                $playUrls[] = $episode['title'] . '第' . $episode['playOrder'] . '集$' . $playSetting;
                            }
                            $vod = [
                                'vod_id' => $id,
                                'vod_name' => $firstEpisode['title'],
                                'vod_pic' => $firstEpisode['vertPoster'],
                                'vod_remarks' => '共' . count($data['data']) . '集',
                                'vod_content' => '播放量:' . $firstEpisode['collectionCount'] . ' 评论:' . $firstEpisode['commentCount'],
                                'vod_play_from' => '围观短剧',
                                'vod_play_url' => implode('#', $playUrls)
                            ];
                        } else {
                            $vod = [
                                'vod_id' => $id,
                                'vod_name' => '围观短剧',
                                'vod_pic' => '',
                                'vod_remarks' => '围观短剧',
                                'vod_content' => '围观短剧内容',
                                'vod_play_from' => '围观短剧',
                                'vod_play_url' => '暂无播放资源#'
                            ];
                        }
                    } catch (Exception $e) {
                        // 异常处理：返回基本信息
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => '围观短剧',
                            'vod_pic' => '',
                            'vod_remarks' => '围观短剧',
                            'vod_content' => '围观短剧内容',
                            'vod_play_from' => '围观短剧',
                            'vod_play_url' => '暂无播放资源#'
                        ];
                    }
                    break;
                    
                case '红果':
                    $url = $plat['host'] . str_replace('fyid', $itemId, $plat['url2']);
                    $response = $this->fetch($url, [
                        CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])
                    ]);
                    $data = json_decode($response, true);
                    if (isset($data['data'])) {
                        $playUrls = [];
                        foreach ($data['data'] as $item) {
                            $playUrls[] = $item['title'] . '$' . $item['video_id'];
                        }
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $data['book_name'] ?? '红果短剧',
                            'vod_pic' => $data['book_pic'] ?? '',
                            'vod_content' => $data['desc'] ?? '',
                            'vod_remarks' => ($data['total'] ?? 0) . '集-' . ($data['duration'] ?? ''),
                            'vod_play_from' => '红果短剧',
                            'vod_play_url' => implode('#', $playUrls)
                        ];
                    }
                    break;
                    
                case '七猫':
                    $playletId = urldecode($itemId);
                    $signString = "playlet_id={$playletId}{$this->config['keys']}";
                    $sign = $this->md5($signString);
                    
                    $detailUrl = "{$plat['host1']}/player/api/v1/playlet/info?playlet_id={$playletId}&sign={$sign}";
                    $headers = array_merge($this->config['headers']['default'], $this->getHeaderX());
                    $html = $this->fetch($detailUrl, [], $headers);
                    if ($html !== false) {
                        $response = json_decode($html, true);
                        $response = is_array($response) ? $response : [];
                        $data = isset($response['data']) ? $response['data'] : [];
                        
                        $playUrl = [];
                        $playList = isset($data['play_list']) ? $data['play_list'] : [];
                        foreach ($playList as $it) {
                            $sort = isset($it['sort']) ? $it['sort'] : '';
                            $videoUrl = isset($it['video_url']) ? $it['video_url'] : '';
                            $playUrl[] = $sort . '$' . $videoUrl;
                        }
                        
                        $playUrlStr = implode('#', $playUrl);
                        
                        $title = isset($data['title']) ? $data['title'] : "未知标题";
                        $imageLink = isset($data['image_link']) ? $data['image_link'] : "未知图片";
                        $tags = isset($data['tags']) ? $data['tags'] : '';
                        $totalEpisodeNum = isset($data['total_episode_num']) ? $data['total_episode_num'] : 0;
                        $intro = isset($data['intro']) ? $data['intro'] : "未知剧情";
                        
                        $vod = [
                            'vod_id' => $id,
                            'vod_name' => $title,
                            'vod_pic' => $imageLink,
                            'vod_actor' => "",
                            'vod_remarks' => "{$tags} {$totalEpisodeNum}集",
                            'vod_content' => $intro,
                            'vod_play_from' => '七猫短剧',
                            'vod_play_url' => $playUrlStr
                        ];
                    }
                    break;
            }
        } catch (Exception $e) {
            // 忽略错误，返回空列表
        }
        
        return ['list' => [$vod]];
    }
    
    public function searchContent($key, $quick = false, $pg = 1) {
        $videos = [];
        $timeout = $this->config['search']['timeout'] / 1000; // 转换为秒
        $limit = $this->config['search']['limit'];
        
        try {
            foreach ($this->config['platformList'] as $platform) {
                $platId = $platform['id'];
                $plat = $this->getPlatformConfig($platId);
                
                switch ($platId) {
                    case '百度':
                        $url = $plat['host'] . str_replace(['**', 'fypage'], [urlencode($key), $pg], $plat['search']);
                        $response = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default']),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $data = json_decode($response, true);
                        if (isset($data['data'])) {
                            foreach ($data['data'] as $item) {
                                $videos[] = [
                                    'vod_id' => '百度@' . $item['id'],
                                    'vod_name' => $item['title'],
                                    'vod_pic' => $item['cover'],
                                    'vod_remarks' => '更新至' . $item['totalChapterNum'] . '集'
                                ];
                            }
                        }
                        break;
                        
                    case '甜圈':
                        $url = $plat['host'] . $plat['search'] . '=' . urlencode($key) . '&offset=' . $pg;
                        $response = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default']),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $data = json_decode($response, true);
                        if (isset($data['data'])) {
                            foreach ($data['data'] as $item) {
                                $videos[] = [
                                    'vod_id' => '甜圈@' . $item['book_id'],
                                    'vod_name' => $item['title'],
                                    'vod_pic' => $item['cover'],
                                    'vod_remarks' => $item['sub_title']
                                ];
                            }
                        }
                        break;
                        
                    case '锦鲤':
                        $data = json_encode(['page' => $pg, 'limit' => $limit, 'type_id' => '', 'year' => '', 'keyword' => $key]);
                        $response = $this->fetch($plat['host'] . $plat['search'], [
                            CURLOPT_POST => 1,
                            CURLOPT_POSTFIELDS => $data,
                            CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default']),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $result = json_decode($response, true);
                        if (isset($result['data']['list'])) {
                            foreach ($result['data']['list'] as $item) {
                                $videos[] = [
                                    'vod_id' => '锦鲤@' . $item['vod_id'],
                                    'vod_name' => $item['vod_name'] ?? '未知短剧',
                                    'vod_pic' => $item['vod_pic'] ?? '',
                                    'vod_remarks' => $item['vod_total'] ?? 0 . '集'
                                ];
                            }
                        }
                        break;
                        
                    case '番茄':
                        $url = $plat['search'] . '?keyword=' . urlencode($key) . '&page=' . $pg;
                        $response = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default']),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $data = json_decode($response, true);
                        if (isset($data['data'])) {
                            foreach ($data['data'] as $item) {
                                $videos[] = [
                                    'vod_id' => '番茄@' . ($item['series_id'] ?? ''),
                                    'vod_name' => $item['title'] ?? '未知标题',
                                    'vod_pic' => $item['cover'] ?? '',
                                    'vod_remarks' => $item['sub_title']
                                ];
                            }
                        }
                        break;
                        
                    case '星芽':
                        $this->initXingyaToken();
                        $headers = $this->config['headers']['default'];
                        $headers['authorization'] = $this->xingyaToken;
                        
                        $data = json_encode(['text' => $key]);
                        $response = $this->fetch($plat['host'] . $plat['search'], [
                            CURLOPT_POST => 1,
                            CURLOPT_POSTFIELDS => $data,
                            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $result = json_decode($response, true);
                        if (isset($result['data']['theater']['search_data'])) {
                            foreach ($result['data']['theater']['search_data'] as $item) {
                                $id = $plat['host'] . $plat['url2'] . '?theater_parent_id=' . $item['id'];
                                $videos[] = [
                                    'vod_id' => '星芽@' . $id,
                                    'vod_name' => $item['title'],
                                    'vod_pic' => $item['cover_url'] ?? '',
                                    'vod_remarks' => $item['total'] ?? 0 . '集'
                                ];
                            }
                        }
                        break;
                        
                    case '西饭':
                        $ts = time();
                        // 西饭搜索页移除过期session，增加referer
                        $url = $plat['host'] . $plat['search'] . '?reqType=search&offset=' . (($pg - 1) * $limit) . '&keyword=' . urlencode($key) . '&quickEngineVersion=-1&density=1.5&version=2001001&androidVersionCode=28&requestId=' . $ts . '000&appId=drama';
                        $response = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => [
                                'User-Agent: okhttp/4.10.0',
                                'Referer: ' . $plat['host'] . '/'
                            ],
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $data = json_decode($response, true);
                        if (isset($data['result']['elements'])) {
                            foreach ($data['result']['elements'] as $vod) {
                                $dj = $vod['duanjuVo'] ?? [];
                                if (!empty($dj['title']) && $dj['title'] !== '未知标题') {
                                    $videos[] = [
                                        'vod_id' => '西饭@' . ($dj['duanjuId'] ?? '') . '#' . ($dj['source'] ?? ''),
                                        'vod_name' => $dj['title'],
                                        'vod_pic' => $dj['coverImageUrl'] ?? '',
                                        'vod_remarks' => $dj['total'] ?? 0 . '集'
                                    ];
                                }
                            }
                        }
                        break;
                        
                    case '星星':
                        // 完全按照星星短剧_yjm.php实现
                        // 原 Python 代码中 searchContentPage 为 pass，故此处留空返回
                        break;
                        
                    case '河马':
                        // 河马短剧搜索逻辑
                        $body = json_encode([
                            "keyword" => $key,
                            "page" => intval($pg),
                            "size" => 15,
                            "searchSource" => "搜索按钮",
                            "hotWordType" => 2,
                            "tagIds" => "",
                            "reservationSwitch" => true
                        ], JSON_UNESCAPED_UNICODE);
                        $apiData = $this->fetchApiData("1803", $body, $plat['host']);
                        if ($apiData && isset($apiData['searchVos'])) {
                            foreach ($apiData['searchVos'] as $vod) {
                                $videos[] = [
                                    "vod_id" => '河马@' . ($vod['bookId'] ?? ''),
                                    "vod_name" => $vod['bookName'] ?? '',
                                    "vod_pic" => $vod['coverWap'] ?? '',
                                    "vod_remarks" => $vod['finishStatusCn'] ?? ''
                                ];
                            }
                        }
                        break;
                        
                    case '围观':
                        try {
                            $data = json_encode(['audience' => '', 'page' => $pg, 'pageSize' => $limit, 'searchWord' => $key, 'subject' => '']);
                            $response = $this->fetch($plat['host'] . $plat['search'], [
                                CURLOPT_POST => 1,
                                CURLOPT_POSTFIELDS => $data,
                                CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default']),
                                CURLOPT_TIMEOUT => $timeout
                            ]);
                            $result = json_decode($response, true);
                            if (isset($result['data'])) {
                                foreach ($result['data'] as $item) {
                                    $videos[] = [
                                        'vod_id' => '围观@' . ($item['oneId'] ?? ''),
                                        'vod_name' => $item['title'] ?? '未知标题',
                                        'vod_pic' => $item['vertPoster'] ?? '',
                                        'vod_remarks' => '集数:' . ($item['episodeCount'] ?? 0) . ' 播放:' . ($item['viewCount'] ?? 0)
                                    ];
                                }
                            }
                        } catch (Exception $e) {
                            // 忽略错误，继续处理其他平台
                        }
                        break;
                        
                    case '红果':
                        $url = $plat['host'] . str_replace(['**', 'fypage'], [urlencode($key), $pg], $plat['search']);
                        $response = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default']),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        $data = json_decode($response, true);
                        if (isset($data['data'])) {
                            foreach ($data['data'] as $item) {
                                $videos[] = [
                                    'vod_id' => '红果@' . ($item['book_id'] ?? ''),
                                    'vod_name' => $item['title'] ?? '未知标题',
                                    'vod_pic' => $item['cover'] ?? '',
                                    'vod_remarks' => $item['total'] ?? 0 . '集'
                                ];
                            }
                        }
                        break;
                        
                    case '七猫':
                        $page = $pg ?: 1;
                        $trackId = 'ec1280db127955061754851657967';
                        
                        $signString = "extend=page={$page}read_preference=0track_id={$trackId}wd={$key}{$this->config['keys']}";
                        $sign = $this->md5($signString);
                        $encodedKey = urlencode($key);
                        $url = "{$plat['host']}{$plat['search']}?extend=&page={$page}&wd={$encodedKey}&read_preference=0&track_id={$trackId}&sign={$sign}";
                        
                        $headers = array_merge($this->config['headers']['default'], $this->getHeaderX());
                        $html = $this->fetch($url, [
                            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                            CURLOPT_TIMEOUT => $timeout
                        ]);
                        if ($html !== false) {
                            $data = json_decode($html, true);
                            $data = is_array($data) ? $data : [];
                            
                            $responseData = isset($data['data']) ? $data['data'] : [];
                            $videoList = isset($responseData['list']) ? $responseData['list'] : [];
                            
                            foreach ($videoList as $vod) {
                                $title = isset($vod['title']) ? $vod['title'] : '';
                                $cleanTitle = preg_replace('/<[^>]+>/', '', $title);
                                $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);
                                $cleanTitle = trim($cleanTitle);
                                
                                if (stripos($cleanTitle, $key) !== false) {
                                    $vodId = isset($vod['id']) ? $vod['id'] : '';
                                    $imageLink = isset($vod['image_link']) ? $vod['image_link'] : '';
                                    $totalNum = isset($vod['total_num']) ? $vod['total_num'] : '';
                                    
                                    $videos[] = [
                                        'vod_id' => '七猫@' . urlencode($vodId),
                                        'vod_name' => $cleanTitle,
                                        'vod_pic' => $imageLink,
                                        'vod_remarks' => "{$totalNum}"
                                    ];
                                }
                            }
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            // 忽略错误，返回已获取的结果
        }
        
        // 去重处理
        $uniqueVideos = [];
        $seenTitles = [];
        foreach ($videos as $video) {
            if (!in_array($video['vod_name'], $seenTitles)) {
                $seenTitles[] = $video['vod_name'];
                $uniqueVideos[] = $video;
            }
        }
        
        return $this->pageResult($uniqueVideos, $pg, 0, $limit);
    }
    
    public function playerContent($flag, $id, $vipFlags = []) {
        $input = $id;
        $cfg = $this->config;
        $plat = $cfg['platform'];
        $ua = 'okhttp/3.12.11';
        
        try {
            if (strpos($flag, '百度') !== false) {
                $url = 'https://api.jkyai.top/API/bddjss.php?video_id=' . $input;
                $response = $this->fetch($url, [
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: ' . $ua,
                        'Content-Type: application/json;charset=utf-8'
                    ]
                ]);
                $data = json_decode($response, true);
                $qualityOrder = ['1080p', 'sc', 'sd'];
                $qualityNames = ['1080p' => '蓝光', 'sc' => '超清', 'sd' => '标清'];
                $urls = [];
                foreach ($qualityOrder as $q) {
                    foreach ($data['data']['qualities'] as $quality) {
                        if ($quality['quality'] === $q) {
                            $urls[] = $qualityNames[$q];
                            $urls[] = $quality['download_url'];
                            break;
                        }
                    }
                }
                return [
                    'parse' => 0,
                    'url' => implode('$', $urls),
                    'header' => ['User-Agent' => $ua]
                ];
            }
            
            if (strpos($flag, '甜圈') !== false) {
                $url = 'https://mov.cenguigui.cn/duanju/api.php?video_id=' . $input . '&type=mp4';
                return [
                    'parse' => 0,
                    'url' => $url,
                    'header' => ['User-Agent' => $ua]
                ];
            }
            
            if (strpos($flag, '锦鲤') !== false) {
                $refererHeaders = ['User-Agent: ' . $ua, 'referer: https://www.jinlidj.com/'];
                $response = $this->fetch($input . '&auto=1', [
                    CURLOPT_HTTPHEADER => $refererHeaders
                ]);
                preg_match('/let data\s*=\s*({[^;]*});/', $response, $matches);
                if (isset($matches[1])) {
                    $data = json_decode($matches[1], true);
                    return [
                        'parse' => 0,
                        'url' => $data['url'],
                        'header' => ['User-Agent' => $ua]
                    ];
                }
            }
            
            // ============ 修复后的番茄解析逻辑 ============
            if (strpos($flag, '番茄') !== false) {
                // 定义更稳健的模拟 UA
                $t_ua = 'Mozilla/5.0 (Linux; Android 14; 23127PN0CC Build/UKQ1.230804.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/123.0.6312.118 Mobile Safari/537.36 novelapp/1.9.6.1';
                
                // 解析节点列表，增加备用方案
                $api_nodes = [
                    "https://fqgo.52dns.cc/video_direct?item_id=",
                    "https://fqgo.52dns.cc/video?item_ids=" // 备选，返回 JSON 需提取
                ];

                foreach ($api_nodes as $node_index => $baseUrl) {
                    $apiUrl = $baseUrl . $input;
                    $response = trim($this->fetch($apiUrl, [
                        CURLOPT_HTTPHEADER => ['User-Agent: ' . $t_ua, 'Referer: https://fqgo.52dns.cc/'],
                        CURLOPT_TIMEOUT => 8,
                        CURLOPT_FOLLOWLOCATION => 1 
                    ]));

                    if (!empty($response)) {
                        // 如果节点0直接返回了视频URL
                        if ($node_index == 0 && strpos($response, 'http') === 0) {
                            return [
                                'parse' => 0,
                                'url' => $response,
                                'header' => ['User-Agent' => $t_ua, 'Referer' => ''] 
                            ];
                        }
                        // 如果节点1返回了 JSON 结构
                        if ($node_index == 1) {
                            $json = json_decode($response, true);
                            if (isset($json['data'][$input]['video_model'])) {
                                $vModel = json_decode($json['data'][$input]['video_model'], true);
                                if (isset($vModel['video_list']['video_1']['main_url'])) {
                                    return [
                                        'parse' => 0,
                                        'url' => base64_decode($vModel['video_list']['video_1']['main_url']),
                                        'header' => ['User-Agent' => $t_ua, 'Referer' => '']
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            // ==========================================
            
            if (strpos($flag, '星星') !== false) {
                // 星星短剧增强URL处理
                $videoUrl = trim($input);
                if (strpos($videoUrl, 'http') !== 0) {
                    $videoUrl = 'https:' . $videoUrl;
                }
                
                return [
                    'parse' => 0,
                    'url' => $videoUrl,
                    'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.87 Safari/537.36']
                ];
            }
            
            if (strpos($flag, '河马') !== false) {
                // 河马短剧播放逻辑
                $parts = explode('@', $id);
                if (count($parts) >= 2) {
                    $bookId = $parts[0];
                    $chapterId = $parts[1];
                    $lastChapterId = count($parts) > 2 ? $parts[2] : $chapterId;
                    $videoUrl = $this->getVideoUrlFromApi($bookId, $chapterId, $lastChapterId);
                    if (!empty($videoUrl)) {
                        return [
                            "parse" => 0,
                            "url" => $videoUrl,
                            "header" => [
                                "User-Agent" => "MOBILE_UA",
                                "Referer" => "https://freevideo.zqqds.cn"
                            ]
                        ];
                    }
                }
            }

            if (strpos($flag, '西饭') !== false) {
                // 修复西饭短剧播放请求头
                return [
                    'parse' => 0,
                    'url' => $input,
                    'header' => [
                        'User-Agent' => 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.91 Mobile Safari/537.36',
                        'Referer' => 'https://xifan-api-cn.youlishipin.com/'
                    ]
                ];
            }
            
            if (strpos($flag, '围观') !== false) {
                try {
                    $playSetting = json_decode($input, true);
                    $videoUrl = $playSetting['high'] ?? $playSetting['normal'] ?? $playSetting['super'] ?? '';
                    if (!empty($videoUrl) && !preg_match('/^https?:\/\//', $videoUrl)) {
                        $videoUrl = 'https://' . $videoUrl;
                    }
                    return [
                        'parse' => 0,
                        'url' => $videoUrl,
                        'header' => ['User-Agent' => $ua]
                    ];
                } catch (Exception $e) {
                    // 容错机制
                }
            }
            
            if (strpos($flag, '红果') !== false) {
                $url = $plat['红果']['host'] . '/api/duanju/api.php?video_id=' . $input;
                $response = $this->fetch($url, [CURLOPT_HTTPHEADER => $this->formatHeaders($this->config['headers']['default'])]);
                $data = json_decode($response, true);
                if (isset($data['data']['url'])) {
                    return ['parse' => 0, 'url' => $data['data']['url']];
                }
            }
            
            if (strpos($flag, '七猫') !== false) {
                return ['parse' => 0, 'url' => $input, 'header' => ['User-Agent' => 'webviewversion/0']];
            }
        } catch (Exception $e) {
            // 忽略错误，返回原始ID
        }
        
        return ['parse' => 0, 'url' => $input];
    }
}

// 运行爬虫
(new Spider())->run();
