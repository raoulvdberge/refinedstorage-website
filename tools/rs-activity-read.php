<?php

die('nope');

$index = json_decode(file_get_contents('rs-activity.json'));

$modpacks = $index->packs;

function timeago($timestamp) {
	   $strTime = array("second", "minute", "hour", "day", "month", "year");
	   $length = array("60","60","24","30","12","10");

	   $currentTime = time();
	   if($currentTime >= $timestamp) {
			$diff     = time()- $timestamp;
			for($i = 0; $diff >= $length[$i] && $i < count($length)-1; $i++) {
			$diff = $diff / $length[$i];
			}

			$diff = round($diff);
			return $diff . " " . $strTime[$i] . "(s) ago ";
	   }
	}

function cmpUpdated($a, $b) {
    if ($a->updated == $b->updated) {
        return 0;
    }
    return ($b->updated < $a->updated) ? -1 : 1;
}
function cmpDls($a, $b) {
    if ($a->dls == $b->dls) {
        return 0;
    }
    return ($b->dls < $a->dls) ? -1 : 1;
}

$sort = 'updated';
if (isset($_GET['sort'])) {
	$sort = $_GET['sort'];
}

$method = 'cmpUpdated';
if ($sort === 'updated') {
	$method = 'cmpUpdated';
} else if ($sort === 'dls') {
	$method = 'cmpDls';
}

uasort($modpacks, $method);

$perPage = 25;
$page = 1;
$totalPages = ceil(count($modpacks)/$perPage);

if (isset($_GET['p']) && ctype_digit($_GET['p'])) {
	$page = $_GET['p'];
}

if ($page < 1 || $page > $totalPages) {
	$page = 1;
}

$modpacks = array_slice($modpacks, ($page-1)*$perPage, $perPage);

function pagination($currentPage, $totalPages, $sort)
{
	?>
<nav>
  <ul class="pagination justify-content-center">
    <li class="page-item <?=$currentPage==1?'disabled':''?>"><a class="page-link" href="?p=<?=$currentPage-1?>&sort=<?=$sort?>">Previous</a></li>
    <li class="page-item <?=$currentPage==$totalPages?'disabled':''?>"><a class="page-link" href="?p=<?=$currentPage+1?>&sort=<?=$sort?>">Next</a></li>
  </ul>
</nav>
	<?php
}

?>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<script defer src="https://use.fontawesome.com/releases/v5.0.9/js/all.js"
            integrity="sha384-8iPTk2s/jMVj81dnzb/iFR2sdA7u06vHJyyLlAd4snFpCl/SnyUjRrbdJsw1pGIl"
            crossorigin="anonymous"></script>

<h1>Refined Storage modpack universe</h1>

<div class="text-center">
	<p>This data was indexed at <strong><?=date('Y-m-d h:i:s', $index->date)?></strong></p>
	<p>
		Sort by <?=$sort == 'updated' ? '<i>last updated</i>' : '<a href="?p='.$page.'&sort=updated">last updated</a>'?> | <?=$sort == 'dls' ? '<i>downloads</i>' : '<a href="?p='.$page.'&sort=dls">downloads</a>'?>
	</p>
	<p>Page <?= $page ?> out of <?= $totalPages ?></p>
	<?php pagination($page, $totalPages, $sort); ?>
</div>

<style>
.head {
	font-weight: bold;
}
td {
	padding: 10px;
}
body {
	padding: 20px;
}
</style>

<table class="table table-bordered table-striped">
<tr class="head">
<td>Icon</td>
<td>Name</td>
<td>Author</td>
<td>Updated</td>
<td>Created</td>
</tr>
<?php foreach($modpacks as $mp): ?>
	<tr>
		<td class="text-center"><img src="<?=$mp->icon?>"></td>
		<td>
			<p><a href="<?=$mp->url?>"><?=$mp->name?></a></p>
			
			<p><?=$mp->description?></p>
			
			<p>
				<small class="text-muted"><i class="fas fa-clock"></i> Updated <?=timeago($mp->updated)?></small>
			</p>
			
			<p class="mb-0">
				<small class="text-muted"><i class="fas fa-download"></i> <?=explode('X', number_format($mp->dls,2,'X', ','))[0]?> downloads</small>
			</p>
		</td>
		<td><?=$mp->author?></td>
		<td><?=date('Y-m-d h:i:s', $mp->updated)?></td>
		<td><?=date('Y-m-d h:i:s', $mp->created)?></td>
	</tr>
<?php endforeach; ?>
</table>

<div class="text-center">
	<p>Page <?= $page ?> out of <?= $totalPages ?></p>
	<?php pagination($page, $totalPages, $sort); ?>
</div>