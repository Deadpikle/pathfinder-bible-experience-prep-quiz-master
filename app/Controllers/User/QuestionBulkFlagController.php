<?php

namespace App\Controllers\User;

use App\Models\CSRF;
use App\Models\FlagReason;
use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\Util;
use App\Services\QuestionBulkFlagService;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

final class QuestionBulkFlagController implements IRequestValidator
{
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        if (!User::isLoggedIn()) {
            return new Response(401);
        }
        return null;
    }

    public function bulkFlag(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || !CSRF::verifyToken('bulk-flag-questions')) {
            return new Response(403);
        }

        $reason = FlagReason::validateReason(Util::validateString($request->post, 'reason'));
        try {
            $count = QuestionBulkFlagService::flag(
                $request->post,
                User::currentUserID(),
                $reason,
                $app,
                $app->db
            );
        } catch (\InvalidArgumentException) {
            return new Response(400);
        }
        return new Redirect('/questions?bulkFlagged=' . $count);
    }
}
