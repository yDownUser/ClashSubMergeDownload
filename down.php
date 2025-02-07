<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core.php');


/**
 * 错误检查
 *
 * @param string $response 远程或本地文件的内容
 * @return bool 如果内容有错误则返回true，否则返回false
 */
function down_error($response)
{
    if (
        empty($response) || $response == 'No nodes were found!' || strlen($response) < 300 || strpos($response, 'Attention Required! | Cloudflare') !== false
        || strpos($response, 'cf-wrapper') !== false || strpos($response, '301 Moved Permanently') !== false || strpos($response, '404 Not Found') !== false
    ) {
        return true;
    }
    return false;
}

function file_set($url)
{
    $link = parse_url($url);

    if (in_array($link['host'], ['raw.githubusercontent.com', 'github.com'])) {
        $filename = str_replace(array($link['host'], $link['scheme'], "://"), array("github", "", ""), $url);
    } else {
        $filename = $link['host'];
    }

    return  $filename;
}
function file_save($filename, $remoteContent)
{
    // 将访问的网址作为文件名（进行简单处理，移除非法字符并添加文件扩展名）
    $localFile = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename) . '.txt';

    $localFilePath =  DOWN_PATH . DIRECTORY_SEPARATOR . $localFile;

    $localContent = getLocalContent($localFilePath);

    $remoteHash = md5($remoteContent);
    $localHash = md5($localContent);

    if ($remoteHash !== $localHash) {
        if (updateLocalFile($localFilePath, $remoteContent)) {
            return  "本地文件 $localFilePath 已更新。\n";
        }
        return  "无法更新本地文件 $localFilePath 。\n";
    }

    return  "内容未更新" . "\n";
}

/**
 * 处理单个 URL
 *
 * @param string $url 要处理的URL
 */
function processUrl($url)
{
    $remoteContent = getRemoteContent($url);

    $re = $url . "  ： ";
    if (down_error($remoteContent)) {
        dataWrite($url . "   TEXT Error : " . $remoteContent, LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_downerror.txt');
        return $re . "文件下载失败！" . "\n";
    }

    return $re . file_save(file_set($url), $remoteContent);
}

/**
 * 处理规则型 URL
 *
 * @param string $url 要处理的URL
 */
function ruleUrl($url_array, $num = 0)
{
    $data = "";
    if ($url_array['rule'] == 1) {
        $data = $num == 0 ? date('Ymd') : date('Ymd', strtotime("-$num day"));
    } elseif ($url_array['rule'] == 2) {
        $data = $num == 0 ? date('Y') . "/" . date('m') . "/" . date('Ymd') : date('Y', strtotime("-$num day")) . "/" . date('m', strtotime("-$num day")) . "/" . date('Ymd', strtotime("-$num day"));
    } else {
        dataWrite(print_r($url_array), LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_urlerror.txt');
        return $url_array['url'] . " : 规则不存在！" . "\n";
    }
    $url = $url_array['url'] . $data . '.' . $url_array['suffix'];

    $remoteContent = getRemoteContent($url);

    $re = "\n  " . $url . "  - ";
    if (down_error($remoteContent)) {
        dataWrite($url . "   TEXT Error : " . $remoteContent, LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_downerror.txt');
        if ($num > 3) {
            return $re . "文件下载失败！" . "\n";
        } else {
            return $re . "文件下载失败！" . ruleUrl($url_array, $num + 1);
        }
    }
    if (empty($url_array['remarks'])) {
        return $re .  file_save(file_set($url), $remoteContent);
    }
    return $re .  file_save($url_array['remarks'], $remoteContent);
}

$directoryPath = CACHE_DIR;
if (!ensureDirectoryExists($directoryPath)) {
    exit("无法创建目录 $directoryPath 。\n");
}

$directoryPath = DOWN_PATH;
if (!ensureDirectoryExists($directoryPath)) {
    exit("无法创建目录 $directoryPath 。\n");
}

if (!ensureDirectoryExists(LOG_PATH)) {
    exit("无法创建目录 " . LOG_PATH . " 。\n");
}
// 配置项
$sites  = array(
    "https://raw.githubusercontent.com/aiboboxx/clashfree/main/clash.yml",
    "https://raw.githubusercontent.com/Pawdroid/Free-servers/main/sub",
    "https://raw.githubusercontent.com/ermaozi/get_subscribe/main/subscribe/clash.yml",
    "https://raw.githubusercontent.com/peasoft/NoMoreWalls/master/list.yml",
    "https://raw.githubusercontent.com/chengaopan/AutoMergePublicNodes/master/list.txt",
    "https://raw.githubusercontent.com/mfuu/v2ray/master/clash.yaml",
    "https://raw.githubusercontent.com/vxiaov/free_proxies/main/clash/clash.provider.yaml",
    "https://raw.githubusercontent.com/free18/v2ray/main/c.yaml",
    "https://raw.githubusercontent.com/snakem982/proxypool/main/source/clash-meta.yaml",
    "https://raw.githubusercontent.com/ripaojiedian/freenode/main/clash",
    "https://raw.githubusercontent.com/a2470982985/getNode/main/clash.yaml",
    "https://raw.githubusercontent.com/zhangkaiitugithub/passcro/main/meta.yaml",
    "https://raw.githubusercontent.com/snakem982/proxypool/main/source/clash-meta-2.yaml",


    //不更新了
    //"https://raw.githubusercontent.com/Jsnzkpg/Jsnzkpg/Jsnzkpg/Jsnzkpg",
    //"https://raw.githubusercontent.com/ssrsub/ssr/master/Clash.yml"

    array(
        'url' => 'https://v2rayshare.githubrowcontent.com/',
        'rule' => 2,
        'suffix' => 'yaml',
        'remarks' => 'v2rayshare.com',
    ),
    array(
        'url' => 'https://clashgithub.com/wp-content/uploads/rss/',
        'rule' => 1,
        'suffix' => 'yml',
    ),
);


foreach ($sites as $url) {
    if (is_array($url)) {
        echo $url["url"] . " : " . ruleUrl($url);
    } else {
        echo processUrl($url);
    }
}
