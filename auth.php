<?php

function authLDAP($username, $password)
{
    if (($ldapConn = ldap_connect($templateData['LDAP_HOST'])) == false) {
        return ['authStatus' => 'Unable to connect to LDAP server'];
    }

    if (!ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
        return ['authStatus' => 'Unable to set LDAP protocol version'];
    }

    if (($link = ldap_bind($ldapConn, $templateData['LDAP_BASE_DN'], $templateData['LDAP_BIND_PW'])) == false) {
        return ['authStatus' => 'Unable to bind to LDAP server with provided DN and password'];
    }

    $sanitizedUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

    $filter = "(|(uid=$sanitizedUsername)(mail=$sanitizedUsername))";

    if (($result = ldap_search($ldapConn, $templateData['LDAP_SEARCH_DN'], $filter)) == false) {
        return ['authStatus' => 'LDAP search failed'];
    }

    if (($entry_id = ldap_first_entry($ldapConn, $result)) == false) {
        return ['authStatus' => 'Unable to get the first entry from the search results'];
    }

    if (($user_dn = ldap_get_dn($ldapConn, $entry_id)) == false) {
        return ['authStatus' => 'Unable to get DN from the entry'];
    }

    if ((ldap_bind($ldapConn, $user_dn, $password)) == false) {
        return ['authStatus' => 'Unable to bind with provided user DN and password'];
    }

    $info = ldap_get_entries($ldapConn, $result);
    $name = $info[0]["displayname"][0];
    $parts = explode(", ", $name);
    $initials = substr($parts[1], 0, 1) . substr($parts[0], 0, 1);
    $firstname = $parts[1];
    $surname = $parts[0];
    $employeetype = "-";

    ldap_unbind($ldapConn);
    return ['authStatus' => 'ok', 'firstname' => $firstname, 'surname' => $surname, 'employeetype' => $employeetype];
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
        $employeetype = "-";

        return ['authStatus' => 'ok', 'firstname' => $firstname, 'surname' => $surname, 'employeetype' => $employeetype];
    } else {
        return ['authStatus' => 'Login gescheitert'];
    }
}
