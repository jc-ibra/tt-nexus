<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Disable auto-routing — all routes are explicit
$routes->setAutoRoute(false);

// -----------------------------------------------------------------------
// Module Routes
// -----------------------------------------------------------------------
require APPPATH . 'Modules/Core/Routes.php';
require APPPATH . 'Modules/Communications/Routes.php';
require APPPATH . 'Modules/KPIsOperativos/Routes.php';
require APPPATH . 'Modules/Buzones/Routes.php';
