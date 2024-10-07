<?php

namespace App\Traits;

use Illuminate\Support\Facades\Gate;

trait AppendPolicies
{
    public $policies = null;
    
    /**
     * Helper to appends all the policies attributes in the request
     *
     * @param  Request $request
     * @return self
     */
    public function loadPolicies($request)
    {
        $policies = collect($request->input('policies', []))->map(function ($item) {
            return explode(',', $item);
        })->flatten()->values();

        if (count($policies)) {
            return $this->appendPolicies($policies->toArray());
        }

        return $this;
    }

    /**
     * The policies that the model will include 
     * 
     * @param  array $policies
     * @return self
     */
    public function appendPolicies($policies)
    {
        list($policies, $deepPolicies) = collect($policies)
            ->partition(function ($policy) {
                return !str_contains($policy, '.');
            });

        $policies = collect($policies)
            ->mapWithKeys(function ($policy) {
                return [$policy => Gate::allows($policy, $this)];
            });

        collect($deepPolicies)
            ->groupBy(function ($policy) {
                list($relation, $policy) = explode('.', $policy);
                return $relation;
            })->each(function ($policies, $relation) {
                $policies = collect($policies)->map(function ($policy) {
                    list($relation, $policy) = explode('.', $policy);
                    return $policy;
                });
                $this->{$relation}->each->appendPolicies($policies);
            });

        $this->policies = $policies;

        $this->append('can');

        return $this;
    }

    /**
     * The can attribute
     * @param  array $can
     * @return self
     */
    public function getCanAttribute($can)
    {
        return $this->policies;
    }
}
