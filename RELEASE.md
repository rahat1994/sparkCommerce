# Release checklist — v1.0.0-beta.1

The base pair releases together: `rahat1994/sparkcommerce` (this repo) and `rahat1994/sparkcommerce-rest-routes`. Tagging is deliberately deferred until every step below passes. Work through them in order.

## 1. Push and open PRs

- [ ] Push `feat/base-release` in **both** repos.
- [ ] Open a PR `feat/base-release` → `main` in both repos.

## 2. CI green

- [ ] `run-tests` workflow green on both PRs.
- [ ] `phpstan` workflow green on both PRs.
- [ ] Code-style workflow green (or auto-fixed) on both PRs.

## 3. Merge

- [ ] Merge both PRs to `main`. Merge the base package first — the rest-routes CI installs it via the path repo, but reviewers should read the base diff first anyway.

## 4. Stripe sandbox manual pass

Run against a host app on `main` of both packages, with **Stripe test keys** (`SPARKCOMMERCE_STRIPE_SECRET_KEY=sk_test_...`), a queue worker (`php artisan queue:work`) and the scheduler (`php artisan schedule:work`) running.

Forward webhooks locally with the Stripe CLI:

```bash
stripe listen --forward-to https://your-app.test/sc/webhooks/stripe \
  --events payment_intent.succeeded,payment_intent.payment_failed,charge.refunded,refund.failed,charge.dispute.created
# put the printed whsec_... into SPARKCOMMERCE_STRIPE_WEBHOOK_SECRET, restart the workers
```

- [ ] **Checkout → paid.** Open the example page (`sparkcommerce-rest-routes/resources/examples/checkout.html`), fill its CONFIG block (API base URL, `pk_test_...`, a bearer token from `POST /sc/v1/login`). Add a product, checkout, pay with `4242 4242 4242 4242`. The status poll must reach `status: paid`; the order shows Paid in the Filament panel; the cart is gone; the order-confirmation mail is queued.
- [ ] **Idempotent re-POST.** Before paying, click checkout again with the same cart: HTTP 200, same tracking number, same client_secret.
- [ ] **Expiry.** Create an unpaid order, set `sparkcommerce.order_ttl` low (e.g. 1), wait for the sweep (≤ 5 min): the order becomes `expired`, stock returns, and the PaymentIntent is canceled in the Stripe dashboard.
- [ ] **Refund.** Refund a paid order from the Filament order action (with and without restock): `payment_status` reaches `refunded` after the `charge.refunded` webhook, and the refund shows in Stripe.
- [ ] **Failure path.** Pay with the decline card `4000 0000 0000 0002`: `payment_status: failed`, order stays `awaiting_payment`, retry succeeds.

## 5. Clean-room install

- [ ] In a **fresh** Laravel 13 app (not the dev host), follow the base README top to bottom, then the rest-routes README, fixing nothing from memory. Every command must work as written. Reaching a paid sandbox order via the example page is the pass condition. Any deviation → fix the README, not the notes.

## 6. Tag

Tag the base package **first** (the rest-routes constraint must have something to resolve to):

- [ ] Base repo: `git tag v1.0.0-beta.1 && git push origin v1.0.0-beta.1` on `main`.
- [ ] **Rest-routes composer.json edit (required before its tag):** the dependency on the base package must move off the dev branch. Change:

  ```diff
  -        "rahat1994/sparkcommerce": "dev-main",
  +        "rahat1994/sparkcommerce": "^1.0@beta",
  ```

  and update (or drop) the local path-repository pin so local dev keeps working against the tag:

  ```diff
           "options": {
               "symlink": true,
               "versions": {
  -                "rahat1994/sparkcommerce": "dev-main"
  +                "rahat1994/sparkcommerce": "1.0.0-beta.1"
               }
           }
  ```

  (The `repositories` entry itself is ignored for consumers — only the root project's repositories apply — so it may stay for development.) Commit on `main`, ensure CI is green with the new constraint.
- [ ] Rest-routes repo: `git tag v1.0.0-beta.1 && git push origin v1.0.0-beta.1`.
- [ ] Set the release date in both `CHANGELOG.md` files (replace "Unreleased") before or with the tag commit.

## 7. Packagist

- [ ] Submit both packages on packagist.org (or confirm the GitHub hook picked up the tags).
- [ ] Verify `composer require rahat1994/sparkcommerce:^1.0@beta` and `composer require rahat1994/sparkcommerce-rest-routes:^1.0@beta` resolve in a scratch project.
- [ ] Check the Packagist pages show the beta version, MIT license, and the README.
