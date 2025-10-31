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
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Entity\ContributionsTypes;
use GuzzleHttp\Client;

/**
 * Preferences for helloasso
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

class Helloasso
{
    public const TABLE = 'preferences';
    public const TABLE_TOKENS = 'tokens';

    public const PAYMENT_PENDING = 'Pending';
    public const PAYMENT_COMPLETE = 'Complete';

    public const API_ROUTE = 'https://api.helloasso.com/';
    public const TEST_API_ROUTE = 'https://api.helloasso-sandbox.com/';

    private Db $zdb;
    private Preferences $preferences;

    /** @var array<int, array<string,mixed>> */
    private array $prices;
    private bool $test_mode;
    private ?string $organization_slug;
    private ?string $client_id;
    private ?string $client_secret;
    /** @var array<int, string> */
    private array $inactives;

    private bool $loaded;
    private bool $amounts_loaded = false;

    /** @var array<string, mixed> */
    private array $guzzle_options = [
        'timeout' => 2.0,
    ];

    /** @var array<string, mixed> */
    private array $tokens = [];
    /** @var array<string> */
    private static array $tokens_types = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Default constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Preferences
     */
    public function __construct(Db $zdb, Preferences $preferences)
    {
        $this->zdb = $zdb;
        $this->preferences = $preferences;
        $this->loaded = false;
        $this->prices = [];
        $this->inactives = [];
        $this->test_mode = false;
        $this->organization_slug = null;
        $this->client_id = null;
        $this->client_secret = null;
        $this->tokens = [];
        $this->load();
    }

    /**
     * Load preferences form the database and amounts from core contributions types
     *
     * @return void
     */
    public function load(): void
    {
        try {
            $results = $this->zdb->selectAll(HELLOASSO_PREFIX . self::TABLE);

            /** @var \ArrayObject<string, mixed> $row */
            foreach ($results as $row) {
                switch ($row->nom_pref) {
                    case 'helloasso_test_mode':
                        $this->test_mode = $row->val_pref === '1' ? true : false;
                        break;
                    case 'helloasso_organization_slug':
                        $this->organization_slug = $row->val_pref;
                        break;
                    case 'helloasso_client_id':
                        $this->client_id = $row->val_pref;
                        break;
                    case 'helloasso_client_secret':
                        $this->client_secret = $row->val_pref;
                        break;
                    case 'helloasso_inactives':
                        $this->inactives = explode(',', $row->val_pref);
                        break;
                    default:
                        //we've got a preference not intended
                        Analog::log(
                            '[' . get_class($this) . '] unknown preference `'
                            . $row->nom_pref . '` in the database.',
                            Analog::WARNING
                        );
                }
            }

            $result = $this->zdb->selectAll(HELLOASSO_PREFIX . self::TABLE_TOKENS);

            foreach ($result as $token) {
                $this->tokens[$token['type']] = $token['value'];
                $this->tokens[$token['type'] . '_expiry'] = $token['expiry'];
            }

            $this->loaded = true;
            $this->loadContributionsTypes();
        } catch (\Exception $e) {
            Analog::log(
                '[' . get_class($this) . '] Cannot load helloasso settings |'
                . $e->getMessage(),
                Analog::ERROR
            );
            //consider plugin is not loaded when missing the main settings
            $this->loaded = false;
        }
    }

    /**
     * Load amounts from core contributions types
     *
     * @return void
     */
    private function loadContributionsTypes(): void
    {
        try {
            $ct = new ContributionsTypes($this->zdb);
            $this->prices = $ct->getCompleteList();
            //amounts should be loaded here
            $this->amounts_loaded = true;
        } catch (\Exception $e) {
            Analog::log(
                '[' . get_class($this) . '] Cannot load amounts from core contributions types'
                . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            //amounts are not loaded at this point
            $this->amounts_loaded = false;
        }
    }

    /**
     * Store values in the database
     *
     * @return bool
     */
    public function store(): bool
    {
        try {
            //store Helloasso test mode
            $values = [
                'nom_pref' => 'helloasso_test_mode',
                'val_pref' => $this->test_mode ? '1' : ''
            ];
            $update = $this->zdb->update(HELLOASSO_PREFIX . self::TABLE);
            $update->set($values)
                ->where(
                    [
                        'nom_pref' => 'helloasso_test_mode'
                    ]
                );

            $edit = $this->zdb->execute($update);

            //store Helloasso organization slug
            $values = [
                'nom_pref' => 'helloasso_organization_slug',
                'val_pref' => $this->organization_slug
            ];
            $update = $this->zdb->update(HELLOASSO_PREFIX . self::TABLE);
            $update->set($values)
                ->where(
                    [
                        'nom_pref' => 'helloasso_organization_slug'
                    ]
                );

            $edit = $this->zdb->execute($update);

            //store Helloasso clientId
            $values = [
                'nom_pref' => 'helloasso_client_id',
                'val_pref' => $this->client_id
            ];
            $update = $this->zdb->update(HELLOASSO_PREFIX . self::TABLE);
            $update->set($values)
                ->where(
                    [
                        'nom_pref' => 'helloasso_client_id'
                    ]
                );

            $edit = $this->zdb->execute($update);

            //store Helloasso clientSecret
            $values = [
                'nom_pref' => 'helloasso_client_secret',
                'val_pref' => $this->client_secret
            ];
            $update = $this->zdb->update(HELLOASSO_PREFIX . self::TABLE);
            $update->set($values)
                ->where(
                    [
                        'nom_pref' => 'helloasso_client_secret'
                    ]
                );

            $edit = $this->zdb->execute($update);

            //store inactives
            $values = [
                'nom_pref' => 'helloasso_inactives',
                'val_pref' => implode(',', $this->inactives)
            ];
            $update = $this->zdb->update(HELLOASSO_PREFIX . self::TABLE);
            $update->set($values)
                ->where(
                    [
                        'nom_pref' => 'helloasso_inactives'
                    ]
                );

            $edit = $this->zdb->execute($update);

            Analog::log(
                '[' . get_class($this)
                . '] Helloasso settings were sucessfully stored',
                Analog::DEBUG
            );

            return true;
        } catch (\Exception $e) {
            Analog::log(
                '[' . get_class($this) . '] Cannot store helloasso settings'
                . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Store tokens
     *
     * @return bool
     */
    private function storeTokens(): bool
    {
        try {
            $this->zdb->connection->beginTransaction();
            $update = $this->zdb->update(HELLOASSO_PREFIX . self::TABLE_TOKENS);
            $update->set(
                [
                    'value' => ':value',
                    'expiry' => ':expiry'
                ]
            )->where->equalTo('type', ':type');

            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            foreach (self::$tokens_types as $type) {
                $value = $this->tokens[$type];
                $expiry = $this->tokens[$type . '_expiry'];
                $stmt->execute(
                    [
                        'expiry' => $expiry,
                        'value' => $value,
                        'type' => $type
                    ]
                );
            }
            $this->zdb->connection->commit();

            Analog::log(
                'Helloasso tokens were successfully stored into database.',
                Analog::DEBUG
            );
            return true;
        } catch (\Exception $e) {
            $this->zdb->connection->rollBack();

            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());

            Analog::log(
                'Unable to store Helloasso tokens | ' . print_r($messages, true),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Helloasso Checkout
     *
     * @param array<string, mixed> $metadata Array of metadata to transmit with payment
     * @param float                $amount   Amount of payment
     *
     * @return array|bool
     */
    public function checkout(array $metadata, float $amount): array|bool
    {
        try {
            $tokens = $this->getTokens();
            $data = [
                'totalAmount' => (int)$amount,
                'initialAmount' => (int)$amount,
                'itemName' => $metadata['item_name'],
                'backUrl' => $this->preferences->getURL() . '/plugins/helloasso/cancel',
                'errorUrl' => $this->preferences->getURL() . '/plugins/helloasso/error',
                'returnUrl' => $this->preferences->getURL() . '/plugins/helloasso/success',
                'containsDonation' => false,
                'payer' => [
                    'firstName' => array_key_exists('checkout_firstname', $metadata) ? $metadata['checkout_firstname'] : null,
                    'lastName' => array_key_exists('checkout_name', $metadata) ? $metadata['checkout_name'] : null,
                    'email' => array_key_exists('checkout_email', $metadata) ? $metadata['checkout_email'] : null,
                    'address' => array_key_exists('checkout_address', $metadata) ? $metadata['checkout_address'] : null,
                    'city' => array_key_exists('checkout_city', $metadata) ? $metadata['checkout_city'] : null,
                    'zipCode' => array_key_exists('checkout_zipcode', $metadata) ? $metadata['checkout_zipcode'] : null,
                    'companyName' => array_key_exists('checkout_company', $metadata) ? $metadata['checkout_company'] : null
                ],
                'metadata' => $metadata
            ];

            $client = $this->setupClient();
            $headers = [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => 'Bearer ' . $tokens['access_token']
                ],
                'json' => $data,
            ];
            $request = $client->post($this->getApiRoute() . 'v5/organizations/' . $this->getOrganizationSlug() . '/checkout-intents', $headers);
            $contents = $request->getBody()->getContents();

            return json_decode($contents, true);
        } catch (\Exception $e) {
            Analog::log(
                '[' . get_class($this) . '] Cannot create Helloasso checkout'
                . '` | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Setup Guzzle client
     *
     * @return Client
     */
    public function setupClient(): Client
    {
        return new Client(
            $this->getClientOptions()
        );
    }

    /**
     * Get Guzzle client options
     *
     * @return array<string, mixed>
     */
    private function getClientOptions(): array
    {
        return $this->guzzle_options;
    }

    /**
     * Get tokens
     *
     * @return ?array<string, mixed>
     */
    public function getTokens(): ?array
    {
        if ($this->isAccessTokenExpired()) {
            if ($this->isRefreshTokenExpired()) {
                $this->getCredentials();
            } else {
                $this->refreshCredentials();
            }
        }
        return $this->tokens;
    }

    /**
     * Is access token expired ?
     *
     * @return bool
     */
    public function isAccessTokenExpired(): bool
    {
        if ($this->tokens['access_token_expiry'] == null) {
            return true;
        }
        return new \DateTime() > new \DateTime($this->tokens['access_token_expiry']);
    }

    /**
     * Is refresh token expired ?
     *
     * @return bool
     */
    public function isRefreshTokenExpired(): bool
    {
        if ($this->tokens['refresh_token_expiry'] == null) {
            return true;
        }
        return new \DateTime() > new \DateTime($this->tokens['refresh_token_expiry']);
    }

    /**
     * Get credentials
     *
     * @return bool
     */
    private function getCredentials(): bool
    {
        return $this->getClientCredentials([
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'grant_type' => 'client_credentials',
        ]);
    }

    /**
     * Refresh credentials
     *
     * @return bool
     */
    private function refreshCredentials(): bool
    {
        return $this->getClientCredentials([
            'client_id' => $this->getClientId(),
            'refresh_token' => $this->tokens['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * Get client credentials
     *
     * @param array $parameters Parameters required by API
     *
     * @return bool
     */
    private function getClientCredentials(array $parameters): bool
    {
        try {
            $client = $this->setupClient();
            $post_parameters = [
                'form_params' => $parameters
            ];
            $now = new \Datetime();
            $request = $client->request('POST', $this->getApiRoute(true) . 'token', $post_parameters);
            $contents = $request->getBody()->getContents();
            $json = json_decode($contents, true);

            $this->tokens['access_token'] = $json['access_token'];
            $this->tokens['access_token_expiry'] = $now->add(\DateInterval::createFromDateString($json['expires_in'] . 'second'))->format('Y-m-d H:i:s');
            // Refresh token is valid for 30 days as stated by the API
            $this->tokens['refresh_token_expiry'] = $now->add(\DateInterval::createFromDateString('29 days'))->format('Y-m-d H:i:s');
            $this->tokens['refresh_token'] = $json['refresh_token'];

            $this->storeTokens();

            return true;
        } catch (\Throwable $e) {
            Analog::log(
                'Error while connecting to Helloasso: ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get API route
     *
     * @param ?bool $auth Get authentification route if true
     *
     * @return string
     */
    public function getApiRoute(?bool $auth = null): string
    {
        $route = self::API_ROUTE;
        if ($this->getTestMode()) {
            $route = self::TEST_API_ROUTE;
        }
        if ($auth) {
            $route = $route . 'oauth2/';
        }
        return $route;
    }

    /**
     * Get organization
     *
     * @return array<string, mixed>
     */
    public function getOrganization(): array
    {
        try {
            $tokens = $this->getTokens();
            $client = $this->setupClient();
            $headers = [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => 'Bearer ' . $tokens['access_token']
                ]
            ];
            $request = $client->request('GET', $this->getApiRoute() . 'v5/organizations/' . $this->getOrganizationSlug(), $headers);
            $contents = $request->getBody()->getContents();
            return json_decode($contents, true);
        } catch (\Throwable $e) {
            Analog::log(
                'Exception when calling OrganisationApi->organizationsOrganizationSlugGet: ' . $e->getMessage(),
                Analog::ERROR
            );
            return [];
        }
    }

    /**
     * Get test mode
     *
     * @return bool
     */
    public function getTestMode(): bool
    {
        return $this->test_mode;
    }

    /**
     * Get Helloasso organization slug
     *
     * @return string
     */
    public function getOrganizationSlug(): ?string
    {
        return $this->organization_slug;
    }

    /**
     * Get Helloasso clientId
     *
     * @return string
     */
    public function getClientId(): ?string
    {
        return $this->client_id;
    }

    /**
     * Get Helloasso clientSecret
     *
     * @return string
     */
    public function getClientSecret(): ?string
    {
        return $this->client_secret;
    }

    /**
     * Get loaded and active amounts
     *
     * @param Login $login Login instance
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAmounts(Login $login): array
    {
        $prices = [];
        foreach ($this->prices as $k => $v) {
            $amount = $v['amount'];
            if (!$this->isInactive($k) && $amount > 0) {
                if ($login->isLogged() || $v['extra'] == ContributionsTypes::DONATION_TYPE) {
                    $prices[$k] = $v;
                }
            }
        }
        return $prices;
    }

    /**
     * Get loaded amounts
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAllAmounts(): array
    {
        return $this->prices;
    }

    /**
     * Is the plugin loaded?
     *
     * @return boolean
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Are amounts loaded?
     *
     * @return boolean
     */
    public function areAmountsLoaded(): bool
    {
        return $this->amounts_loaded;
    }

    /**
     * Set Test mode
     *
     * @param bool $enabled True to enable test mode
     *
     * @return void
     */
    public function setTestMode(bool $enabled): void
    {
        $this->test_mode = $enabled;
    }

    /**
     * Set Helloasso organization slug
     *
     * @param string $organization_slug organization slug
     *
     * @return void
     */
    public function setOrganizationSlug(string $organization_slug): void
    {
        $this->organization_slug = $organization_slug;
    }

    /**
     * Set Helloasso clientId
     *
     * @param string $client_id clientId
     *
     * @return void
     */
    public function setClientId(string $client_id): void
    {
        $this->client_id = $client_id;
    }

    /**
     * Set Helloasso clientSecret
     *
     * @param string $client_secret clientSecret
     *
     * @return void
     */
    public function setClientSecret(string $client_secret): void
    {
        $this->client_secret = $client_secret;
    }

    /**
     * Set new prices
     *
     * @param array<int, string> $ids     array of identifier
     * @param array<int, string> $amounts array of amounts
     *
     * @return void
     */
    public function setPrices(array $ids, array $amounts): void
    {
        $this->prices = [];
        foreach ($ids as $k => $id) {
            $this->prices[$id]['amount'] = $amounts[$k];
        }
    }

    /**
     * Check if the specified contribution is active
     *
     * @param int $id type identifier
     *
     * @return boolean
     */
    public function isInactive(int $id): bool
    {
        return in_array($id, $this->inactives);
    }

    /**
     * Set inactives types
     *
     * @param array<int, string> $inactives array of inactives types
     *
     * @return void
     */
    public function setInactives(array $inactives): void
    {
        $this->inactives = $inactives;
    }

    /**
     * Unset inactives types
     *
     * @return void
     */
    public function unsetInactives(): void
    {
        $this->inactives = [];
    }
}
