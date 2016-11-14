<?php

require 'vendor/autoload.php';

use \Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__.'/../refinedstorage.sqlite',
    'prefix' => ''
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class Wiki extends Illuminate\Database\Eloquent\Model {
    protected $table = 'wiki';
    public $timestamps = false;
}

class WikiRevision extends Illuminate\Database\Eloquent\Model {
    public $timestamps = false;
}

$files=scandir('old_wiki/');
unset($files[0],$files[1]);

foreach($files as $file)
{
	$body=file_get_contents('old_wiki/'.$file);

	$body=preg_replace_callback("/\[.+?\]\(.+?\)/", function($matches) {
		$n=explode('](',$matches[0]);
		$n=explode('[', $n[0]);
		return "[[".$n[1]."]]";
	}, $body);

	if ($file=='Home.md')
	{
		$file='_home.md';
	}
	
	$url=strtolower(str_replace('.md', '', $file));
	$name=str_replace('-', ' ', str_replace('.md', '', $file));

	$w=new Wiki;
	$w->url=$url;
	$w->name=$name;
	$w->status=0;
	$w->save();

	$rev=new WikiRevision;
	$rev->wiki_id = $w->id;
    $rev->body = $body;
    $rev->user_id = 1;
    $rev->reverted_by = 0;
    $rev->reverted_from = 0;
    $rev->date = time();
    $rev->hash = md5(microtime());
    $rev->save();
}