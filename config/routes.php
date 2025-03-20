<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Olodoc\DocumentManagerInterface;
/**
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/:id', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 * 
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Mezzio\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */
return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {

    $docManager = $container->get(DocumentManagerInterface::class);
    //
    // routes
    // 
    $middlewares = [
        Olodoc\Middleware\SetVersionMiddleware::class,
    ];
    $app->route('/search', App\Handler\SearchHandler::class, ['GET']);
    $app->route('/', [...$middlewares, ...[App\Handler\DocumentHandler::class]], ['GET'], $docManager::INDEX_DEFAULT);
    $app->route('/index.html', [...$middlewares, ...[App\Handler\DocumentHandler::class]], ['GET'], $docManager::INDEX_DEFAULT_INDEX);
    $app->route('/latest', [...$middlewares, ...[App\Handler\DocumentHandler::class]], ['GET'], $docManager::INDEX_DEFAULT_LATEST);
    $app->route('/:version/:page', [...$middlewares, ...[App\Handler\DocumentHandler::class]], ['GET'], $docManager::PAGE_ROUTE)
    ->setOptions(
        [
            'constraints' => [
                'version' => '\d.{1,4}|latest',
                'page' => '[a-zA-Z0-9._-]+\.html',
            ],
        ]
    );
    $app->route('/:version/:directory/:page', 
        [...$middlewares, ...[App\Handler\DocumentHandler::class]], 
        ['GET'], 
        $docManager::DIRECTORY_ROUTE
    )->setOptions(
        [
            'constraints' => [
                'version' => '\d.{1,4}|latest',
                'directory' => '[a-zA-Z0-9\/._-]+',
                'page' => '[a-zA-Z0-9._-]+\.html',
            ],
        ]
    );    
};
