# Admin screens: WithCrudModal + a policy

Master-data screens are Livewire components using `App\Livewire\Concerns\WithCrudModal`,
which supplies `create`/`edit`/`save`/`delete`/`cancel` and **guarantees `authorizeAction()`
runs before every mutation**.

A component supplies five methods: `formRules()`, `fillForm(?Model)`, `persist()`,
`findRecord(int)`, `authorizeAction(string, ?Model)`. Copy `app/Livewire/Masters/OwnerList.php`
as the reference implementation.

- `deleteRecord()` is overridable when a screen must refuse a delete on its own terms — see
  `app/Livewire/AccountList.php`, which also aliases `save` to wrap it with an extra guard.
- Building-scoped screens narrow `findRecord()` by `CurrentBuilding`, they do not filter in
  the view.
- Always `$this->authorize('viewAny', Model::class)` at the top of `render()`.
- Build forms from `resources/views/components/form/*` and `components/ui/*`. Reuse
  `x-money` for every amount. Do not hand-roll markup.
- Every component renders with `->layout('components.layouts.app')`.

## Access

Staff roles read master data, `canManageMoney()` (admin + accountant) changes it, and users,
the chart of accounts, accounting periods and the setup wizard are **admin only**. Most
policies get this shape from `App\Policies\Concerns\ManagesMasterData`.

Route-level `role:` middleware is a coarse first gate; the real check is the policy call in
the component. Owner-facing routes (`flats.statement`) are policy-guarded rather than
role-gated so an owner reaches their own record and nobody else's.

Accounts whose code appears in `App\Enums\AccountCode` are reserved — their code and type
cannot be edited and they cannot be deleted.

`App\Support\Navigation` builds the menu from a data structure and drops entries whose route
does not exist, so adding a route lights up its menu item automatically.
