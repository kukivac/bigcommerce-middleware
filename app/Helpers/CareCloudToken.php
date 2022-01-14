<?php

namespace App\Helpers;

class CareCloudToken
{
    /**
     * @param string $bearerToken
     * @param string $expires
     */
    public function __construct(public string $bearerToken, public string $expires)
    {
    }
}
