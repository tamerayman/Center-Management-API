<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseFeeRequest;
use App\Models\Course;
use App\Models\CourseFee;

class CourseFeeController extends Controller
{
    /**
     * تاريخ أسعار كورس — للأدمن فقط.
     */
    public function index(Course $course)
    {
        $this->authorize('finance.sensitive');

        $fees = $course->fees()->with('creator')->orderByDesc('effective_from')->get();

        return response()->json([
            'course' => ['id' => $course->id, 'title' => $course->title],
            'current_fee' => $course->currentFee(),
            'fees' => $fees->map(fn ($fee) => [
                'id' => $fee->id,
                'monthly_fee' => $fee->monthly_fee,
                'effective_from' => $fee->effective_from,
                'created_by' => $fee->creator?->name,
            ]),
        ]);
    }

    /**
     * تعيين/تغيير سعر الكورس — صف جديد بتاريخ سريان (القديم لا يُعدل).
     */
    public function store(StoreCourseFeeRequest $request, Course $course)
    {
        $this->authorize('finance.sensitive');

        $fee = CourseFee::create([
            'course_id' => $course->id,
            'monthly_fee' => number_format((float) $request->monthly_fee, 2, '.', ''),
            'effective_from' => $request->effective_from ?? today()->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Course fee set successfully.',
            'course' => ['id' => $course->id, 'title' => $course->title],
            'current_fee' => $course->currentFee(),
            'fee' => [
                'id' => $fee->id,
                'monthly_fee' => $fee->monthly_fee,
                'effective_from' => $fee->effective_from,
            ],
        ], 201);
    }
}
