<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Program;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Registration::TYPES))],
            'event_id' => ['required_if:type,event', 'nullable', 'integer', 'exists:events,id'],
            'program_id' => ['required_if:type,program', 'nullable', 'integer', 'exists:programs,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'company_name' => ['required_if:type,membership', 'nullable', 'string', 'max:160'],
            'business_field' => ['required_if:type,membership', 'nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'age' => ['nullable', 'integer', 'min:17', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'], // honeypot anti-spam
        ], [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'email' => 'Format email tidak valid.',
            'phone.regex' => 'Nomor HP tidak valid.',
        ], [
            'name' => 'Nama', 'email' => 'Email', 'phone' => 'Nomor HP', 'company_name' => 'Nama usaha',
            'business_field' => 'Bidang usaha', 'event_id' => 'Agenda', 'program_id' => 'Program',
        ]);

        if ($data['type'] === 'event') {
            $event = Event::published()->findOrFail($data['event_id']);
            if (! $event->acceptsRegistration()) {
                throw ValidationException::withMessages(['event_id' => 'Pendaftaran agenda ini sudah ditutup atau kuota penuh.']);
            }
        }

        if ($data['type'] === 'program') {
            $program = Program::published()->findOrFail($data['program_id']);
            if (! $program->registration_open) {
                throw ValidationException::withMessages(['program_id' => 'Pendaftaran program ini sedang ditutup.']);
            }
        }

        $duplicate = Registration::query()
            ->where('type', $data['type'])
            ->where('email', $data['email'])
            ->when($data['event_id'] ?? null, fn ($q, $id) => $q->where('event_id', $id))
            ->when($data['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->where('status', '!=', Registration::STATUS_REJECTED)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah terdaftar sebelumnya.']);
        }

        $registration = Registration::create($data + ['status' => Registration::STATUS_PENDING]);

        return response()->json([
            'message' => 'Pendaftaran berhasil dikirim.',
            'data' => ['id' => $registration->id, 'status' => $registration->status],
        ], 201);
    }
}
