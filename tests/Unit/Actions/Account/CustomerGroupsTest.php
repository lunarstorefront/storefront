<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Core\Contracts\Actions\Customers\CreatesCustomer;
use Lunar\Core\Contracts\Actions\Customers\UpdatesCustomer;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Storefront\Actions\Account\MirrorCustomerGroupAccess;
use Lunar\Storefront\Contracts\CustomerGroupResolver;

beforeEach(function () {
    $this->retail = CustomerGroup::factory()->create(['handle' => 'retail', 'default' => true]);
    $this->trade = CustomerGroup::factory()->create(['handle' => 'trade', 'default' => false]);
});

/** Bind a resolver that puts customers with an account reference in $handles. */
function resolveLinkedTo(array $handles): void
{
    app()->instance(CustomerGroupResolver::class, new class($handles) implements CustomerGroupResolver
    {
        public function __construct(private array $handles) {}

        public function groupsFor(Customer $customer): array
        {
            return filled($customer->account_ref) ? $this->handles : [];
        }
    });
}

function groupHandles(Customer $customer): array
{
    return $customer->customerGroups()->orderBy('handle')->pluck('handle')->all();
}

test('customer groups are left alone when no resolver is bound', function () {
    $customer = Customer::factory()->create(['account_ref' => 'ACC-1']);
    $customer->customerGroups()->sync([$this->trade->id, $this->retail->id]);

    $customer->update(['first_name' => 'Terry']);

    expect(groupHandles($customer))->toBe(['retail', 'trade']);
});

test('a bound resolver sets the groups whenever the customer is saved', function () {
    resolveLinkedTo(['trade']);

    $customer = Customer::factory()->create(['account_ref' => 'ACC-1']);
    expect(groupHandles($customer))->toBe(['trade']);

    $customer->update(['account_ref' => null]);
    expect(groupHandles($customer))->toBe(['retail']);
});

test('groups that do not exist are logged and fall back to the default group', function () {
    Log::spy();
    resolveLinkedTo(['wholesale']);

    $customer = Customer::factory()->create(['account_ref' => 'ACC-1']);

    expect(groupHandles($customer))->toBe(['retail']);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => $context['handles'] === ['wholesale'])->once();
});

test('the groups Lunar\'s customer actions are given are replaced by the resolver\'s', function () {
    resolveLinkedTo(['trade']);

    $created = app(CreatesCustomer::class)->execute(['first_name' => 'Sam', 'last_name' => 'Fitter', 'account_ref' => 'ACC-1'], [$this->retail->id]);
    expect(groupHandles($created))->toBe(['trade']);

    $updated = app(UpdatesCustomer::class)->execute($created, ['account_ref' => null], [$this->retail->id, $this->trade->id]);
    expect(groupHandles($updated))->toBe(['retail']);
});

test('Lunar\'s customer actions keep the groups they are given without a resolver', function () {
    $created = app(CreatesCustomer::class)->execute(['first_name' => 'Sam', 'last_name' => 'Fitter'], [$this->trade->id]);

    expect(groupHandles($created))->toBe(['trade']);
});

test('mirroring copies one group\'s access to another, once, keeping what it already has', function () {
    $table = config('lunar.database.table_prefix').'customer_group_product';
    $productType = ProductType::factory()->create();
    [$shared, $retailOnly] = Product::factory()->for($productType)->count(2)->create();

    $shared->customerGroups()->sync([
        $this->retail->id => ['enabled' => true, 'visible' => true, 'purchasable' => true],
        $this->trade->id => ['enabled' => true, 'visible' => true, 'purchasable' => false],
    ]);
    $retailOnly->customerGroups()->sync([
        $this->retail->id => ['enabled' => true, 'visible' => false, 'purchasable' => true, 'starts_at' => '2026-01-01 00:00:00'],
    ]);

    (new MirrorCustomerGroupAccess)->mirror($this->retail, $this->trade);
    (new MirrorCustomerGroupAccess)->mirror($this->retail, $this->trade);

    $tradeRows = DB::table($table)->where('customer_group_id', $this->trade->id)->get()->keyBy('product_id');

    expect($tradeRows)->toHaveCount(2)
        ->and((bool) $tradeRows[$shared->id]->purchasable)->toBeFalse()
        ->and((bool) $tradeRows[$retailOnly->id]->purchasable)->toBeTrue()
        ->and((bool) $tradeRows[$retailOnly->id]->visible)->toBeFalse()
        ->and((string) $tradeRows[$retailOnly->id]->starts_at)->toStartWith('2026-01-01');
});
