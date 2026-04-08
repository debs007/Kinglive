<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:200'],
            'type'          => ['required', 'in:video,audio,audio_board'],
            'category'      => ['nullable', 'string', 'max:50'],
            'thumbnail_url' => ['nullable', 'url'],
            'seat_count'    => ['nullable', 'integer', 'min:2', 'max:16'],
        ];
    }
}
