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

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Galette;
use Galette\Core\Login;
use Galette\Core\History;
use Galette\Core\Preferences;
use Galette\Filters\HistoryList;

/**
 * This class stores and serve the Helloasso History.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */
class HelloassoHistory extends History
{
    public const TABLE = 'history';
    public const PK = 'id_helloasso';

    public const STATE_NONE = 0;
    public const STATE_PROCESSED = 1;
    public const STATE_ERROR = 2;
    public const STATE_INCOMPLETE = 3;
    public const STATE_ALREADYDONE = 4;

    private int $id;

    /**
     * Default constructor.
     *
     * @param Db           $zdb         Database
     * @param Login        $login       Login
     * @param Preferences  $preferences Preferences
     * @param ?HistoryList $filters     Filtering
     */
    public function __construct(Db $zdb, Login $login, Preferences $preferences, ?HistoryList $filters = null)
    {
        $this->with_lists = false;
        parent::__construct($zdb, $login, $preferences, $filters);
    }

    /**
     * Add a new entry
     *
     * @param array|string $action   the action to log
     * @param string       $argument the argument
     * @param string       $query    the query (if relevant)
     *
     * @return bool true if entry was successfully added, false otherwise
     */
    public function add(array|string $action, string $argument = '', string $query = ''): bool
    {
        $request = $action;
        try {
            $values = [
                'history_date'  => date('Y-m-d H:i:s'),
                'checkout_id'   => $request['data']['id'],
                'amount'        => $request['data']['amount'] / 100,
                'comments'      => $request['data']['order']['id'],
                'request'       => Galette::jsonEncode($request),
                'state'         => self::STATE_NONE
            ];

            $insert = $this->zdb->insert($this->getTableName());
            $insert->values($values);
            $this->zdb->execute($insert);
            $this->id = (int) $this->zdb->driver->getLastGeneratedValue();

            Analog::log(
                'An entry has been added in helloasso history',
                Analog::DEBUG
            );
        } catch (\Exception $e) {
            Analog::log(
                "An error occured trying to add log entry. " . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }

        return true;
    }

    /**
     * Get table's name
     *
     * @param boolean $prefixed Whether table name should be prefixed
     *
     * @return string
     */
    protected function getTableName(bool $prefixed = false): string
    {
        if ($prefixed === true) {
            return PREFIX_DB . HELLOASSO_PREFIX . self::TABLE;
        } else {
            return HELLOASSO_PREFIX . self::TABLE;
        }
    }

    /**
     * Get table's PK
     *
     * @return string
     */
    protected function getPk(): string
    {
        return self::PK;
    }

    /**
     * Gets Helloasso history
     *
     * @return array
     */
    public function getHelloassoHistory(): array
    {
        $orig = $this->getHistory();
        $new = [];
        $dedup = [];
        if (count($orig) > 0) {
            foreach ($orig as $o) {
                try {
                    if (Galette::isSerialized($o['request'])) {
                        $oa = unserialize($o['request']);
                    } else {
                        $oa = Galette::jsonDecode($o['request']);
                    }

                    $o['raw_request'] = print_r($oa, true);
                    $o['request'] = $oa;
                    if (in_array($o['checkout_id'], $dedup)) {
                        $o['duplicate'] = true;
                    } else {
                        $dedup[] = $o['checkout_id'];
                    }

                    $new[] = $o;
                } catch (\Exception $e) {
                    Analog::log(
                        'Error loading helloasso history entry #' . $o[$this->getPk()] .
                        ' ' . $e->getMessage(),
                        Analog::WARNING
                    );
                }
            }
        }
        return $new;
    }

    /**
     * Builds the order clause
     *
     * @return array SQL ORDER clause
     */
    protected function buildOrderClause(): array
    {
        $order = [];

        if ($this->filters->orderby == HistoryList::ORDERBY_DATE) {
            $order[] = 'history_date ' . $this->filters->getDirection();
        }

        return $order;
    }

    /**
     * Is payment already processed?
     *
     * @param array $request Verify sign helloasso parameter
     *
     * @return boolean
     */
    public function isProcessed(array $request): bool
    {
        $select = $this->zdb->select($this->getTableName());
        $select->where(
            [
                'checkout_id' => $request['data']['id'],
                'state'       => self::STATE_PROCESSED
            ]
        );
        $results = $this->zdb->execute($select);

        return (count($results) > 0);
    }

    /**
     * Set payment state
     *
     * @param integer $state State, one of self::STATE_ constants
     *
     * @return boolean
     */
    public function setState(int $state): bool
    {
        try {
            $update = $this->zdb->update($this->getTableName());
            $update
                ->set(['state' => $state])
                ->where([self::PK => $this->id]);
            $this->zdb->execute($update);
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred when updating state field | ' . $e->getMessage(),
                Analog::ERROR
            );
        }
        return false;
    }
}
