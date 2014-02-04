<?php
//vaR_dump(getRoomInfo(file_get_contents('http://v.6.cn/8243')));
//抓6.cn首页房间信息
$contents = file_get_contents('http://www.6.cn');
preg_match_all('/"rid":"([\d]*?)","pic":"([^ ]*?)".*?"isRecommend":(\d*?),.*?"count":"(\d*?)",/', $contents, $arrMatch);
$arrRid = $arrMatch[1];
$arrPic = $arrMatch[2];
$arrIsRecommend = $arrMatch[3];
$arrCount = $arrMatch[4];
foreach ($arrRid as $strKey => $strRid) {
    $arrRooms[$strRid] = array();
    $arrRooms[$strRid]['pic'] = str_replace("\/", "/", $arrPic[$strKey]);
    $arrRooms[$strRid]['is_recommend'] = $arrIsRecommend[$strKey];
    $arrRooms[$strRid]['count'] = $arrCount[$strKey];
    $arrUrls[] = "http://v.6.cn/$strRid";
}

$arrReturn = multi_curl($arrUrls, 'getRoomInfo');
$arrReturn = array_filter($arrReturn);

foreach ($arrReturn as $strKey => $arrItem) {
    $arrRooms[$arrItem['room_id']] = array_merge($arrRooms[$arrItem['room_id']], $arrItem);
}

file_put_contents('rooms', var_export($arrRooms, true));

file_put_contents('rooms_ser', serialize($arrRooms));



function multi_curl($arrUrls, $callback)
{
    $intChunk = 20;
    $arrUrls = array_chunk($arrUrls, $intChunk);
    $arrReturn = array();
    foreach ($arrUrls as $arrItem) {
        $arrReturn = array_merge($arrReturn, classic_curl($arrItem, $callback));
    }
    /*
    //retry
    $intRetry = 2;
    do {
        $arrRetry = array();
        foreach ($arrReturn as $strKey => $arrItem) {
            if (false === $arrItem) {
                $arrRetry[] = $strKey;
            }
        }
        $arrRetry = array_chunk($arrRetry, $intChunk);
        foreach ($arrRetry as $arrItem) {
            $arrReturn = array_merge($arrReturn, classic_curl($arrItem, $callback));
        }
    } while(--$intRetry);
    */
    return $arrReturn;
}

function classic_curl($urls, $callback) {
    $queue = curl_multi_init();
    $map = array();

    foreach ($urls as $url) {
        // create cURL resources
        $ch = curl_init();

        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);

        // add handle
        curl_multi_add_handle($queue, $ch);
        $map[$url] = $ch;
    }

    $active = null;

    // execute the handles
    do {
        $mrc = curl_multi_exec($queue, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active > 0 && $mrc == CURLM_OK) {
        if ($a = curl_multi_select($queue, 5) != -1) {
            do {
                $mrc = curl_multi_exec($queue, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    $responses = array();
    foreach ($map as $url=>$ch) {
        $contents = curl_multi_getcontent($ch);
        $responses[$url] = false;
        if (empty($contents)) {
            user_error("$url get contents fail");
        } else {
            $responses[$url] = call_user_func($callback, $contents);
        }
        curl_multi_remove_handle($queue, $ch);
        curl_close($ch);
    }

    curl_multi_close($queue);
    return $responses;
}


function getRoomInfo($contents)
{
    if (!$contents) {
        user_error('content is empty', E_USER_ERROR);
        return false;
    }
    //kaibo
    preg_match('/<span class="time" id="live_title_2013">开播(.*)<\/span>/', $contents, $arrMatch);
    $strKaiBo = '';
    if (isset($arrMatch[1])) {
        $strKaiBo = trim($arrMatch[1]);
    }
    if (!$strKaiBo) {
        user_error('room is not opened');
        return false;
    }

    //room id
    preg_match('/roomid: (\d*?),/', $contents, $arrMatch);
    $strRoomid = $arrMatch[1];


    //flv url
    preg_match('/"flvtitle":"([^ "]*)"/', $contents, $arrMatch);
    $strFlvTitle = $arrMatch[1];
    preg_match('/Fplayer: \'([^ \']*)\'/', $contents, $arrMatch);
    $strPlayer = $arrMatch[1];
    if ($strFlvTitle && $strPlayer) {
        $strFlvUrl = "http://v.6.cn$strPlayer?fileName=$strFlvTitle";
    } else {
        user_error('flv url is missing', E_USER_ERROR);
        return false;
    }

    //master name
    preg_match('/masterName:\'([^ \']*)\'/', $contents, $arrMatch);
    $strMasterName = '';
    if (isset($arrMatch[1])) {
        $strMasterName = $arrMatch[1];
    }

    //background
    preg_match('/background-image: url\(([^ ]*)\)/', $contents, $arrMatch);
    $strBackground = '';
    if (isset($arrMatch[1])) {
        $strBackground = $arrMatch[1];
    }

    //local
    preg_match('/<span class="local"><i class="fixpng"><\/i>(.*?)<\/span>/', $contents, $arrMatch);
    $strLocal = '';
    if (isset($arrMatch[1])) {
        $strLocal = trim($arrMatch[1]);
    }

    return array('room_id' =>$strRoomid, 'name' => $strMasterName, 'flv_url' => $strFlvUrl, 'background_url' => $strBackground, 'begin_date' => $strKaiBo, 'local' => $strLocal);
}
?>
