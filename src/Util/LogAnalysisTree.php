<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("memory_limit", "10240M");

/**
 * filter url
 *
 * @param $url
 *
 * @return string
 */
function filterUrl($url)
{
    $urlArr = explode("/", $url);

    foreach ($urlArr as $urlIndex => $urlItem) {
        $totalChar = 0;
        $totalNum = 0;
        $totalLen = strlen($urlItem);

        for ($index = 0; $index < $totalLen; $index++) {
            if (is_numeric($urlItem[$index])) {
                $totalNum++;
            } else {
                $totalChar++;
            }
        }

        //md
        if ($totalLen == 32 && $totalChar > 0 && $totalNum > 0) {
            $urlArr[$urlIndex] = "*md*";
            continue;
        }

        //data
        if ($totalLen > 3 && $totalChar > 0 && $totalNum > 0) {
            $urlArr[$urlIndex] = "*data*";
            continue;
        }

        //num
        if ($totalChar == 0 && $totalNum > 0) {
            $urlArr[$urlIndex] = "*num*";
            continue;
        }
    }
    return implode("/", $urlArr);
}


//trace列表排序
function sortTraceByRPCID($a, $b)
{

    $a_arr = explode(".", $a);
    $b_arr = explode(".", $b);

    if (count($a_arr) > count($b_arr)) {
        foreach ($a_arr as $k => $v) {
            if (!isset($b_arr[$k])) {
                $b_arr[$k] = "0";
            }

            if ($v + 0 > $b_arr[$k] + 0) {
                return 1;
            }
            if ($v + 0 == $b_arr[$k] + 0) {
                continue;
            }
            if ($v + 0 < $b_arr[$k] + 0) {
                return -1;
            }
        }
        return 1;
    }

    if (count($a_arr) < count($b_arr)) {
        foreach ($b_arr as $k => $v) {
            if (!isset($a_arr[$k])) {
                $a_arr[$k] = "0";
            }

            if ($v + 0 > $a_arr[$k] + 0) {
                return -1;
            }
            if ($v + 0 == $a_arr[$k] + 0) {
                continue;
            }
            if ($v + 0 < $a_arr[$k] + 0) {
                return 1;
            }
        }
        return -1;

    }

    if (count($a_arr) == count($b_arr)) {
        foreach ($b_arr as $k => $v) {
            if ($v + 0 > $a_arr[$k] + 0) {
                return -1;
            }
            if ($v + 0 == $a_arr[$k] + 0) {
                continue;
            }
            if ($v + 0 < $a_arr[$k] + 0) {
                return 1;
            }
        }
        return 0;
    }
}

if ($argc == 0) {
    echo "error parameter with log path \n";
    exit();
}

if($argc == 1) {
    echo "php thisfile.php [logPath] \n";
    echo "[logPath] must fill \n";

    exit;
}

$filePath = $argv[1];
$filePath = glob($filePath . "/*.log");

$traceMap = [];

$resultMap = [];

// 路径
foreach ($filePath as $pathItem) {
    if (is_dir($pathItem)) {
        continue;
    }

    echo "scan file:" . realpath($pathItem) . "\n";

    $fp = fopen($pathItem, "r");

    while (($line = fgets($fp)) !== FALSE) {
        $line = trim($line);
        $line = json_decode($line, true);

        if ($line["x_name"] == "request.info" ||
            $line["x_name"] == "http.post" ||
            $line["x_name"] == "http.get"
        ) {
            $url = parse_url($line["x_action"]);
            $url["path"] = filterUrl($url["path"]);
            $url = $url["host"] . $url["path"];

            if ($line["x_name"] == "request.info" && ($line["x_rpc_id"] == "1" || $line["x_rpc_id"] == "1.1")) {
                $traceMap[$line["x_trace_id"]]["main"] = $url;
            } else {
                $traceMap[$line["x_trace_id"]][$line["x_rpc_id"]][$url] = 1;
            }

        }

    }
    fclose($fp);
}


foreach ($traceMap as $traceId => $urlMore) {
    if (isset($urlMore["main"])) {
        $mainUrl = $urlMore["main"];

        unset($urlMore["main"]);

        foreach ($urlMore as $rpc => $urlMap) {
            foreach ($urlMap as $url => $val) {
                $resultMap[$mainUrl][$rpc][$url] = 1;
            }
        }
    }
}
unset($traceMap);
ksort($resultMap);

//整理汇总结果
$outputFp = fopen("outputtree.csv", "w");
foreach ($resultMap as $main => $urlMoreInfo) {
    fputcsv($outputFp, [$main]);
    uksort($urlMoreInfo, 'sortTraceByRPCID');

    foreach ($urlMoreInfo as $rpcid => $urlKeyList) {
        $urlMap = ["", $rpcid];

        $count = explode(".", $rpcid);
        $count = count($count);

        for ($k = 0; $k < $count; $k++) {
            $urlMap[2 + $k] = "";
        }

        foreach ($urlKeyList as $u => $val) {
            $urlMap[2 + $k] = $u;
            fputcsv($outputFp, $urlMap);
        }

    }
}
fclose($outputFp);

echo "used memory " . (memory_get_peak_usage() / (1024 * 1024)) . " m \n";