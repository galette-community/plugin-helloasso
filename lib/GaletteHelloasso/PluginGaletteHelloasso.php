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

namespace GaletteHelloasso;

use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Core\GalettePlugin;

/**
 * Galette HelloAsso plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

class PluginGaletteHelloasso extends GalettePlugin
{
    /**
     * Extra menus entries
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getMenusContents(): array
    {
        /**
         * @var Login $login
         */
        global $login;
        $content = [
            'title' => _T("Helloasso", "helloasso"),
            'icon' => 'helloasso'
        ];
        $content['items'] = [];

        if ($login->isAdmin() || $login->isStaff()) {
            $content['items'] = [
                [
                    'label' => _T("Helloasso History", "helloasso"),
                    'route' => [
                        'name' => 'helloasso_history'
                    ]
                ],
                [
                    'label' => _T("Settings"),
                    'route' => [
                        'name' => 'helloasso_preferences'
                    ]
                ]
            ];
        }

        $menus['plugin_helloasso'] = $content;
        return $menus;
    }

    /**
     * Extra public menus entries
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getPublicMenusItemsList(): array
    {
        return [
            [
                'label' => _T("Payment form", "helloasso"),
                'route' => [
                    'name' => 'helloasso_form'
                ],
                'icon' => 'helloasso'
            ]
        ];
    }

    /**
     * Get dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getDashboardsContents(): array
    {
        /** @var Login $login */
        global $login;
        /** @var Preferences $preferences */
        global $preferences;
        $contents = [];

        if ($preferences->showPublicPages($login)) {
            $contents[] = [
                'label' => _T("Payment form", "helloasso"),
                'route' => [
                    'name' => 'helloasso_form'
                ],
                'icon' => 'helloasso'
            ];
        }
        return $contents;
    }

    /**
     * Get current logged-in user dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getMyDashboardsContents(): array
    {
        return [];
    }

    /**
     * Get actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getListActionsContents(Adherent $member): array
    {
        return [];
    }

    /**
     * Get detailed actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getDetailedActionsContents(Adherent $member): array
    {
        return static::getListActionsContents($member);
    }

    /**
     * Get batch actions contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getBatchActionsContents(): array
    {
        return [];
    }
}
