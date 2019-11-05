<?php
$atm3 = 'https://www.curseforge.com/minecraft/modpacks/all-the-mods-3/files/2697844';
$atm3Remix = 'https://www.curseforge.com/minecraft/modpacks/all-the-mods-3-remix/files/2697724';

$dw20 = 'https://www.curseforge.com/minecraft/modpacks/ftb-presents-direwolf20-1-12/files/2690085';

$presets = [
    [
        'All the Mods 3 vs All The Mods 3 Remix',
        $atm3,
        $atm3Remix
    ],
    [
        'All The Mods 3 vs Direwolf20 1.12',
        $atm3,
        $dw20
    ]
];
?>

<!DOCTYPE html>
<html>
<head>
    <title>CurseForge modpack comparer</title>
    <script>
        function updatePreset(e) {
            if (e.value == 'none') {
                document.getElementById('m1').value = '';
                document.getElementById('m2').value = '';
            } else {
                p = e.value.split(',');
                document.getElementById('m1').value = p[0];
                document.getElementById('m2').value = p[1];
            }
        }
    </script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
          integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <script defer src="https://use.fontawesome.com/releases/v5.0.9/js/all.js"
            integrity="sha384-8iPTk2s/jMVj81dnzb/iFR2sdA7u06vHJyyLlAd4snFpCl/SnyUjRrbdJsw1pGIl"
            crossorigin="anonymous"></script>
</head>
<style>
    ul li:hover {
        background: #ccc;
    }

    form, footer {
        margin-bottom: 10px;
    }

    .red {
        color: red;
    }

    .green {
        color: darkgreen;
    }

    .table-names {
        color: #fff;
        text-align: center;
    }

    .table-counts {
        text-align: center;
    }
</style>
</head>
<body>
<div class="container-fluid">
    <header>
        <h1>CurseForge modpack comparer</h1>
    </header>
    <main>
		<div class="alert alert-danger">
            <h3><strong>This tool no longer works :(</strong></h3>
            <p>
                Due to CurseForge actively blocking external requests made by tools like these, this tool no longer works.
            </p>
            <p class="mb-0">
                We are currently looking for a solution to this problem.
            </p>
        </div>
	
        <p>Link the <strong>file URL</strong> of the modpack (for example <code>https://www.curseforge.com/minecraft/modpacks/all-the-mods-3/files/2697844</code>).
        </p>
        <form method="post">
            <div class="form-group">
                <label for="m1">Modpack 1</label>
                <input type="text" class="form-control" id="m1" name="m1">
            </div>
            <div class="form-group">
                <label for="m2">Modpack 2</label>
                <input type="text" class="form-control" id="m2" name="m2">
            </div>
            <div class="form-group">
                <label for="preset">... or use a preset</label>
                <select name="preset" id="preset" class="form-control" onchange="updatePreset(this)">
                    <option value="none">none</option>
                    <?php foreach ($presets as $preset): ?>
                        <option value="<?= $preset[1] ?>,<?= $preset[2] ?>"><?= $preset[0] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="submit" class="btn btn-primary" value="Compare">
        </form>

        <?php
        $m1 = null;
        $m2 = null;

        if (isset($_POST['m1']) && isset($_POST['m2'])) {
            $m1 = $_POST['m1'];
            $m2 = $_POST['m2'];
        }

        if (isset($_GET['a']) && isset($_GET['b'])) {
            $m1 = urldecode($_GET['a']);
            $m2 = urldecode($_GET['b']);
        }

        if ($m1 != null && $m2 != null) {
            echo "<p>Share this result with this link: <code>https://refinedstorage.raoulvdberge.com/packcomparer.php?a=" . htmlentities(urlencode($m1)) . "&b=" . htmlentities(urlencode($m2)) . "</code></p>";

            function get_data($url)
            {
                $data = file_get_contents($url);
                if (!$data) {
                    return ['title' => '?', 'mods' => [], 'missingMods' => [], 'hasMods' => []];
                }

                $title = trim(str_ireplace('FTB Presents', '', explode('" />', explode('<meta property="og:title" content="', $data)[1])[0]));
                if ($title == null) {
                    return ['title' => '?', 'mods' => [], 'missingMods' => [], 'hasMods' => []];
                }

                $mods = explode('<div class="w-5/6">', $data);

                unset($mods[0]);

                $processedMods = [];
                $links = [];

                foreach ($mods as $mod) {
                    $link = 'https://www.curseforge.com/' . explode('" class', explode('href="/', $mod)[1])[0];
                    $name = trim(explode('</a>', explode('w-full">', $mod)[1])[0]);

                    $processedMods[] = $name;
                    $links[$name] = $link;
                }

                sort($processedMods);

                return ['title' => $title, 'mods' => $processedMods, 'modLinks' => $links, 'missingMods' => [], 'hasMods' => []];
            }

            function do_diff($a, $b)
            {
                foreach ($a['mods'] as $aMod) {
                    if (!in_array($aMod, $b['mods'])) {
                        $b['missingMods'][] = $aMod;
                    }
                }
                foreach ($b['mods'] as $bMod) {
                    if (!in_array($bMod, $a['mods'])) {
                        $b['hasMods'][] = $bMod;
                    }
                }

                return $b;
            }

            $m1data = get_data($m1);
            $m2data = get_data($m2);

            $m2data = do_diff($m1data, $m2data);

            ?>
            <table width="100%" class="table table-bordered">
                <tr class="table-names">
                    <td width="20%" class="bg-primary">
                        <strong><?= htmlspecialchars($m1data['title']) ?></strong>
                    </td>
                    <td width="20%" class="bg-primary">
                        <strong><?= htmlspecialchars($m2data['title']) ?></strong>
                    </td>
                    <td width="30%" class="bg-danger">
                        Mods <strong><?= htmlspecialchars($m1data['title']) ?></strong> has but
                        <strong><?= htmlspecialchars($m2data['title']) ?></strong> not
                    </td>
                    <td width="30%" class="bg-success">
                        Mods <strong><?= htmlspecialchars($m2data['title']) ?></strong> has but
                        <strong><?= htmlspecialchars($m1data['title']) ?></strong> not
                    </td>
                </tr>
                <tr class="table-counts">
                    <td>
                        <?= count($m1data['mods']) ?> mods
                    </td>
                    <td>
                        <?= count($m2data['mods']) ?> mods
                    </td>
                    <td>
                        <?= count($m2data['missingMods']) ?> mods
                    </td>
                    <td>
                        <?= count($m2data['hasMods']) ?> mods
                    </td>
                </tr>
                <tr>
                    <td valign="top">
                        <ul>
                            <?php foreach ($m1data['mods'] as $mod): ?>
                                <li><a href="<?= $m1data['modLinks'][$mod] ?>"><?= $mod ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td valign="top">
                        <ul>
                            <?php foreach ($m2data['mods'] as $mod): ?>
                                <li><a href="<?= $m2data['modLinks'][$mod] ?>"><?= $mod ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td valign="top">
                        <ul>
                            <?php foreach ($m2data['missingMods'] as $mod): ?>
                                <li>
                                    <a class="red" href="<?= $m1data['modLinks'][$mod] ?>"><?= $mod ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td valign="top">
                        <ul>
                            <?php foreach ($m2data['hasMods'] as $mod): ?>
                                <li>
                                    <a class="green" href="<?= $m2data['modLinks'][$mod] ?>"><?= $mod ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            </table>
            <?php
        }
        ?>
    </main>
    <footer>By <a href="https://twitter.com/vdbergeraoul">raoulvdberge</a></footer>
</div>
</body>
</html>