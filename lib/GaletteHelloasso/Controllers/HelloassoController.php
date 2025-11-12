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

namespace GaletteHelloasso\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Controllers\AbstractPluginController;
use Galette\Core\History;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\PaymentType;
use Galette\Filters\HistoryList;
use GaletteHelloasso\Helloasso;
use GaletteHelloasso\HelloassoHistory;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Galette Helloasso plugin controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

class HelloassoController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Helloasso")]
    protected array $module_info;

    /**
     * Main form
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function form(Request $request, Response $response): Response
    {
        $helloasso = new Helloasso($this->zdb, $this->preferences);

        $current_url = $this->preferences->getURL();

        $params = [
            'helloasso'     => $helloasso,
            'amounts'       => $helloasso->getAmounts($this->login),
            'page_title'    => _T('HelloAsso payment', 'helloasso'),
            'message'       => null,
            'current_url'   => rtrim($current_url, '/'),
        ];

        if (!$helloasso->isLoaded()) {
            $this->flash->addMessageNow(
                'error',
                _T("<strong>Payment could not work</strong>: An error occurred (that has been logged) while loading Helloasso settings from the database.<br/>Please report the issue to the staff.", "helloasso")
                . '<br/>' . _T("Our apologies for the annoyance.", "helloasso")
            );
        }

        if ($helloasso->getOrganizationSlug() == null || $helloasso->getClientId() == null || $helloasso->getClientSecret() == null) {
            $this->flash->addMessageNow(
                'error',
                _T("Helloasso details have not been defined. Please ask an administrator to add them in the plugin's settings.", "helloasso")
            );
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('helloasso_form'),
            $params
        );
        return $response;
    }

    /**
     * Checkout form
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function formCheckout(Request $request, Response $response): Response
    {
        $helloasso_request = $request->getParsedBody();
        $helloasso = new Helloasso($this->zdb, $this->preferences);
        $adherent = new Adherent($this->zdb);
        $contribution_type = new ContributionsTypes($this->zdb, (int) $helloasso_request['item_id']);

        $current_url = $this->preferences->getURL();

        // Check the amount
        $item_id = $helloasso_request['item_id'];
        $helloasso_amounts = $helloasso->getAmounts($this->login);
        $amount = $helloasso_request['amount'];
        $amount_check = $helloasso_amounts[$item_id]['amount'];

        if ($amount < $amount_check) {
            $this->flash->addMessage(
                'error_detected',
                _T("The amount you've entered is lower than the minimum amount for the selected option. Please choose another option or change the amount.", "helloasso")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('helloasso_form'));
        } else {
            $metadata = [];

            if ($this->login->isLogged() && !$this->login->isSuperAdmin()) {
                $adherent->load($this->login->id);
                $metadata['member_id'] = $this->login->id;
            }

            $metadata['item_id'] = $item_id;
            $metadata['item_name'] = $helloasso_amounts[$item_id]['name'];

            $contains_donation = $contribution_type->isExtension() ? false : true;

            $checkout = $helloasso->checkout($metadata, $amount * 100, $contains_donation);

            if (!$checkout) {
                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occured redirecting to the checkout form.', 'helloasso')
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('helloasso_form'));
            } else {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $checkout['redirectUrl']);
            }
        }
    }

    /**
     * Logs page
     *
     * @param Request         $request  PSR Request
     * @param Response        $response PSR Response
     * @param string|null     $option   Either order, reset or page
     * @param string|int|null $value    Option value
     *
     * @return Response
     */
    public function logs(
        Request $request,
        Response $response,
        ?string $option = null,
        string|int|null $value = null
    ): Response {
        $helloasso_history = new HelloassoHistory($this->zdb, $this->login, $this->preferences);

        $filters = [];
        if (isset($this->session->filter_helloasso_history)) {
            $filters = $this->session->filter_helloasso_history;
        } else {
            $filters = new HistoryList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int) $value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
                case 'reset':
                    $filters = new HistoryList();
                    break;
            }
        }
        $this->session->filter_helloasso_history = $filters;

        //assign pagination variables to the template and add pagination links
        $helloasso_history->setFilters($filters);
        $logs = $helloasso_history->getHelloassoHistory();
        $logs_count = $helloasso_history->getCount();
        $filters->setViewPagination($this->routeparser, $this->view);

        $params = [
            'page_title'        => _T("Helloasso History", "helloasso"),
            'helloasso_history' => $helloasso_history,
            'logs'              => $logs,
            'nb'                => $logs_count,
            'module_id'         => $this->getModuleId()
        ];

        $this->session->filter_helloasso_history = $filters;

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('helloasso_history'),
            $params
        );
        return $response;
    }

    /**
     * Filter
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        //reset history
        $filters = $this->session->filter_helloasso_history ?? new HistoryList();
        if (isset($post['reset']) && isset($post['nbshow'])) {
        } else {
            //number of rows to show
            $filters->show = $post['nbshow'];
        }

        $this->session->filter_helloasso_history = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('helloasso_history'));
    }

    /**
     * Preferences
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function preferences(Request $request, Response $response): Response
    {
        if ($this->session->helloasso !== null) {
            $helloasso = $this->session->helloasso;
            $this->session->helloasso = null;
        } else {
            $helloasso = new Helloasso($this->zdb, $this->preferences);
        }

        $amounts = $helloasso->getAllAmounts();
        $tab = $request->getQueryParams()['tab'] ?? 'helloasso';

        $params = [
            'page_title'    => _T('Helloasso Settings', 'helloasso'),
            'helloasso'     => $helloasso,
            'webhook_url'   => $this->preferences->getURL() . $this->routeparser->urlFor('helloasso_webhook'),
            'amounts'       => $amounts,
            'tab'           => $tab,
            'documentation' => 'https://galette-community.github.io/plugin-helloasso/documentation.html#pr%C3%A9f%C3%A9rences'
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('helloasso_preferences'),
            $params
        );
        return $response;
    }

    /**
     * Store Preferences
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function storePreferences(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $helloasso = new Helloasso($this->zdb, $this->preferences);

        if ($this->login->isAdmin()) {
            if (array_key_exists('helloasso_test_mode', $post)) {
                $helloasso->setTestMode(true);
            } else {
                $helloasso->setTestMode(false);
            }
            if (isset($post['helloasso_organization_slug'])) {
                $helloasso->setOrganizationSlug($post['helloasso_organization_slug']);
            }
            if (isset($post['helloasso_client_id'])) {
                $helloasso->setClientId($post['helloasso_client_id']);
            }
            if (isset($post['helloasso_client_secret'])) {
                $helloasso->setClientSecret($post['helloasso_client_secret']);
            }
        }
        if (isset($post['inactives'])) {
            $helloasso->setInactives($post['inactives']);
        } else {
            $helloasso->unsetInactives();
        }

        $stored = $helloasso->store();
        if ($stored) {
            $this->flash->addMessage(
                'success_detected',
                _T('Helloasso settings have been saved.', 'helloasso')
            );
        } else {
            $this->session->helloasso = $helloasso;
            $this->flash->addMessage(
                'error_detected',
                _T('An error occured saving helloasso settings.', 'helloasso')
            );
        }

        if (isset($post['tab']) && $post['tab'] != 'helloasso') {
            $tab = '?tab=' . $post['tab'];
        } else {
            $tab = '';
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('helloasso_preferences') . $tab);
    }

    /**
     * Webhook
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function webhook(Request $request, Response $response): Response
    {
        $body = $request->getBody();
        $post = json_decode($body->getContents(), true);
        $helloasso = new Helloasso($this->zdb, $this->preferences);

        // Verify notification authenticity
        // https://dev.helloasso.com/docs/secure-webhook
        $legit_ip_address = $helloasso->getTestMode() ? '4.233.135.234' : '51.138.206.200';
        $notification_ip_address = History::findUserIPAddress();
        if ($notification_ip_address != $legit_ip_address) {
            Analog::log(
                'Unauthorized Helloasso notification detected!',
                Analog::ERROR
            );
            return $response->withStatus(403);
        }

        Analog::log(
            "Helloasso webhook request: " . var_export($post, true),
            Analog::DEBUG
        );

        if (
            (isset($post['eventType']) && $post['eventType'] == 'Payment')
            && (isset($post['data']['state']) && $post['data']['state'] == 'Authorized')
            && $post['metadata']['item_id']
        ) {
            $hh = new HelloassoHistory($this->zdb, $this->login, $this->preferences);
            $hh->add($post);

            // are we working on a real contribution?
            $real_contrib = false;
            if (array_key_exists('member_id', $post['metadata'])) {
                $real_contrib = true;
            }

            if ($hh->isProcessed($post)) {
                Analog::log(
                    'A helloasso payment notification has been received, but it is already processed!',
                    Analog::WARNING
                );
                $hh->setState(HelloassoHistory::STATE_ALREADYDONE);
            } else {
                // we'll now try to add the relevant cotisation
                if ($post['data']['cashOutState'] == 'Transfered') {
                    /**
                    * We will use the following parameters:
                    * - $post['data']['amount']: the amount
                    * - $post['metadata']['member_id']: member id
                    * - $post['metadata']['item_id']: contribution type id
                    *
                    * If no member id is provided, we only send to post contribution
                    * script, Galette does not handle anonymous contributions
                    */
                    $amount = $post['data']['amount'];
                    $member_id = array_key_exists('member_id', $post['metadata']) ? $post['metadata']['member_id'] : '';
                    $contrib_args = [
                        'type'          => $post['metadata']['item_id'],
                        'adh'           => $member_id,
                        'payment_type'  => PaymentType::HELLOASSO
                    ];
                    $check_contrib_args = [
                        ContributionsTypes::PK  => $post['metadata']['item_id'],
                        Adherent::PK            => $member_id,
                        'type_paiement_cotis'   => PaymentType::HELLOASSO,
                        'montant_cotis'         => $amount / 100,
                    ];
                    if ($this->preferences->pref_membership_ext != '') { //@phpstan-ignore-line
                        $contrib_args['ext'] = $this->preferences->pref_membership_ext;
                    }
                    $contrib = new Contribution($this->zdb, $this->login, $contrib_args);

                    // all goes well, we can proceed
                    if ($real_contrib) {
                        // Check contribution to set $contrib->errors to [] and handle contribution overlap
                        $valid = $contrib->setNoCheckLogin()->check($check_contrib_args, [], []);
                        if ($valid !== true) {
                            Analog::log(
                                'Cannot create invalid contribution from Helloasso payment:'
                                . implode("\n   ", $valid),
                                Analog::ERROR
                            );
                            $hh->setState(HelloassoHistory::STATE_ERROR);
                            return $response->withStatus(500, 'Internal error');
                        }

                        if ($contrib->store()) {
                            // contribution has been stored :)
                            Analog::log(
                                'Helloasso payment has been successfully registered as a contribution',
                                Analog::DEBUG
                            );
                            $hh->setState(HelloassoHistory::STATE_PROCESSED);
                        } else {
                            // something went wrong :'(
                            Analog::log(
                                'An error occured while storing a new contribution from Helloasso payment',
                                Analog::ERROR
                            );
                            $hh->setState(HelloassoHistory::STATE_ERROR);
                            return $response->withStatus(500, 'Internal error');
                        }
                        return $response->withStatus(200);
                    }
                } else {
                    Analog::log(
                        'A helloasso payment notification has been received, but is not completed!',
                        Analog::WARNING
                    );
                    $hh->setState(HelloassoHistory::STATE_INCOMPLETE);
                    return $response->withStatus(500, 'Internal error');
                }
            }
            return $response->withStatus(200);
        } else {
            // Ignore all other helloasso events.
            Analog::log(
                'Helloasso event ignored. Only Authorized Payments events are processed.',
                Analog::DEBUG
            );
            return $response->withStatus(200);
        }
    }

    /**
     * Return URL
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function returnUrl(Request $request, Response $response): Response
    {
        $params = [
            'page_title'    => _T('Helloasso payment success', 'helloasso')
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('helloasso_success'),
            $params
        );
        return $response;
    }

    /**
     * Cancel URL
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function cancelUrl(Request $request, Response $response): Response
    {
        $this->flash->addMessage(
            'warning_detected',
            _T('Your payment has been aborted!', 'helloasso')
        );
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('helloasso_form'));
    }

    /**
     * Error URL
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function errorUrl(Request $request, Response $response): Response
    {
        $helloasso_request = $request->getQueryParams();

        $params = [
            'page_title'    => _T('Helloasso payment failure', 'helloasso'),
            'error'         => $helloasso_request
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('helloasso_error'),
            $params
        );
        return $response;
    }
}
