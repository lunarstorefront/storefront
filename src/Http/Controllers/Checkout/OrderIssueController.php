<?php

namespace Lunar\Storefront\Http\Controllers\Checkout;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OrderIssueController extends Controller
{
    public function __invoke(): Response
    {
        $reference = session('checkout.order');

        abort_unless($reference, 404);

        return Inertia::render('sales/checkout/OrderIssue', [
            'reference' => $reference,
        ]);
    }
}
