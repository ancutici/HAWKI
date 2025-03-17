<?php

namespace App\Services\Auth;

use Jumbojett\OpenIDConnectClient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;


class OidcService
{
    protected $oidc;

    public function __construct()
    {
        // Retrieve configuration settings
        $idp = config('open_id_connect.oidc_idp');
        $clientId = config('open_id_connect.oidc_client_id');
        $clientSecret = config('open_id_connect.oidc_client_secret');

        // Validate configuration settings
        if (empty($idp) || empty($clientId) || empty($clientSecret)) {
            throw new \InvalidArgumentException('OIDC configuration variables are not set properly.');
        }

        // Initialize the OpenID Connect client
        $this->oidc = new OpenIDConnectClient($idp, $clientId, $clientSecret);

        // Add scopes as an array
        $scopes = config('open_id_connect.oidc_scopes');
        $this->oidc->addScope($scopes);
    }

    public function authenticate()
    {
        try {
            $this->oidc->authenticate();
    
            // Attribute mapping from config
            $usernameAttr = config('open_id_connect.attribute_map.username', 'preferred_username');
            $firstNameAttr = config('open_id_connect.attribute_map.firstname', 'given_name');
            $lastNameAttr = config('open_id_connect.attribute_map.lastname', 'family_name');
            $emailAttr = config('open_id_connect.attribute_map.email', 'email');
            $employeetypeAttr = config('open_id_connect.attribute_map.employeetype', 'employeetype');
    
            // Request attributes from userinfo
            $username = $this->oidc->requestUserInfo($usernameAttr);
            $firstname = $this->oidc->requestUserInfo($firstNameAttr);
            $lastname = $this->oidc->requestUserInfo($lastNameAttr);
            $email = $this->oidc->requestUserInfo($emailAttr);
            $employeetype = $this->oidc->requestUserInfo($employeetypeAttr);
    
            return [
                'username' => $username,
                'name' => trim("$firstname $lastname"),
                'email' => $email,
                'employeetype' => $employeetype ?: 'N/A', // Dummy fallback
            ];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 401);
        }
    }
}