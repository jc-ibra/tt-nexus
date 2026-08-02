<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Disable auto-routing: all routes are explicit
$routes->setAutoRoute(false);

// -----------------------------------------------------------------------
// Module Routes
// -----------------------------------------------------------------------
require APPPATH . 'Modules/Core/Routes.php';
require APPPATH . 'Modules/Communications/Routes.php';
require APPPATH . 'Modules/KPIsOperativos/Routes.php';
require APPPATH . 'Modules/Mailboxes/Routes.php';
require APPPATH . 'Modules/Employees/Routes.php';
require APPPATH . 'Modules/Provisioning/Routes.php';
require APPPATH . 'Modules/ServiceDesk/Routes.php';
require APPPATH . 'Modules/MailDispatch/Routes.php';
require APPPATH . 'Modules/TechBot/Routes.php';
require APPPATH . 'Modules/HelpdeskSupervisor/Routes.php';
require APPPATH . 'Modules/AgentKpis/Routes.php';
