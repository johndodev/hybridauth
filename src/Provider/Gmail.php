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
 * Google OAuth2 provider adapter.
 *
 * Example:
 *
 *   $config = [
 *       'callback' => Hybridauth\HttpClient\Util::getCurrentUrl(),
 *       'keys'     => [ 'id' => '', 'secret' => '' ],
 *       'scope'    => 'profile https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/plus.profile.emails.read',
 *
 *        // google's custom auth url params
 *       'authorize_url_parameters' => [
 *              'approval_prompt' => 'force', // to pass only when you need to acquire a new refresh token.
 *              'access_type'     => ..,      // is set to 'offline' by default
 *              'hd'              => ..,
 *              'state'           => ..,
 *              // etc.
 *       ]
 *   ];
 *
 *   $adapter = new Hybridauth\Provider\Google( $config );
 *
 *   try {
 *       $adapter->authenticate();
 *
 *       $userProfile = $adapter->getUserProfile();
 *       $tokens = $adapter->getAccessToken();
 *       $contacts = $adapter->getUserContacts(['max-results' => 75]);
 *   }
 *   catch( Exception $e ){
 *       echo $e->getMessage() ;
 *   }
 */
class Gmail extends OAuth2
{
    /**
    * {@inheritdoc}
    */
    public $scope = 'profile email https://www.googleapis.com/auth/contacts.readonly https://www.googleapis.com/auth/user.phonenumbers.read';

    /**
    * {@inheritdoc}
    */
    protected $apiBaseUrl = 'https://people.googleapis.com/v1/';

    /**
    * {@inheritdoc}
    */
    protected $authorizeUrl = 'https://accounts.google.com/o/oauth2/auth';

    /**
    * {@inheritdoc}
    */
    protected $accessTokenUrl = 'https://accounts.google.com/o/oauth2/token';

    /**
    * {@inheritdoc}
    */
    protected $apiDocumentation = 'https://developers.google.com/identity/protocols/OAuth2';

    /**
    * {@inheritdoc}
    */
    protected function initialize()
    {
        parent::initialize();

        $this->AuthorizeUrlParameters += [
            'access_type' => 'offline'
        ];

        $this->tokenRefreshParameters += [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
    }

    /**
    * {@inheritdoc}
    */
    public function getUserProfile()
    {
        // TODO phone numbers
        $response = $this->apiRequest('people/me?personFields=emailAddresses,names,photos,phoneNumbers');

        $data = new Data\Collection($response);

        if (! $data->exists('resourceName')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();

        $userProfile->identifier  = str_replace('people/', '', $data->get('resourceName'));
        $userProfile->profileURL  = $data->get('resourceName');

        foreach ($data->get('names') as $name) {
            if ($name->metadata->primary) {
                $userProfile->firstName   = $name->givenName;
                $userProfile->lastName    = $name->familyName;
                $userProfile->displayName = $name->displayName;
                break;
            }
        }

        foreach ($data->get('photos') as $photo) {
            if ($photo->metadata->primary) {
                $userProfile->photoURL = $photo->url;
                break;
            }
        }

        foreach ($data->get('emailAddresses') as $email) {
            $userProfile->addEmail($email->value);
        }

        foreach ($data->get('phoneNumbers') as $phoneNumber) {
            $userProfile->addPhoneNumber($phoneNumber->canonicalForm ?? $phoneNumber->value);
        }

        return $userProfile;
    }


    /**
    * Retrieve Gmail contacts
    */
    public function getUserContacts($parameters = [])
    {
        $response = $this->apiRequest('people/me/connections?personFields=emailAddresses,names,photos,phoneNumbers');

        if (!$response) {
            return [];
        }

        $datas = new Data\Collection($response);

        $contacts = [];

        foreach ($datas->get('connections') as $connection) {

            $contact = new User\Contact();

            $contact->identifier = $connection->resourceName;
            $contact->profileURL = $connection->resourceName;

            if (isset($connection->names)) {
                foreach ($connection->names as $name) {
                    if ($name->metadata->primary) {
                        $contact->displayName = $name->displayName;
                        break;
                    }
                }
            }

            if (isset($connection->photos)) {
                foreach ($connection->photos as $photo) {
                    if ($photo->metadata->primary) {
                        $contact->photoURL = $photo->url;
                        break;
                    }
                }
            }

            if (isset($connection->emailAddresses)) {
                foreach ($connection->emailAddresses as $email) {
                    $contact->addEmail($email->value);

                    if (!$contact->displayName) {
                        $contact->displayName = $contact->email;
                    }
                }
            }

            if (isset($connection->phoneNumbers)) {
                foreach ($connection->phoneNumbers as $phoneNumber) {
                    $contact->addPhoneNumber($phoneNumber->canonicalForm ?? $phoneNumber->value);
                }
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

}
