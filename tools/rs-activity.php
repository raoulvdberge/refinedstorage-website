<?php

die('nope');

class ModpackIndex
{
	public $packs;
	public $date;
}

class Modpack
{
	public $name;
	public $description;
	public $author;
	public $url;
	public $dls;
	public $updated;
	public $created;
	public $icon;
	public $page;
}

function parse($pages): ModpackIndex
{
	$modpacks = [];

	for ($i = 1; $i <= $pages; ++$i) {
		$url = 'https://www.curseforge.com/minecraft/mc-mods/refined-storage/relations/dependents?page=' . $i;
		
		$data = file_get_contents($url);
		
		$listings = explode('<li class="project-listing-row ', $data);
		
		unset($listings[0]);
		
		foreach ($listings as $listing) {
			$parsed = parseListing($listing);
			$parsed->page = $i;
			
			if (!stristr($parsed->url, 'mc-mods')) {
				$modpacks[] = $parsed;
			}
		}
		
		echo "Page $i/$pages done\n";
	}
	
	$mi = new ModpackIndex();
	$mi->packs = $modpacks;
	$mi->date = time();
	
	return $mi;
}

function getBetween($data, string $left, string $right)
{
	if ($data == null) {
		return null;
	}
	
	if (count(explode($left, $data)) < 2) {
		return null;
	}
	
	return trim(explode($right, explode($left, $data)[1])[0]);
}

function parseListing(string $listing): Modpack
{
	$mp = new Modpack;
	
	$mp->icon = getBetween($listing, '<img src="', '"');
	$mp->name = getBetween($listing, '<h3 class="text-primary-500 font-bold text-lg hover:no-underline">', '</h3>');
	$mp->author = getBetween($listing, 'class="font-bold hover:no-underline">', '</a>');
	$mp->description = getBetween($listing, '<p class="text-sm leading-snug">', '</p>');
	$mp->dls = str_replace(' Downloads', '', getBetween($listing, '<span class="mr-2 text-xs text-gray-500">', '</span>'));
	
	if (stristr($mp->dls, 'K')) {
		$mp->dls = str_replace('K', '', $mp->dls) * 1000;
	}
	if (stristr($mp->dls, 'M')) {
		$mp->dls = str_replace('M', '', $mp->dls) * 1000000;
	}
	
	$mp->updated = getBetween(getBetween($listing, '<span class="mr-2 text-xs text-gray-500">Updated <abbr class="tip standard-date standard-datetime"', '</span>'), 'data-epoch="', '"');
	$mp->created = getBetween(getBetween($listing, '<span class="text-xs text-gray-500">Created <abbr class="tip standard-date standard-datetime"', '</span>'), 'data-epoch="', '"');
	
	if ($mp->updated == null) {
		$mp->updated = $mp->created;
	}
	
	$mp->url = 'https://www.curseforge.com' . getBetween($listing, '<div class="project-avatar project-avatar-64">
    <a href="', '"');
	
	return $mp;
}

file_put_contents('rs-activity.json', json_encode(parse(221)));