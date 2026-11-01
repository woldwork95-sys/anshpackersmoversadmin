<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('sitemap.xml', 'Sitemap::index');

$routes->get('blog', 'Blog::index');
$routes->get('blog/(:segment)', 'Blocks::show/$1');
$routes->get('blocks/(:segment)', 'Blog::legacy/$1');

$routes->get('api', 'Api\Content::index', ['filter' => 'cors']);
$routes->options('api', 'Api\Content::index', ['filter' => 'cors']);

$routes->get('admin/login', 'Admin\AuthController::login', ['as' => 'admin.login', 'filter' => 'adminGuest']);
$routes->post('admin/login', 'Admin\AuthController::attempt', ['as' => 'admin.login.attempt', 'filter' => ['adminGuest', 'csrf']]);

// Everything under /admin except login is covered by the adminAuth filter.
$routes->get('admin', 'Admin\DashboardController::index', ['as' => 'admin.home']);

$routes->get('admin/blocks', 'Admin\BlocksController::index', ['as' => 'admin.blocks']);
$routes->get('admin/blocks/create', 'Admin\BlocksController::create', ['as' => 'admin.blocks.create']);
$routes->post('admin/blocks', 'Admin\BlocksController::store', ['as' => 'admin.blocks.store', 'filter' => 'csrf']);
$routes->post('admin/blocks/images', 'Admin\BlockImagesController::store', ['as' => 'admin.blocks.images', 'filter' => 'csrf']);
$routes->get('admin/blocks/(:num)', 'Admin\BlocksController::show/$1', ['as' => 'admin.blocks.show']);
$routes->get('admin/blocks/(:num)/edit', 'Admin\BlocksController::edit/$1', ['as' => 'admin.blocks.edit']);
$routes->post('admin/blocks/(:num)', 'Admin\BlocksController::update/$1', ['as' => 'admin.blocks.update', 'filter' => 'csrf']);
$routes->get('admin/blocks/(:num)/delete', 'Admin\BlocksController::delete/$1', ['as' => 'admin.blocks.delete']);
$routes->post('admin/blocks/(:num)/delete', 'Admin\BlocksController::destroy/$1', ['as' => 'admin.blocks.destroy', 'filter' => 'csrf']);

$routes->get('admin/categories', 'Admin\CategoriesController::index', ['as' => 'admin.categories']);
$routes->get('admin/categories/create', 'Admin\CategoriesController::create', ['as' => 'admin.categories.create']);
$routes->post('admin/categories', 'Admin\CategoriesController::store', ['as' => 'admin.categories.store', 'filter' => 'csrf']);
$routes->get('admin/categories/(:num)/edit', 'Admin\CategoriesController::edit/$1', ['as' => 'admin.categories.edit']);
$routes->post('admin/categories/(:num)', 'Admin\CategoriesController::update/$1', ['as' => 'admin.categories.update', 'filter' => 'csrf']);
$routes->get('admin/categories/(:num)/delete', 'Admin\CategoriesController::delete/$1', ['as' => 'admin.categories.delete']);
$routes->post('admin/categories/(:num)/delete', 'Admin\CategoriesController::destroy/$1', ['as' => 'admin.categories.destroy', 'filter' => 'csrf']);

$routes->get('admin/services', 'Admin\ServicesController::index', ['as' => 'admin.services']);
$routes->get('admin/services/create', 'Admin\ServicesController::create', ['as' => 'admin.services.create']);
$routes->post('admin/services', 'Admin\ServicesController::store', ['as' => 'admin.services.store', 'filter' => 'csrf']);
$routes->get('admin/services/(:num)', 'Admin\ServicesController::show/$1', ['as' => 'admin.services.show']);
$routes->get('admin/services/(:num)/edit', 'Admin\ServicesController::edit/$1', ['as' => 'admin.services.edit']);
$routes->post('admin/services/(:num)', 'Admin\ServicesController::update/$1', ['as' => 'admin.services.update', 'filter' => 'csrf']);
$routes->get('admin/services/(:num)/delete', 'Admin\ServicesController::delete/$1', ['as' => 'admin.services.delete']);
$routes->post('admin/services/(:num)/delete', 'Admin\ServicesController::destroy/$1', ['as' => 'admin.services.destroy', 'filter' => 'csrf']);

$routes->get('admin/settings', 'Admin\SettingsController::index', ['as' => 'admin.settings']);
$routes->post('admin/logout', 'Admin\AuthController::logout', ['as' => 'admin.logout', 'filter' => 'csrf']);
