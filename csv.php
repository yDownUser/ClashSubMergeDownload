<?php
require_once('core/core.php');

$sendUrl = getenv('URL');
$secret = getenv('KEY');


if (!isset($mode)) {
    $mode = '';
}

$filePath = DATA_PATH . DIRECTORY_SEPARATOR;
if (!empty($mode)) {
    $filePath .=  $mode . '_';
}

$filename = $filePath . 'Acache.csv'; // CSV文件名

$datafile = DATA_PATH . DIRECTORY_SEPARATOR . 'Acache.json'; // CSV文件名

$proxiesData = file_get_contents($datafile);
$proxiesList = json_decode($proxiesData, true);
$proxiesDB = array();


$i = 0;
// 打开CSV文件进行读取
if (($handle = fopen($filename, 'r')) !== false) {
    $isFirstRow = true; // 标记是否为第一行
    // 逐行读取CSV文件中的数据
    while (($data = fgetcsv($handle)) !== false) {
        if ($isFirstRow) {
            $isFirstRow = false;
            continue; // 跳过第一行
        }

        // $data是一个数组，包含当前行的各个字段值
        $field1 = trim($data[0]); // 第一个字段 节点
        $field2 = $data[1]; // 第二个字段 带宽 (MB/s)
        if ($mode == 'delay') {
            // 在这里进行你想要执行的操作，比如输出字段值
            if ($field2 != 0) {
                if (isset($proxiesList[$field1])) {
                    $i++;
                    $proxiesList[$field1]['name'] = $i . "_" . $field2 . "ms";
                    $proxiesDB[] = $proxiesList[$field1];
                }
            }
        } else {
            $field3 = $data[2]; // 第三个字段 延迟 (ms)
            $field4 = $data[3]; // 第三个字段 落地ip
            $field5 = $data[4]; // 第三个字段 "落地地区"
            //print_r($field1 . ' ' . $field2 . ' ' . $field3 . ' ' . $field4 . ' ' . $field5);
            //echo "\n";
            // 在这里进行你想要执行的操作，比如输出字段值
            if ($field2 != 0 && $field3 != 0) {
                if (isset($proxiesList[$field1])) {
                    $i++;
                    $field5 = str_replace(array('"', "中国  "), array('', ""), $field5);
                    $area = explode("  ", $field5);
                    $name = $i . "_" . str_replace('"', "", $field4);
                    $name .= "_" . $area[0];
                    $name .= "_" . formatBandwidth($field2);

                    $proxiesList[$field1]['name'] = $name;
                    $proxiesDB[] = $proxiesList[$field1];
                }
            }
        }
    }
    // 关闭文件句柄
    fclose($handle);

    $json = json_encode($proxiesDB);
    $yaml = yaml_emit(array('proxies' => $proxiesDB), YAML_UTF8_ENCODING);

    file_put_contents($filePath . 'Adata.json', $json);
    file_put_contents($filePath . 'Adata.yaml', $yaml);

    $response = curl_post($sendUrl, array('isAppend' => 0, 'pwd' => $secret, 'json' => json_encode($proxiesDB)), 15, true);

    echo $response;
    echo "\n";
    echo "全部搞定关闭";
    echo "\n";
} else {
    // 文件打开失败处理
    echo "无法打开CSV文件";
}

echo "\n";
echo '本次 上传任务 执行完毕';
echo "\n";
