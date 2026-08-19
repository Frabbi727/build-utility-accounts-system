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
