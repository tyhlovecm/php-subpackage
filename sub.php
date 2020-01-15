<?php
header('Content-type: application/json;charset=utf-8");');
define('DOWNLOAD_DIR', '/www/download/sdkgame/');//包存放目录
define('SUBPACKAGE_DIR', __DIR__.DIRECTORY_SEPARATOR."/subPackage");//需要用到的类
define('PLIST_TMP_PATH', '/tmp/tmp_dir');//info.plist临时产生目录

//接受参数
$params = json_decode(file_get_contents('php://input'), true);
//返回响应
response(subpackage($params));

function makeSign($p, $a){
	return md5(md5($p.$a).'resub');
}

function checkSign($p, $a, $o){
	// echo makeSign($p, $a);die;
	return $o === makeSign($p, $a);
}

function response($content){
	exit(base64_encode($content));
}

//是分包还是生成母包
function isSubpackage($p, $a){
	return $p === $a;
}

function isIos($a){
	if (strpos($a, 'ios') !== false) {
		return true;
	}
	return false;
}

function isAndroid($a){
	if (strpos($a, 'and') !== false) {
		return true;
	}
	return false;
}

//-6 拒绝访问 -5 游戏原包不存在 -4 验证错误 -3 请求数据为空 -2 分包失败 -1 无法创建文件,打包失败 
function Subpackage($params){
	$p = base64_decode($params['p']);
	$a = base64_decode($params['a']);
	$o = base64_decode($params['o']);
	$c = base64_decode($params['c']);

	//检查参数
	if (empty($p) || empty($a)) {
		return -3;
	}

	//检测签名
	if (!checkSign($p, $a, $o)) {
		return -4;
	}

	$pinyin = isset($p) ? $p :'';
	$agentgame = isset($a) ? $a :'';
	$pinyinarr = explode('/', $pinyin);

	//是否ios
	$isIos = isIos($a);

	$sourfile = $isIos?DOWNLOAD_DIR.$pinyin.DIRECTORY_SEPARATOR.$pinyinarr[0].".ipa":DOWNLOAD_DIR.$pinyin.DIRECTORY_SEPARATOR.$pinyinarr[0].".apk";
	if (!file_exists($sourfile)) {
		if (!file_exists(DOWNLOAD_DIR . $pinyin)) {
			mkdir(DOWNLOAD_DIR . $pinyin, 0777, true);//注意要设置文件权限
		}
		if ($pinyinarr[0] == $agentgame) {
			return 1;
		}
		return -5;//游戏原包不存在
	}

	$filename= $isIos?$agentgame.".ipa":$agentgame.".apk";
	$newfile = DOWNLOAD_DIR.$pinyin."/".$filename;
	if(file_exists($newfile)){
		if ($pinyinarr[0] == $agentgame) {
			$data = $isIos?getIpainfo($sourfile):getApkinfo($sourfile);
			return json_encode($data);
		}
		if ($c) {
			return 2;//已分包
		}
		del_file($newfile);
		subpackage($p, $a, $c);
	}
	if (!copy($sourfile, $newfile)) {
		return -1;//无法创建文件,打包失败
	}

	return $isIos ? packIos($newfile, $agentgame):packAndroid($newfile, $agentgame);
}

//渠道信息写入
function packAndroid($newfile, $agentgame){
	$var = explode("_", $agentgame);
	$huomark = "p99" . "g" . $var['1'] . "a" . $var['2'];
	$channelname = "META-INF/gamechannel";
	$huosdk = "META-INF/huosdk_" . $huomark;

	$zip = new ZipArchive();
	$return = -2;
	if ($zip->open($newfile) === TRUE) {
		$zip->addFromString($channelname, json_encode(['agentgame' => $agentgame]));
		$zip->addFromString($huosdk, json_encode(['agentgame' => $huomark]));
		$zip->close();
		$return = 1;
	}
	return $return;
}

//渠道信息写入
function packIos($newfile, $agentgame){
	$var = explode("_", $agentgame);
	$huomark = "p91" . "g" . $var['1'] . "a" . $var['2'];

	$zip = zip_open($newfile);

	$i=1;
	if ($zip) {
		while ($zip_entry = zip_read($zip)) {
			$channelname=zip_entry_name($zip_entry);
			if (zip_entry_open($zip, $zip_entry, "r")) {
				$i++;
				$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				zip_entry_close($zip_entry);
			}
			if($i==3){
				break;
			}
		}
		zip_close($zip);
	}
	$zip = new ZipArchive();
	$return = -2;
	if ($zip->open($newfile) === TRUE) {
		$zip->addEmptyDir($channelname."_gameChannel/");
		$zip->addEmptyDir($channelname."_gameChannel/gameChannel_".$agentgame.'/');//渠道信息写入目录
		$zip->close();
		$return = 1;
	}
	return $return;
}

//获取包信息
function del_file($files)
{
	//如果是文件,  判断是2分钟以前的文件进行删除
	$file=$files;
	$files = fopen($files, "r");
	$f = fstat($files);
	fclose($files);
	// if ($f['mtime'] < (time() - 60 * 2)) {
	if (@unlink($file)) {
		@unlink($file);
	} else {
		@unlink($file);
	}
	// }
}

//获取apk包版本信息
function getApkinfo($file){
	require SUBPACKAGE_DIR."/ApkParser.php";
	$appObj = new Apkparser(); 
	$appObj->open($file);
	$gameinfo['appname'] = $appObj->getAppName();
	$gameinfo['pakagename'] = $appObj->getPackage();
	$gameinfo['vername'] = $appObj->getVersionName();
	$gameinfo['verid'] = $appObj->getVersionCode();
	$gameinfo['size'] = filesize($file);
	return $gameinfo;
}

//获取ipa包信息
function getIpainfo($targetFile){
	require SUBPACKAGE_DIR."/vendor/autoload.php";
	//临时目录存放info.plist
	$storage_path = PLIST_TMP_PATH;

	// 遍历zip包中的Info.plist文件
	$zipper = new \Chumper\Zipper\Zipper;
	$zipFiles = $zipper->make($targetFile)->listFiles('/Info\.plist$/i');

	$gameinfo = [];
	if ($zipFiles) {
	    foreach ($zipFiles as $k => $filePath) {
	        // 正则匹配包根目录中的Info.plist文件
	        if (preg_match("/Payload\/([^\/]*)\/Info\.plist$/i", $filePath, $matches)) {
	            $app_folder = $matches[1];

	            // 将plist文件解压到ipa目录中的对应包名目录中
	            $zipper->make($targetFile)->folder('Payload/'.$app_folder)->extractMatchingRegex($storage_path, "/Info\.plist$/i");

	            // 拼接plist文件完整路
	            $fp = $storage_path.'/Info.plist';
	            
	            // 获取plist文件内容
	            $content = file_get_contents($fp);

	            // 解析plist成数组
	            $ipa = new \CFPropertyList\CFPropertyList();
	            $ipa->parse($content);
	            $ipaInfo = $ipa->toArray();

	            $gameinfo['appname'] = $ipaInfo['CFBundleName'];
	            $gameinfo['pakagename'] = $ipaInfo['CFBundleIdentifier'];
	            $gameinfo['vername'] = $ipaInfo['CFBundleShortVersionString'];
	            $gameinfo['verid'] = str_replace('.', '', $ipaInfo['CFBundleShortVersionString']);
	            $gameinfo['size'] = filesize($targetFile);
	            break;
	        }
	    }
	}
	return $gameinfo;
}