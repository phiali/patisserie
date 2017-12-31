<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file) || is_file($file . 'index.html') || is_file($file . '/index.html')) {
        return false;
    }
}
define('APPLICATION_PATH', realpath(__DIR__ . '/../'));
require __DIR__ . '/../vendor/autoload.php';

session_start();

$yamlFile = __DIR__ . '/../config/site.yaml';
if (!file_exists($yamlFile)) {
    throw new \RuntimeException("Please ensure the config/site.yaml exists");
}

$siteConfiguration = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($yamlFile));

// Instantiate the app
try {
    if (!empty($siteConfiguration['timezone'])) {
        if (!date_default_timezone_set($siteConfiguration['timezone'])) {
            throw new Exception($siteConfiguration['timezone'] . ' is not a valid timezone');
        }
    }
} catch (Exception $exception) {
    throw new RuntimeException($exception->getMessage());
}

$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Register the View
$container = $app->getContainer();
$container['csrf'] = function ($container) {
    return new \Slim\Csrf\Guard;
};

$container['auth'] = function($container) {
    return new \Patisserie\Auth;
};

$container['flash'] = function ($container) {
    return new \Slim\Flash\Messages;
};

$container['view'] = function ($container) use ($siteConfiguration) {
    $view = new \Slim\Views\Twig(
        ['../src/templates'],
        [
//            'cache' => 'data/cache'
        ]
    );

    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));
    // https://github.com/kanellov/slim-twig-flash
    $view->addExtension(new Knlv\Slim\Views\TwigMessages(
        new Slim\Flash\Messages()
    ));

    $view->addExtension(new \Patisserie\CsrfExtension($container['csrf']));

    $view->getEnvironment()->addGlobal('siteTitle', $siteConfiguration['siteTitle']);
    $view->getEnvironment()->addGlobal('auth', [
        'check' => $container->auth->check()
    ]);

    return $view;
};

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// If a password isn't configured then we'll send the user off to set one up
if (   !$siteConfiguration['username']
    || !$siteConfiguration['password']) {
    if ('/_p/configure/password' !== filter_input(INPUT_SERVER, 'REQUEST_URI')) {
        header("Location: /_p/configure/password");
        exit();
    }
}

$users = [
    $siteConfiguration['username'] => $siteConfiguration['password']
];

// Set up authentication via Basic Auth, see https://github.com/tuupola/slim-basic-auth
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    'path'        => ['/_p/api'],
    'passthrough' => [''],
    'users'       => $users
]));

// Run app
define('PUBLIC_FOLDER', __DIR__);
$app->run();