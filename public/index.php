<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $params = ["currentRoute" => "home"];
    return $this->get('renderer')->render($response, "index.phtml", $params);
});

$app->get('/urls', function ($request, $response) {
    $params = ["currentRoute" => "urls"];
    return $this->get('renderer')->render($response, "urls.phtml", $params);
});

$app->run();
