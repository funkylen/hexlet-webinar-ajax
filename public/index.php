<?php

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use DI\Container;
use Illuminate\Support\Arr;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$container->set('view', function () {
    return Twig::create(__DIR__ . '/../templates', ['cache' => false]);
});

$container->set('db', function () {
    return new Database();
});

$app = AppFactory::createFromContainer($container);

$app->add(TwigMiddleware::createFromContainer($app));

$container->set('router', fn() => $app->getRouteCollector()->getRouteParser());

$app->get('/', function (Request $request, Response $response, $args) {
    return $this->get('view')->render($response, 'layout.twig', [
        'products' => $this->get('db')->get('products'),
        'cart' => $this->get('db')->get('cart'),
    ]);
})->setName('products');

$app->post('/add/{product_id}', function (Request $request, Response $response, $args) {
    $products = $this->get('db')->get('products');
    $cart = $this->get('db')->get('cart');

    $product = Arr::first($products, fn($item) => $item['id'] === (int) $args['product_id']);

    if (!$product) {
        throw new \Exception('product_id not found');
    }

    $cart[] = $product['id'];
    $this->get('db')->set('cart', array_unique($cart));

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('add');

$app->post('/remove/{product_id}', function (Request $request, Response $response, $args) {
    $cart = $this->get('db')->get('cart');

    $this->get('db')->set('cart', array_filter($cart, fn($id) => $id !== (int) $args['product_id']));

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('remove');

$app->run();
