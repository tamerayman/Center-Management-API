<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateChargesRequest;
use App\Http\Resources\MonthlyChargeResource;
use App\Models\MonthlyCharge;
use App\Services\Finance\ChargeService;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function __construct(private ChargeService $charges) {}

    /**
     * الفواتير — admin/secretary الكل، والمدرس كورساته فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = MonthlyCharge::query()->with(['student', 'course'])->latest();

        if ($user->isTeacher()) {
            $query->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id));
        } else {
            $this->authorize('finance.viewAny');
        }

        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->query('student_id')));
        $query->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->query('course_id')));
        $query->when($request->filled('year'), fn ($q) => $q->where('period_year', $request->query('year')));
        $query->when($request->filled('month'), fn ($q) => $q->where('period_month', $request->query('month')));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));

        return MonthlyChargeResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * توليد فواتير شهر — للأدمن. الـ unique constraint يمنع الازدواج.
     */
    public function generate(GenerateChargesRequest $request)
    {
        $this->authorize('finance.sensitive');

        try {
            $result = $this->charges->generateForPeriod(
                (int) $request->period_year,
                (int) $request->period_month,
                $request->course_id,
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Charge generation failed due to a concurrent request. Please try again.',
            ], 409);
        }

        return response()->json([
            'message' => $result['created'] > 0
                ? "Generated {$result['created']} monthly charge(s)."
                : 'No new charges to generate (all active subscriptions already have charges for this period).',
            'period' => ['year' => (int) $request->period_year, 'month' => (int) $request->period_month],
            'created_count' => $result['created'],
        ], 200);
    }
}
