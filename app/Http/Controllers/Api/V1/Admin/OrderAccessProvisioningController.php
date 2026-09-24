<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminOrderResource;
use App\Models\Order;
use App\Services\OrderAccessProvisioningService;
use Illuminate\Support\Facades\Gate;

class OrderAccessProvisioningController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Order $order,
        OrderAccessProvisioningService $provisioning,
    ): AdminOrderResource {
        Gate::authorize('manage', $order);
        $result = $provisioning->provision($order);

        return (new AdminOrderResource($result['order']))->additional([
            'provisioning' => [
                'grants_created' => $result['grants_created'],
                'grants_existing' => $result['grants_existing'],
            ],
        ]);
    }
}
