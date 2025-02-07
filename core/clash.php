<?php


$SS_Cipher = array(
    "aes-128-ctr",
    "aes-192-ctr",
    "aes-256-ctr",
    "aes-128-cfb",
    "aes-192-cfb",
    "aes-256-cfb",
    "aes-128-gcm",
    "aes-192-gcm",
    "aes-256-gcm",
    "aes-128-ccm",
    "aes-192-ccm",
    "aes-256-ccm",
    "aes-128-gcm-siv",
    "aes-256-gcm-siv",
    "chacha20-ietf",
    "chacha20",
    "xchacha20",
    "chacha20-ietf-poly1305",
    "xchacha20-ietf-poly1305",
    "chacha8-ietf-poly1305",
    "xchacha8-ietf-poly1305",
    "2022-blake3-aes-128-gcm",
    "2022-blake3-aes-256-gcm",
    "2022-blake3-chacha20-poly1305",
    "lea-128-gcm",
    "lea-192-gcm",
    "lea-256-gcm",
    "rabbit128-poly1305",
    "aegis-128l",
    "aegis-256",
    "aez-384",
    "deoxys-ii-256-128",
    "rc4-md5",
    "none"
);

//url替换为base
function linkToBase64($link)
{
    // "=" => ""
    return str_replace(array('-', '_',), array('+', '/'), $link);
}

// 过滤emoji表情的函数
function filterEmoji($str)
{
    return $str;
    $str = preg_replace_callback(
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str
    );
    return $str;
}


function textDecode64($text, $search‌)
{
    if (is_string($text) && $text !== '') {
        if (stripos($text, $search‌) === false) {
            $decoded_data = base64_decode(linkToBase64($text));
            if ($decoded_data !== false) {
                return $decoded_data;
            }
        }
    }
    return $text;
}

function isPort($port)
{
    //!ctype_digit($port) ||
    if ($port < 1 || $port > 65535) {
        return false;
    }
    return true;
}

function errClash($data, $text)
{
    echo $data . "  " . $text;
    echo "\n";
    dataWrite($data . "  " . $text, LOG_PATH . DIRECTORY_SEPARATOR . date('Y-m-d') . '_clasherror.txt');
    return array();
}

function linkClash($link, $text, $num)
{
    if (empty($link) || substr($link, 0, $num) !== $text) {
        return "";
    }
    return substr($link, $num);
}

function vmessToClash($link)
{
    // 去除 vmess:// 前缀
    $link_data = linkClash($link, 'vmess://', 8);
    if (empty($link_data)) {
        return errClash($link, "无效的链接格式link");
    }

    $vmess_data =  textDecode64($link_data, '{');
    $params = json_decode($vmess_data, true);

    if ($params === null || json_last_error() != JSON_ERROR_NONE) {
        // 提取参数
        $vmess_array = explode('?', $link_data);
        if (empty($vmess_array[1])) {
            return errClash($link, "解析链接提取参数错误?");
        }
        $params = array();
        parse_str($vmess_array[1], $params);

        $vmess_array[0] = textDecode64($vmess_array[0], '@');
        $parts = explode('@', $vmess_array[0]);
        if (count($parts) < 2) {
            return errClash($link, "解析链接提取参数错误@");
        }

        // 解码SS链接
        $parts[0] =  textDecode64($parts[0], ':');
        $parts[1] =  textDecode64($parts[1], ':');

        list($params["type"], $params["id"]) = explode(':', $parts[0], 2);
        list($params["add"], $params["port"]) = explode(':', $parts[1], 2);
        $params["ps"] = $params["remarks"];
    }


    if (isPort($params["port"]) == false) {
        return errClash($link, "解析端口出错port");
    }
    // 构建 Clash 规则
    $clash_config = [
        "name" => filterEmoji($params["ps"]),
        "port" => $params["port"],
        "server" => $params["add"],
        "type" => "vmess",
        "cipher" => isset($params["type"]) ? $params["type"] : "auto",
        "uuid" => $params["id"],
        "alterId" => isset($params["aid"]) ? intval($params["aid"]) : 0,

        // "network" => isset($params["net"]) ? $params["net"] : "tcp",
    ];


    $clash_config["cipher"] = $clash_config["cipher"] == 'none' ? "auto" : $clash_config["cipher"];

    if (isset($params["tls"])) {
        $clash_config["tls"] =  $params["tls"] == 'tls' ? true : false;
        $clash_config["skip-cert-verify"] = false; //$clash_config["tls"];
    }

    if (isset($params["net"])) {

        $clash_config["network"] = $params["net"];
        if ($clash_config["network"] == 'ws') {
            $clash_config["ws-opts"] = array(
                "path" => isset($params["path"]) ? $params["path"] : "/",
                "headers" => array(
                    "Host" => isset($params["host"]) ? $params["host"] : ""
                )
                //max-early-data: //Early Data 首包长度阈值
                //early-data-header-name:
                //v2ray-http-upgrade: false //使用 http upgrade
                //v2ray-http-upgrade-fast-open: false //启用 http upgrade 的 fast open
            );
            // if (count($params) > 11) {
            //     dataWrite($vmess_link, LOG_DATA);
            //     dataWrite(yaml_emit($clash_config), LOG_DATA);
            // }
        } elseif ($clash_config["network"] == 'grpc') {
            if (isset($params["sni"])) {
                $clash_config["grpc-opts"] = array(
                    "grpc-service-name" => $params["sni"],
                );
            }
        } elseif ($clash_config["network"] == 'http') {

            /*
http-opts:
    method: "GET"
    path:
    - '/'
    - '/video'
    headers:
      Connection:
      - keep-alive
            */
        } elseif ($clash_config["network"] != 'tcp') {
            // dataWrite($vmess_link, LOG_DATA);
            // dataWrite(yaml_emit($clash_config), LOG_DATA);

            return errClash($link, "有net");
        }
    }
    // 返回 Clash 规则
    return $clash_config;
}

function trojanToClash($trojan_link)
{
    // 解码 Trojan 链接
    $trojan_data = $trojan_link; //linkToBase64($trojan_link);
    $trojan_decoded = substr($trojan_data, 9); // base64_decode(substr($trojan_data, 9));

    // 提取参数
    $parts = explode('@', $trojan_decoded);
    $password = $parts[0];
    $server_port = $parts[1];
    $server_parts = explode(':', $server_port);
    $server = $server_parts[0];
    $query = parse_url($server_parts[1]);
    $port = intval($query["path"]); //intval($server_parts[1]);
    $name = filterEmoji(urldecode($query["fragment"]));
    parse_str($query['query'], $params);
    // 构建 Clash 配置
    $clash_config = [
        "name" => $name,
        'server' => $server,
        'port' => $port,
        "type" => "trojan",
        'password' => $password,
    ];

    if (isset($params["type"])) {
        $clash_config["network"] = $params["type"];
        if ($clash_config["network"] == 'ws') {
            $clash_config["ws-opts"] = array(
                "path" => isset($params["path"]) ? $params["path"] : "/",
                "headers" => array(
                    "host" => isset($params["host"]) ? $params["host"] : ""
                )
            );
        } else if ($clash_config["network"] == 'grpc') {
            $clash_config["grpc-opts"] = array(
                "grpc-service-name" => isset($params["serviceName"]) ? $params["serviceName"] : ""
            );
        }
    }

    if (!empty($params["sni"])) {
        $clash_config["sni"] = $params["sni"];
    }

    if (isset($params["allowInsecure"])) {
        $clash_config["skip-cert-verify"] = empty($params["allowInsecure"]) ? true : false;
    }

    if (count($params) > 2) { //$clash_config["network"] != 'tcp' && 
        dataWrite($trojan_link, LOG_DATA);
        dataWrite(yaml_emit($clash_config), LOG_DATA);
    }

    return $clash_config;
}
function ssToClash($link)
{
    global $SS_Cipher;

    // 去除 ss:// 前缀
    $link_data = linkClash($link, "ss://", 5);
    if (empty($link_data)) {
        return errClash($link, "无效的链接格式link");
    }
    $ss_data = $link_data;
    $ss_para = "";

    if (stripos($ss_data, '#') !== false) {
        $position = mb_stripos($ss_data, "#");
        $ss_para = mb_substr($ss_data, $position + 1);
        $ss_data = mb_substr($ss_data, 0, $position);
    }

    $ss_data =  textDecode64($ss_data, '@');

    if (stripos($ss_data, '#') !== false) {
        $position = mb_stripos($ss_data, "#");
        $ss_para = mb_substr($ss_data, $position + 1);
        $ss_data = mb_substr($ss_data, 0, $position);
    }

    // 解析链接中的各个参数
    $parts = explode('@', $ss_data);

    if (count($parts) < 2) {
        return errClash($link, "解析链接格式错误@");
    }

    // 解码SS链接
    $parts[0] =  str_replace("ss://", "", $parts[0]);
    $parts[0] =  textDecode64($parts[0], ':');
    $parts[0] =  str_replace("ss://", "", $parts[0]);
    $parts[0] =  textDecode64($parts[0], ':');
    $parts[1] =  textDecode64($parts[1], ':');

    list($method, $password) = explode(':', $parts[0], 2);
    list($server, $port) = explode(':', $parts[1], 2);

    if (!in_array($method, $SS_Cipher)) {
        return errClash($link, "解析加密出错method");
    }
    if (isPort($port) == false) {
        return errClash($link, "解析端口出错port");
    }

    $params = rawurldecode($ss_para);
    if (stripos($params, '/?') !== false) {
        return errClash($link, "解析插件出错params");
    }
    $name = trim(filterEmoji($params));

    if (empty($name)) {
        // 使用更安全的随机数生成函数
        $name = "ss_" . random_int(10001, 99999);
    }

    $clash_config = [
        "name" => $name,
        "port" => $port,
        "server" => $server,
        "type" => "ss",
        "password" => $password,
        "cipher" => $method,
        # udp: true
    ];

    return $clash_config;
}

/* 
    

    list($param1, $param2) = explode('#', $params);
    $contract = explode('/?', $param1);
    $name = filterEmoji(urldecode($param2));
    $port = intval($contract[0]);

    $param3 = isset($contract[1]) ? $contract[1] : '';

   

    if (stripos($param3, 'plugin=obfs') !== false) {
        $clash_config['plugin'] = "obfs";
        $clash_config['plugin-opts'] = [
            //"mode" => 'http', # tls
            //"host" => 'bing.com', 
        ];
    } else if (stripos($param3, 'plugin=v2ray') !== false) {
        $clash_config['plugin'] = "v2ray-plugin";
        $clash_config['plugin-opts'] = [
            "mode" => 'websocket', # no QUIC now
            //"tls" => true, # wss
            "skip-cert-verify" => true,
            //"host" => ' bing.com',
            "path" =>  "/",
            "mux" => true,
            //"headers" => [
            //    'custom' => 'value'
            // ],
        ];
    }

    if ($param3 != '') {
        dataWrite($ss_link, LOG_DATA);
        dataWrite(yaml_emit($clash_config), LOG_DATA);
    }
     */
function ssrToClash($link)
{
    global $SS_Cipher;

    // 去除 ssr:// 前缀
    $link_data = linkClash($link, 'ssr://', 6);
    if (empty($link_data)) {
        return errClash($link, "无效的链接格式link");
    }
    // 解码 SSR 链接
    $ssr_data =  textDecode64($link_data, '/?');

    // 提取参数
    if (stripos($ssr_data, '/?') !== false) {
        $ssr_array = explode('/?', $ssr_data);
    } elseif (stripos($ssr_data, '/>') !== false) {
        $ssr_array = explode('/>', $ssr_data);
    } elseif (stripos($link_data, 'remarks=') !== false) {
        $ssr_data =  $link_data;
        $position = mb_stripos($ssr_data, 'remarks=');

        $ssr_array = array(
            mb_substr($ssr_data, 0, $position),
            mb_substr($ssr_data, $position),
        );
    } else {
        return errClash($link, "解析链接格式错误/?");
    }

    if (empty($ssr_array[1])) {
        return errClash($link, "解析链接提取参数错误/?");
    }
    $params = array();
    parse_str($ssr_array[1], $params);

    $ssrDB = explode(':', $ssr_array[0], 6);
    if (count($ssrDB) == 5) {
        list($server, $port, $protocol, $method, $base) = $ssrDB;
        $obfs_array = array("plain", "http_simple", "http_post", "random_head", "tls1.2_ticket_auth", "tls1.2_ticket_fastauth");
        foreach ($obfs_array as $value) {
            if (stripos($base, $value) !== false) {
                $obfs = $value;
                $password_base64 = str_replace($value, "", $base);
                break;
            }
        }
        if (empty($obfs)) {
            return errClash($link, "解析链接提取参数错误obfs");
        }
    } else {
        list($server, $port, $protocol, $method, $obfs, $password_base64) = $ssrDB;
    }


    if (!in_array($method, $SS_Cipher)) {
        return errClash($link, "解析加密出错method");
    }
    if (isPort($port) == false) {
        return errClash($link, "解析端口出错port");
    }

    $password = base64_decode($password_base64);

    $name = filterEmoji(base64_decode($params["remarks"]));
    // 构建 Clash 配置
    $clash_config = [
        "name" => $name,
        'server' => $server,
        'port' => $port,
        'type' => "ssr",
        'password' => $password,
        'cipher' => $method,
        'protocol' => $protocol,
        'obfs' => $obfs,
        //'udp' => true
    ];
    if (!empty($params["obfsparam"])) {
        $clash_config["obfsparam"] = $params["obfsparam"];
    }
    if (!empty($params["protoparam"])) {
        $clash_config["protoparam"] = $params["protoparam"];
    }

    return $clash_config;
}



/*
SS与SSR
// https://wiki.metacubex.one/config/proxies/ss/

base64 编码解码   + 和 / 分别替换为 – 和 _
method 加密协议
password 密码
server 服务器地址
port 端口
ss://method:password@server:port

obfsparam 混淆参数
protoparam 协议参数
remarks 备注
group 组织
params_base64=/?obfsparam=obfsparam&protoparam=protoparam&remarks=remarks&group=group
password_base64=password
protocol 协议
obfs 混淆
password_base64 密码 base64加解码
params_base64 参数值 base64加解码
ssr://server:port:protocol:method:obfs:password_base64/?params_base64



Cipher
Shadowsocks 加密
    AES
        aes-128-ctr	aes-192-ctr	aes-256-ctr
        aes-128-cfb	aes-192-cfb	aes-256-cfb
        aes-128-gcm	aes-192-gcm	aes-256-gcm
        aes-128-ccm	aes-192-ccm	aes-256-ccm
        aes-128-gcm-siv		aes-256-gcm-siv
    CHACHA
        chacha20-ietf	
        chacha20	xchacha20
        chacha20-ietf-poly1305	xchacha20-ietf-poly1305
        chacha8-ietf-poly1305	xchacha8-ietf-poly1305
        2022 Blake3
        2022-blake3-aes-128-gcm
        2022-blake3-aes-256-gcm
        2022-blake3-chacha20-poly1305
    LEA
        lea-128-gcm
        lea-192-gcm
        lea-256-gcm
    其他
        rabbit128-poly1305
        aegis-128l
        aegis-256
        aez-384
        deoxys-ii-256-128
        rc4-md5
        none	


password
Shadowsocks 密码


udp-over-tcp
启用 UDP over TCP，默认 false


udp-over-tcp-version
UDP over TCP 的协议版本，默认 1。可选值 1/2。


插件
plugin
插件，支持 obfs/v2ray-plugin/shadow-tls/restls

plugin-opts
插件设置

plugin: obfs
plugin-opts:
  mode: tls
  host: bing.com

plugin: v2ray-plugin
plugin-opts:
    mode: websocket # no QUIC now
    # tls: true # wss
    # 可使用 openssl x509 -noout -fingerprint -sha256 -inform pem -in yourcert.pem 获取
    # 配置指纹将实现 SSL Pining 效果
    # fingerprint: xxxx
    # skip-cert-verify: true
    # host: bing.com
    # path: "/"
    # mux: true
    # headers:
    #   custom: value
    # v2ray-http-upgrade: false

plugin: shadow-tls
client-fingerprint: chrome
plugin-opts:
    host: "cloud.tencent.com"
    password: "shadow_tls_password"
    version: 2 # support 1/2/3    

plugin: restls
client-fingerprint: chrome  # 可以是chrome, ios, firefox, safari中的一个
plugin-opts:
    host: "www.microsoft.com" # 应当是一个TLS 1.3 服务器
    password: [YOUR_RESTLS_PASSWORD]
    version-hint: "tls13"
    # Control your post-handshake traffic through restls-script
    # Hide proxy behaviors like "tls in tls".
    # see https://github.com/3andne/restls/blob/main/Restls-Script:%20Hide%20Your%20Proxy%20Traffic%20Behavior.md
    # 用restls剧本来控制握手后的行为，隐藏"tls in tls"等特征
    # 详情：https://github.com/3andne/restls/blob/main/Restls-Script:%20%E9%9A%90%E8%97%8F%E4%BD%A0%E7%9A%84%E4%BB%A3%E7%90%86%E8%A1%8C%E4%B8%BA.md
    restls-script: "300?100<1,400~100,350~100,600~100,300~200,300~100"
/* 

proxies:
  - name: "ss1"
    type: ss
    server: server
    port: 443
    cipher: aes-128-gcm
    password: "password"
    udp: true
    udp-over-tcp: false
    udp-over-tcp-version: 2
    ip-version: ipv4
    plugin: obfs
    plugin-opts:
        mode: tls
    smux:
        enabled: false
  - name: "ssr"
    type: ssr
    server: server
    port: 443
    cipher: chacha20-ietf
    password: "password"
    obfs: tls1.2_ticket_auth
    protocol: auth_sha1_v4
    # obfs-param: domain.tld
    # protocol-param: "#"
    # udp: true

  */