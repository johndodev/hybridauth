<?php
/*!
* Hybridauth
* https://hybridauth.github.io | https://github.com/hybridauth/hybridauth
*  (c) 2017 Hybridauth authors | https://hybridauth.github.io/license.html
*/

namespace Hybridauth\Provider;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\Data;
use Hybridauth\User;

/**
 * Instagram OAuth2 provider adapter.
 */
class Instagram extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    protected $scope = 'follower_list';

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl = 'https://graph.facebook.com/v2.8/';

    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl = 'https://api.instagram.com/oauth/authorize/';

    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl = 'https://api.instagram.com/oauth/access_token';

    /**
     * {@inheritdoc}
     */
    protected $apiDocumentation = 'https://www.instagram.com/developer/authentication/';

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        parent::initialize();

        // The Instagram API requires an access_token from authenticated users
        // for each endpoint, see https://www.instagram.com/developer/endpoints.
        $accessToken = $this->getStoredData($this->accessTokenName);
        $this->apiRequestParameters[$this->accessTokenName] = $accessToken;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfile()
    {
        $response = $this->apiRequest('users/self/');

        $data = new Data\Collection($response);

        if (! $data->exists('data')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();

        $data = $data->filter('data');

        $userProfile->identifier  = $data->get('id');
        $userProfile->description = $data->get('bio');
        $userProfile->photoURL    = $data->get('profile_picture');
        $userProfile->webSiteURL  = $data->get('website');
        $userProfile->displayName = $data->get('full_name');
        $userProfile->displayName = $userProfile->displayName ?: $data->get('username');

        $userProfile->data = (array) $data->get('counts');

        return $userProfile;
    }

    public function getUserContacts()
    {
        $contacts = [];

        $apiUrl = 'users/self/follows';

        do {
            $response = $this->apiRequest($apiUrl);
var_dump($response);die;
            $data = new Data\Collection($response);

            if (! $data->exists('data')) {
                throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
            }

            if ($data->filter('data')->isEmpty()) {
                continue;
            }

            foreach ($data->filter('data')->toArray() as $item) {
                $contacts[] = $this->fetchUserContact($item);
            }

            if ($data->filter('paging')->exists('next')) {
                $apiUrl = $data->filter('paging')->get('next');

                $pagedList = true;
            } else {
                $pagedList = false;
            }
        } while ($pagedList);

        return $contacts;
    }

    /**
     * Parse the user contact.
     *
     * @param array $item
     *
     * @return \Hybridauth\User\Contact
     */
    protected function fetchUserContact($item)
    {
        $userContact = new User\Contact();
var_dump($item);die;
        $item = new Data\Collection($item);

        $userContact->identifier  = $item->get('id');
        $userContact->displayName = $item->get('full_name');
        $userContact->photoURL = $item->get('profile_picture');

        return $userContact;
    }
}
