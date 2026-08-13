<?php

namespace Illuminate\Http;

interface Request
{
    /**
     * @return \App\Models\Auth\User|null
     */
    public function user($guard = null);
}