<?php

namespace Lunar\Storefront\Http\Controllers\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Storefront\Actions\Account\DeleteCustomerAddress;
use Lunar\Storefront\Actions\Account\FindCustomerAddress;

class DestroyAddressController extends Controller
{
    public function __construct(
        protected FindCustomerAddress $find,
        protected DeleteCustomerAddress $delete,
    ) {}

    public function __invoke(string $id, Request $request): RedirectResponse
    {
        $user = $request->user();

        // The route is behind `auth`; a user model that is not a Lunar user
        // has no address book.
        abort_unless($user instanceof Model && $user instanceof LunarUser, 403);

        // Someone else's address and an unknown id are both a 404.
        $address = $this->find->get($user, $id);
        abort_if($address === null, 404);

        $this->delete->delete($user, $address);

        return back();
    }
}
