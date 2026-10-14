<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Exceptions\CustomerGroupNameTakenException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Customers\Services\CustomerActivity;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Staff create, rename and delete customer groups, and put customers in them or take them out.
 *
 * Changes to a group are audited. Adding or removing a customer goes in the
 * activity log against the customer, naming the group by public ID; adding a
 * customer who is already in the group changes nothing and logs nothing.
 */
final readonly class ManageCustomerGroups
{
    public function __construct(private CustomerActivity $customerActivity) {}

    /**
     * @throws CustomerGroupNameTakenException When another group has the name, ignoring case.
     */
    public function create(string $name, ?string $description): CustomerGroup
    {
        return $this->saved(new CustomerGroup(['name' => $name, 'description' => $description]));
    }

    /**
     * @param  array{name?: string, description?: string|null}  $changes
     *
     * @throws CustomerGroupNameTakenException When another group has the name, ignoring case.
     */
    public function update(CustomerGroup $group, array $changes): CustomerGroup
    {
        return $this->saved($group->fill($changes));
    }

    /**
     * Deletes the group and its memberships. Promotions limited to it lose that limit to it (Promotions removes the link).
     */
    public function delete(CustomerGroup $group): void
    {
        $group->delete();
    }

    /**
     * @param  list<Customer>  $customers
     * @return int How many weren't in the group already.
     */
    public function addMembers(StaffMember $actor, CustomerGroup $group, array $customers): int
    {
        return DB::transaction(function () use ($actor, $group, $customers): int {
            $attached = $group->members()->syncWithoutDetaching(array_map(static fn (Customer $customer): int => $customer->id, $customers))['attached'];

            foreach ($customers as $customer) {
                if (in_array($customer->id, $attached, true)) {
                    $this->customerActivity->record($actor, $customer, 'customer_group_member_added', "Added to group {$group->public_id}:");
                }
            }

            return count($attached);
        });
    }

    public function removeMember(StaffMember $actor, CustomerGroup $group, Customer $customer): void
    {
        DB::transaction(function () use ($actor, $group, $customer): void {
            if ($group->members()->detach($customer->id) > 0) {
                $this->customerActivity->record($actor, $customer, 'customer_group_member_removed', "Removed from group {$group->public_id}:");
            }
        });
    }

    private function saved(CustomerGroup $group): CustomerGroup
    {
        try {
            $group->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new CustomerGroupNameTakenException(previous: $exception);
        }

        return $group;
    }
}
