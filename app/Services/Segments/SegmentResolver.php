<?php

namespace App\Services\Segments;

use App\Models\Contact;
use App\Models\Segment;
use App\Models\Store;

/**
 * Translates a Segment's condition groups into an Eloquent query over contacts.
 *
 * Groups are ORed; conditions within a group are ANDed. Only contacts who have
 * given marketing consent are returned (required for broadcasts).
 */
class SegmentResolver
{
    /**
     * Build the base query for contacts matching a segment.
     */
    public function queryFor(Segment $segment): \Illuminate\Database\Eloquent\Builder
    {
        return $this->queryForConditions($segment->store_id, $segment->conditions ?? []);
    }

    /**
     * Build the query from a raw conditions array.
     */
    public function queryForConditions(string $storeId, array $conditions): \Illuminate\Database\Eloquent\Builder
    {
        $query = Contact::query()->where('store_id', $storeId);

        // Marketing consent required.
        $query->where('consent_state', 'OPT_IN');

        // Group by "group" key; OR the groups together.
        $groups = collect($conditions)->groupBy('group')->values();

        $query->where(function ($q) use ($groups) {
            foreach ($groups as $rows) {
                $q->orWhere(function ($groupQuery) use ($rows) {
                    foreach ($rows as $cond) {
                        $groupQuery->where(function ($condQuery) use ($cond) {
                            $this->applyCondition($condQuery, $cond);
                        });
                    }
                });
            }
        });

        return $query;
    }

    public function count(Segment $segment): int
    {
        return $this->queryFor($segment)->count();
    }

    protected function applyCondition($q, array $cond): void
    {
        $field = $cond['field'] ?? 'tags';
        $op = $cond['operator'] ?? 'contains';
        $value = $cond['value'] ?? null;

        switch ($field) {
            case 'tags':
                $this->applyJsonContains($q, 'tags', $op, $value);
                break;

            case 'consent':
                $this->applyString($q, 'consent_state', $op, $value ?? 'OPT_IN');
                break;

            case 'opted_in':
                $q->whereNotNull('opt_in_at');
                break;

            case 'profile_name':
                $this->applyString($q, 'profile_name', $op, $value);
                break;

            case 'email':
                $this->applyString($q, 'email', $op, $value);
                break;

            case 'wa_id':
                $this->applyString($q, 'wa_id', $op, $value);
                break;

            case 'source':
                $this->applyString($q, 'opt_in_source', $op, $value);
                break;

            case 'last_seen_days':
                $q->where('last_seen_at', $this->applyNumberOp($op, $value, 'days'));
                break;

            case 'total_orders':
            case 'lifetime_value':
                $this->applyCustomerNumeric($q, $field === 'total_orders' ? 'total_orders' : 'lifetime_value', $op, $value);
                break;

            default:
                // metadata / custom attribute via JSON.
                $this->applyJson($q, 'metadata', $field, $op, $value);
                break;
        }
    }

    protected function applyString($q, string $column, string $op, $value): void
    {
        switch ($op) {
            case 'equals':
                $q->where($column, $value);
                break;
            case 'not_equals':
                $q->where($column, '!=', $value);
                break;
            case 'exists':
                $q->whereNotNull($column);
                break;
            case 'in':
                $q->whereIn($column, (array) $value);
                break;
            case 'contains':
            default:
                $q->where($column, 'like', "%{$value}%");
                break;
        }
    }

    protected function applyJsonContains($q, string $column, string $op, $value): void
    {
        switch ($op) {
            case 'not_equals':
                $q->whereJsonDoesntContain($column, $value);
                break;
            case 'in':
                foreach ((array) $value as $v) {
                    $q->whereJsonContains($column, $v);
                }
                break;
            case 'equals':
            case 'contains':
            default:
                $q->whereJsonContains($column, $value);
                break;
        }
    }

    protected function applyJson($q, string $column, string $key, string $op, $value): void
    {
        $path = $column.'->'.$key;

        switch ($op) {
            case 'equals':
                $q->where($path, $value);
                break;
            case 'not_equals':
                $q->where($path, '!=', $value);
                break;
            case 'gt':
                $q->where($path, '>', $value);
                break;
            case 'lt':
                $q->where($path, '<', $value);
                break;
            case 'exists':
                $q->whereNotNull($path);
                break;
            case 'contains':
            default:
                $q->where($path, 'like', "%{$value}%");
                break;
        }
    }

    protected function applyNumberOp(string $op, $value, string $unit = 'days')
    {
        $days = (int) $value;

        return match ($op) {
            'gt' => now()->subDays($days - 1),
            'lt' => now()->subDays($days + 1),
            'equals' => now()->subDays($days),
            default => now()->subDays($days),
        };
    }

    /**
     * Apply a numeric condition against the linked Shopify customer
     * (total_orders / lifetime_value) via a relation join.
     */
    protected function applyCustomerNumeric($q, string $column, string $op, $value): void
    {
        $q->whereHas('shopifyCustomer', function ($c) use ($column, $op, $value) {
            switch ($op) {
                case 'gt':
                    $c->where($column, '>', $value);
                    break;
                case 'lt':
                    $c->where($column, '<', $value);
                    break;
                case 'not_equals':
                    $c->where($column, '!=', $value);
                    break;
                case 'exists':
                    $c->whereNotNull($column);
                    break;
                case 'equals':
                default:
                    $c->where($column, '=', $value);
                    break;
            }
        });
    }
}
