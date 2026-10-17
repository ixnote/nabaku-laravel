# React API integration

The React application remains a separate project. Point its existing API client at `https://<laravel-host>/api/v1` (locally `http://localhost:8000/api/v1`). There is no frontend build or Node production dependency in this Laravel project.

Set `CORS_ALLOWED_ORIGINS` to the React application's exact origin(s), comma-separated, and set `FRONTEND_URL` for email links. Configure the activation, manager activation, reset and Flutterwave redirect paths to match actual React routes. These routes cannot be inferred from this repository.

## Authentication

```js
const apiBase = import.meta.env.VITE_API_BASE_URL;
const response = await fetch(`${apiBase}/authentication/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ id: emailOrPhone, password }),
});
const body = await response.json();
if (!response.ok) throw new Error(body.message);
// Pass body.authorization.token through the application's existing auth state.
const profile = await fetch(`${apiBase}/authentication/get-profile`, {
  headers: { Authorization: `Bearer ${body.authorization.token}` },
});
```

`VITE_API_BASE_URL` is an example for Vite; use the equivalent setting in the existing React build. Tokens retain the Node `authorization: { token, expiresIn }` envelope and 30-day validity. Cookies, Sanctum, and CSRF tokens are not needed for this Bearer API. Multiple device tokens are supported. Existing JWTs work with the same HS256 secret, which must be at least 32 bytes for the new JWT library. If the old secret is shorter, rotate it and require login at cutover. Existing bcrypt passwords do not require a reset.

The complete endpoint list is in [ROUTE_INVENTORY.md](../ROUTE_INVENTORY.md). Paths and methods are preserved, except duplicate route registration is removed and an additional `DELETE /user/{id}` endpoint replaces reliance on the legacy GET mutation.

## Requests

| Flow | Request |
| --- | --- |
| Register | `POST authentication/register`: `email`, `phone`, `password` (6–50 characters) |
| Login | `POST authentication/login`: `id` (email/phone), `password` |
| Forgot/resend | `POST authentication/password/forgot` or `resend-activation`: `id` |
| Reset | `POST authentication/password/reset`: `token`, `password` |
| Manager activation | `PUT authentication/activate/manager/{token}`: `password` |
| Profile | `PUT authentication/set-profile`: optional `profile_step`, `organization_details` (`name`, `description`, `type`, `profile_image`, `cover_image`), `address`, `cac_details`, `bank_details`, `contact_details`; an empty string clears an image, the description or a contact field |
| Organization update | `PUT organization/update`: `name`; optional `phone`, `description`, `type` |
| Own password | `PUT organization/update/password`: `password`; acts on the authenticated account |
| Admin password | `PUT organization/update/organization-password`: `user` (organization ID), `password` |
| Invite manager | `POST user/add`: `email`; owner only, maximum five managers |
| Project create | Multipart `POST project/create`: `title`, `description`, `target_amount`, image `cover_image`, optional `media[]` (max five files) |
| Project update | `PUT project/update/{id}`: `title`, `description`, `target_amount`, `closed`; optional multipart image `file` |
| Activity create/update | `name`, `start`, `end`, optional `location`, image `file`; dates stay strings in MongoDB |
| Avatar/file | Multipart `PUT organization/update/avatar` or `file/upload`: `file`, with Bearer token |
| Upload signature | `GET file/signature`, with Bearer token: returns `signature`, `timestamp`, `folder`, `cloudName`, `apiKey` for a direct browser upload to Cloudinary |
| Verify bank | `POST bank/verify`: `account_number` (10 digits), `account_bank` (bank code string) |
| Save bank | `POST bank/create` or `PUT bank/update/{id}`: `bankCode`, `bankName`, `accountName`, `accountNumber`; verified account name comes from provider |
| Donate | Public `POST donation/make-donation`: `email`, `amount` (min NGN 1,000), `projectId`, `anonymous` boolean; `name`/`phone` required when not anonymous |
| Withdraw | `POST withdraw` or `withdrawal`: `amount` (min NGN 10), `bankId`; organization owner only |
| Admin withdrawal | `PATCH withdraw/admin/update/{id}`: `status` = `pending`, `in-progress`, `completed`, or `cancelled` |
| Graph | `GET donation/graph/year?startDate=...&endDate=...` within one calendar year |
| Dashboard range | `GET organization/dashboard?start=...&end=...`; defaults to the last seven days |

Multipart PUT requires PHP 8.4 or newer; the project and Docker image enforce this baseline. Let the browser generate the multipart Content-Type boundary when sending `FormData`.

Most responses use `{ success, message?, data? }`; bank endpoints retain `status: "success"`, checkout returns the provider's checkout envelope, the graph retains `statuse` and `results`, and withdrawal creation retains `status: true`. Errors use `{ success: false, message }`. Invalid input is 400, missing/invalid credentials 401, permission denial 403, missing resources 404, financial conflicts 409, and provider failures 502/503. `X-Request-ID` is returned for tracing.

Lists use `page` (default 1) and `pageSize` (usually 50; organization/activity private lists 500), capped at 500. Pagination retains `prevPage`, `currentPage`, `nextPage`, `pageTotal`, `pageSize`, `total`. Empty lists return 200. Supported textual filters are escaped literal searches; ownership is always imposed server-side. Date filters use `startDate`/`endDate`; timestamps are UTC.

IDs remain 24-character MongoDB ID strings in JSON, ObjectIds in MongoDB. Existing `createdAt`/`updatedAt` names and millisecond UTC strings are retained. `balance` remains the booked wallet balance; use `available_balance` when showing spendable funds, and `reserved_balance` for pending withdrawals.

## Intentional changes requiring frontend review

- File upload now requires authentication. Profile setup remains possible after registration/activation/login.
- Public organization responses omit credentials, bank account details, internal admin/blocked flags, and private profile fields. Private details remain available from `authentication/get-profile`.
- Block/unblock and platform wallet/report endpoints require admin access; manager permissions resolve through their organization. Managers cannot invite managers, change organization bank/profile settings, or initiate withdrawals.
- Organization deletion is admin-only (an admin cannot delete their own account), and archives the account and hides public resources while retaining financial history.
- `GET user/delete?userId=...` works for compatibility; use `DELETE user/{id}` in React.
- Corrected project ownership checks, donation status filters, scoped pagination totals, graph date handling, and project title search.
- Activation emails link to `FRONTEND_URL` + `/pop?token=...`; reset emails link to `/reset-password?token=...&email=...`, matching the web app's pages.
- Activation/reset tokens are single-use; new activation tokens expire after 24 hours. Legacy activation links without expiry require an explicit `LEGACY_ACTIVATION_UNTIL` deadline or resend.
- Inactive login returns 403; use resend activation. Forgot/resend return generic responses to avoid account enumeration.
- Monetary input allows at most two decimal places. A checkout timeout returns its reference; investigate or reconcile it before asking the donor to retry. Withdrawals can be completed or cancelled once and cannot be reopened.

Provider contracts: [Flutterwave checkout](https://developer.flutterwave.com/docs/flutterwave-standard-1), [HMAC webhooks](https://developer.flutterwave.com/v4.0.0/docs/webhooks), [legacy webhooks](https://developer.flutterwave.com/v2.0/reference/webhooks), [Cloudinary signatures](https://cloudinary.com/documentation/authentication_signatures). Set `FLUTTERWAVE_SIGNATURE_MODE` to `legacy` for the existing `verif-hash` integration, or `hmac` for the raw-body `flutterwave-signature` scheme matching the provider account. Switching signature modes does not switch the v3 checkout/verification API to v4.
