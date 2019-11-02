<?php

$roles = [
    'admin' => 300,
    'contributor' => 200,
    'editor' => 100,
    'user' => 0
];

$wikiSidebarTabs = ['guides', 'blocks', 'items'];

date_default_timezone_set('Europe/Brussels');

session_start();

require '../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\NotFoundException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/../refinedstorage.sqlite',
    'prefix' => ''
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class NeedsAuthentication
{
    private $view;
    private $accessLevel;

    public function __construct(\Slim\Views\Twig $view, $accessLevel)
    {
        $this->view = $view;
        $this->accessLevel = $accessLevel;
    }

    public function __invoke($request, $response, $next)
    {
        if (getUser() == null) {
            return $response->withRedirect('/login?next=' . $request->getUri()->getPath());
        }

        if (getUser()->role < $this->accessLevel) {
            return $this->view->render($response->withStatus(403), '403.twig');
        }

        return $next($request, $response);
    }
}

class User extends Illuminate\Database\Eloquent\Model
{
    public function wikiRevisions()
    {
        return $this->hasMany('WikiRevision');
    }

    public function releases()
    {
        return $this->hasMany('Release');
    }
}

function getUser()
{
    if (isset($_SESSION['user'])) {
        return User::find($_SESSION['user']);
    }
    return null;
}

class Release extends Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('User');
    }
}

function getReleases()
{
    $releases = Release::orderBy('date', 'desc');
    if (getUser() == null) {
        $releases = $releases->where('status', '=', 0);
    }
    return $releases;
}

function getLatestStableRelease()
{
    $releases = Release::orderBy('date', 'desc')->where('type', 'release');
    if (getUser() == null) {
        $releases = $releases->where('status', '=', 0);
    }
    // If we release stable 1.2 after stable 1.4, 1.2 would be considered the "newer" stable. Avoid that.
    $stable = null;
    foreach ($releases->get() as $release) {
        if ($stable == null) {
            $stable = $release;
        } else if (version_compare($release->version, $stable->version) == 1) {
            $stable = $release;
        }
    }
    return $stable;
}

function getRelease($id)
{
    $releases = Release::where('id', '=', $id);;
    if (getUser() == null) {
        $releases = $releases->where('status', '=', 0);
    }
    return $releases->first();
}

class Wiki extends Illuminate\Database\Eloquent\Model
{
    protected $table = 'wiki';
    public $timestamps = false;

    public function revisions()
    {
        return $this->hasMany('WikiRevision');
    }

    public function tags()
    {
        return $this->hasMany('WikiTag', 'wiki_id');
    }
}

function getWikiByUrl($url)
{
    $wiki = Wiki::where(['url' => $url]);
    if (getUser() == null) {
        $wiki = $wiki->where('status', '=', 0);
    }
    return $wiki->first();
}

function getWikiByName($name)
{
    $wiki = Wiki::where(['name' => $name]);
    if (getUser() == null) {
        $wiki = $wiki->where('status', '=', 0);
    }
    return $wiki->first();
}

function getWikiRevision($wiki, $revisionHash)
{
    $revision = $wiki->revisions()->orderBy('date', 'desc');
    if ($revisionHash == null) {
        $revision = $revision->first();
    } else {
        $revision = $revision->where('hash', $revisionHash)->first();
    }
    return $revision;
}

class WikiRevision extends Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('User');
    }

    public function wiki()
    {
        return $this->belongsTo('Wiki');
    }

    public function revertedBy()
    {
        return $this->belongsTo('User', 'reverted_by', 'id');
    }

    public function revertedFrom()
    {
        return $this->belongsTo('WikiRevision', 'reverted_from', 'id');
    }
}

class Tag extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;
}

class WikiTag extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    public function wiki()
    {
        return $this->belongsTo('Wiki');
    }

    public function tag()
    {
        return $this->belongsTo('Tag');
    }

    public function release()
    {
        return $this->belongsTo('Release');
    }
}

$app = new \Slim\App;

$container = $app->getContainer();
$container['cache'] = function ($container) {
    return new FilesystemAdapter('', 0, __DIR__ . '/../cache/');
};
$container['view'] = function ($container) use ($roles) {
    $view = new \Slim\Views\Twig('../templates');

    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');

    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));

    $view->getEnvironment()->addExtension(new Twig_Extensions_Extension_Date());

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('uri', function () {
        return $_SERVER['REQUEST_URI'];
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('getUser', function () {
        return getUser();
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('can', function ($role) use ($roles) {
        $user = getUser();

        return $user != null && $user->role >= $roles[$role];
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('role', function ($role) use ($roles) {
        foreach ($roles as $name => $value) {
            if ($role >= $value) {
                return ucfirst($name);
            }
        }
        return 'Unknown role';
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('getReleaseBadge', function ($type) {
        if ($type == 'beta' || $type == 'alpha') {
            return '<span class="badge ' . ($type == 'alpha' ? 'badge-warning' : 'badge-info') . '">' . ucfirst($type) . '</span>';
        }
        return '';
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('icons', function ($icons) {
        $wikiPages = explode(',', $icons);
        $data = '';

        foreach ($wikiPages as $wikiPage) {
            $wiki = getWikiByName($wikiPage);

            if ($wiki == null) {
                $data .= 'Unknown wiki page "' . $wikiPage . '"';
            } else if ($wiki->icon != null) {
                $data .= '<div class="pull-left" style="margin: 5px; margin-top: 0; margin-left: 1px">';
                $data .= '<a href="/wiki/' . $wiki->url . '"><img src="' . $wiki->icon . '" class="wiki-icon-list" data-tooltip="top" title="' . $wiki->name . '"></a>';
                $data .= '</div>';
            }
        }

        return $data;
    }));

    return $view;
};

$container['notFoundHandler'] = function ($container) {
    return function (Request $request, Response $response) use ($container) {
        return $container->view->render($response->withStatus(404), '404.twig');
    };
};

$container['env'] = function () {
    return json_decode(file_get_contents('../env.json'), true);
};

if ($container['env']['type'] == 'live') {
    $container['errorHandler'] = function ($container) {
        return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $exception) use ($container) {
            return $container['view']->render($container['response']->withStatus(500), '500.twig');
        };
    };

    $container['phpErrorHandler'] = function ($container) {
        return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $exception) use ($container) {
            return $container['view']->render($container['response']->withStatus(500), '500.twig');
        };
    };
}

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.twig', [
        'latest' => getLatestStableRelease(),
        'releases' => [
            '1.14' => getReleases()->where('mc_version', '1.14.4')->first(),
            '1.12' => getReleases()->where('mc_version', '1.12.2')->first(),
            '1.11' => getReleases()->where('mc_version', '1.11.2')->first(),
            '1.10' => getReleases()->where('mc_version', '1.10.2')->first(),
            '1.9' => getReleases()->where('mc_version', '1.9.4')->first()
        ]
    ]);
});

$app->get('/releases', function (Request $request, Response $response) {
    $releases = getReleases();

    $perPage = 25;
    $page = 0;
    $pagesTotal = ceil(count($releases->get()) / $perPage);

    if (isset($request->getParams()['page']) && ctype_digit($request->getParams()['page'])) {
        $page = $request->getParams()['page'] - 1;
        $page = max($page, 0);
        $page = min($page, $pagesTotal - 1);
    }

    $releases = $releases->skip($perPage * $page)->take($perPage);

    return $this->view->render($response, 'releases.twig', ['releases' => $releases->get(), 'page' => $page, 'pagesTotal' => $pagesTotal, 'latest' => getLatestStableRelease()]);
});

$app->get('/releases/create', function (Request $request, Response $response) {
    return $this->view->render($response, 'releases_create.twig', ['errors' => []]);
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

function validateRelease($version, $type, $mc_version, $fileUrl, $downloadUrl)
{
    $errors = [];
    if (empty($version)) {
        $errors[] = 'Missing version';
    }
    if ($type != 'alpha' && $type != 'beta' && $type != 'release') {
        $errors[] = 'Invalid release type';
    }
    if (empty($mc_version)) {
        $errors[] = 'Missing Minecraft version';
    }
    if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid file URL';
    }
    if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid download URL';
    }
    return $errors;
}

$app->post('/releases/create', function (Request $request, Response $response) {
    $version = $request->getParams()['version'];
    $type = $request->getParams()['type'];
    $mc_version = $request->getParams()['mc_version'];
    $fileUrl = $request->getParams()['file_url'];
    $downloadUrl = $request->getParams()['download_url'];
    $changelog = $request->getParams()['changelog'];

    $errors = validateRelease($version, $type, $mc_version, $fileUrl, $downloadUrl);

    if (count($errors) == 0) {
        $release = new Release();
        $release->version = $version;
        $release->type = $type;
        $release->mc_version = $mc_version;
        $release->file_url = $fileUrl;
        $release->download_url = $downloadUrl;
        $release->changelog = $changelog;
        $release->user_id = getUser()->id;
        $release->date = time();
        $release->status = 0;
        $release->save();

        $this->cache->deleteItem('update');

        return $response->withRedirect('/releases/' . $release->id);
    } else {
        return $this->view->render($response, 'releases_create.twig', ['errors' => $errors]);
    }
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

$app->get('/releases/{id}/edit', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);

    if ($release == null) {
        throw new NotFoundException($request, $response);
    }

    return $this->view->render($response, 'releases_edit.twig', ['release' => $release, 'errors' => []]);
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

$app->post('/releases/{id}/edit', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);

    if ($release == null) {
        throw new NotFoundException($request, $response);
    }

    $version = $request->getParams()['version'];
    $type = $request->getParams()['type'];
    $mc_version = $request->getParams()['mc_version'];
    $fileUrl = $request->getParams()['file_url'];
    $downloadUrl = $request->getParams()['download_url'];
    $changelog = $request->getParams()['changelog'];

    $errors = validateRelease($version, $type, $mc_version, $fileUrl, $downloadUrl);

    if (count($errors) == 0) {
        $release->version = $version;
        $release->type = $type;
        $release->mc_version = $mc_version;
        $release->file_url = $fileUrl;
        $release->download_url = $downloadUrl;
        $release->changelog = $changelog;
        $release->save();

        $this->cache->deleteItem('update');

        return $response->withRedirect('/releases/' . $release->id);
    } else {
        return $this->view->render($response, 'releases_edit.twig', ['release' => $release, 'errors' => $errors]);
    }
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

$app->get('/releases/{id:[0-9]+}', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);

    if ($release == null) {
        throw new NotFoundException($request, $response);
    }

    return $this->view->render($response, 'releases_view.twig', ['release' => $release]);
});

$app->get('/releases/{id}/delete', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);

    if ($release == null) {
        throw new NotFoundException($request, $response);
    }

    $release->status = 1;
    $release->save();

    $this->cache->deleteItem('update');

    return $response->withRedirect('/releases/' . $release->id);
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

$app->get('/releases/{id}/restore', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);

    if ($release == null) {
        throw new NotFoundException($request, $response);
    }

    $release->status = 0;
    $release->save();

    $this->cache->deleteItem('update');

    return $response->withRedirect('/releases/' . $release->id);
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

function findAndParseWiki(\Slim\Container $container, $url, $revisionHash = null, $parent = null)
{
    $wiki = getWikiByUrl($url);

    if ($wiki == null) {
        return null;
    }

    $revision = getWikiRevision($wiki, $revisionHash);

    if ($revision == null) {
        return null;
    }

    $parser = new Parsedown();

    $revision['body'] = $parser->text($revision['body']);

    $revision['body'] = preg_replace_callback('/\\[\\[\\@(.+?)\\]\\]/', function ($matches) use ($wiki, $container) {
        $otherWiki = getWikiByName($matches[1]);

        if ($otherWiki != null) {
            if ($otherWiki->url == $wiki->url) {
                return 'Circular wiki include';
            }

            return findAndParseWiki($container, $otherWiki->url, null, $wiki)['revision']['body'];
        } else {
            return 'Unknown wiki reference';
        }
    }, $revision['body']);

    $revision['body'] = preg_replace_callback('/\\[\\[\\#(.+?)\\]\\]/', function ($matches) use ($wiki, $parent) {
        $var = $matches[1];

        switch ($var) {
            case 'name':
                return $parent != null ? $parent['name'] : $wiki['name'];
            default:
                return 'Unknown variable';
        }
    }, $revision['body']);

    $revision['body'] = preg_replace_callback("/\\[\\[(.+?)(\\|(.+?))?\\]\\]/", function ($matches) {
        $tags = function ($reference) {
            $additionalTags = [];

            if ($reference == null) {
                $additionalTags[] = 'style="color: #c00"';
            } else if ($reference->icon != null) {
                $additionalTags[] = 'data-tooltip="right"';
                $additionalTags[] = 'title="<img src=\'' . $reference->icon . '\' class=\'wiki-icon-tooltip\'>"';
            }

            return implode(' ', $additionalTags);
        };

        if (count($matches) == 4) {
            $reference = getWikiByName($matches[1]);

            return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . $tags($reference) . '>' . $matches[3] . '</a>';
        } else if (count($matches) == 2) {
            $reference = getWikiByName($matches[1]);

            return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . $tags($reference) . '>' . $matches[1] . '</a>';
        } else {
            return '?';
        }
    }, $revision['body']);

    $revision['body'] = str_replace('<table>', '<table class="table">', $revision['body']);

    $wiki['revision'] = $revision;

    return $wiki;
}

$app->get('/wiki', function (Request $request, Response $response) {
    return handleWiki($this, $request, $response, ['url' => '_home']);
});

$app->get('/wiki/create', function (Request $request, Response $response, $args) {
    $copy = isset($request->getParams()['copy']) ? $request->getParams()['copy'] : null;
    $copyRevision = null;

    if ($copy != null) {
        $copy = getWikiByUrl($copy);
        $copyRevision = getWikiRevision($copy, null);
    }

    return $this->view->render($response, 'wiki_create.twig', ['errors' => [], 'copy' => $copy, 'copyRevision' => $copyRevision]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->get('/wiki/pages', function (Request $request, Response $response, $args) {
    $wikis = Wiki::orderBy('url', 'ASC')->get();
    foreach ($wikis as $wiki) {
        $wiki['last_revision'] = getWikiRevision($wiki, null);
    }
    return $this->view->render($response, 'wiki_pages.twig', ['wikis' => $wikis]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->post('/wiki/create', function (Request $request, Response $response, $args) {
    $url = $request->getParams()['url'];
    $name = $request->getParams()['name'];
    $body = $request->getParams()['body'];
    $icon = $request->getParams()['icon'];

    $errors = validateWiki(null, $url, null, $name);

    if (count($errors) == 0) {
        $wiki = new Wiki();
        $wiki->url = $url;
        $wiki->name = $name;
        $wiki->status = 0;
        $wiki->icon = $icon;
        $wiki->save();

        $rev = new WikiRevision();
        $rev->wiki_id = $wiki->id;
        $rev->body = $body;
        $rev->user_id = getUser()->id;
        $rev->reverted_by = 0;
        $rev->reverted_from = 0;
        $rev->date = time();
        $rev->hash = md5(microtime());

        $rev->save();

        if (isset($request->getParams()['submit_back'])) {
            return $response->withRedirect('/wiki/' . $wiki->url);
        } else {
            return $response->withRedirect('/wiki/' . $wiki->url . '/edit');
        }
    } else {
        return $this->view->render($response, 'wiki_create.twig', ['errors' => $errors]);
    }
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

function validateWiki($currentUrl, $url, $currentName, $name)
{
    $errors = [];
    if (empty($url)) {
        $errors[] = 'Missing URL';
    } else if ($currentUrl != $url) {
        if (getWikiByUrl($url) != null) {
            $errors[] = 'URL conflict';
        }
    }
    if (!empty($url) && !ctype_alnum(str_replace(['-', '_'], '', $url))) {
        $errors[] = 'Invalid URL, allowed: alphabetical letters, numbers, - and _.';
    }
    if (empty($name)) {
        $errors[] = 'Missing name';
    } else if ($currentName != $name) {
        if (getWikiByName($name) != null) {
            $errors[] = 'Name conflict';
        }
    }
    return $errors;
}

$app->get('/wiki/{url}/delete', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $wiki->status = 1;
    $wiki->save();

    return $response->withRedirect('/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->get('/wiki/{url}/restore', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $wiki->status = 0;
    $wiki->save();

    return $response->withRedirect('/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

function getTagsForWiki($wiki)
{
    $tags = $wiki->tags;
    $newTags = [];
    foreach ($tags as $tag) {
        $newTags[$tag->tag_id] = $tag->release_id;
    }
    return $newTags;
}

$app->get('/wiki/{url}/edit', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $wiki['revision'] = $wiki->revisions()->orderBy('date', 'desc')->first();

    return $this->view->render($response, 'wiki_edit.twig', ['wiki' => $wiki, 'errors' => [], 'body' => $wiki->body, 'wikiTags' => getTagsForWiki($wiki), 'tags' => Tag::all(), 'releases' => getReleases()->get()]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->post('/wiki/{url}/edit', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $url = $request->getParams()['url'];
    $name = $request->getParams()['name'];
    $body = $request->getParams()['body'];
    $icon = $request->getParams()['icon'];

    $errors = validateWiki($wiki->url, $url, $wiki->name, $name);

    if (count($errors) == 0) {
        $wiki->name = $name;
        $wiki->url = $url;
        $wiki->icon = $icon;
        $wiki->save();

        $rev = new WikiRevision();
        $rev->wiki_id = $wiki->id;
        $rev->body = $request->getParams()['body'];
        $rev->user_id = getUser()->id;
        $rev->reverted_by = 0;
        $rev->reverted_from = 0;
        $rev->date = time();
        $rev->hash = md5(microtime());

        $rev->save();

        foreach ($wiki->tags as $tag) {
            $tag->delete();
        }

        foreach (Tag::all() as $tag) {
            if (isset($request->getParams()['tag-' . $tag->id], $request->getParams()['tag-release-' . $tag->id])) {
                $release = getRelease($request->getParams()['tag-release-' . $tag->id]);

                if ($release != null) {
                    $wikiTag = new WikiTag();
                    $wikiTag->wiki_id = $wiki->id;
                    $wikiTag->tag_id = $tag->id;
                    $wikiTag->release_id = $release->id;
                    $wikiTag->save();
                }
            }
        }

        if (isset($request->getParams()['submit_back'])) {
            return $response->withRedirect('/wiki/' . $wiki->url);
        } else {
            return $response->withRedirect('/wiki/' . $wiki->url . '/edit');
        }
    } else {
        return $this->view->render($response, 'wiki_edit.twig', ['wiki' => $wiki, 'errors' => $errors, 'body' => $body, 'wikiTags' => getTagsForWiki($wiki), 'tags' => Tag::all(), 'releases' => getReleases()->get()]);
    }
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->get('/wiki/{url}/revisions', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    return $this->view->render($response, 'wiki_revisions.twig', ['wiki' => $wiki, 'revisions' => $wiki->revisions()->orderBy('date', 'desc')->get()]);
});

$app->get('/wiki/{url}/{revision}/revert', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $revOld = getWikiRevision($wiki, $args['revision']);

    if ($revOld == null) {
        throw new NotFoundException($request, $response);
    }

    $rev = new WikiRevision();
    $rev->wiki_id = $wiki->id;
    $rev->body = $revOld->body;
    $rev->user_id = $revOld->user_id;
    $rev->reverted_by = getUser()->id;
    $rev->reverted_from = $revOld->id;
    $rev->date = time();
    $rev->hash = md5(microtime());

    $rev->save();

    return $response->withRedirect('/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->post('/wiki/update-tab', function (Request $request, Response $response, $args) use ($wikiSidebarTabs) {
    if (isset($request->getParams()['tab'])) {
        $tab = $request->getParams()['tab'];

        if (in_array($tab, $wikiSidebarTabs)) {
            $_SESSION['tab'] = $tab;
        }
    }

    return $response;
});

$app->get('/wiki/{url}[/{revision}]', function (Request $request, Response $response, $args) {
    return handleWiki($this, $request, $response, $args);
});

function handleWiki(\Slim\Container $container, Request $request, Response $response, $args)
{
    global $wikiSidebarTabs;

    $wiki = findAndParseWiki($container, $args['url'], isset($args['revision']) ? $args['revision'] : null);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $sidebarTabs = [];
    foreach ($wikiSidebarTabs as $name) {
        $sidebarTabs[] = [
            'name' => $name,
            'data' => findAndParseWiki($container, '_sidebar_' . $name)
        ];
    }

    return $container->view->render($response, 'wiki.twig', [
        'wiki' => $wiki,
        'sidebarTabs' => $sidebarTabs,
        'sidebarTabCurrent' => $_SESSION['tab'] ?? $wikiSidebarTabs[0],
        'old' => isset($args['revision'])
    ]);
}

$app->get('/login', function (Request $request, Response $response) {
    $next = $request->getParams()['next'] ?? null;

    return $this->view->render($next != null ? $response->withStatus(401) : $response, 'login.twig', [
        'failed' => false,
        'next' => $next
    ]);
});

$app->post('/login', function (Request $request, Response $response) {
    $email = $request->getParams()['email'];
    $password = $request->getParams()['password'];

    $user = User::where(['email' => $email])->first();

    if ($user != null && password_verify($password, $user->password)) {
        $_SESSION['user'] = $user['id'];

        return $response->withRedirect($request->getParams()['next'] ?? '/');
    } else {
        return $this->view->render($response, 'login.twig', [
            'failed' => true
        ]);
    }
});

$app->get('/logout', function (Request $request, Response $response) {
    unset($_SESSION['user']);

    return $response->withRedirect('/');
})->add(new NeedsAuthentication($container['view'], $roles['user']));

$app->get('/profile/{username}', function (Request $request, Response $response, $args) {
    $user = User::where('username', '=', $args['username'])->first();

    if ($user == null) {
        throw new NotFoundException($request, $response);
    }

    $wikiActivity = $user->wikiRevisions()->orderBy('date', 'desc')->limit(10)->get();
    $releaseActivity = $user->releases()->orderBy('date', 'desc')->limit(10)->get();

    return $this->view->render($response, 'profile.twig', ['profile' => $user, 'wikiActivity' => $wikiActivity, 'releaseActivity' => $releaseActivity]);
});

$app->get('/search', function (Request $request, Response $response) {
    return $this->view->render($response, 'search.twig', ['show' => false, 'query' => '']);
});

$app->post('/search', function (Request $request, Response $response) {
    $query = $request->getParams()['query'];
    $wikis = [];

    if (!empty($query)) {
        $allWikis = Wiki::where('status', '=', 0)->get();

        foreach ($allWikis as $wiki) {
            if (stristr($wiki->name, $query)) {
                $wikis[] = $wiki;
            } else {
                $rev = getWikiRevision($wiki, null);

                if (stristr($rev->body, $query)) {
                    $wikis[] = $wiki;
                }
            }
        }
    }

    return $this->view->render($response, 'search.twig', ['show' => !empty($query), 'results' => $wikis, 'query' => $query]);
});

$app->get('/update', function (Request $request, Response $response) {
    $updateData = $this->cache->getItem('update');

    if (!$updateData->isHit()) {
        $data = [];

        $data['website'] = 'https://refinedstorage.raoulvdberge.com/';
        $data['promos'] = [];

        foreach (['1.14.4', '1.12.2', '1.12.1', '1.12', '1.11.2', '1.11', '1.10.2', '1.9.4', '1.9'] as $mcVersion) {
            $data[$mcVersion] = [];

            $versions = getReleases()->where('mc_version', '=', $mcVersion)->where('status', '=', '0')->get();

            foreach ($versions as $version) {
                $data[$mcVersion][$version->version] = str_replace("\r", "", $version->changelog);
            }

            $data['promos'][$mcVersion . '-latest'] = $versions[0]->version;
            $data['promos'][$mcVersion . '-recommended'] = $versions[0]->version;
        }

        $updateData->set($data);

        $this->cache->save($updateData);
    }

    return $response->withJson($updateData->get(), 200, JSON_PRETTY_PRINT);
});

$app->run();
