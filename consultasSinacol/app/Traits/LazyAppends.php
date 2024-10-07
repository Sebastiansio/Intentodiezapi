<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait LazyAppends
{
    /**
     * Append attributes to query when building a query.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function lazyAppend($attributes)
    {
        $attributes = collect(is_string($attributes) ? func_get_args() : $attributes);

        if (!$attributes->count() || !$this->appendable) {
            return $this;
        }

        $attributes->filter(function ($attribute) {
            return in_array($attribute, $this->appendable);
        });

        return parent::append($attributes->toArray());
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes,
            $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes,
            $mutatedAttributes
        );

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        // Nueva función que agrega sub-elementos dinámicamente, en la practica es
        // util para en un solo query agregar más elementos
        foreach ($this->getSubArrayableAppends() as $key) {
            list($model, $key) = explode('.', $key);

            if ($this->{$model}) {
                if (is_array($this->{$model}) || $this->{$model} instanceof \Illuminate\Support\Collection) {
                    foreach ($this->{$model} as $index => $themodel) {
                        $attributes[$model][$index][$key] = $themodel->mutateAttributeForArray($key, null);
                    }
                } else {
                    $attributes[$model][$key] = $this->{$model}->mutateAttributeForArray($key, null);
                }
            }
        }

        return $attributes;
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (! count($this->appends)) {
            return [];
        }

        $appends = collect($this->appends)->filter(function ($append) {
            return !str_contains($append, '.');
        })->toArray();

        return $this->getArrayableItems(
            array_combine($appends, $appends)
        );
    }

    /**
     * Get all of the sub appendable values that are arrayable.
     *
     * @return array
     */
    protected function getSubArrayableAppends()
    {
        if (! count($this->appends)) {
            return [];
        }

        $appends = collect($this->appends)->filter(function ($append) {
            return str_contains($append, '.');
        })->toArray();

        return $this->getArrayableItems(
            array_combine($appends, $appends)
        );
    }

    /**
     * Helper to appends all the appends attributes in the request
     *
     * @param  Request $request
     * @return void
     */
    public function appendsRequest($request)
    {

        $appends = collect($request->input('appends', []))->map(function ($item) {

            return explode(',', $item);
        })->flatten()->values();

        $this->lazyAppend($appends);

        return $this;
    }
}
