<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Section;
use App\Rules\SchedulableSlot;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de creación de reserva.
 *
 * La regla SchedulableSlot envuelve al ScheduleValidator del dominio:
 * el rechazo por horario/cutoff ocurre en la capa HTTP con los mensajes
 * propios del dominio.
 */
class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date_format:Y-m-d'],
            'reservation_time' => ['required', 'date_format:H:i', new SchedulableSlot],
            'reservation_people_count' => ['required', 'integer', 'min:1'],
            'reservation_location' => ['required', 'integer', 'exists:locations,id'],
            'reservation_section' => [
                'required',
                'integer',
                'exists:sections,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    // Solo falla si la sección existe pero es de otra ubicación
                    // (así no se duplica el mensaje del exists).
                    $section = Section::query()->find($value);

                    if ($section !== null && (int) $this->input('reservation_location') !== $section->location_id) {
                        $fail('La sección seleccionada no pertenece a la ubicación elegida.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reservation_date.required' => 'La fecha de reserva es obligatoria.',
            'reservation_date.date_format' => 'La fecha de reserva debe tener formato Y-m-d.',
            'reservation_time.required' => 'La hora de reserva es obligatoria.',
            'reservation_time.date_format' => 'La hora de reserva debe tener formato H:i.',
            'reservation_people_count.required' => 'La cantidad de personas es obligatoria.',
            'reservation_people_count.integer' => 'La cantidad de personas debe ser un número entero.',
            'reservation_people_count.min' => 'La cantidad de personas debe ser al menos 1.',
            'reservation_location.required' => 'La ubicación es obligatoria.',
            'reservation_location.integer' => 'La ubicación debe ser un identificador válido.',
            'reservation_location.exists' => 'La ubicación seleccionada no existe.',
            'reservation_section.required' => 'La sección es obligatoria.',
            'reservation_section.integer' => 'La sección debe ser un identificador válido.',
            'reservation_section.exists' => 'La sección seleccionada no existe.',
        ];
    }
}
