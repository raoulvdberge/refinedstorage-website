<?php

require 'vendor/autoload.php';

use \Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__.'/refinedstorage.sqlite',
    'prefix' => ''
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class Release extends Illuminate\Database\Eloquent\Model {
    public $timestamps = false;
}

$pages=4;

for($i=1;$i<=$pages;++$i)
{

	$url="https://minecraft.curseforge.com/projects/refined-storage/files?page=$i";
	echo $url."<br>";
	$data=file_get_contents($url);
	$parts=explode('<tr class="project-file-list-item">', $data);
	unset($parts[0]);

	foreach($parts as $part)
	{
		$type=explode('<td class="project-file-release-type">
', $part);
		$type=$type[1];
		$type=explode('</td>', $type);
		$type=$type[0];
		$type=explode('title="', $type);
		$type=$type[1];
		$type=explode('">', $type);
		$type=trim(strtolower($type[0]));

		$url=explode('<a class="overflow-tip" href="', $part);
		$url=$url[1];
		$url=explode('">', $url);
		$version=$url[1];
		$url='https://minecraft.curseforge.com'.trim($url[0]);

		$version=explode('</a>', $version);
		$version=trim(str_replace("Refined Storage", "", $version[0]));

		$date=explode('data-epoch="', $part);
		$date=$date[1];
		$date=explode('">', $date);
		$date=trim($date[0]);

		$mc_version=explode('<span class="version-label">', $part);
		$mc_version=$mc_version[1];
		$mc_version=explode('</span>', $mc_version);
		$mc_version=trim($mc_version[0]);

		$cd=file_get_contents($url);
		$cdp=explode('<div class="logbox">', $cd);
		$cl=$cdp[1];
		$cl=explode('</div>', $cl);
		$cl=$cl[0];
		$cl=trim(strip_tags($cl));

		$d=[
			'type'=>$type,
			'url'=>$url,
			'version'=>$version,
			'date'=>$date,
			'mc_version'=>$version,
			'changelog'=>$cl
		];

		echo '<pre>';
		print_r($d);
		echo '</pre>';

		$r = new Release;
		$r->version=$version;
		$r->changelog=$cl;
		$r->url=$url;
		$r->date=$date;
		$r->mc_version=$mc_version;
		$r->user_id=1;
		$r->status=0;
		$r->type=$type;
		$r->save();
	}
}