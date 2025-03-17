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
            $userInfo = $this->oidc->requestUserInfo();
            // dd([
            //     'username' => $userInfo->preferred_username ?? $userInfo->email,
            //     'name' => $userInfo->name ?? trim(($userInfo->given_name ?? '') . ' ' . ($userInfo->family_name ?? '')),
            //     'email' => $userInfo->email ?? '',
            //     'employeetype' => $userInfo->employeetype ?? 'N/A', // Wenn nicht vorhanden, Dummy
            // ]);
            return [
                'username' => $userInfo->preferred_username ?? $userInfo->email,
                'name' => $userInfo->name ?? trim(($userInfo->given_name ?? '') . ' ' . ($userInfo->family_name ?? '')),
                'email' => $userInfo->email ?? '',
                'employeetype' => $userInfo->employeetype ?? 'N/A', // Wenn nicht vorhanden, Dummy
            ];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 401);
        }
    }
    
}