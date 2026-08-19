# Money is decimal strings, never floats

- Money columns are `decimal(15, 2)`, and models cast them with `decimal:2` — which means
  Eloquent hands you a **string**, not a float.
- Compare and combine with `bccomp`, `bcadd`, `bcsub`, `bcmul`, `bcdiv`, always with an
  explicit scale of `2`. Never use `==`, `+`, `-`, `round()` or `(float)` on money.
- Normalise anything entering the domain with `bcadd($value, '0', 2)`.
- Model `@property` docblocks for money must say `string`, not `float`. Larastan infers
  `float` from the column type when the docblock is missing, which produces false
  positives at level 6 — declare the property.
- `bcdiv` truncates. Whenever you divide money, decide explicitly where the leftover
  paisa goes (see [[billing-charge-heads]] for the equal-share rule).

## Quantities and rates are not money

Metered utilities introduce two non-money numeric kinds. Do not put them through the
scale-2 rules above:

- **Consumption is a quantity at scale 3** (`decimal(15, 3)` — kWh, m³). It is never
  rounded to paisa and never compared with a money value.
- **Tariff rates are scale 4** (`decimal(15, 4)`), because a per-unit rate needs more
  precision than the money it produces.

A quantity times a rate is accumulated at **scale 4** and collapses to money exactly
**once**, at the end of the computation — never per slab, or a multi-slab bill sheds
paisa at every boundary.

## `Money::round()` is the one place that rounds

`App\Support\Money::round()` rounds half-up. It exists solely for that scale-4 → scale-2
collapse. Everywhere else still truncates and places the leftover paisa explicitly.

The distinction is whether a known total is being **split** or a new amount is being
**computed**. Splitting a total across flats must truncate and place the remainder, so
the parts add back up to the whole. Computing `consumption × rate` has no whole to add
back up to, and truncating it would shave a fraction off every utility line forever,
always in the building's favour.
