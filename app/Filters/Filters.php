<?php


namespace App\Filters;


use Illuminate\Http\Request;

abstract class Filters {

    protected $filters = [];
    /**
     * @var Request
     */
    protected $request, $builder;

    public function __construct(Request $request)
    {

        $this->request = $request;
    }

    public function apply($builder)
    {
        $this->builder = $builder;

        foreach ($this->getFilters() as $filter => $value)
        {
            if (method_exists($this, $filter))
            {
                $this->$filter($value);
            }
        }

        return $this->builder;

    }


    /**
     * @return array
     */
    protected function getFilters(): array
    {
        return array_filter($this->request->only($this->filters));
    }
}