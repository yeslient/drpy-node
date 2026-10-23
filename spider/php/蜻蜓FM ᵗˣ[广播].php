<?php
require_once __DIR__ . '/lib/spider.php';

class Spider extends BaseSpider {
    private const HOST = 'http://www.qingting.fm';
    private const PC_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    private function getHeaders() {
        return [
            'User-Agent: ' . self::PC_UA
        ];
    }
    
    public function homeContent($filter) {
        $class_names = '广东&浙江&北京&天津&河北&上海&山西&内蒙古&辽宁&吉林&黑龙江&江苏&安徽&福建&江西&山东&河南&湖北&湖南&广西&海南&重庆&四川&贵州&云南&陕西&甘肃&宁夏&新疆&西藏&青海&资讯&音乐&交通&经济&文艺&都市&体育&双语&综合&生活&旅游&曲艺&方言';
        $class_urls = '217&99&3&5&7&83&19&31&44&59&69&85&111&129&139&151&169&187&202&239&254&257&259&281&291&316&327&351&357&308&342&433&442&429&439&432&441&430&431&440&438&435&436&434';
        
        $class_names_array = explode('&', $class_names);
        $class_urls_array = explode('&', $class_urls);
        
        $classes = [];
        for ($i = 0; $i < count($class_names_array); $i++) {
            $classes[] = [
                'type_id' => $class_urls_array[$i],
                'type_name' => $class_names_array[$i]
            ];
        }
        
        return [
            'class' => $classes,
            'filters' => (object)[],
            'list' => []
        ];
    }
    
    public function categoryContent($tid, $pg = 1, $filter = [], $extend = []) {
        // 严格按照原始JS的URL格式构建：/radiopage/fyclass/fypage
        $url = self::HOST . '/radiopage/' . $tid . '/' . $pg;
        $html = $this->fetch($url, [], $this->getHeaders());
        
        // 使用与原始JS相同的选择器：.contentSec&&.radio;span&&Text;img&&src;.descRadio&&Text;a&&href
        $items = $this->pdfa($html, '.contentSec&&.radio');
        $videos = [];
        
        foreach ($items as $itemHtml) {
            $title = $this->pdfh($itemHtml, 'span&&Text');
            $img = $this->pd($itemHtml, 'img&&src');
            $desc = $this->pdfh($itemHtml, '.descRadio&&Text');
            $href = $this->pd($itemHtml, 'a&&href');
            
            if (!empty($title)) {
                $videos[] = [
                    'vod_id' => $href,
                    'vod_name' => $title,
                    'vod_pic' => $img,
                    'vod_remarks' => $desc
                ];
            }
        }
        
        return $this->pageResult($videos, $pg, 0, 20);
    }
    
    public function detailContent($ids) {
        $id = is_array($ids) ? $ids[0] : $ids;
        $url = $id;
        $html = $this->fetch($url, [], $this->getHeaders());
        
        $title = $this->pdfh($html, '.radioName&&Text') . ' ' . $this->pdfh($html, '.radioDesc&&Text');
        $img = $this->pd($html, '.radioLogo&&img&&src');
        $content = $this->pdfh($html, '.radioDetail&&Text');
        
        $playFrom = ['蜻蜓FM'];
        $playUrls = [];
        $urls = [];
        
        // 提取radio ID，用于生成播放链接
        $radioId = basename($id);
        $urls[] = '直播' . '$' . $id;
        $playUrls[] = implode('#', $urls);
        
        return ['list' => [[
            'vod_id' => $id,
            'vod_name' => $title,
            'vod_pic' => $img,
            'vod_remarks' => '',
            'vod_content' => $content,
            'vod_play_from' => implode('$$$', $playFrom),
            'vod_play_url' => implode('$$$', $playUrls)
        ]]];
    }
    
    public function searchContent($key, $quick = false, $pg = 1) {
        return $this->pageResult([], $pg, 0, 0);
    }
    
    public function playerContent($flag, $id, $vipFlags = []) {
        // 处理URL，确保正确替换主机名和路径
        $url = preg_replace('/www\.qingting\.fm\/radios\//', 'lhttp.qingting.fm/live/', $id);
        $url = rtrim($url, '/') . '/64k.mp3';
        
        return [
            'parse' => 0,
            'url' => $url,
            'header' => $this->getHeaders()
        ];
    }
}

(new Spider())->run();
