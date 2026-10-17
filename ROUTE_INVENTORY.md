# Existing route inventory

Static inventory from `../fundraiser-api/server.js` and `app/routes/`, prepared 2026-09-23. Authentication below describes route middleware only; controllers/stores may impose additional checks. This is a source inventory, not proof that an endpoint currently works.

All rows below are ported and covered by local route-resolution tests; feature tests exercise the mounted handlers. “Tested” does not mean every response branch has an exact Node fixture or that provider/React staging acceptance is complete. Thirteen captured Node responses have exact compatibility comparisons. Laravel uses its own parameter syntax while preserving these public URLs.

| Method | Existing URL | Route-level access | Handler | Ported | Tested |
| --- | --- | --- | --- | --- | --- |
| GET | `/` | Public | Welcome JSON | [x] | [x] |
| POST | `/api/v1/authentication/register` | Public | `authCntrl.register` | [x] | [x] |
| POST | `/api/v1/authentication/login` | Public | `authCntrl.login` | [x] | [x] |
| POST | `/api/v1/authentication/password/forgot` | Public | `authCntrl.passwordForgot` | [x] | [x] |
| POST | `/api/v1/authentication/password/reset` | Public | `authCntrl.passwordReset` | [x] | [x] |
| PUT | `/api/v1/authentication/set-profile` | Authenticated | `authCntrl.setProfile` | [x] | [x] |
| GET | `/api/v1/authentication/states` | Public | `authCntrl.getStates` | [x] | [x] |
| GET | `/api/v1/authentication/activate/:token` | Public | `authCntrl.activateAccount` | [x] | [x] |
| PUT | `/api/v1/authentication/activate/manager/:token` | Public | `authCntrl.activateAccountAndSavePassword` | [x] | [x] |
| GET | `/api/v1/authentication/activate/user/:id` | Authenticated + admin | `authCntrl.activateAccountAdmin` | [x] | [x] |
| GET | `/api/v1/authentication/get-profile` | Authenticated | `authCntrl.getProfile` | [x] | [x] |
| POST | `/api/v1/authentication/resend-activation` | Public | `authCntrl.resendActivation` | [x] | [x] |
| GET | `/api/v1/bank/list` | Public | `BankCntrl.getBankList` | [x] | [x] |
| GET | `/api/v1/bank/` | Authenticated | `BankCntrl.getBank` | [x] | [x] |
| POST | `/api/v1/bank/verify` | Authenticated | `BankCntrl.verifyBank` | [x] | [x] |
| PUT | `/api/v1/bank/update/bank-details` | Authenticated | `BankCntrl.updateBankDetails` | [x] | [x] |
| PUT | `/api/v1/bank/update/:bankId` | Authenticated | `BankCntrl.updateBankDetails` | [x] | [x] |
| POST | `/api/v1/bank/create` | Authenticated | `BankCntrl.addBankDetails` | [x] | [x] |
| PUT | `/api/v1/file/upload` | Public | `uploadImage` | [x] | [x] |
| GET | `/api/v1/file/signature` | Authenticated | `getUploadSignature` (added to Node 2026-10-10) | [x] | [x] |
| GET | `/api/v1/wallet/balance/admin` | Authenticated | `walletCtrl.getAdminBalance` | [x] | [x] |
| GET | `/api/v1/wallet/:id` | Authenticated | `walletCtrl.balance` | [x] | [x] |
| GET | `/api/v1/wallet/` | Authenticated | `walletCtrl.balance` | [x] | [x] |
| GET | `/api/v1/organization/list` | Public | `organizationCtrl.getOrganizations` | [x] | [x] |
| GET | `/api/v1/organization/` | Authenticated | `organizationCtrl.getOrganization` | [x] | [x] |
| GET | `/api/v1/organization/dashboard` | Authenticated | `organizationCtrl.getDashboard` | [x] | [x] |
| GET | `/api/v1/organization/overview` | Authenticated | `organizationCtrl.getOverview` | [x] | [x] |
| GET | `/api/v1/organization/public/:id` | Public | `organizationCtrl.getPublicOrganization` | [x] | [x] |
| PUT | `/api/v1/organization/update/avatar` | Authenticated | `organizationCtrl.uploadAvatar` | [x] | [x] |
| PUT | `/api/v1/organization/update/password` | Authenticated | `organizationCtrl.changePassword` | [x] | [x] |
| PUT | `/api/v1/organization/update/organization-password` | Authenticated + admin | `organizationCtrl.changePasswordAdmin` | [x] | [x] |
| POST | `/api/v1/organization/block/:id` | Authenticated | `organizationCtrl.block` | [x] | [x] |
| PUT | `/api/v1/organization/update` | Authenticated | `organizationCtrl.updateOrganization` | [x] | [x] |
| DELETE | `/api/v1/organization/delete/:id` | Admin | `organizationCtrl.deleteOrganization` | [x] | [x] |
| GET | `/api/v1/organization/:id` | Public | `organizationCtrl.getOrganization` | [x] | [x] |
| POST | `/api/v1/project/create` | Authenticated | `projectCtrl.createProject` | [x] | [x] |
| GET | `/api/v1/project/all` | Authenticated | `projectCtrl.getProjects` | [x] | [x] |
| GET | `/api/v1/project/random` | Public | `projectCtrl.getRandomProjects` | [x] | [x] |
| GET | `/api/v1/project/admin/all` | Authenticated + admin | `projectCtrl.getProjectsAdmin` | [x] | [x] |
| PUT | `/api/v1/project/update/:projectId` | Authenticated | `projectCtrl.updateProject` | [x] | [x] |
| DELETE | `/api/v1/project/delete/:projectId` | Authenticated | `projectCtrl.deleteProject` | [x] | [x] |
| GET | `/api/v1/project/organization/:organizationId` | Public | `projectCtrl.getOrganizationProjects` | [x] | [x] |
| GET | `/api/v1/project/:projectId` | Public | `projectCtrl.getProject` | [x] | [x] |
| POST | `/api/v1/donation/make-donation` | Public | `DonationCntrl.makeDonation` | [x] | [x] |
| POST | `/api/v1/donation/webhook` | Public | `DonationCntrl.webhook` | [x] | [x] |
| GET | `/api/v1/donation/` | Authenticated | `DonationCntrl.getOrganizationDonations` | [x] | [x] |
| GET | `/api/v1/donation/project/:projectId` | Authenticated | `DonationCntrl.getProjectDonations` | [x] | [x] |
| GET | `/api/v1/donation/:donationId` | Authenticated | `DonationCntrl.getProjectDonation` | [x] | [x] |
| GET | `/api/v1/donation/graph/year` | Authenticated | `DonationCntrl.getDonationsByTimePeriod` | [x] | [x] |
| POST | `/api/v1/donation/make-donation` | Authenticated | `DonationCntrl.makeDonation` | [x] | [x] |
| POST | `/api/v1/activity/add` | Authenticated | `ActivityCntrl.addActivity` | [x] | [x] |
| DELETE | `/api/v1/activity/delete/:activityId` | Authenticated | `ActivityCntrl.deleteActivity` | [x] | [x] |
| GET | `/api/v1/activity/all` | Authenticated | `ActivityCntrl.getActivities` | [x] | [x] |
| GET | `/api/v1/activity/public` | Public | `ActivityCntrl.getPublicActivities` | [x] | [x] |
| GET | `/api/v1/activity/public/:organizationId` | Public | `ActivityCntrl.getPublicOrganizationActivities` | [x] | [x] |
| GET | `/api/v1/activity/:activityId` | Authenticated | `ActivityCntrl.getActivity` | [x] | [x] |
| PUT | `/api/v1/activity/:activityId` | Authenticated | `ActivityCntrl.updateActivity` | [x] | [x] |
| GET | `/api/v1/global/admin/dashboard` | Authenticated + admin | `GlobalCtrl.getAdminDashboard` | [x] | [x] |
| GET | `/api/v1/global/search` | Public | `GlobalCtrl.searchOrganizationsAndProjects` | [x] | [x] |
| POST | `/api/v1/user/add` | Authenticated | `userCtrl.addUser` | [x] | [x] |
| GET | `/api/v1/user/delete` | Authenticated | `userCtrl.deleteUser` | [x] | [x] |
| POST | `/api/v1/withdraw/` | Authenticated | `WithdrawalController.makeWithdrawalRequest` | [x] | [x] |
| PATCH | `/api/v1/withdraw/admin/update/:requestId` | Authenticated + admin | `WithdrawalController.updateWithdrawalRequestForAdmin` | [x] | [x] |
| GET | `/api/v1/withdraw/single/:requestId` | Authenticated | `WithdrawalController.viewSingleWithdrawalRequestDetails` | [x] | [x] |
| GET | `/api/v1/withdraw/admin/all` | Authenticated + admin | `WithdrawalController.viewWithdrawalRequestsForAdmin` | [x] | [x] |
| GET | `/api/v1/withdraw/admin/requests` | Authenticated + admin | `WithdrawalController.getWithdrawalRequestsForAdmin` | [x] | [x] |
| GET | `/api/v1/withdraw/all` | Authenticated | `WithdrawalController.viewWithdrawalRequestsForOrganization` | [x] | [x] |
| POST | `/api/v1/withdrawal/` | Authenticated | `WithdrawalController.makeWithdrawalRequest` | [x] | [x] |
| PATCH | `/api/v1/withdrawal/admin/update/:requestId` | Authenticated + admin | `WithdrawalController.updateWithdrawalRequestForAdmin` | [x] | [x] |
| GET | `/api/v1/withdrawal/single/:requestId` | Authenticated | `WithdrawalController.viewSingleWithdrawalRequestDetails` | [x] | [x] |
| GET | `/api/v1/withdrawal/admin/all` | Authenticated + admin | `WithdrawalController.viewWithdrawalRequestsForAdmin` | [x] | [x] |
| GET | `/api/v1/withdrawal/admin/requests` | Authenticated + admin | `WithdrawalController.getWithdrawalRequestsForAdmin` | [x] | [x] |
| GET | `/api/v1/withdrawal/all` | Authenticated | `WithdrawalController.viewWithdrawalRequestsForOrganization` | [x] | [x] |

72 route registrations including the root route, both withdrawal mounts, and the duplicate donation registration.

Notes:

- The first public `POST /api/v1/donation/make-donation` handler normally handles the request before the later authenticated duplicate. Laravel implements the intended public checkout once.
- `/api/v1/withdraw` and `/api/v1/withdrawal` mount the same six handlers; Laravel preserves both aliases.
- `/api/v1/transaction` is commented out; its route/controller implementations are also commented out. It is not an active route group.
- `/api/v1/counter` and the scheduler hook are commented out and their referenced implementations are not present in this source inventory.
- `GET /api/v1/user/delete` is a legacy mutation through GET. Laravel retains it and adds `DELETE /api/v1/user/{id}` for client migration.
- Laravel requires authentication for uploads, redacts public organization details, and enforces admin/ownership checks on wallet and organization mutations; see `docs/API.md`.
- Static paths must retain precedence over parameter paths, such as project `/random` before `/:projectId`.
