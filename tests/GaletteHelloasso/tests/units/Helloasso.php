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

namespace GaletteHelloasso\tests\units;

use Galette\GaletteTestCase;

/**
 * Helloasso tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */
class Helloasso extends GaletteTestCase
{
    protected int $seed = 20250723111237;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(HELLOASSO_PREFIX . \GaletteHelloasso\Helloasso::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test empty
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $helloasso = new \GaletteHelloasso\Helloasso($this->zdb, $this->preferences);
        $this->assertFalse($helloasso->getTestMode());
        $this->assertSame('', $helloasso->getOrganizationSlug());
        $this->assertSame('', $helloasso->getClientId());
        $this->assertSame('', $helloasso->getClientSecret());

        $amounts = $helloasso->getAmounts($this->login);
        $this->assertCount(0, $amounts);
        $this->assertCount(7, $helloasso->getAllAmounts());
        $this->assertTrue($helloasso->areAmountsLoaded());
        $this->assertTrue($helloasso->isLoaded());
    }
}
