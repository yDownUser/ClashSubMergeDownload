<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core.php');

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

$filterMode = true;
function base64_decode_file($content, $de = false)
{
    $data = explode("\n", $content);
    $decodeDB = array();
    foreach ($data as $rs) {
        if ($de) {
            $rs = base64_decode(linkToBase64($rs));
        }
        $proxie = array();
        if (strpos($rs, "vmess://") !== false) {
            $proxie = vmessToClash($rs);
        } elseif (strpos($rs, "vless://") !== false) {
        } elseif (strpos($rs, "ssr://") !== false) {
            $proxie = ssrToClash($rs);
        } elseif (strpos($rs, "ss://") !== false) {
            $proxie = ssToClash($rs);
        }
        if (!empty($proxie)) {
            $decodeDB[] = $proxie;
        }
    }
    return $decodeDB;
}
// 处理内容并解析 YAML
function parseYamlContent($content, $file)
{
    try {
        $yamldata = Yaml::parse($content);
        return $yamldata;
    } catch (ParseException $exception) {
        throw new ParseException($file . "  YAML 解析失败: " . $exception->getMessage());
    }
    return false;
}


function analysisFile($file)
{
    try {
        // 读取文件内容
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("$file 文件读取失败");
        }

        if (isBase64($content)) {
            $content = base64_decode($content);
            $decodeDB = base64_decode_file($content);
            if (empty($decodeDB)) {
                throw new RuntimeException("$file 文件解不出proxies");
            }
            return $decodeDB;
        }

        $yamlDB = parseYamlContent(str_replace(array('<pre style="word-wrap: break-word; white-space: pre-wrap;">', '</pre>', '!<str> ', '!&lt;str&gt; '), "", $content), $file);
        if (is_array($yamlDB)) {
            if (empty($yamlDB['proxies'])) {
                throw new RuntimeException("$file 文件不存在proxies");
            }
            return $yamlDB['proxies'];
        } else {
            $decodeDB = base64_decode_file($content);
            if (empty($decodeDB)) {
                $decodeDB = base64_decode_file(base64_decode($content));
                if (empty($decodeDB)) {
                    $decodeDB = base64_decode_file($content);
                    if (empty($decodeDB)) {
                        throw new RuntimeException("$file yaml也不是base也解不出proxies");
                    }
                }
            }
            return $decodeDB;
        }
    } catch (Exception $e) {
        dataWrite($file . '  读取 Error: ' . $e->getMessage(), LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_imperror.txt');
        echo $e->getMessage() . "\n";
    }
    return array();
}



$directoryPath = CACHE_DIR;
if (!ensureDirectoryExists($directoryPath)) {
    exit("无法创建目录 $directoryPath 。\n");
}

$directoryPath = DOWN_PATH;
if (!ensureDirectoryExists($directoryPath)) {
    exit("无法创建目录 $directoryPath 。\n");
}

$dataPath = DATA_PATH;
if (!ensureDirectoryExists($dataPath)) {
    exit("无法创建目录 $dataPath 。\n");
}

$downFiles = getFilesInDirectory(DOWN_PATH);

$proxiesDB = array();
foreach ($downFiles as $file) {
    $proDB = analysisFile($file);
    if (!empty($proDB)) {
        $proxiesDB = array_merge($proxiesDB, $proDB);
    }
}

$proxiesDel = array();
$total = 0;
$num = 0;

foreach ($proxiesDB as $key => $rss) {
    $total++;
    unset($rss["name"]);
    if ($filterMode) {
        $proxiesDel[$key] = serialize(array("type" => $rss["type"], "server" => $rss["server"], "port" => $rss["port"])); // 序列化以确保唯一性
    } else {
        $proxiesDel[$key] = serialize($rss); // 序列化以确保唯一性
    }
}

// 使用 array_unique 函数去重复节点
//$uniqueNodes = array_unique($proxiesDel, SORT_REGULAR);
// 使用 array_unique 和 array_map 去重复节点
$uniqueNodes = array_map('unserialize', array_unique($proxiesDel));


$proxiesJSON = array();
$proxiesYAML = array();
foreach ($uniqueNodes as $key => $rts) {
    if (in_array($rts["server"], array("127.0.0.1"))) {
        continue;
    }
    $num++;
    $name = $rts["type"] . '_' . $cur_data . '_' . $num;
    if ($filterMode) {
        $proxiesDB[$key]["name"] = $name;
        $proxiesYAML[] = $proxiesDB[$key];
        $proxiesJSON[$name] = $proxiesDB[$key];
    } else {
        $rts["name"] = $name;
        $proxiesYAML[] = $rts;
        $proxiesJSON[$name] = $rts;
    }
}

$json = json_encode($proxiesJSON);
$yaml = Yaml::dump(array('proxies' => $proxiesYAML), 4, YAML_UTF8_ENCODING);

file_put_contents(sprintf("%s%s%s.json", DATA_PATH, DIRECTORY_SEPARATOR, "Acache"), $json);
file_put_contents(sprintf("%s%s%s.yaml", DATA_PATH, DIRECTORY_SEPARATOR, "Acache"), $yaml);
echo  "\n";
echo "合计 $num 个,去重 " . $total - $num . " 个,总计 $total 个" . "\n";
