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

$this->register(
    'Galette Helloasso',     //Name
    'Helloasso integration', //Short description
    'Guillaume AGNIERAY',    //Author
    '1.0.0-rc2',             //Version
    '1.2.1',                 //Galette compatible version
    'helloasso',             //routing name and translation domain
    '2025-12-08',            //Release date
    [   //Permissions needed
        'helloasso_preferences'        => 'staff',
        'store_helloasso_preferences'  => 'staff',
        'helloasso_history'            => 'staff',
        'filter_helloasso_history'     => 'staff'
    ]
);

$this->setCsrfExclusions(
    [
    '/helloasso_(webhook|success|cancel|error)/',
    ]
);
