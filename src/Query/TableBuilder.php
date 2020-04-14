<?php

namespace miradnan\QueryCache\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use miradnan\QueryCache\Contracts\QueryCacheModuleInterface;
use miradnan\QueryCache\Traits\QueryCacheModule;

class TableBuilder extends BaseBuilder implements QueryCacheModuleInterface
{
    use QueryCacheModule;

    /**
     * {@inheritdoc}
     */
    public function get($columns = ['*'])
    {
        if (!empty($this->from)) {
            $cacheKey = $this->cachePrefix . md5($this->from . implode('|', $columns));

            // get all the columns and all the rows from table
            $items = Cache::rememberForever($cacheKey, function () use ($columns) {
                return DB::table($this->from)->get();
            });

            ###### FILTER Records
            $filters = [];
            foreach ($this->wheres AS $where) {
                $column = trim(str_ireplace([$this->from, '.'], '', $where['column']));

                switch ($where['type']) {
                    case 'In':
                        $filters[$column] = $where['values'];
                        break;
                }
            }
            ##########################################

            # Filter columns as in the query builder object
            if (count($columns) > 1) {
                $results = collect([]);
                foreach ($items AS $item) {
                    $r = new \stdClass();
                    foreach ($columns AS $column) {
                        if (!empty($item->{$column})) {
                            $r->{$column} = $item->{$column};
                        }
                    }
                    $results->add($r);
                }
                $items = $results;
            }

            if ($filters) {
                $collection = collect([]);
                $whereColumns = array_keys($filters);
                foreach ($items AS $item) {
                    foreach ($whereColumns AS $column) {
                        if (array_key_exists($column, $filters) && !empty($item->{$column})) {
                            if (!in_array($item->{$column}, $filters[$column])) {
                                continue 2;
                            }
                        }
                    }
                    $collection->add($item);
                }
                $items = $collection;
            }

            return $items;
        }

        return parent::get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function useWritePdo()
    {
        // Do not cache when using the write pdo for query.
        $this->dontCache();

        // Call parent method
        parent::useWritePdo();

        return $this;
    }
}
