<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ActiveDirectory extends BaseConfig
{
    /**
     * --------------------------------------------------------------------
     * Enable Active Directory Authentication
     * --------------------------------------------------------------------
     * Set to true to enable AD/LDAP login attempt. If set to false,
     * the system will immediately bypass AD and use local DB credentials.
     */
    public bool $enableAD = false;

    /**
     * --------------------------------------------------------------------
     * LDAP Host URL
     * --------------------------------------------------------------------
     * Specify the Active Directory server hostname or IP address.
     * Use 'ldap://hostname' (port 389) or secure 'ldaps://hostname' (port 636).
     */
    public string $host = 'ldap://ad.example.local';

    /**
     * --------------------------------------------------------------------
     * LDAP Port
     * --------------------------------------------------------------------
     * Default is 389 for standard LDAP, 636 for secure LDAPS.
     */
    public int $port = 389;

    /**
     * --------------------------------------------------------------------
     * AD Active Directory Domain Suffix
     * --------------------------------------------------------------------
     * The base domain suffix for User Principal Name (UPN) authentication.
     * e.g., 'example.local' (binds as 'username@example.local')
     */
    public string $domain = 'example.local';

    /**
     * --------------------------------------------------------------------
     * Bind Format
     * --------------------------------------------------------------------
     * How to construct the login identity string for AD bind:
     * - 'upn':   Uses 'username@domain.local'
     * - 'prefix': Uses 'NETBIOS\username' (requires $netbiosDomain below)
     */
    public string $bindFormat = 'upn';

    /**
     * --------------------------------------------------------------------
     * NetBIOS Domain Name
     * --------------------------------------------------------------------
     * Only used if $bindFormat is set to 'prefix'.
     * e.g., 'CORP' -> binds as 'CORP\username'
     */
    public string $netbiosDomain = 'CORP';

    /**
     * --------------------------------------------------------------------
     * Fallback to Local Database
     * --------------------------------------------------------------------
     * If true, if AD login fails or is unreachable, the system will
     * fallback to standard local Shield database password checking.
     * This is highly recommended so local administrators ('admin')
     * can log in even if the Active Directory domain controller is offline.
     */
    public bool $fallbackToLocal = true;

    /**
     * --------------------------------------------------------------------
     * Auto-Create User (Just-In-Time Provisioning)
     * --------------------------------------------------------------------
     * If a user successfully logs in via AD but does not exist in the local
     * 'users' database table, auto-create a local Shield user record.
     */
    public bool $autoCreateUser = true;

    /**
     * --------------------------------------------------------------------
     * Default Group for Auto-Created Users
     * --------------------------------------------------------------------
     * The CodeIgniter Shield auth group assigned to JIT provisioned users.
     * Options match your Config/AuthGroups.php (e.g. 'user', 'admin').
     */
    public string $defaultGroup = 'user';
}
