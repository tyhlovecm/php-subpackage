<?php
// 识别APK信息
require __DIR__."/../vendor/autoload.php";

$targetFile = __DIR__.'/../app-package/testms2and_6033_1077_1078.apk';
$apk = new \ApkParser\Parser($targetFile);
$manifest = $apk->getManifest();

// 包名
$package_name = $manifest->getPackageName();

// 版本号
$version_name = $manifest->getVersionName();

// 版本编号
$version_code = $manifest->getVersionCode();

$size = filesize($targetFile);

// 其他方法参考官方文档
var_dump($package_name, $version_name, $version_code,$size);