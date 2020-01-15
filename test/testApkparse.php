<?php
// 识别APK信息
require "../vendor/autoload.php";

$apk = new \ApkParser\Parser($this->targetFile);
$manifest = $apk->getManifest();

// 包名
$package_name = $manifest->getPackageName();

// 版本号
$version_name = $manifest->getVersionName();

// 版本编号
$version_code = $manifest->getVersionCode();

// 其他方法参考官方文档
var_dump($package_name, $version_name, $version_code);