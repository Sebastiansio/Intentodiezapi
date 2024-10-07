<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait LazyLoads
{
    /**
     * Append attributes to query when building a query.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function lazyLoad($attributes)
    {
        $attributes = collect(is_string($attributes) ? func_get_args() : $attributes);

        if (!$attributes->count()) {
            return $this;
        }

        $attributes->filter(function ($attribute) {
            return in_array($attribute, $this->loadable);
        });

        return $this->load($attributes->toArray());
    }

    /**
     * Helper to appends all the appends attributes in the request
     *
     * @param  Request $request
     * @return void
     */
    public function loadRequest($request)
    {
        $load = collect($request->input('load', []))->map(function ($item) {
            return explode(',', $item);
        })->flatten()->values();

        return tap($this)->lazyLoad($load);
    }
}
