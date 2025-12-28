<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Transition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get monthly expense totals.
     *
     * @return Collection
     */
    public function getMonthlyExpenseTotals(): Collection
    {
        return Expense::select(
            DB::raw('YEAR(date) as year'),
            DB::raw('MONTH(date) as month'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'month' => $item->month,
                    'total_amount' => $item->total_amount,
                ];
            });
    }

    /**
     * Get monthly transaction totals.
     *
     * @return Collection
     */
    public function getMonthlyTransactionTotals(): Collection
    {
        return Transition::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'month' => $item->month,
                    'total_amount' => $item->total_amount,
                ];
            });
    }
}
