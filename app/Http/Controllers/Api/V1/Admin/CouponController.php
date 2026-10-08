<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCouponRequest;
use App\Models\Coupon;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('coupons.view'), 403);
        $coupons = Coupon::query()->withCount(['redemptions as reserved_count' => fn ($query) => $query->where('status', 'reserved'), 'redemptions as consumed_count' => fn ($query) => $query->where('status', 'consumed')])
            ->when($request->query('search'), fn ($query, $search) => $query->where('code', 'like', '%'.mb_strtoupper(trim((string) $search)).'%'))
            ->when(in_array($request->query('active'), ['0', '1'], true), fn ($query) => $query->where('is_active', $request->query('active') === '1'))
            ->orderByDesc('id')->paginate(25);

        return response()->json($coupons);
    }

    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        abort_unless($request->user()->can('coupons.view'), 403);

        return response()->json($coupon->loadCount(['redemptions as reserved_count' => fn ($query) => $query->where('status', 'reserved'), 'redemptions as consumed_count' => fn ($query) => $query->where('status', 'consumed')]));
    }

    public function store(StoreCouponRequest $request, AuditTrail $audit): JsonResponse
    {
        $coupon = DB::transaction(function () use ($request, $audit): Coupon {
            $coupon = Coupon::query()->create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $audit->record('coupon.created', $coupon, $request->user());

            return $coupon;
        });

        return response()->json($coupon, 201);
    }

    public function update(StoreCouponRequest $request, Coupon $coupon, AuditTrail $audit): JsonResponse
    {
        $coupon = DB::transaction(function () use ($request, $coupon, $audit): Coupon {
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            $wasActive = $locked->is_active;
            $locked->update([...$request->validated(), 'updated_by' => $request->user()->id]);
            $audit->record($wasActive !== $locked->is_active ? 'coupon.activation_changed' : 'coupon.updated', $locked, $request->user(), ['changed_fields' => implode(',', array_keys($locked->getChanges()))]);

            return $locked->refresh();
        }, 3);

        return response()->json($coupon);
    }
}
