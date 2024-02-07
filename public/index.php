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
    $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
    $twig->addExtension(new \Twig\Extension\DebugExtension());
    $twig->getEnvironment()->enableDebug();

    return $twig;
});

$container->set('db', function () {
    return new Database();
});

$app = AppFactory::createFromContainer($container);

$app->add(TwigMiddleware::createFromContainer($app));

$container->set('router', fn() => $app->getRouteCollector()->getRouteParser());

$app->get('/', function (Request $request, Response $response) {
    $filter = $request->getQueryParams()['name'] ?? null;

    $products = $this->get('db')->get('products');

    if (!empty($filter)) {
        $products = array_filter($products, fn($item) => str_contains(mb_strtolower($item['name']), mb_strtolower($filter)));
    }

    return $this->get('view')->render($response, 'products.twig', [
        'filter'   => $filter,
        'products' => $products,
        'cart'     => array_keys($this->get('db')->get('cart')),
    ]);
})->setName('products');

$app->get('/cart', function (Request $request, Response $response) {
    $products = $this->get('db')->get('products');
    $cart     = $this->get('db')->get('cart');

    $cartProducts = [];

    foreach ($cart as $id => $count) {
        $product        = Arr::first($products, fn($item) => $item['id'] === $id);

        $cartProducts[] = [
            'id'          => $id,
            'name'        => $product['name'],
            'description' => $product['description'],
            'count'       => $count,
            'price'       => $product['price'] * $count,
        ];
    }

    return $this->get('view')->render($response, 'cart.twig', compact('cartProducts', 'cart'));
})->setName('cart');

$app->post('/add/{product_id}', function (Request $request, Response $response, $args) {
    $products = $this->get('db')->get('products');
    $cart     = $this->get('db')->get('cart');

    $product = Arr::first($products, fn($item) => $item['id'] === (int) $args['product_id']);

    if (!$product) {
        throw new \Exception('product_id not found');
    }

    $cart[$product['id']] = 1;
    $this->get('db')->set('cart', $cart);

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('add');

$app->post('/remove/{product_id}', function (Request $request, Response $response, $args) {
    $cart = $this->get('db')->get('cart');

    unset($cart[(int) $args['product_id']]);

    $this->get('db')->set('cart', $cart);

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('remove');

$app->run();
