<?php

namespace App\Services;

use App\Models\ExitPermit;
use App\Models\OrderMeal;

class ExitPermitLunchConversionService
{
    public function applyPendingForDate(string $permitDate): void
    {
        $permits = ExitPermit::query()
            ->whereDate('permit_date', $permitDate)
            ->where('status', 'approved')
            ->whereNotNull('md_approved_at')
            ->whereNotNull('hr_verified_at')
            ->whereNotNull('attendance_checked_at')
            ->whereHas('requestors', fn($query) => $query->whereRaw("UPPER(TRIM(COALESCE(reimburs_lunch_box, 'N'))) = ?", ['Y']))
            ->get();

        foreach ($permits as $permit) {
            /** @var ExitPermit $permit */
            $this->applyIfEligible($permit);
        }
    }

    public function applyIfEligible(ExitPermit $exitPermit): void
    {
        if (!$this->isEligible($exitPermit)) {
            return;
        }

        $requestorCount = $exitPermit->requestors()
            ->whereRaw("UPPER(TRIM(COALESCE(reimburs_lunch_box, 'N'))) = ?", ['Y'])
            ->count();

        if ($requestorCount <= 0) {
            return;
        }

        if ($this->hasAlreadyReducedQuota($exitPermit)) {
            return;
        }

        $this->reduceGeneralLunchQuota($exitPermit, $requestorCount);
    }

    private function isEligible(ExitPermit $exitPermit): bool
    {
        return $exitPermit->status === 'approved'
            && (bool) $exitPermit->md_approved_at
            && (bool) $exitPermit->hr_verified_at
            && (bool) $exitPermit->attendance_checked_at;
    }

    private function reduceGeneralLunchQuota(ExitPermit $exitPermit, int $requestorCount): void
    {
        $remainingToReduce = $requestorCount;

        $generalLunchOrders = OrderMeal::query()
            ->where('order_scope', OrderMeal::SCOPE_GENERAL)
            ->where('meal_type', 'lunch')
            ->whereDate('meal_date', $exitPermit->permit_date)
            ->orderBy('id')
            ->get();

        foreach ($generalLunchOrders as $order) {
            /** @var OrderMeal $order */
            if ($remainingToReduce <= 0) {
                break;
            }

            $currentQuantity = (int) $order->quantity;
            $reduction = min($currentQuantity, $remainingToReduce);

            if ($reduction <= 0) {
                continue;
            }

            $order->quantity = max(0, $currentQuantity - $reduction);

            $tag = sprintf('[Lunch box allowance converted to reimbursement for employee EP#%d: -%d packs]', $exitPermit->id, $reduction);
            $order->notes = trim(((string) $order->notes) . ' ' . $tag);
            $order->save();

            $remainingToReduce -= $reduction;
        }
    }

    private function hasAlreadyReducedQuota(ExitPermit $exitPermit): bool
    {
        $oldTagPrefix = sprintf('[AUTO-CONVERT EP#%d', $exitPermit->id);
        $legacyTagPrefix = sprintf('[Konversi Lunch Box EP#%d', $exitPermit->id);
        $legacyQuotaPrefix = sprintf('[Pengalihan jatah lunch box ke uang reimbursement karyawan EP#%d', $exitPermit->id);
        $newTagPrefix = sprintf('[Lunch box allowance converted to reimbursement for employee EP#%d', $exitPermit->id);

        return OrderMeal::query()
            ->where('order_scope', OrderMeal::SCOPE_GENERAL)
            ->where('meal_type', 'lunch')
            ->whereDate('meal_date', $exitPermit->permit_date)
            ->where(function ($query) use ($oldTagPrefix, $legacyTagPrefix, $legacyQuotaPrefix, $newTagPrefix) {
                $query->where('notes', 'like', '%' . $oldTagPrefix . '%')
                    ->orWhere('notes', 'like', '%' . $legacyTagPrefix . '%')
                    ->orWhere('notes', 'like', '%' . $legacyQuotaPrefix . '%')
                    ->orWhere('notes', 'like', '%' . $newTagPrefix . '%');
            })
            ->exists();
    }
}
