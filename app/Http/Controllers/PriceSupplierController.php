<?php

namespace App\Http\Controllers;

use App\Models\PriceSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PriceSupplierController extends Controller
{
    private const SISCA_EMAIL = 'sisca@example.com';

    public function index(): Response
    {
        $this->authorizePriceManager();

        $priceSuppliers = PriceSupplier::query()
            ->orderByDesc('is_active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn(PriceSupplier $priceSupplier) => $this->transformPriceSupplier($priceSupplier))
            ->values()
            ->all();

        $activePriceSupplier = PriceSupplier::query()
            ->active()
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('PriceSuppliers/Index', [
            'items' => $priceSuppliers,
            'activePriceSupplier' => $activePriceSupplier ? $this->transformPriceSupplier($activePriceSupplier) : null,
        ]);
    }

    public function create(): Response
    {
        $this->authorizePriceManager();

        return Inertia::render('PriceSuppliers/Create', [
            'defaultIsActive' => !$this->hasActivePriceSupplier(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePriceManager();

        $validated = $this->validatedData($request);

        $priceSupplier = DB::transaction(function () use ($validated) {
            $priceSupplier = PriceSupplier::create($validated);
            $this->syncActivePriceSupplier($priceSupplier);

            return $priceSupplier;
        });

        return redirect()->route('price-suppliers.index')->with('success', sprintf('%s has been saved.', $priceSupplier->supplier_name));
    }

    public function edit(PriceSupplier $priceSupplier): Response
    {
        $this->authorizePriceManager();

        return Inertia::render('PriceSuppliers/Edit', [
            'priceSupplier' => $this->transformPriceSupplier($priceSupplier),
        ]);
    }

    public function update(Request $request, PriceSupplier $priceSupplier): RedirectResponse
    {
        $this->authorizePriceManager();

        $validated = $this->validatedData($request);

        DB::transaction(function () use ($priceSupplier, $validated) {
            $priceSupplier->update($validated);
            $this->syncActivePriceSupplier($priceSupplier);
        });

        return redirect()->route('price-suppliers.index')->with('success', sprintf('%s has been updated.', $priceSupplier->supplier_name));
    }

    public function destroy(PriceSupplier $priceSupplier): RedirectResponse
    {
        $this->authorizePriceManager();

        DB::transaction(function () use ($priceSupplier) {
            $wasActive = (bool) $priceSupplier->is_active;
            $priceSupplier->delete();

            if ($wasActive && !PriceSupplier::query()->active()->exists()) {
                $latest = PriceSupplier::query()->orderByDesc('effective_date')->orderByDesc('id')->first();

                if ($latest) {
                    $latest->forceFill(['is_active' => true])->save();
                }
            }
        });

        return redirect()->route('price-suppliers.index')->with('success', 'Price supplier has been deleted.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'meal_unit_price' => ['required', 'integer', 'min:1'],
            'local_tax_rate' => ['required', 'numeric', 'min:0'],
            'service_tax_rate' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function transformPriceSupplier(PriceSupplier $priceSupplier): array
    {
        return [
            'id' => $priceSupplier->id,
            'supplier_name' => $priceSupplier->supplier_name,
            'meal_unit_price' => (int) $priceSupplier->meal_unit_price,
            'local_tax_rate' => (float) $priceSupplier->local_tax_rate,
            'service_tax_rate' => (float) $priceSupplier->service_tax_rate,
            'effective_date' => $priceSupplier->getRawOriginal('effective_date') ?: null,
            'is_active' => (bool) $priceSupplier->is_active,
            'notes' => $priceSupplier->notes,
        ];
    }

    private function authorizePriceManager(): void
    {
        $user = request()->user();
        $isSisca = $user?->role?->code === 'hr' && strtolower((string) $user?->email) === self::SISCA_EMAIL;

        if (!in_array($user?->role?->code, ['admin'], true) && !$isSisca) {
            abort(403);
        }
    }

    private function hasActivePriceSupplier(): bool
    {
        return PriceSupplier::query()->active()->exists();
    }

    private function syncActivePriceSupplier(PriceSupplier $priceSupplier): void
    {
        if ($priceSupplier->is_active) {
            PriceSupplier::query()
                ->where('id', '!=', $priceSupplier->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return;
        }

        if (!PriceSupplier::query()->active()->exists()) {
            $priceSupplier->forceFill(['is_active' => true])->save();
        }
    }
}