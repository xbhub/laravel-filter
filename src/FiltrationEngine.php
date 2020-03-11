<?php

namespace Xbhub\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FiltrationEngine
{
    /**
     * Request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $builder;

    /**
     * Array of filters to be applied.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Fields search able
     *
     * @var array
     */
    protected $fieldSearchable = [];

    /**
     * Constructor.
     *
     * @param Builder $builder
     * @param Request $request
     */
    public function __construct(Builder $builder, Request $request, array $fieldSearchable)
    {
        $this->builder = $builder;
        $this->request = $request;
        $this->fieldSearchable = $fieldSearchable;
    }

    /**
     * Add filters to engine.
     *
     * @param array $filters
     *
     * @return $this
     */
    public function plugFilters(array $filters = [])
    {
        $this->filters = array_merge($this->filters, $filters);

        return $this;
    }

    /**
     * Apply filters on query.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run()
    {
        // default filter
        $this->handleDefaultFilter();

        // handle cusstom filter
        foreach ($this->getFilters() as $filter => $value) {
            $this->resolveFilter($filter)->filter($this->builder, $value);
        }

        return $this->builder;
    }

    /**
     * Get applicable filters based on their presence in the query string.
     *
     * @return array
     */
    protected function getFilters()
    {
        return $this->filterFilters($this->filters);
    }

    /**
     * Resolve a filter from the filters array by its key.
     *
     * @param mixed $filter
     *
     * @return \Xbhub\Filter\Filter
     */
    public function resolveFilter($filter)
    {
        return new $this->filters[$filter];
    }

    /**
     * Get only the filters included in the query string
     * and return a key, value pair array.
     *
     * @param array $filters
     *
     * @return array
     */
    protected function filterFilters(array $filters)
    {
        return array_filter($this->request->only(array_keys($filters)));
    }

    /**
     * handle default
     */
    protected function handleDefaultFilter()
    {
        $builder = $this->builder;
        $fieldsSearchable = $this->fieldSearchable;
        $search = $this->request->get('search', null);
        $searchFields = $this->request->get('searchFields');
        $filter = $this->request->get('filter');
        $orderBy = $this->request->get('orderBy');
        $sortedBy = $this->request->get('sortedBy', 'asc');
        $with = $this->request->get('with');
        $withCount = $this->request->get('withCount');
        $searchJoin = $this->request->get('searchJoin');

        if ($search && is_array($fieldsSearchable) && count($fieldsSearchable)) {
            $searchFields = is_array($searchFields) || is_null($searchFields) ? $searchFields : explode(';', $searchFields);
            $fields = $this->parserFieldsSearch($fieldsSearchable, $searchFields);
            $isFirstField = true;
            $searchData = $this->parserSearchData($search);
            $search = $this->parserSearchValue($search);
            $builderForceAndWhere = strtolower($searchJoin) === 'and';

            $builder = $builder->where(function ($query) use ($fields, $search, $searchData, $isFirstField, $builderForceAndWhere) {
            /** @var Builder $query */
                foreach ($fields as $field => $condition) {
                    if (is_numeric($field)) {
                        $field = $condition;
                        $condition = "=";
                    }
                    $value = null;
                    $condition = trim(strtolower($condition));
                    if (isset($searchData[$field])) {
                        $value = ($condition == "like" || $condition == "ilike") ? "%{$searchData[$field]}%" : $searchData[$field];
                    } else {
                        if (!is_null($search)) {
                            $value = ($condition == "like" || $condition == "ilike") ? "%{$search}%" : $search;
                        }
                    }
                    $relation = null;
                    if (stripos($field, '.')) {
                        $explode = explode('.', $field);
                        $field = array_pop($explode);
                        $relation = implode('.', $explode);
                    }
                    $builderTableName = $query->getModel()->getTable();
                    if ($isFirstField || $builderForceAndWhere) {
                        if (!is_null($value)) {
                            if (!is_null($relation)) {
                                $query->whereHas($relation, function ($query) use ($field, $condition, $value) {
                                    $query->where($field, $condition, $value);
                                });
                            } else {
                                $query->where($builderTableName . '.' . $field, $condition, $value);
                            }
                            $isFirstField = false;
                        }
                    } else {
                        if (!is_null($value)) {
                            if (!is_null($relation)) {
                                $query->orWhereHas($relation, function ($query) use ($field, $condition, $value) {
                                    $query->where($field, $condition, $value);
                                });
                            } else {
                                $query->orWhere($builderTableName . '.' . $field, $condition, $value);
                            }
                        }
                    }
                }
            });
        }

        // orderBy
        if (isset($orderBy) && !empty($orderBy)) {
            $split = explode('|', $orderBy);
            if (count($split) > 1) {
                $table = $builder->getModel()->getTable();
                $sortTable = $split[0];
                $sortColumn = $split[1];
                $split = explode(':', $sortTable);
                if (count($split) > 1) {
                    $sortTable = $split[0];
                    $keyName = $table . '.' . $split[1];
                } else {
                    $prefix = Str::singular($sortTable);
                    $keyName = $table . '.' . $prefix . '_id';
                }
                $builder = $builder
                    ->leftJoin($sortTable, $keyName, '=', $sortTable . '.id')
                    ->orderBy($sortColumn, $sortedBy)
                    ->addSelect($table . '.*');
            } else {
                $builder = $builder->orderBy($orderBy, $sortedBy);
            }
        }

        // filter
        if (isset($filter) && !empty($filter)) {
            if (is_string($filter)) {
                $filter = explode(';', $filter);
            }
            $builder = $builder->select($filter);
        }
        if ($with) {
            $with = explode(';', $with);
            $builder = $builder->with($with);
        }
        if ($withCount) {
            $withCount = explode(';', $withCount);
            $builder = $builder->withCount($withCount);
        }


        return $this->builder;
    }

    /**
     * parse field search
     *
     * @param array $fields
     * @param array $searchFields
     * @return void
     */
    protected function parserFieldsSearch(array $fields = [], array $searchFields = null)
    {
        if (!is_null($searchFields) && count($searchFields)) {
            $acceptedConditions = [
                '=',
                'like',
            ];
            $originalFields = $fields;
            $fields = [];
            foreach ($searchFields as $index => $field) {
                $field_parts = explode(':', $field);
                $temporaryIndex = array_search($field_parts[0], $originalFields);
                if (count($field_parts) == 2) {
                    if (in_array($field_parts[1], $acceptedConditions)) {
                        unset($originalFields[$temporaryIndex]);
                        $field = $field_parts[0];
                        $condition = $field_parts[1];
                        $originalFields[$field] = $condition;
                        $searchFields[$index] = $field;
                    }
                }
            }
            foreach ($originalFields as $field => $condition) {
                if (is_numeric($field)) {
                    $field = $condition;
                    $condition = "=";
                }
                if (in_array($field, $searchFields)) {
                    $fields[$field] = $condition;
                }
            }
            if (count($fields) == 0) {
                throw new \Exception('filter.fields_not_accepted', ['field' => implode(',', $searchFields)]);
            }
        }
        return $fields;
    }

    /**
     * parse search data
     *
     * @param [type] $search
     * @return void
     */
    protected function parserSearchData($search)
    {
        $searchData = [];
        if (stripos($search, ':')) {
            $fields = explode(';', $search);
            foreach ($fields as $row) {
                try {
                    list($field, $value) = explode(':', $row);
                    $searchData[$field] = $value;
                } catch (\Exception $e) {
                    //Surround offset error
                }
            }
        }
        return $searchData;
    }

    /**
     * parse search value
     *
     * @param [type] $search
     * @return void
     */
    protected function parserSearchValue($search)
    {
        if (stripos($search, ';') || stripos($search, ':')) {
            $values = explode(';', $search);
            foreach ($values as $value) {
                $s = explode(':', $value);
                if (count($s) == 1) {
                    return $s[0];
                }
            }
            return null;
        }
        return $search;
    }
}
