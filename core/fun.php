<?php

/**
 * 确保目录存在
 *
 * @param string $directoryPath 目录的路径
 */
function ensureDirectoryExists($directoryPath, $permission = 0755)
{
    // 检查目录是否存在
    if (!is_dir($directoryPath)) {
        // 尝试创建目录
        if (!mkdir($directoryPath, $permission, true)) {
            return false;
        }
    }
    return true;
}

/**
 * 获取本地文件内容
 *
 * @param string $filePath 本地文件的路径
 * @return string 本地文件的内容，如果失败则返回空字符串
 */
function getLocalContent($filePath)
{
    if (is_readable($filePath)) {
        return file_get_contents($filePath);
    }
    return '';
}

/**
 * 更新本地文件内容
 *
 * @param string $filePath 本地文件的路径
 * @param string $newContent 要写入的新内容
 */
function updateLocalFile($filePath, $newContent)
{
    if (file_put_contents($filePath, $newContent) !== false) {
        return true;
    }
    return false;
}

// 读取目录下的所有文件
function getFilesInDirectory($directory)
{
    $files = glob($directory . DIRECTORY_SEPARATOR . '*');
    $files = array_filter($files, 'is_file'); // 过滤出文件，排除目录
    return $files;
}

//写入文件
function dataWrite($str, $file)
{
    $fp = fopen($file, 'a'); //opens file in append mode  
    fwrite($fp, $str . "\n");
    fclose($fp);
}

function formatBandwidth($v)
{
    if ($v <= 0) {
        return "";
    }
    if ($v < 1024) {
        return sprintf("%.02fB/s", $v);
    }
    $v /= 1024;
    if ($v < 1024) {
        return sprintf("%.02fKB/s", $v);
    }
    $v /= 1024;
    if ($v < 1024) {
        return sprintf("%.02fMB/s", $v);
    }
    $v /= 1024;
    if ($v < 1024) {
        return sprintf("%.02fGB/s", $v);
    }
    $v /= 1024;
    return sprintf("%.02fTB/s", $v);
}

/**
 * 获取远程网页内容
 *
 * @param string $url 远程网页的URL
 * @return string 远程网页的内容，如果失败则返回空字符串
 */
function getRemoteContent($url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    // 禁用SSL验证
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    // 设置连接超时时间（秒）
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

    // 设置总超时时间（秒）
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);

    $content = curl_exec($curl);
    if ($content === false) {
        $error = curl_error($curl);
        dataWrite($url . "   CURL Error (#" . $error . "): " . curl_error($curl), LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_downerror.txt');
        curl_close($curl);
        return '';
    }

    curl_close($curl);
    return $content;
}

function curl_down($url, $proxy = 0, $timeout = 15)
{
    // 代理服务器的地址和端口
    $proxyAddress = '127.0.0.1';
    $proxyPort = 7890;
    // 初始化 cURL
    $curl = curl_init($url);


    if ($proxy == 0) {
        // 设置代理地址和端口
        curl_setopt($curl, CURLOPT_PROXY, $proxyAddress);
        curl_setopt($curl, CURLOPT_PROXYPORT, $proxyPort);
    } else if ($proxy == 2) {
        $proxyPort = 17890;
        // 设置代理地址和端口
        curl_setopt($curl, CURLOPT_PROXY, $proxyAddress);
        curl_setopt($curl, CURLOPT_PROXYPORT, $proxyPort);
    }


    // 设置选项
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // 如果服务器证书无效，可忽略 SSL 验证（不推荐在生产环境中使用）
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout); // 设置超时时间秒

    // 执行请求并获取响应
    $response = curl_exec($curl);
    // 检查是否出现错误
    if ($response === false) {
        $error = curl_error($curl);
        dataWrite($url . '  curl_down Error: ' . $error, LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_curlerror.txt');
        //exit;
    }

    // 关闭 cURL 资源
    curl_close($curl);
    return $response;
}

function curl_post($url, $data = array(), $timeout = 15, $none = false)
{
    // 创建一个 cURL 资源
    $curl = curl_init();
    // 设置请求的 URL
    curl_setopt($curl, CURLOPT_URL, $url);
    // 设置为 POST 请求
    curl_setopt($curl, CURLOPT_POST, true);
    // 设置 POST 数据
    if (!empty($data)) {
        if (is_array($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
    }
    // 设置选项
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // 如果服务器证书无效，可忽略 SSL 验证（不推荐在生产环境中使用）
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout); // 设置超时时间秒

    // 执行请求并获取响应
    $response = curl_exec($curl);


    // 检查是否出现错误
    if ($response === false) {
        $error = curl_error($curl);
        $errText = $url;
        if ($none) {
            $errText = "SEND ";
        }
        dataWrite($errText . '  curl_post Error: ' . $error, LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_curlerror.txt');
        //exit;
    }

    // 关闭 cURL 资源
    curl_close($curl);
    if ($none) {
        return '';
    }
    return $response;
}
function isBase64($string)
{
    $decoded = base64_decode($string, true);
    return $decoded !== false && base64_encode($decoded) === $string;
}

function dataWrite2($str, $file)
{
    // 验证文件路径是否安全
    if (!preg_match('/^[a-zA-Z0-9\/\._-]+$/', $file)) {
        throw new InvalidArgumentException("Invalid file path: $file");
    }

    // 确保文件路径为绝对路径
    $file = realpath($file);

    try {
        // 使用 file_put_contents 简化文件写入操作
        if (false === file_put_contents($file, $str . "\n", FILE_APPEND | LOCK_EX)) {
            throw new RuntimeException("Failed to write to file: $file");
        }
    } catch (Exception $e) {
        // 记录错误日志或采取其他措施
        error_log("Error writing to file: " . $e->getMessage());
        throw $e;
    }
}
