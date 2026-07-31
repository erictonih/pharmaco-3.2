<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'DashboardController::index', ['filter' => 'auth']);
$routes->get('connexion', 'AuthController::login');
$routes->post('connexion', 'AuthController::attempt');
$routes->post('deconnexion', 'AuthController::logout', ['filter' => 'auth']);

$routes->group('', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('saisie/client', 'IntakeController::clientForm');
    $routes->post('saisie/client', 'IntakeController::storeClient');
    $routes->get('saisie/medicaments', 'IntakeController::medicationForm');
    $routes->post('saisie/medicaments', 'IntakeController::storeMedications');
    $routes->get('visualisation', 'AnalyticsController::visualisation');
    $routes->get('tableau-de-bord', 'AnalyticsController::dashboard');
    $routes->get('analyse', 'AnalyticsController::analyse');
    $routes->get('analyse-mpox', 'MpoxController::index');
    $routes->get('tdc', 'AnalyticsController::tdc');
    $routes->get('utilisateurs', 'UsersController::index');
    $routes->match(['get', 'post'], 'parametres', 'SettingsController::index');
});

$routes->get('api/donnees', 'ApiController::data', ['filter' => 'auth']);
$routes->get('api/risques-medicaments', 'ApiController::risks', ['filter' => 'auth']);
