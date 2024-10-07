<?php

namespace App\Traits;

use Illuminate\Support\Facades\Gate;

trait RequestsAppends
{
    /**
     * Append data from any of the request traits
     *
     * @param  Illuminate\Http\Request $request
     * @return self
     */
    public function loadDataFromRequest($request = null)
    {
        if (! $request) {
            $request = request();
        }

        return $this->appendsRequest($request)->loadRequest($request)->loadPolicies($request);
    }
}
