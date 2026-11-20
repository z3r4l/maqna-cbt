<?php

namespace Config;

use CodeIgniter\Shield\Config\Auth as ShieldAuth;

class Auth extends ShieldAuth
{
    public array $validFields = ['email', 'username'];
    public bool $allowRegistration = false;
}
