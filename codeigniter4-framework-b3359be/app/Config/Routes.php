<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'DashboardController::index', ['filter' => 'auth']);
$routes->get('connexion', 'AuthController::login');
$routes->post('connexion', 'AuthController::authenticate');
$routes->post('deconnexion', 'AuthController::logout', ['filter' => 'auth']);
$routes->get('patients/nouveau', 'PatientController::create', ['filter' => 'auth']);
$routes->post('patients', 'PatientController::store', ['filter' => 'auth']);
$routes->get('medicaments/nouveau', 'PatientController::medicationForm', ['filter' => 'auth']);
$routes->post('medicaments', 'PatientController::storeMedications', ['filter' => 'auth']);
$routes->get('tableau-de-bord', 'DataController::table', ['filter' => 'auth']);
$routes->get('visualisations', 'DataController::charts', ['filter' => 'auth']);
$routes->get('analyse', 'DataController::analysis', ['filter' => 'auth']);
$routes->get('tdc', 'DataController::tdc', ['filter' => 'auth']);
$routes->get('utilisateurs', 'UserController::index', ['filter' => 'auth']);
$routes->post('utilisateurs', 'UserController::create', ['filter' => 'auth']);
$routes->post('utilisateurs/(:num)/statut', 'UserController::toggle/$1', ['filter' => 'auth']);
$routes->post('utilisateurs/(:num)', 'UserController::update/$1', ['filter' => 'auth']);
$routes->post('utilisateurs/(:num)/supprimer', 'UserController::delete/$1', ['filter' => 'auth']);
$routes->get('api/donnees', 'DataController::api', ['filter' => 'auth']);
$routes->get('api/regions', 'DataController::regions', ['filter' => 'auth']);
$routes->get('api/regions/(:num)/districts', 'DataController::districts/$1', ['filter' => 'auth']);
