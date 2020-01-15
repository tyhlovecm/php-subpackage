<?php
// 识别APK信息
require __DIR__."/../vendor/autoload.php";

$targetFile = __DIR__.'/../app-package/mscqios_6026_1074.ipa';

//临时目录存放info.plist
$storage_path = __DIR__.'/../tmp-dir';

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
print_r($gameinfo);