<?php

require '../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/../refinedstorage.sqlite',
    'prefix' => ''
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class Release extends Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;
}

foreach (Release::all() as $release) {
    if (stristr($release->file_url, 'minecraft.curseforge.com')) {
        $parts = explode('files/', $release->file_url);

        $release->file_url = 'https://www.curseforge.com/minecraft/mc-mods/refined-storage/files/' . $parts[1];
        $release->download_url = 'https://www.curseforge.com/minecraft/mc-mods/refined-storage/download/' . $parts[1];

        $release->save();
    }
}