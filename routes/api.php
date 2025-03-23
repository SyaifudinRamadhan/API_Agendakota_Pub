<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
 */
// Route::get('/test-mail', function(){
//     Mail::to('syaifudinramadhan@gmail.com')->send(new TwoFactorNoitication(User::where('email', "syaifudinramadhan@gmail.com")->first() ,"fTF^&57"));
// });
Route::get('/test', function (Request $req) {
    return Storage::delete('private/inv_attchs/' . 'pMh4T_1737634058.png');
});
// Route::get('/download-ticket', [\App\Http\Controllers\PchCtrl::class, 'downloadTicket']);
Route::get('/verify/{subId}', [\App\Http\Controllers\Authenticate::class, 'verify'])->name('verify');
Route::get('/verify/{subId}/auto/{redirect}', [\App\Http\Controllers\Authenticate::class, 'verify'])->name('verifyAndRedirect');
Route::get('/verify-invite/{token}', [\App\Http\Controllers\OrgCtrl::class, 'acceptInviteTeam'])->name('accept-invite');
Route::patch('/webhook-payment', [\App\Http\Controllers\WebhookCtrl::class, 'handleWebhookRedirect'])->name('pkg.payment.redirect');
Route::post('/webhook-refund-data', [\App\Http\Controllers\WebhookCtrl::class, 'receiveValidationRefund']);
Route::middleware('apiToken')->prefix('/')->group(function () {
    // Register route
    Route::post('/register', [\App\Http\Controllers\Authenticate::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\Authenticate::class, 'login']);
    Route::post('/auto-login-trx', [\App\Http\Controllers\Authenticate::class, 'autoLoginForBasicAuth']);
    Route::post('/login-w-google', [\App\Http\Controllers\Authenticate::class, 'loginGoogle']);
    Route::post('/login-w-otp', [\App\Http\Controllers\Authenticate::class, 'loginWithOtp']);
    Route::post('/verify-otp', [\App\Http\Controllers\Authenticate::class, 'verifyOtp']);
    Route::post('/request-reset-pass', [\App\Http\Controllers\Authenticate::class, 'requestResetPass']);
    Route::post('/reset-pass', [\App\Http\Controllers\Authenticate::class, 'resetPassword']);

    // Public route
    Route::get('/org-profile/{orgId}', [\App\Http\Controllers\OrgCtrl::class, 'getOrg']);
    Route::get('/event/{eventId}', [\App\Http\Controllers\EventCtrl::class, 'getById']);
    Route::get('/ecs/{strKey}', [\App\Http\Controllers\CustomUrlCtrl::class, 'getEvent']);
    Route::get('/event-daily-refresh-date/{eventId}', [\App\Http\Controllers\EventCtrl::class, 'getAvailableSeatNumberDailyTicket']);
    Route::get('/ticket-reschedule', [\App\Http\Controllers\EventCtrl::class, 'getRescheduleAvailableData']);
    Route::get('/event-slug/{slug}', [\App\Http\Controllers\EventCtrl::class, 'getBySlug']);
    Route::get('/event-orgs/{orgId}', [\App\Http\Controllers\EventCtrl::class, 'getByOrg']);
    Route::get('/tickets/{eventId}', [\App\Http\Controllers\TicketCtrl::class, 'getTicketsPublic']);
    Route::post("/check-avl-ticket-daily-events", [\App\Http\Controllers\EventCtrl::class, "getVisitDateAvlTickets"]);
    Route::post("/check-avl-ticket-daily-event", [\App\Http\Controllers\EventCtrl::class, "getVisitDateAvlTicket"]);
    Route::get('/method-trxs', [\App\Http\Controllers\PkgPayCtrl::class, 'listPayMethod']);
    Route::post('/{xApiToken}/webhook-payment', [\App\Http\Controllers\WebhookCtrl::class, 'handleWebhookRedirect'])->name('pkg.payment.redirect');
    Route::post('/{xApiToken}/webhook-refund-data', [\App\Http\Controllers\WebhookCtrl::class, 'receiveValidationRefund']);
    Route::get('/categories', [\App\Http\Controllers\AdminCtrl::class, 'categories']);
    Route::get('/topics', [\App\Http\Controllers\AdminCtrl::class, 'topics']);
    Route::get('/topics-act', [\App\Http\Controllers\AdminCtrl::class, 'topicsAct']);
    Route::get('/org-types', [\App\Http\Controllers\AdminCtrl::class, 'orgTypes']);
    Route::get('/cities', [\App\Http\Controllers\AdminCtrl::class, 'cities']);
    Route::get('/front-banners', [\App\Http\Controllers\AdminCtrl::class, 'frontBanners']);
    Route::get('/spotlight', [\App\Http\Controllers\AdminCtrl::class, 'getSpotlight']);
    Route::get('/special-day', [\App\Http\Controllers\AdminCtrl::class, 'getSpcDay']);
    Route::get('/selected-event', [\App\Http\Controllers\AdminCtrl::class, 'getSlctEvent']);
    Route::get('/selected-activity', [\App\Http\Controllers\AdminCtrl::class, 'getSlctActivity']);
    Route::get('/active-spotlights', [\App\Http\Controllers\AdminCtrl::class, 'getActiveSpotlights']);
    Route::get('/active-special-days', [\App\Http\Controllers\AdminCtrl::class, 'getActiveSpcDays']);
    Route::get('/active-selected-events', [\App\Http\Controllers\AdminCtrl::class, 'getActiveSlctEvents']);
    Route::get('/active-selected-activities', [\App\Http\Controllers\AdminCtrl::class, 'getActiveSlctActivities']);
    Route::get('/pop-events', [\App\Http\Controllers\SearchCtrl::class, 'popularEvents']);
    Route::get('/pop-city-events', [\App\Http\Controllers\SearchCtrl::class, 'popularCityEvents']);
    Route::get('/search', [\App\Http\Controllers\SearchCtrl::class, 'searchEvents']);
    Route::get('/get-banks-code', [\App\Http\Controllers\WithdrawCtrl::class, 'getBanksCode']);
    Route::get('/commision-price', [\App\Http\Controllers\AdminCtrl::class, 'getProfitSetting']);
    // ================= Route for test only ================
    // Route::post('/create-trx-pkg/{eventId}', [\App\Http\Controllers\PkgPayCtrl::class, 'createTrxEd']);
    // Route::get('/get-trx-pkg', [\App\Http\Controllers\PkgPayCtrl::class, 'getTrx']);
    // =======================================================

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/is-login', function (Request $request) {
            if ($request->user()->deleted == 0 && $request->user()->is_active == 1) {
                return $request->user();
            } else {
                return response()->json(["data" => null], 404);
            }
        });
        // No two factor route for transaction and logout
        Route::post('/logout', [\App\Http\Controllers\Authenticate::class, 'logout']);
        Route::post('/buy-ticket', [\App\Http\Controllers\PchCtrl::class, 'create']);
        Route::post('/fill-survey', [\App\Http\Controllers\SurveyCtrl::class, 'fillSurveyUser']);
        Route::post("/request-two-factor", [\App\Http\Controllers\TwoFactorCtrl::class, 'requestOTP']);
        // Two factor routes
        Route::middleware('auth.twoFactor')->group(function () {
            Route::put('/update', [\App\Http\Controllers\UserCtrl::class, 'updateProfile']);
            Route::put('/update-password', [\App\Http\Controllers\UserCtrl::class, 'updatePassword']);
            Route::put('/set-two-factor', [\App\Http\Controllers\UserCtrl::class, 'setTwoFactor']);
            Route::get('/profile', [\App\Http\Controllers\UserCtrl::class, 'getUser']);
            // Route Ticket Transaction
            Route::post('/purchase-reschedule', [\App\Http\Controllers\PchCtrl::class, 'reSchedule']);
            Route::post('/request-refund', [\App\Http\Controllers\PchCtrl::class, 'submitRefund']);
            Route::get('/get-purchase', [\App\Http\Controllers\PchCtrl::class, 'get']);
            Route::get('/get-purchases', [\App\Http\Controllers\PchCtrl::class, 'purchases']);
            Route::get('/download-ticket', [\App\Http\Controllers\PchCtrl::class, 'downloadTicket']);
            Route::post('/invite-user', [\App\Http\Controllers\InvitationCtrl::class, 'create']);
            Route::delete('/get-back-invite', [\App\Http\Controllers\InvitationCtrl::class, 'getBackPurchase']);
            Route::post('/invitation-accept', [\App\Http\Controllers\InvitationCtrl::class, 'accept']);
            Route::delete('/invitation-delete', [\App\Http\Controllers\InvitationCtrl::class, 'delete']);
            Route::get('/invitations-received', [\App\Http\Controllers\InvitationCtrl::class, 'invitationsRcv']);
            Route::get('/invitations-sent', [\App\Http\Controllers\InvitationCtrl::class, 'invitationsSdr']);

            Route::post('/checkin', [\App\Http\Controllers\CheckinCtrl::class, 'createByUser']);

            // Route organization
            Route::group(["prefix" => "org"], function () {
                Route::post('/register-org', [\App\Http\Controllers\OrgCtrl::class, 'create']);
                Route::get('/user-orgs', [\App\Http\Controllers\OrgCtrl::class, 'getOrgsByUser']);

                Route::middleware('authOrganizer')->group(function () {
                    Route::put('/update-org', [\App\Http\Controllers\OrgCtrl::class, 'update']);
                    Route::delete('/delete-org', [\App\Http\Controllers\OrgCtrl::class, 'delete']);
                    Route::post('/team/invite', [\App\Http\Controllers\OrgCtrl::class, 'inviteTeam']);
                    Route::delete('/team/delete', [\App\Http\Controllers\OrgCtrl::class, 'deleteTeam']);
                    Route::get('/teams', [\App\Http\Controllers\OrgCtrl::class, 'getTeams']);

                    Route::prefix("{orgId}")->group(function () {
                        Route::get('/org-legality', [\App\Http\Controllers\LegalityDataCtrl::class, 'getLegality']);
                        Route::post('/org-legality/create', [\App\Http\Controllers\LegalityDataCtrl::class, 'create']);
                        Route::put('/org-legality/update', [\App\Http\Controllers\LegalityDataCtrl::class, 'update']);

                        Route::get('/get-banks-code', [\App\Http\Controllers\WithdrawCtrl::class, 'getBanksCode']);
                        Route::post('/bank/add', [\App\Http\Controllers\WithdrawCtrl::class, 'createAccount']);
                        Route::delete('/bank/delete', [\App\Http\Controllers\WithdrawCtrl::class, 'deleteAccount']);
                        Route::post('/bank/verify', [\App\Http\Controllers\WithdrawCtrl::class, 'verifyAccount']);
                        Route::get('/bank/list', [\App\Http\Controllers\WithdrawCtrl::class, 'banks']);
                        Route::post('/withdraw/create', [\App\Http\Controllers\WithdrawCtrl::class, 'createWd']);
                        Route::delete('/withdraw/delete', [\App\Http\Controllers\WithdrawCtrl::class, 'deleteWdOrg']);
                        Route::get('/withdraw/detail', [\App\Http\Controllers\WithdrawCtrl::class, 'getWdOrg']);
                        Route::get('/withdraw/list', [\App\Http\Controllers\WithdrawCtrl::class, 'wdsOrg']);
                        Route::get('/withdraw/available', [\App\Http\Controllers\WithdrawCtrl::class, 'availableForWd']);

                        Route::post('/event/create', [\App\Http\Controllers\EventCtrl::class, 'create']);
                    });
                });

                Route::middleware('eventOrganizer')->prefix("{orgId}")->group(function () {
                    Route::get('/events', [\App\Http\Controllers\EventCtrl::class, 'getByOrg']);
                    Route::middleware('eventData')->prefix('/event')->group(function () {
                        Route::get("/", [\App\Http\Controllers\EventCtrl::class, 'getById']);
                        Route::put('/update-peripheral', [\App\Http\Controllers\EventCtrl::class, 'updatePeripheralField']);
                        Route::put('/update', [\App\Http\Controllers\EventCtrl::class, 'update']);
                        Route::delete('/delete', [\App\Http\Controllers\EventCtrl::class, 'delete']);
                        Route::post('/change-state', [\App\Http\Controllers\EventCtrl::class, 'setPublishState']);
                        Route::get('/download-qr-event', [\App\Http\Controllers\EventCtrl::class, 'getQREvent']);
                        // Route::get('/get-trx-pkg', [\App\Http\Controllers\PkgPayCtrl::class, 'getTrx']);
                        // Route::post('/renew-trx-pkg', [\App\Http\Controllers\PkgPayCtrl::class, 'renewTransaction']);
                        Route::prefix("{eventId}/manage")->group(function () {
                            Route::get('/custom-url', [\App\Http\Controllers\CustomUrlCtrl::class, 'get']);
                            Route::post('/custom-url/create', [\App\Http\Controllers\CustomUrlCtrl::class, 'createUpdate']);
                            Route::get('/rundowns', [\App\Http\Controllers\RundownCtrl::class, 'getRundowns']);
                            Route::post('/session/create', [\App\Http\Controllers\EvtSessionCtrl::class, 'create']);
                            Route::get('/session', [\App\Http\Controllers\EvtSessionCtrl::class, 'get']);
                            Route::get('/sessions', [\App\Http\Controllers\EvtSessionCtrl::class, 'getSessions']);
                            Route::get('/ticket', [\App\Http\Controllers\TicketCtrl::class, 'get']);
                            Route::get('/tickets', [\App\Http\Controllers\TicketCtrl::class, 'getTickets']);
                            Route::post('/ticket/create', [\App\Http\Controllers\TicketCtrl::class, 'create']);
                            Route::post('/ticket/create-bulk', [\App\Http\Controllers\TicketCtrl::class, 'bulkCreate']);
                            Route::put('/ticket/update', [\App\Http\Controllers\TicketCtrl::class, 'update']);
                            Route::delete('/ticket/delete', [\App\Http\Controllers\TicketCtrl::class, 'delete']);
                            Route::get('/refunds', [\App\Http\Controllers\PchCtrl::class, 'getRefundsOrg']);
                            Route::get('/refund/detail', [\App\Http\Controllers\PchCtrl::class, 'getRefundOrg']);
                            Route::post('/refund/change-state', [\App\Http\Controllers\PchCtrl::class, 'considerationRefund']);
                            Route::middleware('eventSessionData')->group(function () {
                                Route::put('/session/update', [\App\Http\Controllers\EvtSessionCtrl::class, 'update']);
                                Route::delete('/session/delete', [\App\Http\Controllers\EvtSessionCtrl::class, 'delete']);
                            });
                            Route::get('/voucher', [\App\Http\Controllers\VoucherCtrl::class, 'get']);
                            Route::get('/vouchers', [\App\Http\Controllers\VoucherCtrl::class, 'gets']);
                            Route::post('/voucher/create', [\App\Http\Controllers\VoucherCtrl::class, 'create']);
                            Route::put('/voucher/update', [\App\Http\Controllers\VoucherCtrl::class, 'update']);
                            Route::delete('/voucher/delete', [\App\Http\Controllers\VoucherCtrl::class, 'delete']);
                            Route::post('/checkin', [\App\Http\Controllers\CheckinCtrl::class, 'createByOrg']);
                            Route::delete('/checkin/delete', [\App\Http\Controllers\CheckinCtrl::class, 'delete']);
                            Route::get('/checkin/detail', [\App\Http\Controllers\CheckinCtrl::class, 'get']);
                            Route::get('/checkin/report', [\App\Http\Controllers\CheckinCtrl::class, 'checkins']);
                            Route::get('/user-surveys', [\App\Http\Controllers\SurveyCtrl::class, 'getSurvey']);
                            Route::post('/mail/send', [\App\Http\Controllers\MailBroadcastCtrl::class, 'create']);
                            Route::post('/mail/resend', [\App\Http\Controllers\MailBroadcastCtrl::class, 'resendMail']);
                            Route::delete('/mail/delete', [\App\Http\Controllers\MailBroadcastCtrl::class, 'delete']);
                            Route::get('/mail/gets', [\App\Http\Controllers\MailBroadcastCtrl::class, 'gets']);
                            Route::post('/create-invitation', [\App\Http\Controllers\OrgInvCtrl::class, 'singleCreateInv']);
                            Route::post('/create-bulk-invitation', [\App\Http\Controllers\OrgInvCtrl::class, 'bulkCreateInv']);
                            Route::post('/create-bulk-invitation-v2', [\App\Http\Controllers\OrgInvCtrl::class, 'bulkCreateInvV2']);
                            Route::delete('/cancel-invitations', [\App\Http\Controllers\OrgInvCtrl::class, 'cancelInv']);
                            Route::get('/invitations', [\App\Http\Controllers\OrgInvCtrl::class, 'getAll']);
                            Route::get('/invitation-attachment/{invId}/file/{file}', [\App\Http\Controllers\SpecialFilesCtrl::class, 'AttachInvProtection']);
                        });
                    });
                });
            });

            // Admin Route
            Route::middleware('admin')->prefix('admin')->group(function () {
                // Basic Route
                Route::post('/category/create', [\App\Http\Controllers\AdminCtrl::class, 'createCategory']);
                Route::delete('/category/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteCategory']);
                Route::post('/category/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'setPriorityPlusCat']);
                Route::post('/category/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'setPriorityMinCat']);
                Route::post('/topic/create', [\App\Http\Controllers\AdminCtrl::class, 'createTopic']);
                Route::delete('/topic/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteTopic']);
                Route::post('/topic-act/create', [\App\Http\Controllers\AdminCtrl::class, 'createTopicAct']);
                Route::delete('/topic-act/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteTopicAct']);
                Route::post('/org-type/create', [\App\Http\Controllers\AdminCtrl::class, 'createOrgType']);
                Route::delete('/org-type/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteOrgType']);
                Route::post('/city/create', [\App\Http\Controllers\AdminCtrl::class, 'createCity']);
                Route::delete('/city/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteCity']);
                Route::post('/city/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'setPriorityPlusCity']);
                Route::post('/city/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'setPriorityMinCity']);
                Route::post('/city/set-viral-state', [\App\Http\Controllers\AdminCtrl::class, 'setViralCity']);
                Route::post('/fbanner/create', [\App\Http\Controllers\AdminCtrl::class, 'createFrontBanner']);
                Route::delete('/fbanner/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteFirstBanner']);
                Route::post('/fbanner/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'setPriorityPlusFBanner']);
                Route::post('/fbanner/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'setPriorityMinFBanner']);
                Route::post('/admin/create', [\App\Http\Controllers\AdminCtrl::class, 'createAdmin']);
                Route::delete('/admin/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteAdmin']);
                Route::get('/admins', [\App\Http\Controllers\AdminCtrl::class, 'admins']);
                Route::delete('/purchase/delete', [\App\Http\Controllers\AdminCtrl::class, 'deletePch']);
                Route::get('/purchase/detail', [\App\Http\Controllers\AdminCtrl::class, 'pchDetail']);
                Route::get('/purchases', [\App\Http\Controllers\AdminCtrl::class, 'purchases']);
                Route::delete('/payment/delete', [\App\Http\Controllers\AdminCtrl::class, 'deletePayment']);
                Route::get('/payment/detail', [\App\Http\Controllers\AdminCtrl::class, 'paymentDetail']);
                Route::get('/payments', [\App\Http\Controllers\AdminCtrl::class, 'payments']);

                //Home controller data
                Route::post('/spotlight/create', [\App\Http\Controllers\AdminCtrl::class, 'createSpotlight']);
                Route::put('/spotlight/update', [\App\Http\Controllers\AdminCtrl::class, 'updateSpotlight']);
                Route::post('/spotlight/set-view', [\App\Http\Controllers\AdminCtrl::class, 'setViewSpotlight']);
                Route::delete('/spotlight/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteSpotlight']);
                Route::post('/spotlight/event/add', [\App\Http\Controllers\AdminCtrl::class, 'addEventSpotlight']);
                Route::delete('/spotlight/event/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteEventSpotlight']);
                Route::post('/spotlight/event/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'addPrioEventSpotlight']);
                Route::post('/spotlight/event/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'minPrioEventSpotlight']);
                Route::get('/spotlight', [\App\Http\Controllers\AdminCtrl::class, 'getSpotlight']);
                Route::get('/spotlights', [\App\Http\Controllers\AdminCtrl::class, 'listSpotlights']);

                Route::post('/special-day/create', [\App\Http\Controllers\AdminCtrl::class, 'createSpcDay']);
                Route::put('/special-day/update', [\App\Http\Controllers\AdminCtrl::class, 'updateSpcDay']);
                Route::post('/special-day/set-view', [\App\Http\Controllers\AdminCtrl::class, 'setViewSpcDay']);
                Route::delete('/special-day/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteSpcDay']);
                Route::post('/special-day/event/add', [\App\Http\Controllers\AdminCtrl::class, 'addEventSpcDay']);
                Route::delete('/special-day/event/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteEventSpcDay']);
                Route::post('/special-day/event/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'addPrioEventSpcDay']);
                Route::post('/special-day/event/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'minPrioEventSpcDay']);
                Route::get('/special-day', [\App\Http\Controllers\AdminCtrl::class, 'getSpcDay']);
                Route::get('/special-days', [\App\Http\Controllers\AdminCtrl::class, 'listSpcDays']);

                Route::post('/selected-event/create', [\App\Http\Controllers\AdminCtrl::class, 'createSlctEvent']);
                Route::put('/selected-event/update', [\App\Http\Controllers\AdminCtrl::class, 'updateSlctEvent']);
                Route::post('/selected-event/set-view', [\App\Http\Controllers\AdminCtrl::class, 'setViewSlctEvent']);
                Route::delete('/selected-event/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteSlctEvent']);
                Route::post('/selected-event/event/add', [\App\Http\Controllers\AdminCtrl::class, 'addEventSlctEvent']);
                Route::delete('/selected-event/event/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteEventSlctEvent']);
                Route::post('/selected-event/event/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'addPrioEventSlctEvent']);
                Route::post('/selected-event/event/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'minPrioEventSlctEvent']);
                Route::get('/selected-event', [\App\Http\Controllers\AdminCtrl::class, 'getSlctEvent']);
                Route::get('/selected-events', [\App\Http\Controllers\AdminCtrl::class, 'listSlctEvents']);

                Route::post('/selected-activity/create', [\App\Http\Controllers\AdminCtrl::class, 'createSlctActivity']);
                Route::put('/selected-activity/update', [\App\Http\Controllers\AdminCtrl::class, 'updateSlctActivity']);
                Route::post('/selected-activity/set-view', [\App\Http\Controllers\AdminCtrl::class, 'setViewSlctActivity']);
                Route::delete('/selected-activity/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteSlctActivity']);
                Route::post('/selected-activity/event/add', [\App\Http\Controllers\AdminCtrl::class, 'addEventSlctActivity']);
                Route::delete('/selected-activity/event/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteEventSlctActivity']);
                Route::post('/selected-activity/event/set-prio-plus', [\App\Http\Controllers\AdminCtrl::class, 'addPrioEventSlctActivity']);
                Route::post('/selected-activity/event/set-prio-min', [\App\Http\Controllers\AdminCtrl::class, 'minPrioEventSlctActivity']);
                Route::get('/selected-activity', [\App\Http\Controllers\AdminCtrl::class, 'getSlctActivity']);
                Route::get('/selected-activities', [\App\Http\Controllers\AdminCtrl::class, 'listSlctActivities']);

                // Primary admin route
                Route::put('/commision-price/update', [\App\Http\Controllers\AdminCtrl::class, 'udpdateProfitSetting']);
                Route::get('/refund-setting', [\App\Http\Controllers\AdminCtrl::class, 'refundSettings']);
                Route::post('/refund-setting/create', [\App\Http\Controllers\AdminCtrl::class, 'createRefundSetting']);
                Route::put('/refund-setting/update', [\App\Http\Controllers\AdminCtrl::class, 'updateRefundSetting']);
                Route::delete('/refund-setting/delete', [\App\Http\Controllers\AdminCtrl::class, 'deleteRefundSetting']);
                Route::get('/refund-ticket-manager', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'refundTicketManager']);
                Route::put('/user/update', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'updateProfileUser']);
                Route::put('/user/update-password', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'updatePasswordUser']);
                Route::get('/user/profile', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'userDetail']);
                Route::delete('/user/delete', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'userDelete']);
                Route::post('/user/get-back', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'getBack']);
                Route::post('/user/set-active', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'setActive']);
                Route::get('/users', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'users']);
                Route::post('/organization/create', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'createOrganization']);
                Route::put('/organization/update', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'updateProfileOrg']);
                Route::post('/organization/get-back', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'getBackOrg']);
                Route::delete('/organization/delete', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'deleteOrganization']);
                Route::get('/organization/detail', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'organizationDetail']);
                Route::get('/organizations', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'organizations']);
                Route::get('/legality-datas', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'getLegalities']);
                Route::get('/legality-data/detail', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'getLegality']);
                Route::put('/legality-data/update', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'changeLegalityState']);
                Route::delete('/legality-data/delete', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'legalityDelete']);
                Route::get('/all-event', [\App\Http\Controllers\AdminCtrl::class, 'events']);
                Route::prefix('org/{orgId}')->group(function () {
                    Route::put('/update-org-profile', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'updateProfileOrg']);
                    Route::delete('/delete-organization', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'deleteOrganization']);
                    Route::get('/list-bank', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'listBank']);
                    Route::delete('/delete-bank', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'deleteBank']);
                    Route::put('/update-status-bank', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'updateBankStatus']);
                    Route::post('/event/create', [\App\Http\Controllers\EventCtrl::class, 'create']);
                    Route::put('/event/update', [\App\Http\Controllers\EventCtrl::class, 'update']);
                    Route::delete('/event/delete', [\App\Http\Controllers\EventCtrl::class, 'deleteForAdmin']);
                    Route::put('/event/rollback', [\App\Http\Controllers\EventCtrl::class, 'rollbackEvent']);
                    Route::prefix('/event/{eventId}/manage')->group(function () {
                        Route::middleware('eventData')->group(function () {
                            Route::get('/custom-url', [\App\Http\Controllers\CustomUrlCtrl::class, 'get']);
                            Route::post('/custom-url/create', [\App\Http\Controllers\CustomUrlCtrl::class, 'createUpdate']);
                            Route::get('/rundowns', [\App\Http\Controllers\RundownCtrl::class, 'getRundowns']);
                            Route::post('/session/create', [\App\Http\Controllers\EvtSessionCtrl::class, 'create']);
                            Route::get('/session', [\App\Http\Controllers\EvtSessionCtrl::class, 'get']);
                            Route::get('/sessions', [\App\Http\Controllers\EvtSessionCtrl::class, 'getSessions']);
                            Route::get('/ticket', [\App\Http\Controllers\TicketCtrl::class, 'get']);
                            Route::get('/tickets', [\App\Http\Controllers\TicketCtrl::class, 'getTickets']);
                            Route::post('/ticket/create', [\App\Http\Controllers\TicketCtrl::class, 'create']);
                            Route::put('/ticket/update', [\App\Http\Controllers\TicketCtrl::class, 'update']);
                            Route::delete('/ticket/delete', [\App\Http\Controllers\TicketCtrl::class, 'delete']);
                            Route::put('/ticket/rollback', [\App\Http\Controllers\TicketCtrl::class, 'rollbackTicket']);
                            Route::middleware('eventSessionData')->group(function () {
                                Route::put('/session/update', [\App\Http\Controllers\EvtSessionCtrl::class, 'update']);
                                Route::delete('/session/delete', [\App\Http\Controllers\EvtSessionCtrl::class, 'delete']);
                            });
                            Route::get('/voucher', [\App\Http\Controllers\VoucherCtrl::class, 'get']);
                            Route::get('/vouchers', [\App\Http\Controllers\VoucherCtrl::class, 'gets']);
                            Route::post('/voucher/create', [\App\Http\Controllers\VoucherCtrl::class, 'create']);
                            Route::put('/voucher/update', [\App\Http\Controllers\VoucherCtrl::class, 'update']);
                            Route::delete('/voucher/delete', [\App\Http\Controllers\VoucherCtrl::class, 'delete']);
                        });
                    });
                });
                Route::get('/withdraws', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'listWithdraw']);
                Route::get('/withdraw/detail', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'withdrawDetail']);
                Route::delete('/withdraw/delete', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'deleteWithdraw']);
                Route::put('/withdraw/change-state', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'updateWithdrawStatus']);
                Route::get('/refunds', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'getRefunds']);
                Route::get('/refund', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'getRefund']);
                Route::post('/refund/change-state', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'considerationRefund']);
                Route::post('/refund/set-finish', [\App\Http\Controllers\AdminPrimaryCtrl::class, 'setFinishRefund']);

                Route::get('/mail-errors', [\App\Http\Controllers\ResendTrxNotification::class, 'get']);
                Route::delete('/mail-errors/delete', [\App\Http\Controllers\ResendTrxNotification::class, 'delete']);
                Route::post('/mail-errors/resend', [\App\Http\Controllers\ResendTrxNotification::class, 'mainResend']);
                Route::post('/activate-org-invitation', [\App\Http\Controllers\AdminCtrl::class, 'activateInvFeature']);
            });
        });
    });
});
