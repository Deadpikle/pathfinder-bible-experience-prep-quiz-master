<?php

namespace App\Controllers\Admin;

use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\Util;
use App\Models\Views\CsvDownloadResponse;
use App\Services\QuestionCsvExportService;
use App\Services\QuestionScope;
use Yamf\Request;
use Yamf\Responses\Response;

final class QuestionExportController extends BaseAdminController
{
    public function exportCsv(PBEAppConfig $app, Request $request): Response
    {
        // Deliberately enforced here as well as in BaseAdminController so the
        // download cannot become public if route validation changes later.
        if (!$app->loggedIn || !$app->isAdmin) {
            return new Response(403);
        }

        $bankID = Util::validateInteger($request->get, 'bankID');
        $scope = QuestionScope::forApp($app, $app->db);
        if ($bankID <= 0 || !$scope->canReadBank($bankID)) {
            return new Response(403);
        }

        try {
            $filters = $request->get;
            $filters['flagUserID'] = $app->isWebAdmin ? -1 : User::currentUserID();
            $contents = QuestionCsvExportService::export($bankID, $filters, $app->db);
        } catch (\InvalidArgumentException) {
            return new Response(400);
        }
        return new CsvDownloadResponse($contents, 'questions-bank-' . $bankID . '.csv');
    }
}
