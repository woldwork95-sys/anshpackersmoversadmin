<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Limits for the admin sign-in form.
 */
class AdminAuth extends BaseConfig
{
    /**
     * Failed and attempted sign-ins allowed per address in the decay window.
     */
    public int $maxAttempts = 5;

    /**
     * Seconds before the attempt bucket refills.
     */
    public int $decaySeconds = 60;
}
