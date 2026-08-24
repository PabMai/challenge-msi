<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReservationRequest;
use App\Jobs\CreateReservationJob;
use App\Models\ReservationAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Endpoints del flujo asíncrono de reserva.
 *
 * POST /reserve       -> encola el job y devuelve 202 + attempt_url
 * GET  /reserve/attempts/{uuid} -> estado del intento (polling del frontend)
 */
final class ReserveController extends Controller
{
    public function store(CreateReservationRequest $request): JsonResponse
    {
        $job = CreateReservationJob::enqueue(
            date: $request->string('reservation_date')->toString(),
            time: $request->string('reservation_time')->toString(),
            peopleCount: $request->integer('reservation_people_count'),
        );

        return response()->json(
            ['attempt_url' => route('reserve.attempts.show', ['attempt' => $job->attemptId])],
            Response::HTTP_ACCEPTED,
        );
    }

    public function show(string $attempt): JsonResponse
    {
        $model = ReservationAttempt::query()->findOrFail($attempt);

        return response()->json([
            'status' => $model->status,
            'result' => $model->result,
            'error' => $model->error,
        ]);
    }
}
