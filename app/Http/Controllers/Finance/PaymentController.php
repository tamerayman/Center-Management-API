<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /**
     * سجل المدفوعات — admin/secretary الكل، والمدرس كورساته فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::query()->with(['student', 'course', 'charge', 'creator'])->latest();

        if ($user->isTeacher()) {
            $query->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id));
        } else {
            $this->authorize('finance.viewAny');
        }

        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->query('student_id')));
        $query->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->query('course_id')));
        $query->when($request->filled('method'), fn ($q) => $q->where('method', $request->query('method')));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));
        $query->when($request->filled('year'), fn ($q) => $q->whereYear('paid_at', $request->query('year')));
        $query->when($request->filled('month'), fn ($q) => $q->whereMonth('paid_at', $request->query('month')));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->query('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->query('date_to')));
        $query->when($request->filled('amount_min'), fn ($q) => $q->where('amount', '>=', (float) $request->query('amount_min')));
        $query->when($request->filled('amount_max'), fn ($q) => $q->where('amount', '<=', (float) $request->query('amount_max')));

        return PaymentResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * تسجيل دفعة — admin/secretary لأي طالب، والمدرس لكورساته فقط.
     */
    public function store(StorePaymentRequest $request)
    {
        $course = Course::findOrFail($request->course_id);

        $this->authorize('finance.recordPayment', $course);

        $student = User::findOrFail($request->student_id);
        $actor = $request->user();

        $payment = $this->payments->recordPayment($actor, $student, $course, $request->validated());

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => new PaymentResource($payment->load(['student', 'course', 'charge', 'creator'])),
        ], 201);
    }

    /**
     * تعديل دفعة — للأدمن فقط. بتعيد حساب الفاتورة داخل transaction.
     */
    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $this->authorize('finance.sensitive');

        $payment = $this->payments->updatePayment($request->user(), $payment, $request->validated());

        return response()->json([
            'message' => 'Payment updated successfully.',
            'data' => new PaymentResource($payment->load(['student', 'course', 'charge', 'creator'])),
        ]);
    }

    /**
     * إلغاء دفعة بسبب مُسجل — للأدمن فقط. بدون حذف فيزيائي.
     */
    public function cancel(CancelPaymentRequest $request, Payment $payment)
    {
        $this->authorize('finance.sensitive');

        $payment = $this->payments->cancelPayment($request->user(), $payment, $request->cancellation_reason);

        return response()->json([
            'message' => 'Payment cancelled successfully.',
            'data' => new PaymentResource($payment->load(['student', 'course', 'charge', 'creator', 'canceller'])),
        ]);
    }
}
