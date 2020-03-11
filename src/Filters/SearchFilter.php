<?php

namespace Xbhub\Filter\Filters;

use Illuminate\Database\Eloquent\Builder;
use Xbhub\Filter\Filter;

class SearchFilter extends Filter
{
    /**
     * Filter values mappings.
     *
     * @var array
     */
    protected $mappings = [
        //
    ];

    /**
     * Filter records.
     *
     * @param Builder $builder
     * @param mixed   $value
     *
     * @return Builder
     */
    public function filter(Builder $builder, $value)
    {
        dd($value);
    }
}
