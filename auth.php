<?php

function authLDAP($username, $password)
{
    if (($connection = ldap_connect($templateData['LDAP_HOST'])) == false) {
        return ['authStatus' => 'Verbindung zum LDAP-Server konnte nicht hergestellt werden'];
    }

    ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);

    if (($link = ldap_bind($connection, $templateData['LDAP_BASE_DN'], $templateData['LDAP_BIND_PW'])) == false) {
        return ['authStatus' => 'Bind fehlgeschlagen'];
    }

    $sanitizedUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

    $filter = "(|(uid=$sanitizedUsername)(mail=$sanitizedUsername))";

    if (($result = ldap_search($connection, $templateData['LDAP_SEARCH_DN'], $filter)) == false) {
        return ['authStatus' => 'Suche im LDAP-Baum fehlgeschlagen'];
    }

    if (($entry_id = ldap_first_entry($connection, $result)) == false) {
        return ['authStatus' => 'Eintrag des Suchergenisses konnte nicht abgeholt werden'];
    }

    if (($user_dn = ldap_get_dn($connection, $entry_id)) == false) {
        return ['authStatus' => 'Benutzername oder Passwort falsch'];
    }

    if ((ldap_bind($connection, $user_dn, $password)) == false) {
        return ['authStatus' => 'Benutzername oder Passwort falsch'];
    }

    $info = ldap_get_entries($connection, $result);
    $userinfo = $info[0];
    if (isset($userinfo['displayname'])) {
        // Attribute displayname is set, expect to be comma separated names =>
        // extract first characters
        $name = $userinfo['displayname'][0];
        $parts = explode(', ', $name);
        $initials = substr($parts[1], 0, 1) . substr($parts[0], 0, 1);
    } else {
        if (!isset($userinfo['givenname']) || !isset($userinfo['sn'])) {
            return ['authStatus' => 'Unerwartete Datenstruktur von LDAP erhalten'];
        }
        $firstname = $userinfo['givenname'][0];
        $surname = $userinfo['sn'][0];
    }

    ldap_unbind($connection);
    return ['authStatus' => 'ok', 'firstname' => $firstname, 'surname' => $surname];
}

function authLTI($LTI_CONSUMER_KEY, $LTI_CONSUMER_SECRET, $post_oauth_consumer_key)
{
    $ok = $post_oauth_consumer_key === $LTI_CONSUMER_KEY;

    $provider = new OAuthProvider();

    $provider->consumerKey = $LTI_CONSUMER_KEY;
    $provider->consumerSecret = $LTI_CONSUMER_SECRET;

    $provider->consumerHandler(function ($provider) use ($LTI_CONSUMER_SECRET) {
        $provider->consumer_secret = $LTI_CONSUMER_SECRET;
        return OAUTH_OK;
    });

    $provider->timestampNonceHandler(function ($provider) {
        return OAUTH_OK;
    });

    try {
        $provider->isRequestTokenEndpoint(true);
        $provider->checkOAuthRequest();
    } catch (OAuthException $e) {
        $ok = false;
    }

    if ($ok) {
        $firstname = $_POST['lis_person_name_given'];
        $surname = $_POST['lis_person_name_family'];

        return ['authStatus' => 'ok', 'firstname' => $firstname, 'surname' => $surname];
    } else {
        return ['authStatus' => 'Login gescheitert'];
    }
}
