<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette Helloasso plugin (https://galette-community.github.io/plugin-helloasso).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use GaletteHelloasso\Controllers\HelloassoController;

//Constants and classes from plugin
require_once $module['root'] . '/_config.inc.php';

$app->get(
    '/preferences',
    [HelloassoController::class, 'preferences']
)->setName('helloasso_preferences')->add($authenticate);

$app->post(
    '/preferences',
    [HelloassoController::class, 'storePreferences']
)->setName('store_helloasso_preferences')->add($authenticate);

$app->get(
    '/form',
    [HelloassoController::class, 'form']
)->setName('helloasso_form');

$app->post(
    '/form',
    [HelloassoController::class, 'formCheckout']
)->setName('helloasso_formCheckout');

$app->get(
    '/logs[/{option:order|reset|page}/{value}]',
    [HelloassoController::class, 'logs']
)->setName('helloasso_history')->add($authenticate);

//history filtering
$app->post(
    '/history/filter',
    [HelloassoController::class, 'filter']
)->setName('filter_helloasso_history')->add($authenticate);

$app->post(
    '/webhook',
    [HelloassoController::class, 'webhook']
)->setName('helloasso_webhook');

$app->get(
    '/success',
    [HelloassoController::class, 'returnUrl']
)->setName('helloasso_success');

$app->get(
    '/cancel',
    [HelloassoController::class, 'cancelUrl']
)->setName('helloasso_cancel');

$app->get(
    '/error',
    [HelloassoController::class, 'errorUrl']
)->setName('helloasso_error');
