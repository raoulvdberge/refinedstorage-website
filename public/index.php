<?php
/**
 * Refined Storage
 * @Authors: raoulvdberge, JayJay1989
 */
$roles = [
    'admin' => 300,
    'contributor' => 200,
    'editor' => 100,
    'user' => 0
];

$wikiSidebarTabs = ['guides', 'blocks', 'items'];

$can_register = true;

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
    public $timestamps = false;
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
    return $releases->first();
}

function getTags($id)
{
    $tags = Tag::where('id', '=', $id);;
    if (getUser() == null) {
        $tags = $tags->where('status', '=', 0);
    }
    return $tags->first();
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

/**
 * Slim Framework
 */
$app = new \Slim\App;

$container = $app->getContainer();

$container['cache'] = function ($container) {
   return new FilesystemAdapter('', 0, __DIR__ . '/../cache/');
};

$container['flash'] = function () {
    return new \Slim\Flash\Messages();
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
            return $container['view']->render($container['response']->withStatus(500), '500.twig', ['error' => $exception]);
        };
    };

    $container['phpErrorHandler'] = function ($container) {
        return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $exception) use ($container) {
            return $container['view']->render($container['response']->withStatus(500), '500.twig', [ 'error' => $exception]);
        };
    };
}
/**
 * Home
 * @url: /
 * @method: get
 */
$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.twig', [
        'latest' => getLatestStableRelease(),
        'releases' => [
            '1.13' => getRelease()->where('mc_version', '1.13.2')->first(),
            '1.12' => getReleases()->where('mc_version', '1.12.2')->first(),
            '1.11' => getReleases()->where('mc_version', '1.11.2')->first(),
            '1.10' => getReleases()->where('mc_version', '1.10.2')->first(),
            '1.9' => getReleases()->where('mc_version', '1.9.4')->first()
        ]
    ]);
});

/**
 * Releases
 * @url: /releases
 * @method: get
 */
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

/**
 * Releases create
 * @url: /releases/create
 * @method: get, post
 * @role: contributor
 */
$app->get('/releases/create', function (Request $request, Response $response) {
    return $this->view->render($response, 'releases_create.twig', ['errors' => []]);
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

function validateRelease($version, $type, $mc_version, $url)
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
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid URL';
    }
    return $errors;
}

$app->post('/releases/create', function (Request $request, Response $response) {
    $version = $request->getParams()['version'];
    $type = $request->getParams()['type'];
    $mc_version = $request->getParams()['mc_version'];
    $url = $request->getParams()['url'];
    $changelog = $request->getParams()['changelog'];

    $errors = validateRelease($version, $type, $mc_version, $url);

    if (count($errors) == 0) {
        $release = new Release();
        $release->version = $version;
        $release->type = $type;
        $release->mc_version = $mc_version;
        $release->url = $url;
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

/**
 * Releases edit
 * @url: /releases/{id}/edit
 * @method: get, post
 * @role: contributor
 */
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
    $url = $request->getParams()['url'];
    $changelog = $request->getParams()['changelog'];

    $errors = validateRelease($version, $type, $mc_version, $url);

    if (count($errors) == 0) {
        $release->version = $version;
        $release->type = $type;
        $release->mc_version = $mc_version;
        $release->url = $url;
        $release->changelog = $changelog;
        $release->save();

        $this->cache->deleteItem('update');

        return $response->withRedirect('/releases/' . $release->id);
    } else {
        return $this->view->render($response, 'releases_edit.twig', ['release' => $release, 'errors' => $errors]);
    }
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

/**
 * Releases
 * @url: /releases/{id}
 * @method: get
 */
$app->get('/releases/{id:[0-9]+}', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);

    if ($release == null) {
        throw new NotFoundException($request, $response);
    }

    return $this->view->render($response, 'releases_view.twig', ['release' => $release]);
});

/**
 * Releases Delete
 * @url: /releases/{id}/delete
 * @method: get
 * @role: contributor
 */
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

/**
 * Releases Restore
 * @url: /releases/{id}/restore
 * @method: get
 * @role: contributor
 */
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

/**
 * find and parse wiki (custom markdown tags)
 */
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
        print_r($parent);
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

/**
 * Markdown
 * @method: get
 * @role: editor
 */
$app->get('/markdown', function (Request $request, Response $response) {
    return $this->view->render($response, 'markdown.twig');
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

/**
 * Wiki
 * @url: /wiki
 * @method: get
 */
$app->get('/wiki', function (Request $request, Response $response) {
    return handleWiki($this, $request, $response, ['url' => '_home']);
});

/**
 * Wiki Create
 * @url: /wiki/create
 * @method: get
 * role: editor
 */
$app->get('/wiki/create', function (Request $request, Response $response, $args) {
    $copy = isset($request->getParams()['copy']) ? $request->getParams()['copy'] : null;
    $copyRevision = null;

    if ($copy != null) {
        $copy = getWikiByUrl($copy);
        $copyRevision = getWikiRevision($copy, null);
    }

    return $this->view->render($response, 'wiki_create.twig', ['errors' => [], 'copy' => $copy, 'copyRevision' => $copyRevision]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

/**
 * Wiki pages
 * @url: /wiki/pages
 * @method: get
 * @role: editor
 */
$app->get('/wiki/pages', function (Request $request, Response $response, $args) {
    $wikis = Wiki::orderBy('url', 'ASC')->get();
    foreach ($wikis as $wiki) {
        $wiki['last_revision'] = getWikiRevision($wiki, null);
    }
    return $this->view->render($response, 'wiki_pages.twig', ['wikis' => $wikis]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']))->setName('wiki.pages');

/**
 * Wiki Create
 * @url: /wiki
 * @method: post
 * @role: editor
 */
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

function getPath($url){
    $path = parse_url($url);
    return $path['path'];
}

/***
 * Wiki Force delete
 * @url: /wiki/revision/{hash}/delete
 * @method: get
 * @role: admin
 */
$app->get('/wiki/revision/{hash}/delete', function (Request $request, Response $response, $args){
    $url = $args['hash'];
    $revision = WikiRevision::where('hash','=',$url);
    $referer = $request->getHeader('HTTP_REFERER');
    $revision->delete();
    $this->flash->addMessage('removed', true);
    return $response->withRedirect(getPath($referer[0]));
})->add(new NeedsAuthentication($container['view'], $roles['admin']));

/***
 * Wiki Delete
 * @url: /wiki/{url}/delete
 * @method: get
 * @role: admin
 */
$app->get('/wiki/{url}/delete', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    $wiki->status = 1;
    $wiki->save();

    return $response->withRedirect('/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

/**
 * Wiki Restore
 * @url: /wiki/{url}/restore
 * @method: get
 * @role: editor
 */
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

/**
 * Wiki edit
 * @url: /wiki/{url}/edit
 * @method: get, post
 * @role: editor
 */
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

/**
 * Wiki revision viewer
 * @url: /wiki/{url}/revisions
 * @method: get
 */
$app->get('/wiki/{url}/revisions', function (Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        throw new NotFoundException($request, $response);
    }

    return $this->view->render($response, 'wiki_revisions.twig', ['wiki' => $wiki, 'revisions' => $wiki->revisions()->orderBy('date', 'desc')->get()]);
});

/**
 * Wiki revert to revision
 * @url: /wiki/{url}/{revision}/revert
 * @method: get
 * @role: editor
 */
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

/**
 * Wiki Revision viewer
 * @url: /wiki/{url}[/{revision}]
 * @method: get
 */
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

/**
 * Register
 * @url: /register
 * @method: get, post
 * @role: admin
 */
if ($can_register){

    $app->get('/register', function ($request, $response) {
        $next = $request->getParams()['next'] ?? null;
        return $this->view->render($next != null ? $response->withStatus(401) : $response, 'register.twig', [
            'failed' => false,
            'next' => $next
        ]);
    })->add(new NeedsAuthentication($container['view'], $roles['admin']));

    $app->post('/register', function ($request, $response) {
        $email = $request->getParams()['email'];
        $password = $request->getParams()['password'];
        $username = $request->getParams()['username'];
        $role = $request->getParams()['role'];
        if ( !empty($role) && !empty($email) && !empty($password) && !empty($username)){
            $user = new User();
            $user->username = $username;
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->email = $email;
            $user->date_created = time();
            $user->role = $role;
            $user->save();
            return $this->view->render($response, 'register.twig', [
                'failed' => false,
                'created' => true
            ]);
        }else{
            return $this->view->render($response, 'register.twig', [
                'failed' => true,
                'created' => false
            ]);
        }

    })->add(new NeedsAuthentication($container['view'], $roles['admin']));
}

/**
 * Tags
 * @url: /tags
 * @method: get
 * @role: editor
 */
$app->get('/tags', function (Request $request, Response $response){
    $tags = Tag::orderBy('id', 'ASC')->get();
    $removed = $this->flash->getFirstMessage('removed') != null ? $this->flash->getFirstMessage('removed') : false;
    $created = $this->flash->getFirstMessage('created') != null ? $this->flash->getFirstMessage('created') : false;
    return $this->view->render($response, 'tags_view.twig', ['tags'=> $tags, 'removed' => $removed, 'created' => $created]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']))->setName('tags');

/**
 * Create Tags
 * @url: /tags/create
 * @method: get, post
 * @role: editor
 */
$app->get('/tags/create', function (Request $request, Response $response){
    return $this->view->render($response, 'tags_create.twig');
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->post('/tags/create', function (Request $request, Response $response){
    $name = $request->getParams()['name'];
    $badge = $request->getParams()['badge'];

    if (!empty($name) && !empty($badge)){
        $tags = new Tag();
        $tags->name = $name;
        $tags->badge = $badge;
        $tags->save();
        $this->flash->addMessage('created', true);
        return $response->withRedirect($this->router->pathFor('tags'));
    }else{
        return $this->view->render($response, 'tags_create.twig', [
            'failed' => true
        ]);
    }
})->add(new NeedsAuthentication($container['contributor'], $roles['editor']));

/**
 * Tags edit {id}
 * @url: /tags/{id}/edit
 * @method: get, post
 * @role: editor
 */
$app->get('/tags/{id}/edit', function (Request $request, Response $response, $arguments){
    $tags = getTags($arguments['id']);
    return $this->view->render($response, 'tags_edit.twig', ['tags' => $tags]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

$app->post('/tags/{id}/edit', function (Request $request, Response $response, $arguments){
    $name = $request->getParams()['name'];
    $badge = $request->getParams()['badge'];
    $tags = getTags($arguments['id']);
    $tags->name = $name;
    $tags->badge = $badge;
    $tags->save();
    return $this->view->render($response, 'tags_edit.twig', [ 'tags'=>$tags, 'edited' => true ]);
})->add(new NeedsAuthentication($container['view'], $roles['editor']));

/***
 * Tags delete
 * @url: /tags/{id}/delete
 * @method: get
 * @role: admin
 */
$app->get('/tags/{id}/delete', function (Request $request, Response $response, $arguments){
    $tags = getTags($arguments['id']);
    $tags->delete();
    $this->flash->addMessage('removed', true);
    return $response->withRedirect($this->router->pathFor('tags'));
})->add(new NeedsAuthentication($container['view'], $roles['contributor']));

/**
 * Login
 * @url: /login
 * @method: get, post
 */
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
    echo password_verify($password, $user->password);
    if ($user != null && password_verify($password, $user->password)) {
        $_SESSION['user'] = $user['id'];

        return $response->withRedirect($request->getParams()['next'] ?? '/');
    } else {
        return $this->view->render($response, 'login.twig', [
            'failed' => true
        ]);
    }
});

/**
 * Logout
 * @url: /logout
 * @method: get
 */
$app->get('/logout', function (Request $request, Response $response) {
    unset($_SESSION['user']);

    return $response->withRedirect('/');
})->add(new NeedsAuthentication($container['view'], $roles['user']));

/**
 * Profile
 * @url: /profile/{username}
 * @method: get
 */

$app->get('/profile/{username}', function (Request $request, Response $response, $args) {
    $user = User::where('username', '=', $args['username'])->first();

    if ($user == null) {
        throw new NotFoundException($request, $response);
    }

    $wikiActivity = $user->wikiRevisions()->orderBy('date', 'desc')->limit(10)->get();
    $releaseActivity = $user->releases()->orderBy('date', 'desc')->limit(10)->get();

    return $this->view->render($response, 'profile.twig', ['profile' => $user, 'wikiActivity' => $wikiActivity, 'releaseActivity' => $releaseActivity]);
});


/**
 * Search
 * @url: /search
 * @method: get, post
 */
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

/**
 * Update
 * @url: /update
 * @method: get
 */
$app->get('/update', function (Request $request, Response $response) {
    $updateData = $this->cache->getItem('update');

    if (!$updateData->isHit()) {
        $data = [];

        $data['website'] = 'https://mrs.lateur.pro/';
        $data['promos'] = [];
        $mcVersions = ['1.13.2', '1.13.1', '1.13', '1.12.2', '1.12.1', '1.12', '1.11.2', '1.11', '1.10.2', '1.9.4', '1.9', '1.8'];

        foreach ($mcVersions as $mcVersion) {
            $data[$mcVersion] = [];
            $versions = getReleases()->where('mc_version', '=', $mcVersion)->where('status', '=', '0')->get();
            if($versions[0]->version !=null){
                foreach ($versions as $version) {
                    $data[$mcVersion][$version->version] = str_replace("\r", "", $version->changelog);
                }
                $data['promos'][$mcVersion . '-latest'] = $versions[0]->version;
                $data['promos'][$mcVersion . '-recommended'] = $versions[0]->version;
            }
        }
        //remove null's and empty array entries
        $updateData->set(array_filter($data));
        $this->cache->save($updateData);
    }

    return $response->withJson($updateData->get(), 200, JSON_PRETTY_PRINT);
});

$app->run();
