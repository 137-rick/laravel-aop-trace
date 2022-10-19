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

if ($argc == 0) {
    echo "error parameter with log path \n";
    exit();
}

$filePath = $argv[1];
$filePath = glob($filePath . "/*.log");
$apiList = [];

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
            $url = $url["host"] . "" . $url["path"];

            if ($line["x_name"] == "request.info" && $line["x_rpc_id"] == "1") {
                $traceMap[$line["x_trace_id"]]["main"] = $url;
            } else {

                if (!isset($traceMap[$line["x_trace_id"]][$url])) {
                    $traceMap[$line["x_trace_id"]][$url] = 0;
                }
                $traceMap[$line["x_trace_id"]][$url]++;
            }

            if (!isset($apiList[$url])) {
                $apiList[$url] = 0;
            }
            $apiList[$url]++;
        }

    }
    fclose($fp);
}

foreach ($traceMap as $traceId => $urlMore) {
    if (isset($urlMore["main"])) {
        $mainUrl = $urlMore["main"];

        unset($urlMore["main"]);
        $resultMap[$mainUrl] = array_merge($resultMap[$mainUrl] ?? [], $urlMore);
    }
}
unset($traceMap);
ksort($resultMap);

$outputFp = fopen("output.csv", "w");
foreach ($resultMap as $main => $urlMoreInfo) {
    fputcsv($outputFp, [$main]);
    foreach ($urlMoreInfo as $url => $count) {
        fputcsv($outputFp, ["", $url]);
    }
}
fclose($outputFp);

var_dump(memory_get_peak_usage() / (1024 * 1024));