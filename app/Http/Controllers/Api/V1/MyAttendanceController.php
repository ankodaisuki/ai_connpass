<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MyAttendanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 自分の申し込み一覧 API コントローラ
 */
class MyAttendanceController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * 自分の Applied 申し込み一覧（15件/ページ、applied_at 昇順）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $attendances = $request->user()
            ->eventAttendances()
            ->where('status', AttendanceStatus::Applied)
            ->with('event')
            ->orderBy('applied_at', 'asc')
            ->paginate(self::PER_PAGE);

        return MyAttendanceResource::collection($attendances);
    }
}
